<?php
/**
 * Kaptor - Auditor web: recolección de datos por fases.
 *
 * Auditar un sitio son unas veinticinco peticiones de red. En un hosting
 * compartido eso no cabe en una sola carga de página, así que el trabajo se
 * parte en fases y el navegador va pidiendo la siguiente. Cada llamada tiene
 * un presupuesto de tiempo y se retira en cuanto lo gasta, igual que hace el
 * rastreador de correos.
 *
 * Lo recogido se guarda en la fila de la auditoría como JSON; cuando se llega
 * a la última fase se pasa por Chequeos y se calcula la nota.
 */
declare(strict_types=1);

final class Auditor
{
    /**
     * Los tres modos de análisis.
     *
     * Es el mismo motor: lo que cambia es hasta dónde llega. El modo completo
     * da una foto de las siete áreas quedándose en la portada; los otros dos
     * recorren el sitio y aprietan en su terreno. Tener un solo motor con tres
     * profundidades, en vez de tres programas distintos, es lo que mantiene
     * esto manejable.
     */
    public const MODOS = ['completo', 'seo', 'malware'];

    /** Orden de las fases de cada modo. La última calcula la nota y cierra. */
    public const FASES_POR_MODO = [
        'completo' => ['portada', 'archivos', 'recursos', 'enlaces', 'malware', 'psi', 'cerrar'],
        'seo'      => ['portada', 'archivos', 'rastreo', 'vinculos', 'recursos', 'psi', 'cerrar'],
        'malware'  => ['portada', 'archivos', 'rastreo', 'codigo', 'malware', 'cerrar'],
    ];

    /** Fases del modo completo, que es el de siempre. */
    public const FASES = ['portada', 'archivos', 'recursos', 'enlaces', 'malware', 'psi', 'cerrar'];

    /** Cuánto se enseña de cada fase mientras corre. */
    public const ETIQUETAS = [
        'portada'  => 'Abriendo la página',
        'archivos' => 'Buscando robots.txt y el mapa del sitio',
        'recursos' => 'Pesando imágenes y estilos',
        'enlaces'  => 'Comprobando enlaces',
        'malware'  => 'Buscando código malicioso',
        'rastreo'  => 'Recorriendo las páginas del sitio',
        'vinculos' => 'Comprobando todos los enlaces',
        'codigo'   => 'Analizando los archivos de código',
        'psi'      => 'Pidiendo a Google la nota de velocidad',
        'cerrar'   => 'Calculando la nota',
    ];

    /** Topes de lo que se mira, para no eternizarse en sitios enormes. */
    public const MAX_IMAGENES = 12;
    public const MAX_ESTILOS  = 4;
    public const MAX_ENLACES  = 10;

    /**
     * Cuántas páginas se recorren y cuántos enlaces se comprueban a fondo.
     *
     * Cien páginas de fábrica cubren entero el sitio de casi cualquier negocio,
     * y el tope se puede subir hasta TOPE_PAGINAS desde Ajustes. Las fases se
     * reanudan solas entre llamadas, así que un sitio grande tarda más pero no
     * se corta.
     */
    public const MAX_PAGINAS_SEO   = 100;
    public const MAX_ARCHIVOS_JS   = 25;

    /** Techo de lo que se puede pedir desde Ajustes. */
    public const MIN_PAGINAS  = 5;
    public const TOPE_PAGINAS = 2000;

    /**
     * Enlaces que se comprueban uno a uno.
     *
     * No puede ser un número fijo: con el tope en 1.500 páginas, revisar solo
     * 600 enlaces dejaría sin comprobar la mayor parte de las direcciones del
     * mapa, que es justo lo que se quería mirar. Va con el presupuesto de
     * páginas, con un suelo para los sitios pequeños y un techo para que no se
     * dispare.
     */
    public const MAX_VINCULOS  = 600;
    public const TOPE_VINCULOS = 6000;

    /** Cuántas páginas pide el usuario, dentro de lo razonable. */
    public static function topePaginas(): int
    {
        return Ajustes::entero(
            'seo_max_paginas', self::MAX_PAGINAS_SEO, self::MIN_PAGINAS, self::TOPE_PAGINAS
        );
    }

    /** Enlaces a comprobar para ese presupuesto de páginas. */
    public static function topeVinculos(int $paginas): int
    {
        return max(self::MAX_VINCULOS, min(self::TOPE_VINCULOS, $paginas * 3));
    }

    // =====================================================================
    //  Alta y avance
    // =====================================================================

    /**
     * Crea la auditoría y la deja en cola.
     *
     * @param string $papel 'principal' o 'competidor'
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function crear(string $url, string $lote, string $papel = 'principal', ?int $usuarioId = null, string $modo = 'completo'): array
    {
        $modo = in_array($modo, self::MODOS, true) ? $modo : 'completo';

        $url = self::normalizar($url);
        if ($url === '') {
            return ['ok' => false, 'error' => 'Esa dirección no se entiende.'];
        }

        // Validación sin DNS: resolver aquí cada dominio de un lote de cien
        // tardaría minutos. Http revalida con DNS antes de cada descarga, que
        // es donde de verdad importa para la protección contra SSRF.
        $val = Seguridad::validarUrlBasica($url, Ajustes::activo('permitir_privadas'));
        if (!$val['ok']) {
            return ['ok' => false, 'error' => $val['error'] ?? 'Dirección no permitida.'];
        }

        $id = BD::insertar('cr_auditorias', [
            'usuario_id'  => $usuarioId,
            'lote'        => $lote,
            'papel'       => $papel === 'competidor' ? 'competidor' : 'principal',
            'modo'        => $modo,
            'url'         => mb_substr($val['url'], 0, 500),
            'host'        => mb_substr((string) parse_url($val['url'], PHP_URL_HOST), 0, 190),
            'estado'      => 'cola',
            'fase'        => 'portada',
            'token'       => bin2hex(random_bytes(16)),
            'creado'      => date('Y-m-d H:i:s'),
            'actualizado' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'id' => $id];
    }

    /** Completa una dirección a medio escribir ("ejemplo.com" -> "https://ejemplo.com/"). */
    public static function normalizar(string $entrada): string
    {
        $t = trim($entrada);
        if ($t === '') { return ''; }

        // Quita lo que la gente pega de más.
        $t = preg_replace('~\s+~', '', $t) ?? $t;
        if (!preg_match('~^https?://~i', $t)) { $t = 'https://' . ltrim($t, '/'); }

        $p = parse_url($t);
        if (!$p || empty($p['host']) || !str_contains($p['host'], '.')) { return ''; }

        return $t;
    }

