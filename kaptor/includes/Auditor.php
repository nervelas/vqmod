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
    /** Orden de las fases. La última calcula la nota y cierra. */
    public const FASES = ['portada', 'archivos', 'recursos', 'enlaces', 'malware', 'psi', 'cerrar'];

    /** Cuánto se enseña de cada fase mientras corre. */
    public const ETIQUETAS = [
        'portada'  => 'Abriendo la página',
        'archivos' => 'Buscando robots.txt y el mapa del sitio',
        'recursos' => 'Pesando imágenes y estilos',
        'enlaces'  => 'Comprobando enlaces',
        'malware'  => 'Buscando código malicioso',
        'psi'      => 'Pidiendo a Google la nota de velocidad',
        'cerrar'   => 'Calculando la nota',
    ];

    /** Topes de lo que se mira, para no eternizarse en sitios enormes. */
    public const MAX_IMAGENES = 12;
    public const MAX_ESTILOS  = 4;
    public const MAX_ENLACES  = 10;

    // =====================================================================
    //  Alta y avance
    // =====================================================================

    /**
     * Crea la auditoría y la deja en cola.
     *
     * @param string $papel 'principal' o 'competidor'
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function crear(string $url, string $lote, string $papel = 'principal', ?int $usuarioId = null): array
    {
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

        // Se encadenan fases mientras quede presupuesto: en un servidor rápido
        // una sola llamada puede terminar la auditoría entera.
        while (microtime(true) < $fin) {
            try {
                $datos = self::ejecutarFase($fase, $fila, $datos);
            } catch (Throwable $e) {
                error_log('Kaptor / auditor fase ' . $fase . ': ' . $e->getMessage());
                // Una fase que revienta no tumba la auditoría: se salta y se
                // sigue, porque el resto de los datos siguen valiendo.
                $datos['fallos'][$fase] = $e->getMessage();
            }

            $siguiente = self::siguienteFase($fase);

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

    private static function siguienteFase(string $fase): string
    {
        $i = array_search($fase, self::FASES, true);
        return $i === false || $i + 1 >= count(self::FASES) ? 'cerrar' : self::FASES[$i + 1];
    }

    /** Resumen de estado para el navegador. */
    private static function progreso(array $fila): array
    {
        $fase = (string) ($fila['fase'] ?? 'portada');
        $i    = array_search($fase, self::FASES, true);
        $pct  = $i === false ? 0 : (int) round((($i + 1) / count(self::FASES)) * 100);
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

    private static function ejecutarFase(string $fase, array $fila, array $datos): array
    {
        return match ($fase) {
            'portada'  => self::fasePortada($fila, $datos),
            'archivos' => self::faseArchivos($fila, $datos),
            'recursos' => self::faseRecursos($fila, $datos),
            'enlaces'  => self::faseEnlaces($fila, $datos),
            'malware'  => self::faseMalware($fila, $datos),
            'psi'      => self::fasePsi($fila, $datos),
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

        // Mapa del sitio: vale el que declare robots.txt o el de la ruta de siempre.
        $mapa = '';
        if (preg_match('~^\s*sitemap:\s*(\S+)~mi', $datos['robots']['texto'], $m)) {
            $mapa = trim($m[1]);
        }
        $candidatos = array_values(array_filter([$mapa, $raiz . '/sitemap.xml', $raiz . '/sitemap_index.xml']));
        $datos['sitemap'] = ['existe' => false, 'url' => ''];
        foreach ($candidatos as $cand) {
            $s = Http::obtener($cand, ['timeout' => 10, 'solo_cabeceras' => true]);
            if ($s['ok']) { $datos['sitemap'] = ['existe' => true, 'url' => $cand]; break; }
        }

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

        // El HTML crudo no se guarda: ya se exprimió y ocuparía megas por fila.
        $guardar = $datos;
        unset($guardar['html'], $guardar['html_google']);

        BD::actualizar('cr_auditorias', [
            'estado'      => 'listo',
            'fase'        => 'cerrar',
            'nota'        => $notas['global'],
            'notas_area'  => json_encode($notas['areas']),
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
    public static function historial(?int $usuarioId, int $limite = 50): array
    {
        $sql = "SELECT `id`,`url`,`host`,`titulo`,`estado`,`nota`,`notas_area`,`lote`,`token`,`creado`
                  FROM `cr_auditorias` WHERE `papel` = 'principal'";
        $par = [];
        if ($usuarioId !== null) { $sql .= ' AND `usuario_id` = ?'; $par[] = $usuarioId; }
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