    /**
     * Avanza una auditoría lo que dé el presupuesto de tiempo.
     *
     * @return array{ok:bool,estado:string,fase:string,etiqueta:string,progreso:int,error?:string}
     */
    public static function avanzar(int $id, int $presupuestoMs = 6000): array
    {
        $fin = microtime(true) + ($presupuestoMs / 1000);

        $fila = BD::fila('SELECT * FROM `cr_auditorias` WHERE `id` = ?', [$id]);
        if (!$fila) { return ['ok' => false, 'estado' => 'error', 'fase' => '', 'etiqueta' => '', 'progreso' => 0, 'error' => 'Auditoría no encontrada.']; }

        if (in_array($fila['estado'], ['listo', 'error'], true)) {
            return self::progreso($fila);
        }

        $datos = json_decode((string) ($fila['datos'] ?? ''), true);
        if (!is_array($datos)) { $datos = []; }

        $fase = (string) $fila['fase'];
        $modo = (string) ($fila['modo'] ?? 'completo');
        if (!in_array($modo, self::MODOS, true)) { $modo = 'completo'; }

        // Se encadenan fases mientras quede presupuesto: en un servidor rápido
        // una sola llamada puede terminar la auditoría entera.
        while (microtime(true) < $fin) {
            try {
                $datos = self::ejecutarFase($fase, $fila, $datos, $modo);
            } catch (Throwable $e) {
                error_log('Kaptor / auditor fase ' . $fase . ': ' . $e->getMessage());
                // Una fase que revienta no tumba la auditoría: se salta y se
                // sigue, porque el resto de los datos siguen valiendo.
                $datos['fallos'][$fase] = $e->getMessage();
            }

            $siguiente = self::siguienteFase($fase, $modo);

            if ($fase === 'cerrar') {
                self::cerrar($id, $fila, $datos);
                $fila = BD::fila('SELECT * FROM `cr_auditorias` WHERE `id` = ?', [$id]);
                return self::progreso($fila ?: []);
            }

            // La portada que no se pudo abrir no da para seguir.
            if ($fase === 'portada' && empty($datos['portada_ok'])) {
                $fase = 'cerrar';
                continue;
            }

            // Hay fases que no caben en una sola llamada: el rastreo de un
            // sitio de cincuenta páginas, la comprobación de ciento cincuenta
            // enlaces. Esas se quedan donde están hasta que avisan de que
            // terminaron, y mientras tanto el navegador va pidiendo más.
            $bandera = ['rastreo' => '_rastreo_listo', 'vinculos' => '_vinculos_listo', 'codigo' => '_codigo_listo'];
            if (isset($bandera[$fase]) && empty($datos[$bandera[$fase]])) {
                BD::actualizar('cr_auditorias', [
                    'estado' => 'midiendo', 'fase' => $fase, 'datos' => json_encode($datos),
                    'actualizado' => date('Y-m-d H:i:s'),
                ], '`id` = ?', [$id]);
                $fila['estado'] = 'midiendo';
                $fila['fase']   = $fase;
                return self::progreso($fila);
            }

            $fase = $siguiente;
            BD::actualizar('cr_auditorias', [
                'estado' => 'midiendo', 'fase' => $fase, 'datos' => json_encode($datos),
                'actualizado' => date('Y-m-d H:i:s'),
            ], '`id` = ?', [$id]);

            // PageSpeed puede tardar un minuto: nunca se encadena con otra fase.
            if ($fase === 'psi') { break; }
        }

        BD::actualizar('cr_auditorias', [
            'estado' => 'midiendo', 'fase' => $fase, 'datos' => json_encode($datos),
            'actualizado' => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$id]);

        $fila['estado'] = 'midiendo';
        $fila['fase']   = $fase;
        return self::progreso($fila);
    }

    /** Las fases que le tocan a este modo. */
    public static function fasesDe(string $modo): array
    {
        return self::FASES_POR_MODO[$modo] ?? self::FASES_POR_MODO['completo'];
    }

    private static function siguienteFase(string $fase, string $modo = 'completo'): string
    {
        $fases = self::fasesDe($modo);
        $i = array_search($fase, $fases, true);
        return $i === false || $i + 1 >= count($fases) ? 'cerrar' : $fases[$i + 1];
    }

    /** Resumen de estado para el navegador. */
    private static function progreso(array $fila): array
    {
        $fase  = (string) ($fila['fase'] ?? 'portada');
        $fases = self::fasesDe((string) ($fila['modo'] ?? 'completo'));
        $i     = array_search($fase, $fases, true);
        $pct   = $i === false ? 0 : (int) round((($i + 1) / count($fases)) * 100);
        $estado = (string) ($fila['estado'] ?? 'cola');

        return [
            'ok'       => $estado !== 'error',
            'id'       => (int) ($fila['id'] ?? 0),
            'estado'   => $estado,
            'fase'     => $fase,
            'etiqueta' => self::ETIQUETAS[$fase] ?? '',
            'progreso' => $estado === 'listo' ? 100 : min(95, $pct),
            'nota'     => isset($fila['nota']) && $fila['nota'] !== null ? (int) $fila['nota'] : null,
            'host'     => (string) ($fila['host'] ?? ''),
            'error'    => (string) ($fila['error'] ?? ''),
        ];
    }

    // =====================================================================
    //  Las fases
    // =====================================================================

    private static function ejecutarFase(string $fase, array $fila, array $datos, string $modo = 'completo'): array
    {
        return match ($fase) {
            'portada'  => self::fasePortada($fila, $datos),
            'archivos' => self::faseArchivos($fila, $datos),
            'recursos' => self::faseRecursos($fila, $datos),
            'enlaces'  => self::faseEnlaces($fila, $datos),
            'malware'  => self::faseMalware($fila, $datos),
            'psi'      => self::fasePsi($fila, $datos),
            'rastreo'  => self::faseRastreo($fila, $datos, $modo),
            'vinculos' => self::faseVinculos($fila, $datos),
            'codigo'   => self::faseCodigo($fila, $datos),
            default    => $datos,
        };
    }

    /** 1. La portada: el grueso de la información sale de aquí. */
    private static function fasePortada(array $fila, array $datos): array
    {
        $url = (string) $fila['url'];
        $r = Http::obtener($url, ['detalle' => true, 'timeout' => Ajustes::entero('auditor_timeout', 25, 5, 90)]);

        $datos['url_pedida'] = $url;
        $datos['portada_ok'] = $r['ok'];
        $datos['codigo']     = $r['codigo'];
        $datos['error']      = $r['error'];

        if (!$r['ok']) {
            // Muchos sitios de Guatemala solo existen en http://. Si https
            // falla, se reintenta sin cifrar para poder auditarlo igual (y
            // que el informe diga precisamente que no tiene candado).
            if (stripos($url, 'https://') === 0) {
                $r2 = Http::obtener('http://' . substr($url, 8), ['detalle' => true, 'timeout' => 20]);
                if ($r2['ok']) { $r = $r2; $datos['portada_ok'] = true; $datos['error'] = ''; }
            }
            if (!$datos['portada_ok']) { return $datos; }
        }

        $datos['url']       = $r['url_final'];
        $datos['host']      = (string) parse_url($r['url_final'], PHP_URL_HOST);
        $datos['esquema']   = strtolower((string) parse_url($r['url_final'], PHP_URL_SCHEME));
        $datos['codigo']    = $r['codigo'];
        $datos['bytes']     = $r['bytes'];
        $datos['cabeceras'] = $r['cabeceras'];
        $datos['tiempos']   = $r['tiempos'];
        $datos['cert']      = $r['cert'];
        $datos['version']   = $r['version'];
        $datos['html']      = $r['cuerpo'];

        $p = new Pagina($r['cuerpo'], $r['url_final']);
        $datos['titulo'] = $p->titulo();

        // Lo que hace falta en fases posteriores se apunta ya, para no volver
        // a analizar el HTML en cada paso.
        $datos['_imagenes'] = array_slice(array_values(array_filter(
            $p->imagenes(), static fn($i) => $i['url'] !== '' && stripos($i['url'], 'http') === 0
        )), 0, self::MAX_IMAGENES);
        $datos['_estilos']  = array_slice($p->estilos(), 0, self::MAX_ESTILOS);

        $internos = array_values(array_filter($p->enlaces(), static fn($e) => $e['interno'] && stripos($e['url'], 'http') === 0));
        // Sin repetidos y sin la propia portada.
        $vistos = [];
        $lista  = [];
        foreach ($internos as $e) {
            $u = rtrim($e['url'], '/');
            if ($u === rtrim($r['url_final'], '/') || isset($vistos[$u])) { continue; }
            $vistos[$u] = true;
            $lista[] = $e['url'];
        }
        $datos['_enlaces'] = array_slice($lista, 0, self::MAX_ENLACES);

        return $datos;
    }

    /** 2. robots.txt, mapa del sitio, llms.txt y la prueba de http:// */
    private static function faseArchivos(array $fila, array $datos): array
    {
        $raiz = self::raiz((string) ($datos['url'] ?? $fila['url']));

        // robots.txt: se descarga entero porque su contenido se analiza.
        $rob = Http::obtener($raiz . '/robots.txt', ['timeout' => 12, 'max_bytes' => 200000]);
        $datos['robots'] = [
            'existe' => $rob['ok'] && $rob['cuerpo'] !== '',
            'texto'  => $rob['ok'] ? mb_substr($rob['cuerpo'], 0, 20000) : '',
        ];

        // Mapa del sitio. Se buscan los índices además de los mapas sueltos y
        // se siguen: un sitio grande no tiene un mapa, tiene un índice que
        // apunta a diez. Quedarse en el primero es ver el 10 % del sitio.
        $tope = self::topePaginas();
        $mapa = Mapa::descubrir($raiz, (string) $datos['robots']['texto'], max(600, $tope * 6));

        $datos['sitemap'] = [
            'existe'     => $mapa['existe'],
            'url'        => $mapa['mapas'][0] ?? '',
            'mapas'      => $mapa['mapas'],
            'declaradas' => $mapa['declaradas'],
            'origen'     => $mapa['origen'],
            'recortado'  => $mapa['recortado'],
        ];
        // La lista completa se guarda aparte: la usa el rastreo para llegar a
        // TODAS las páginas, no solo a las que cuelgan de la portada.
        $datos['_del_mapa'] = $mapa['urls'];

        $llms = Http::obtener($raiz . '/llms.txt', ['timeout' => 8, 'solo_cabeceras' => true]);
        $datos['llms'] = ['existe' => $llms['ok']];

        // ¿La versión sin cifrar redirige a la cifrada?
        if (($datos['esquema'] ?? '') === 'https') {
            $host = (string) ($datos['host'] ?? '');
            $sin  = Http::obtener('http://' . $host . '/', [
                'timeout' => 10, 'solo_cabeceras' => true, 'sin_redirecciones' => true,
            ]);
            $datos['http_redirige'] = (string) ($sin['redirige'] ?? '');
            // ¿Hay algo escuchando sin cifrar? Si no contesta nadie, no existe
            // una copia insegura del sitio y eso es bueno, no un fallo.
            $datos['http_responde'] = $sin['codigo'] > 0;
        }

        return $datos;
    }

    /** 3. Peso real de las imágenes y contenido de las hojas de estilo. */
    private static function faseRecursos(array $fila, array $datos): array
    {
        // Imágenes: lo que interesa es cuánto pesan.
        $imagenes = [];
        foreach (($datos['_imagenes'] ?? []) as $img) {
            $peso = self::pesoDe($img['url']);
            $imagenes[] = [
                'url'         => $img['url'],
                'bytes'       => $peso['bytes'],
                'tipo'        => $peso['tipo'],
                'exacto'      => $peso['exacto'],
                'alt'         => (bool) $img['alt'],
                'dimensiones' => (bool) $img['dimensiones'],
                'lazy'        => (bool) $img['lazy'],
                'posicion'    => (int) $img['posicion'],
            ];
        }
        $datos['imagenes'] = $imagenes;

        // Estilos: se leen para saber si el diseño se adapta al celular.
        $css = [];
        foreach (($datos['_estilos'] ?? []) as $url) {
            $c = Http::obtener($url, ['timeout' => 12, 'max_bytes' => 900000]);
            $css[] = [
                'url'   => $url,
                'leido' => $c['ok'],
                'bytes' => $c['bytes'],
                'media' => $c['ok'] && (bool) preg_match('~@media[^{]*\(\s*(max|min)-width~i', $c['cuerpo']),
            ];
        }
        // El diseño adaptable también puede vivir dentro de un <style> de la página.
        if (preg_match('~@media[^{]*\(\s*(max|min)-width~i', (string) ($datos['html'] ?? ''))) {
            $css[] = ['url' => '(estilos dentro de la página)', 'leido' => true, 'bytes' => 0, 'media' => true];
        }
        $datos['css'] = $css;

        unset($datos['_imagenes'], $datos['_estilos']);
        return $datos;
    }

    /**
     * Rastreo del sitio: se recorren las páginas siguiendo los enlaces internos.
     *
     * Quedarse en la portada da una foto bonita y poco más. Los problemas que
     * de verdad hunden un sitio —títulos repetidos en veinte páginas, contenido
     * de cuatro frases, páginas a las que no llega nadie— solo salen mirando
     * varias a la vez.
     *
     * Va en anchura y no en profundidad, que es como rastrea Google: primero
     * todo lo que cuelga de la portada, luego el siguiente nivel. Así, si el
     * presupuesto se acaba, lo visto es lo más importante del sitio y no una
     * rama perdida.
     */
    private static function faseRastreo(array $fila, array $datos, string $modo): array
    {
        $tope = self::topePaginas();

        $raiz   = self::raiz((string) ($datos['url'] ?? $fila['url']));
        $inicio = (string) ($datos['url'] ?? $fila['url']);

        $paginas = $datos['paginas'] ?? [];
        $cola    = $datos['_cola']   ?? null;
        $vistas  = $datos['_vistas'] ?? [];

        // --- Primera vuelta: se prepara la cola -----------------------------
        if ($cola === null) {
            // La portada ya está descargada de la fase anterior.
            $p0 = new Pagina((string) ($datos['html'] ?? ''), $inicio);
            $paginas[] = self::fichaPagina($inicio, 200, $p0, (int) ($datos['bytes'] ?? 0), (int) ($datos['tiempos']['total'] ?? 0), 0);

            $vistas = [self::claveUrl($inicio) => true];
            $cola   = [];

            // 1) Lo que cuelga de la portada, por enlaces.
            $datos['_alcanzadas'] = [self::claveUrl($inicio) => true];
            foreach (self::internosDe($p0, $raiz) as $u) {
                $k = self::claveUrl($u);
                $datos['_alcanzadas'][$k] = true;
                if (!isset($vistas[$k])) { $vistas[$k] = true; $cola[] = ['url' => $u, 'nivel' => 1]; }
            }

            // 2) Y TODO lo que declare el mapa del sitio. Esto es lo que hace
            //    que el análisis cubra el sitio entero y no solo la parte bien
            //    enlazada, que es justo donde nunca están los problemas.
            //    Entran al final de la cola: primero lo que se alcanza
            //    navegando, que es lo que de verdad ve un visitante.
            $delMapa = $datos['_del_mapa'] ?? [];
            foreach ($delMapa as $u) {
                $k = self::claveUrl($u);
                if (!isset($vistas[$k])) { $vistas[$k] = true; $cola[] = ['url' => $u, 'nivel' => 9]; }
            }
            $datos['_mapa_claves'] = array_map([self::class, 'claveUrl'], $delMapa);
        }

        // Dónde está cada dirección dentro de la cola. Se rehace en cada llamada
        // porque la cola viaja en la base de datos entre una y otra.
        $indice = [];
        foreach ($cola as $i => $c) { $indice[self::claveUrl($c['url'])] = $i; }

        // --- Rastreo, hasta donde dé el tiempo de esta llamada ---------------
        $hasta = microtime(true) + 4.5;
        while ($cola && count($paginas) < $tope && microtime(true) < $hasta) {
            $clave0 = array_key_first($cola);
            $item   = $cola[$clave0];
            unset($cola[$clave0]);
            $r = Http::obtener($item['url'], ['timeout' => 12, 'max_bytes' => 1500000]);

            if (!$r['ok']) {
                $paginas[] = ['url' => $item['url'], 'codigo' => $r['codigo'], 'error' => true, 'nivel' => $item['nivel']];
                continue;
            }

            $pg = new Pagina($r['cuerpo'], $r['url_final']);
            $paginas[] = self::fichaPagina($r['url_final'], $r['codigo'], $pg, $r['bytes'], $r['ms'], $item['nivel']);

            // En el modo de virus se guarda el HTML de cada página: el código
            // inyectado casi nunca está solo en la portada.
            if ($modo === 'malware' && count($datos['_htmls'] ?? []) < 30) {
                $datos['_htmls'][] = mb_substr($r['cuerpo'], 0, 260000);
            }

            // Los enlaces de esta página: sirven para dos cosas a la vez, para
            // seguir rastreando y para saber a qué páginas SÍ llega alguien
            // navegando. Lo segundo es lo que separa una página huérfana de
            // una normal, y hay que apuntarlo aunque la página ya se conozca.
            foreach (self::internosDe($pg, $raiz) as $u) {
                $k = self::claveUrl($u);
                $datos['_alcanzadas'][$k] = true;

                if (isset($vistas[$k])) {
                    // Ya estaba en la cola por el mapa: se le corrige el nivel,
                    // porque ahora sabemos que sí se llega navegando.
                    //
                    // Esto antes recorría media cola por cada enlace de cada
                    // página. Con cien páginas no se nota; con mil quinientas
                    // y un menú largo son cientos de millones de vueltas, del
                    // orden de medio minuto de puro buscar. Ahora va directo.
                    $pos = $indice[$k] ?? null;
                    if ($pos !== null && isset($cola[$pos]) && $cola[$pos]['nivel'] === 9) {
                        $cola[$pos]['nivel'] = min(9, $item['nivel'] + 1);
                    }
                    continue;
                }
                if ($item['nivel'] < 9) {
                    $vistas[$k] = true;
                    $cola[]     = ['url' => $u, 'nivel' => $item['nivel'] + 1];
                    $indice[$k] = array_key_last($cola);
                }
            }
        }

        // --- Lo aprendido se guarda para la siguiente vuelta ------------------
        // array_values: al sacar de la cola con unset quedan huecos en los
        // índices, y json_encode convertiría el array en un objeto.
        $cola = array_values($cola);

        $datos['paginas'] = $paginas;
        $datos['_cola']   = $cola;
        $datos['_vistas'] = $vistas;

        // Cuánto del sitio se llegó a ver: el informe lo dice tal cual, sin
        // dar a entender que se analizó todo cuando se analizó una parte.
        $datos['cobertura'] = [
            'analizadas' => count($paginas),
            'declaradas' => (int) ($datos['sitemap']['declaradas'] ?? 0),
            'en_cola'    => count($cola),
            'tope'       => $tope,
        ];

        $datos['_rastreo_listo'] = !$cola || count($paginas) >= $tope;

        // Al terminar, las huérfanas: están en el mapa y no las enlaza nadie.
        if ($datos['_rastreo_listo']) {
            $alcanzadas = $datos['_alcanzadas'] ?? [];
            $huerfanas  = [];
            foreach (($datos['_mapa_claves'] ?? []) as $k) {
                if (!isset($alcanzadas[$k])) { $huerfanas[$k] = true; }
            }
            // Y se marca cada página analizada con lo que de verdad es.
            foreach ($datos['paginas'] as $i => $p) {
                $k = self::claveUrl((string) $p['url']);
                $datos['paginas'][$i]['huerfana'] = isset($huerfanas[$k]);
                if (isset($huerfanas[$k])) { $datos['paginas'][$i]['nivel'] = 9; }
                elseif (($p['nivel'] ?? 0) === 9) { $datos['paginas'][$i]['nivel'] = 2; }
            }
            $datos['huerfanas_total'] = count($huerfanas);
        }

        return $datos;
    }

    /** Ficha de una página rastreada: lo que hace falta para el análisis. */
    private static function fichaPagina(string $url, int $codigo, Pagina $p, int $bytes, int $ms, int $nivel): array
    {
        $enc  = $p->encabezados();
        $imgs = $p->imagenes();

        $canonical = '';
        if (preg_match('~<link[^>]+rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']([^"\']+)~i', $p->html(), $m)) {
            $canonical = trim($m[1]);
        }
        $robots = strtolower($p->meta('robots'));

        $internos = 0; $externos = 0;
        foreach ($p->enlaces() as $e) {
            if ($e['interno']) { $internos++; } elseif ($e['externo']) { $externos++; }
        }

        return [
            'url'         => $url,
            'codigo'      => $codigo,
            'error'       => false,
            'nivel'       => $nivel,
            'titulo'      => $p->titulo(),
            'descripcion' => $p->meta('description'),
            'h1'          => count($enc[1]),
            'h1_texto'    => $enc[1][0] ?? '',
            'h2'          => count($enc[2]),
            'palabras'    => $p->palabras(),
            'canonical'   => $canonical,
            'noindex'     => str_contains($robots, 'noindex'),
            'imgs'        => count($imgs),
            'imgs_sin_alt' => count(array_filter($imgs, static fn($i) => empty($i['alt']))),
            'internos'    => $internos,
            'externos'    => $externos,
            'bytes'       => $bytes,
            'ms'          => $ms,
            'og'          => $p->meta('og:title') !== '',
            'schema'      => count($p->tiposSchema()),
        ];
    }

    /** Enlaces internos navegables de una página. */
    private static function internosDe(Pagina $p, string $raiz): array
    {
        $salida = [];
        foreach ($p->enlaces() as $e) {
            if (!$e['interno'] || stripos($e['url'], 'http') !== 0) { continue; }
            // Fuera archivos que no son páginas.
            if (preg_match('~\.(jpe?g|png|gif|webp|avif|svg|ico|css|js|pdf|zip|rar|docx?|xlsx?|mp[34]|avi|mov)(\?|$)~i', $e['url'])) { continue; }
            if (stripos($e['url'], $raiz) !== 0) { continue; }
            $salida[] = $e['url'];
        }
        return array_values(array_unique($salida));
    }

    /** Dos direcciones que solo cambian en la barra final son la misma página. */
    private static function claveUrl(string $url): string
    {
        $sin = (string) preg_replace('~[#?].*$~', '', $url);
        return rtrim(strtolower($sin), '/');
    }

    /**
     * Comprobación de TODOS los enlaces, por dentro y por fuera.
     *
     * Un enlace roto hacia fuera molesta; uno hacia dentro se lleva por delante
     * el rastreo de Google y manda al visitante a una página de error. Aquí se
     * miran los dos, y además se apunta si un enlace pasa por una cadena de
     * redirecciones, que es tiempo perdido en cada visita.
     */
    private static function faseVinculos(array $fila, array $datos): array
    {
        $raiz = self::raiz((string) ($datos['url'] ?? $fila['url']));

        // Se juntan todos los enlaces de todas las páginas rastreadas.
        if (!isset($datos['_porRevisar'])) {
            $todos = [];

            // 1) Cada página que se llegó a abrir ya se sabe si responde.
            foreach (($datos['paginas'] ?? []) as $pag) {
                if (!empty($pag['error'])) { continue; }
                $todos[(string) $pag['url']] = true;
            }

            // 2) Y todos los enlaces que salen de la portada, incluidos los
            //    que apuntan fuera: un enlace roto hacia otro sitio también da
            //    sensación de abandono.
            $p0 = new Pagina((string) ($datos['html'] ?? ''), (string) ($datos['url'] ?? ''));
            foreach ($p0->enlaces() as $e) {
                if (stripos($e['url'], 'http') === 0) { $todos[$e['url']] = true; }
            }

            // 3) Y lo que declare el mapa, aunque no se haya llegado a abrir:
            //    una dirección del mapa que devuelve 404 es de los fallos que
            //    más molestan a Google, y solo se ve comprobándolas.
            foreach (($datos['_del_mapa'] ?? []) as $u) { $todos[$u] = true; }

            $datos['_porRevisar'] = array_slice(array_keys($todos), 0, self::topeVinculos(self::topePaginas()));
            $datos['vinculos'] = ['revisados' => 0, 'rotos' => [], 'redirigidos' => [], 'externos' => 0, 'internos' => 0];
        }

        $hasta = microtime(true) + 4.5;
        $v = $datos['vinculos'];

        while ($datos['_porRevisar'] && microtime(true) < $hasta) {
            $url = array_shift($datos['_porRevisar']);
            $interno = stripos($url, $raiz) === 0;

            $r = Http::obtener($url, ['timeout' => 8, 'solo_cabeceras' => true, 'sin_redirecciones' => true]);
            $v['revisados']++;
            if ($interno) { $v['internos']++; } else { $v['externos']++; }

            // 405 = el servidor no admite HEAD; no es un enlace roto.
            if ($r['codigo'] >= 400 && $r['codigo'] !== 405 && count($v['rotos']) < 60) {
                $v['rotos'][] = ['url' => $url, 'codigo' => $r['codigo'], 'interno' => $interno];
            } elseif (!empty($r['redirige']) && count($v['redirigidos']) < 40) {
                $v['redirigidos'][] = ['url' => $url, 'a' => (string) $r['redirige'], 'interno' => $interno];
            }
        }

        $datos['vinculos'] = $v;
        $datos['_vinculos_listo'] = !$datos['_porRevisar'];
        return $datos;
    }

    /**
     * Descarga y analiza los archivos de JavaScript del sitio.
     *
     * Es lo que separa un vistazo de un análisis de verdad: casi todo el código
     * malicioso de hoy no está en el HTML sino en un archivo .js aparte, que
     * desde la página solo se ve como una línea inocente. Los buenos escáneres
     * del mercado los abren uno a uno; aquí también.
     */
    private static function faseCodigo(array $fila, array $datos): array
    {
        $raiz = self::raiz((string) ($datos['url'] ?? $fila['url']));

        if (!isset($datos['_js'])) {
            // Los de la portada y los de todas las páginas que se rastrearon:
            // el código inyectado suele estar en una plantilla interior, no en
            // la portada, que es la única que el dueño mira.
            $urls = [];
            $p0 = new Pagina((string) ($datos['html'] ?? ''), (string) ($datos['url'] ?? ''));
            foreach ($p0->scripts()['urls'] as $u) { $urls[$u] = true; }

            foreach (($datos['_htmls'] ?? []) as $html) {
                $pg = new Pagina($html, (string) ($datos['url'] ?? ''));
                foreach ($pg->scripts()['urls'] as $u) { $urls[$u] = true; }
            }

            $datos['_js'] = array_slice(array_keys($urls), 0, self::MAX_ARCHIVOS_JS);
            $datos['archivos_js'] = [];
        }

        $hasta = microtime(true) + 4.5;
        while ($datos['_js'] && microtime(true) < $hasta) {
            $url = array_shift($datos['_js']);
            $r = Http::obtener($url, ['timeout' => 12, 'max_bytes' => 1200000]);
            if (!$r['ok'] || $r['cuerpo'] === '') { continue; }

            $datos['archivos_js'][] = [
                'url'     => $url,
                'propio'  => stripos($url, $raiz) === 0,
                'bytes'   => $r['bytes'],
                // Solo se guarda el veredicto, no el archivo: guardar megas de
                // código en la base de datos por cada auditoría no tiene sentido.
                'senales' => Malware::analizarCodigo($r['cuerpo'], $url),
            ];
        }

        $datos['_codigo_listo'] = !$datos['_js'];
        return $datos;
    }

    /**
     * Cuánto pesa un archivo, sin descargarlo entero.
     *
     * Se intentan tres cosas, de la más barata a la más cara, porque no todos
     * los servidores contestan igual:
     *   1. HEAD y leer Content-Length. Lo ideal, pero muchos servidores no lo
     *      mandan en HEAD (y sin esto el chequeo de imágenes pesadas, que es
     *      el más útil del informe, se quedaba callado).
     *   2. Pedir UN byte con Range: el servidor contesta con el tamaño total
     *      en Content-Range. Cuesta lo mismo que nada.
     *   3. Descargar con un tope. Si se llega al tope ya sabemos que la imagen
     *      es pesada, que es justo lo que queríamos averiguar.
     *
     * @return array{bytes:int,tipo:string,exacto:bool}
     */
    private static function pesoDe(string $url): array
    {
        $tope = 400000;

        // 1) HEAD
        $h = Http::obtener($url, ['timeout' => 10, 'solo_cabeceras' => true]);
        $tipo = (string) ($h['cabeceras']['content-type'] ?? '');
        $len  = (int) ($h['cabeceras']['content-length'] ?? 0);
        if ($len > 0) { return ['bytes' => $len, 'tipo' => $tipo, 'exacto' => true]; }

        // 2) Un solo byte, para que el servidor diga el total.
        $r = Http::obtener($url, [
            'timeout'   => 10,
            'max_bytes' => 2048,
            'cabeceras' => ['Accept: */*', 'Range: bytes=0-0'],
        ]);
        if ($tipo === '') { $tipo = (string) ($r['cabeceras']['content-type'] ?? $r['tipo']); }
        $rango = (string) ($r['cabeceras']['content-range'] ?? '');
        if ($rango !== '' && preg_match('~/\s*(\d+)\s*$~', $rango, $m)) {
            return ['bytes' => (int) $m[1], 'tipo' => $tipo, 'exacto' => true];
        }

        // 3) Descarga con tope.
        $g = Http::obtener($url, ['timeout' => 12, 'max_bytes' => $tope]);
        if (!$g['ok'] && $g['bytes'] === 0) { return ['bytes' => 0, 'tipo' => $tipo, 'exacto' => false]; }
        if ($tipo === '') { $tipo = $g['tipo']; }

        return [
            'bytes'  => $g['bytes'],
            'tipo'   => $tipo,
            // Si se cortó en el tope, el archivo es MÁS grande que esto.
            'exacto' => $g['bytes'] < $tope,
        ];
    }

    /** 4. Enlaces internos que no llevan a ninguna parte. */
    private static function faseEnlaces(array $fila, array $datos): array
    {
        $rotos = [];
        $n = 0;
        foreach (($datos['_enlaces'] ?? []) as $url) {
            $h = Http::obtener($url, ['timeout' => 9, 'solo_cabeceras' => true]);
            $n++;
            // 405 = el servidor no admite HEAD, no es un enlace roto.
            if (!$h['ok'] && $h['codigo'] >= 400 && $h['codigo'] !== 405) {
                $rotos[] = ['url' => $url, 'codigo' => $h['codigo']];
            }
        }
        $datos['enlaces_rotos']    = $rotos;
        $datos['enlaces_probados'] = $n;

        unset($datos['_enlaces']);
        return $datos;
    }

    /**
     * 5. Código malicioso.
     *
     * La página se vuelve a pedir dos veces más, haciéndose pasar por el robot
     * de Google y por un celular. La infección más común en los sitios hechos
     * con WordPress no se le enseña al visitante: se le enseña SOLO a Google
     * (para colocar spam) o SOLO a quien entra desde el teléfono (para
     * mandarlo a otro sitio). Sin comparar las tres respuestas no se ve.
     */
    private static function faseMalware(array $fila, array $datos): array
    {
        if (!Ajustes::activo('malware_activo', true)) { return $datos; }

        $url = (string) ($datos['url'] ?? $fila['url']);

        // --- Como el robot de Google ---------------------------------------
        $g = Http::obtener($url, [
            'timeout'   => 18,
            'agente'    => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'cabeceras' => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: es-ES,es;q=0.9'],
        ]);
        $datos['google_consultado'] = $g['ok'];
        if ($g['ok']) { $datos['html_google'] = $g['cuerpo']; }

        // --- Como un celular, sin seguir la redirección --------------------
        // Interesa saber SI redirige y adónde, no adónde acaba llegando.
        $m = Http::obtener($url, [
            'timeout'           => 15,
            'solo_cabeceras'    => true,
            'sin_redirecciones' => true,
            'agente'            => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 '
                                 . '(KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'cabeceras'         => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: es-ES,es;q=0.9'],
        ]);

        $destino = (string) ($m['redirige'] ?? '');
        $fuera   = '';
        if ($destino !== '') {
            $hostDestino = strtolower((string) parse_url($destino, PHP_URL_HOST));
            $hostPropio  = strtolower((string) ($datos['host'] ?? ''));
            // Redirigir dentro del propio sitio es normal (a /es, a /inicio...).
            // Mandar al celular a OTRO dominio no lo es nunca.
            $mismo = $hostDestino === '' || $hostDestino === $hostPropio
                  || str_ends_with($hostDestino, '.' . $hostPropio)
                  || str_ends_with($hostPropio, '.' . $hostDestino);
            if (!$mismo) { $fuera = $destino; }
        }
        $datos['redirige_movil'] = [
            'consultado' => $m['codigo'] > 0,
            'destino'    => $fuera,
        ];

        // --- ¿Google tiene el dominio marcado? ------------------------------
        $datos['lista_negra'] = Malware::listaNegra($url);

        // --- Y los setenta motores de VirusTotal ----------------------------
        $datos['virustotal'] = Malware::virusTotal($url);

        return $datos;
    }

    /** 6. La nota de Google (opcional). */
    private static function fasePsi(array $fila, array $datos): array
    {
        $psi = Psi::analizar((string) ($datos['url'] ?? $fila['url']));
        if (is_array($psi) && !isset($psi['error'])) {
            $datos['psi'] = $psi;
        } elseif (is_array($psi)) {
            $datos['psi_aviso'] = $psi['error'];
        }
        return $datos;
    }

    // =====================================================================
    //  Cierre: chequeos, nota y guardado
    // =====================================================================

    private static function cerrar(int $id, array $fila, array $datos): void
    {
        if (empty($datos['portada_ok'])) {
            BD::actualizar('cr_auditorias', [
                'estado' => 'error',
                'fase'   => 'cerrar',
                'error'  => mb_substr((string) ($datos['error'] ?: 'No se pudo abrir la página.'), 0, 255),
                'datos'  => null,
                'actualizado' => date('Y-m-d H:i:s'),
            ], '`id` = ?', [$id]);
            return;
        }

        $pagina    = new Pagina((string) ($datos['html'] ?? ''), (string) ($datos['url'] ?? ''));
        $hallazgos = Chequeos::todos($datos, $pagina);
        $notas     = Informe::notas($hallazgos);
        $modo      = (string) ($fila['modo'] ?? 'completo');

        // En los modos a fondo la nota que se guarda es la de SU área: es la
        // que el usuario pidió y la que tiene que salir en las listas.
        $notaModo = Informe::notaDeModo($modo, $notas['global'], $notas['areas']);

        // El HTML crudo no se guarda: ya se exprimió y ocuparía megas por fila.
        // Lo que solo servía para trabajar se tira: el HTML de cada página y
        // las colas del rastreo ocupan megas y ya no hacen falta.
        //
        // Se tira TODO lo que empieza por guion bajo, que es la marca de
        // "esto es andamio". Antes iban uno a uno y se colaban los que se
        // añadían después: en un sitio de mil páginas, las listas de control
        // del rastreo eran las dos terceras partes de la fila guardada.
        $guardar = $datos;
        unset($guardar['html'], $guardar['html_google']);
        foreach (array_keys($guardar) as $k) {
            if ($k !== '' && $k[0] === '_') { unset($guardar[$k]); }
        }

        BD::actualizar('cr_auditorias', [
            'estado'      => 'listo',
            'fase'        => 'cerrar',
            'nota'        => $notaModo,
            'notas_area'  => json_encode($notas['areas'] + ['_global' => $notas['global']]),
            'titulo'      => mb_substr((string) ($datos['titulo'] ?? ''), 0, 255),
            'error'       => '',
            'datos'       => json_encode($guardar),
            'hallazgos'   => json_encode($hallazgos),
            'actualizado' => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$id]);
    }

    /** Esquema + host de una dirección, sin ruta. */
    public static function raiz(string $url): string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host'])) { return ''; }
        return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    // =====================================================================
    //  Consultas
    // =====================================================================

    /** Una auditoría por su id. */
    public static function porId(int $id): ?array
    {
        return BD::fila('SELECT * FROM `cr_auditorias` WHERE `id` = ?', [$id]);
    }

    /** Una auditoría por su enlace para compartir. */
    public static function porToken(string $token): ?array
    {
        if (!preg_match('~^[a-f0-9]{32}$~', $token)) { return null; }
        return BD::fila('SELECT * FROM `cr_auditorias` WHERE `token` = ?', [$token]);
    }

    /** Todas las de una tanda, la principal primero. */
    public static function porLote(string $lote): array
    {
        if ($lote === '') { return []; }
        return BD::todos(
            "SELECT * FROM `cr_auditorias` WHERE `lote` = ?
              ORDER BY (`papel` = 'principal') DESC, `id` ASC",
            [$lote]
        );
    }

    /** Historial del usuario (solo las principales). */
    public static function historial(?int $usuarioId, int $limite = 50, string $modo = ''): array
    {
        $sql = "SELECT `id`,`url`,`host`,`titulo`,`estado`,`nota`,`notas_area`,`lote`,`token`,`modo`,`creado`
                  FROM `cr_auditorias` WHERE `papel` = 'principal'";
        $par = [];
        if ($usuarioId !== null) { $sql .= ' AND `usuario_id` = ?'; $par[] = $usuarioId; }
        if ($modo !== '' && in_array($modo, self::MODOS, true)) { $sql .= ' AND `modo` = ?'; $par[] = $modo; }
        $sql .= ' ORDER BY `id` DESC LIMIT ' . max(1, min(200, $limite));
        return BD::todos($sql, $par);
    }

    /** Borra una auditoría (y sus competidores si es la principal del lote). */
    public static function borrar(int $id, ?int $usuarioId): bool
    {
        $fila = self::porId($id);
        if (!$fila) { return false; }
        if ($usuarioId !== null && (int) $fila['usuario_id'] !== $usuarioId) { return false; }

        if ($fila['papel'] === 'principal' && $fila['lote'] !== '') {
            BD::ejecutar('DELETE FROM `cr_auditorias` WHERE `lote` = ?', [$fila['lote']]);
        } else {
            BD::ejecutar('DELETE FROM `cr_auditorias` WHERE `id` = ?', [$id]);
        }
        return true;
    }
}
