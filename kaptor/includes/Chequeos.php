<?php
/**
 * Kaptor - Los chequeos del auditor web.
 *
 * Cada chequeo devuelve un HALLAZGO con cuatro cosas:
 *   titulo   qué se encontró, en una línea
 *   cuesta   qué le cuesta ESO al dueño del negocio, en su idioma
 *   arreglo  qué hay que hacer
 *   valor    el dato medido, para enseñarlo en el informe
 *
 * El campo "cuesta" es el que vende. Un informe que dice "falta la etiqueta
 * meta description" no mueve a nadie; uno que dice "Google se está inventando
 * el texto que aparece bajo tu nombre en los resultados" sí.
 *
 * Los pesos son puntos dentro de su área. Un chequeo que no se pudo medir se
 * marca 'na' y sale del reparto, de modo que una web que no se dejó analizar
 * no aparece penalizada por algo que nunca se comprobó.
 */
declare(strict_types=1);

final class Chequeos
{
    /** Las siete áreas y lo que pesa cada una en la nota global (suma 100). */
    public const AREAS = [
        'velocidad'  => ['nombre' => 'Velocidad',           'peso' => 19, 'icono' => 'rayo'],
        'movil'      => ['nombre' => 'Celular',             'peso' => 18, 'icono' => 'movil'],
        'seo'        => ['nombre' => 'Google',              'peso' => 18, 'icono' => 'lupa'],
        'malware'    => ['nombre' => 'Código malicioso',    'peso' => 14, 'icono' => 'virus'],
        'seguridad'  => ['nombre' => 'Seguridad',           'peso' => 13, 'icono' => 'escudo'],
        'negocio'    => ['nombre' => 'Contacto y ventas',   'peso' => 12, 'icono' => 'chat'],
        'ia'         => ['nombre' => 'Visibilidad en IA',   'peso' =>  6, 'icono' => 'chispa'],
    ];

    /** Estados posibles de un hallazgo. */
    public const BIEN = 'bien';
    public const AVISO = 'aviso';
    public const MAL = 'mal';
    public const NA = 'na';

    /**
     * Lanza todos los chequeos sobre los datos recogidos.
     *
     * @param array  $d Datos del recolector (Auditor::recoger)
     * @return array<int,array>
     */
    public static function todos(array $d, Pagina $p): array
    {
        return array_merge(
            self::velocidad($d, $p),
            self::movil($d, $p),
            self::seguridad($d, $p),
            self::seo($d, $p),
            self::negocio($d, $p),
            self::ia($d, $p),
            self::malware($d, $p),
            // Y, cuando hubo rastreo, lo que solo se ve comparando páginas
            // entre sí: títulos repetidos, contenido pobre, enlaces rotos...
            Seo::chequeos($d)
        );
    }

    /** Arma un hallazgo. */
    private static function h(
        string $clave, string $area, string $estado, int $peso,
        string $titulo, string $cuesta = '', string $arreglo = '', string $valor = '',
        bool $critico = false
    ): array {
        return [
            'clave' => $clave, 'area' => $area, 'estado' => $estado, 'peso' => $peso,
            'titulo' => $titulo, 'cuesta' => $cuesta, 'arreglo' => $arreglo, 'valor' => $valor,
            // Un hallazgo crítico no se promedia con lo demás: si el sitio
            // está infectado, da igual lo bonita que sea su descripción para
            // Google. Informe tapa la nota global cuando hay alguno.
            'critico' => $critico,
        ];
    }

    /** Elige estado según un umbral de menor-es-mejor. */
    private static function umbral(float $valor, float $bien, float $aviso): string
    {
        if ($valor <= $bien)  { return self::BIEN; }
        if ($valor <= $aviso) { return self::AVISO; }
        return self::MAL;
    }

    /** Milisegundos en algo legible: "1.4 s" o "380 ms". */
    private static function ms(int $ms): string
    {
        return $ms >= 1000 ? number_format($ms / 1000, 1, ',', '.') . ' s' : $ms . ' ms';
    }

    /** Bytes en algo legible. */
    private static function pesoTxt(int $bytes): string
    {
        if ($bytes >= 1048576) { return number_format($bytes / 1048576, 1, ',', '.') . ' MB'; }
        if ($bytes >= 1024)    { return number_format($bytes / 1024, 0, ',', '.') . ' KB'; }
        return $bytes . ' B';
    }

    // =====================================================================
    //  1. VELOCIDAD
    // =====================================================================

    private static function velocidad(array $d, Pagina $p): array
    {
        $r    = [];
        $t    = $d['tiempos'] ?? [];
        $cab  = $d['cabeceras'] ?? [];
        $psi  = $d['psi'] ?? null;

        // --- Respuesta del servidor (TTFB) --------------------------------
        $ttfb = (int) ($t['ttfb'] ?? 0);
        if ($ttfb > 0) {
            $estado = self::umbral($ttfb, 800, 1800);
            $r[] = self::h('ttfb', 'velocidad', $estado, 12,
                $estado === self::BIEN
                    ? 'El servidor responde rápido'
                    : 'El servidor tarda en responder',
                'Es el tiempo que pasa desde que alguien toca el enlace hasta que el servidor dice la primera palabra. Todo lo demás ocurre después de esto: si aquí ya se pierde un segundo, la página nunca podrá sentirse rápida.',
                'Revisa el plan de hosting y activa una caché del lado del servidor. Es la mejora con mejor relación esfuerzo/resultado de toda la lista.',
                self::ms($ttfb));
        }

        // --- Tiempo total de descarga del documento -----------------------
        $total = (int) ($t['total'] ?? 0);
        if ($total > 0) {
            $estado = self::umbral($total, 1500, 3500);
            $r[] = self::h('carga', 'velocidad', $estado, 7,
                'La página tarda ' . self::ms($total) . ' en descargarse',
                'Más de la mitad de las visitas desde el celular se van si la página tarda más de tres segundos. Se van antes de ver nada, así que ni siquiera aparecen como visita perdida: simplemente no existen.',
                'Reduce el peso de las imágenes y activa la compresión. Son los dos cambios que más bajan este número.',
                self::ms($total));
        }

        // --- Peso del HTML -------------------------------------------------
        $bytes = (int) ($d['bytes'] ?? 0);
        if ($bytes > 0) {
            $estado = self::umbral($bytes, 120000, 400000);
            $r[] = self::h('peso_html', 'velocidad', $estado, 5,
                'El código de la página pesa ' . self::pesoTxt($bytes),
                'Un código inflado obliga al celular a leer mucho antes de poder dibujar nada, y con datos móviles se nota.',
                'Quita el código que no se usa y evita pegar contenido dentro del HTML que debería ir en archivos aparte.',
                self::pesoTxt($bytes));
        }

        // --- Compresión ----------------------------------------------------
        $enc = strtolower((string) ($cab['content-encoding'] ?? ''));
        $comprimido = $enc !== '' && preg_match('~gzip|br|deflate|zstd~', $enc);
        $r[] = self::h('compresion', 'velocidad', $comprimido ? self::BIEN : self::MAL, 9,
            $comprimido ? 'La página viaja comprimida' : 'La página viaja sin comprimir',
            'Comprimir reduce el texto de la página entre un 60 % y un 80 % sin cambiar nada de lo que se ve. No hacerlo es regalar tiempo de carga a cambio de nada.',
            'Pídele a tu proveedor de hosting que active gzip o brotli, o añádelo en el archivo .htaccess. Es un cambio de dos líneas.',
            $comprimido ? strtoupper($enc) : 'sin comprimir');

        // --- Caché del navegador -------------------------------------------
        $cache = strtolower((string) ($cab['cache-control'] ?? ''));
        $tieneCache = $cache !== '' && !preg_match('~no-store|no-cache|max-age\s*=\s*0~', $cache);
        $r[] = self::h('cache', 'velocidad', $tieneCache ? self::BIEN : self::AVISO, 5,
            $tieneCache ? 'El navegador puede guardar la página' : 'El navegador tiene que descargarlo todo cada vez',
            'Sin caché, quien vuelve a tu sitio lo descarga entero otra vez. La segunda visita debería ser instantánea y no lo es.',
            'Configura las cabeceras de caché para las imágenes, el CSS y el JavaScript. Tu proveedor de hosting puede activarlo.',
            $tieneCache ? 'activada' : 'desactivada');

        // --- Protocolo ------------------------------------------------------
        $ver = (string) ($d['version'] ?? '');
        if ($ver !== '') {
            $moderno = in_array($ver, ['2', '3'], true);
            $r[] = self::h('http2', 'velocidad', $moderno ? self::BIEN : self::AVISO, 5,
                $moderno ? 'Usa un protocolo moderno (HTTP/' . $ver . ')' : 'Usa un protocolo antiguo (HTTP/' . $ver . ')',
                'HTTP/2 descarga todos los archivos de la página a la vez. Con el protocolo antiguo van en fila de uno en uno, y eso se nota en páginas con muchas imágenes.',
                'Casi todos los hosting ya lo soportan: normalmente solo hay que pedir que lo activen.',
                'HTTP/' . $ver);
        }

        // --- Imágenes pesadas ------------------------------------------------
        $imgs = $d['imagenes'] ?? [];
        $conPeso = array_values(array_filter($imgs, static fn($i) => (int) ($i['bytes'] ?? 0) > 0));
        if ($conPeso) {
            $pesadas = array_values(array_filter($conPeso, static fn($i) => (int) $i['bytes'] > 250000));
            $suma    = array_sum(array_map(static fn($i) => (int) $i['bytes'], $conPeso));
            $estado  = count($pesadas) === 0 ? self::BIEN : (count($pesadas) <= 2 ? self::AVISO : self::MAL);

            $r[] = self::h('imagenes_peso', 'velocidad', $estado, 12,
                count($pesadas) === 0
                    ? 'Las imágenes tienen un peso razonable'
                    : count($pesadas) . ' ' . (count($pesadas) === 1 ? 'imagen pesa' : 'imágenes pesan') . ' más de lo que debería',
                'Las imágenes sin optimizar son, casi siempre, la razón número uno de que una página vaya lenta. Una sola foto de 3 MB puede tardar más en bajar que todo el resto del sitio junto.',
                'Reduce las fotos al tamaño en que realmente se ven y guárdalas en formato WebP. Se puede bajar el peso un 80 % sin que se note a simple vista.',
                // Cuando el servidor no dice el tamaño hay que cortar la descarga,
                // y entonces lo medido es un suelo, no la cifra real: se dice así.
                'de ' . count($conPeso) . ' imágenes, '
                    . (count(array_filter($conPeso, static fn($i) => empty($i['exacto']))) > 0 ? 'más de ' : '')
                    . self::pesoTxt($suma) . ' en total');

            // Formatos antiguos
            $viejas = array_values(array_filter($conPeso, static function ($i) {
                $tipo = strtolower((string) ($i['tipo'] ?? ''));
                return (int) $i['bytes'] > 80000 && (str_contains($tipo, 'jpeg') || str_contains($tipo, 'jpg') || str_contains($tipo, 'png'));
            }));
            if ($viejas) {
                $r[] = self::h('imagenes_formato', 'velocidad', count($viejas) > 3 ? self::MAL : self::AVISO, 6,
                    count($viejas) . ' ' . (count($viejas) === 1 ? 'imagen usa' : 'imágenes usan') . ' un formato antiguo',
                    'JPG y PNG son formatos de hace treinta años. WebP da la misma calidad ocupando la mitad, y todos los navegadores de hoy lo entienden.',
                    'Convierte las fotos a WebP. Hay plugins que lo hacen solo y herramientas gratuitas en línea.',
                    count($viejas) . ' en JPG/PNG');
            }
        }

        // --- Imágenes sin medidas: provocan saltos del diseño -----------------
        if ($imgs) {
            $sinDim = count(array_filter($imgs, static fn($i) => empty($i['dimensiones'])));
            if ($sinDim > 0) {
                $prop = $sinDim / max(1, count($imgs));
                $r[] = self::h('imagenes_medidas', 'velocidad', $prop > 0.5 ? self::MAL : self::AVISO, 5,
                    $sinDim . ' de ' . count($imgs) . ' imágenes no declaran su tamaño',
                    'Es lo que hace que la página "salte" mientras carga y que el visitante acabe tocando un botón que no quería. Google lo mide y lo penaliza.',
                    'Añade los atributos width y height a cada etiqueta de imagen, con las medidas reales del archivo.',
                    $sinDim . ' sin medidas');
            }

            // Carga diferida de lo que está más abajo.
            $abajo = array_values(array_filter($imgs, static fn($i) => (int) ($i['posicion'] ?? 0) >= 3));
            if (count($abajo) >= 3) {
                $sinLazy = count(array_filter($abajo, static fn($i) => empty($i['lazy'])));
                if ($sinLazy >= 3) {
                    $r[] = self::h('imagenes_diferidas', 'velocidad', self::AVISO, 4,
                        'Las imágenes del final se descargan aunque nadie baje a verlas',
                        'El visitante paga con su tiempo y con sus datos móviles por fotos que quizá nunca llegue a ver.',
                        'Añade loading="lazy" a las imágenes que quedan por debajo de la primera pantalla.',
                        $sinLazy . ' sin carga diferida');
                }
            }
        }

        // --- JavaScript que frena el pintado ----------------------------------
        $sc = $p->scripts();
        if ($sc['bloqueantes'] > 0) {
            $r[] = self::h('js_bloqueante', 'velocidad', $sc['bloqueantes'] > 2 ? self::MAL : self::AVISO, 7,
                $sc['bloqueantes'] . ' ' . ($sc['bloqueantes'] === 1 ? 'archivo de código frena' : 'archivos de código frenan') . ' el dibujado de la página',
                'El navegador se detiene a descargar y ejecutar esos archivos antes de enseñar nada. Durante ese rato el visitante ve una pantalla en blanco.',
                'Añade defer o async a esas etiquetas de script, o muévelas al final de la página.',
                (string) $sc['bloqueantes']);
        }

        // --- Número de archivos ------------------------------------------------
        $recursos = count($p->estilos()) + $sc['externos'];
        if ($recursos > 0) {
            $estado = self::umbral($recursos, 20, 40);
            $r[] = self::h('recursos', 'velocidad', $estado, 4,
                'La página pide ' . $recursos . ' archivos de código y estilos',
                'Cada archivo es un viaje de ida y vuelta al servidor. Muchos archivos pequeños pueden ser más lentos que uno grande.',
                'Junta los archivos CSS y JavaScript, y quita los plugins que ya no uses.',
                (string) $recursos);
        }

        // --- Datos reales de Google (PageSpeed) ---------------------------------
        if (is_array($psi) && isset($psi['rendimiento'])) {
            $nota = (int) $psi['rendimiento'];
            $estado = $nota >= 90 ? self::BIEN : ($nota >= 50 ? self::AVISO : self::MAL);
            $r[] = self::h('psi_rendimiento', 'velocidad', $estado, 14,
                'Google puntúa la velocidad en celular con ' . $nota . ' sobre 100',
                'Es la misma nota que Google usa para decidir posiciones. Por debajo de 50 el sitio se considera lento y compite en desventaja frente a cualquiera que cargue rápido.',
                'Ataca primero las imágenes y el tiempo de respuesta del servidor: suelen valer la mitad de la nota.',
                $nota . '/100');

            if (isset($psi['lcp']) && (int) $psi['lcp'] > 0) {
                $lcp = (int) $psi['lcp'];
                $r[] = self::h('lcp', 'velocidad', self::umbral($lcp, 2500, 4000), 8,
                    'Lo principal de la pantalla aparece en ' . self::ms($lcp),
                    'Es el momento en que el visitante por fin ve aquello a lo que venía. Google quiere que ocurra antes de dos segundos y medio.',
                    'Casi siempre el culpable es la imagen grande de arriba: redúcela y cárgala con prioridad.',
                    self::ms($lcp));
            }
            if (isset($psi['cls'])) {
                $cls = (float) $psi['cls'];
                $r[] = self::h('cls', 'velocidad', self::umbral($cls, 0.1, 0.25), 6,
                    'Estabilidad del diseño mientras carga: ' . number_format($cls, 2, ',', '.'),
                    'Mide cuánto se mueven las cosas de sitio mientras la página termina de cargar. Es lo que provoca que alguien toque el botón equivocado.',
                    'Reserva el espacio de las imágenes y de los anuncios declarando su tamaño.',
                    number_format($cls, 2, ',', '.'));
            }
        }

        return $r;
    }

    // =====================================================================
    //  2. CELULAR
    // =====================================================================

    private static function movil(array $d, Pagina $p): array
    {
        $r = [];

        // --- La etiqueta que lo decide todo ---------------------------------
        $vp = $p->meta('viewport');
        if ($vp === '') {
            $r[] = self::h('viewport', 'movil', self::MAL, 25,
                'La página no está preparada para celulares',
                'Sin esta instrucción el celular enseña la versión de computadora encogida: letras ilegibles y botones imposibles de tocar. Hoy más de siete de cada diez visitas llegan desde un teléfono, así que es como cerrarle la puerta a la mayoría. Google además manda al final a los sitios que no funcionan en celular.',
                'Añade la etiqueta viewport en la cabecera del sitio. Es una línea, pero luego hay que revisar que el diseño de verdad se adapte.',
                'ausente');
        } else {
            $r[] = self::h('viewport', 'movil', self::BIEN, 25,
                'La página se adapta al ancho del celular', '', '', 'presente');

            // Bloquear el zoom deja fuera a quien no ve bien.
            if (preg_match('~user-scalable\s*=\s*(no|0)|maximum-scale\s*=\s*1(\.0)?\b~i', $vp)) {
                $r[] = self::h('zoom_bloqueado', 'movil', self::AVISO, 5,
                    'La página impide ampliar con los dedos',
                    'Cualquiera que necesite acercar para leer un teléfono o una dirección no puede hacerlo, y se va.',
                    'Quita user-scalable=no y maximum-scale de la etiqueta viewport.',
                    'zoom bloqueado');
            }
        }

        // --- ¿Hay diseño adaptable de verdad? --------------------------------
        $css = $d['css'] ?? [];
        $revisados = array_values(array_filter($css, static fn($c) => !empty($c['leido'])));
        if ($revisados) {
            $conMedia = array_values(array_filter($revisados, static fn($c) => !empty($c['media'])));
            $r[] = self::h('css_adaptable', 'movil', $conMedia ? self::BIEN : self::MAL, 20,
                $conMedia
                    ? 'El diseño cambia según el tamaño de pantalla'
                    : 'El diseño no cambia según el tamaño de pantalla',
                'Aunque exista la etiqueta de celular, si los estilos no tienen reglas para pantallas pequeñas el contenido se sale de la pantalla y hay que arrastrar a los lados para leer.',
                'El diseño necesita reglas específicas para pantallas pequeñas. Es trabajo de maquetación, no una casilla que se active.',
                $conMedia ? count($conMedia) . ' de ' . count($revisados) . ' hojas de estilo' : 'ninguna regla encontrada');
        }

        // --- Anchos fijos en el HTML ------------------------------------------
        if (preg_match_all('~width\s*:\s*(\d{4,})px~i', $d['html'] ?? '', $m)) {
            $r[] = self::h('ancho_fijo', 'movil', self::AVISO, 8,
                'Hay bloques con un ancho fijo mayor que la pantalla de un celular',
                'Un ancho fijo grande obliga a desplazarse en horizontal para leer, que es la forma más rápida de que alguien cierre la página.',
                'Sustituye los anchos fijos en píxeles por anchos flexibles (porcentajes o max-width).',
                count($m[1]) . ' bloques');
        }

        // --- Flash y otros fósiles --------------------------------------------
        if (preg_match('~<(object|embed)[^>]+(flash|shockwave|\.swf)~i', $d['html'] ?? '')) {
            $r[] = self::h('flash', 'movil', self::MAL, 6,
                'La página usa Flash',
                'Flash dejó de existir en 2020. Ningún celular ni navegador actual lo abre: ese contenido está sencillamente en blanco para todo el mundo.',
                'Sustituye el contenido en Flash por vídeo o HTML normal.',
                'presente');
        }

        // --- Nota de Google ----------------------------------------------------
        $psi = $d['psi'] ?? null;
        if (is_array($psi) && isset($psi['accesibilidad'])) {
            $nota = (int) $psi['accesibilidad'];
            $r[] = self::h('psi_accesibilidad', 'movil', $nota >= 90 ? self::BIEN : ($nota >= 60 ? self::AVISO : self::MAL), 12,
                'Google puntúa la facilidad de uso con ' . $nota . ' sobre 100',
                'Mide si los textos se leen, si los botones se pueden tocar y si el sitio sirve para alguien con poca visión. Es también lo que revisa quien se plantea demandar por accesibilidad.',
                'Sube el contraste de los textos, agranda los botones y pon texto alternativo en las imágenes.',
                $nota . '/100');
        }

        return $r;
    }

    // =====================================================================
    //  3. SEGURIDAD
    // =====================================================================

    private static function seguridad(array $d, Pagina $p): array
    {
        $r   = [];
        $cab = $d['cabeceras'] ?? [];
        $esHttps = ($d['esquema'] ?? '') === 'https';

        // --- El candado --------------------------------------------------------
        $r[] = self::h('https', 'seguridad', $esHttps ? self::BIEN : self::MAL, 22,
            $esHttps ? 'El sitio va cifrado (HTTPS)' : 'El sitio NO va cifrado',
            'Sin candado, Chrome escribe "No es seguro" junto a la dirección, en rojo, a la vista de todo el que entra. Se pierde la confianza antes de leer una sola palabra, y Google lo usa como factor de posicionamiento desde hace años.',
            'Instala un certificado. Let\'s Encrypt es gratuito y casi todos los hosting lo activan con un clic.',
            $esHttps ? 'sí' : 'no');

        // --- Certificado ---------------------------------------------------------
        $cert = $d['cert'] ?? [];
        if ($esHttps && !empty($cert['vence'])) {
            $dias = (int) $cert['dias'];
            if ($dias < 0) {
                $r[] = self::h('ssl_vence', 'seguridad', self::MAL, 16,
                    'El certificado de seguridad YA VENCIÓ',
                    'El navegador enseña una pantalla roja de advertencia a pantalla completa antes de dejar entrar. Prácticamente nadie pasa de ahí: el sitio está caído a efectos comerciales, aunque el servidor funcione.',
                    'Renueva el certificado hoy mismo. Es urgente.',
                    'venció el ' . $cert['vence']);
            } else {
                $estado = $dias < 15 ? self::MAL : ($dias < 30 ? self::AVISO : self::BIEN);
                $r[] = self::h('ssl_vence', 'seguridad', $estado, 16,
                    $dias < 30
                        ? 'El certificado de seguridad vence en ' . $dias . ' días'
                        : 'El certificado de seguridad está vigente',
                    'Cuando vence, el navegador bloquea el sitio con una pantalla de advertencia y las visitas se detienen en seco. Casi siempre pasa un fin de semana y nadie se entera hasta el lunes.',
                    'Activa la renovación automática y una alerta por correo treinta días antes.',
                    $dias . ' días (' . ($cert['emisor'] ?: 'emisor desconocido') . ')');
            }
        }

        // --- ¿http:// lleva a https://? -------------------------------------------
        if ($esHttps) {
            $red   = (string) ($d['http_redirige'] ?? '');
            $vive  = !empty($d['http_responde']);
            if (array_key_exists('http_redirige', $d)) {
                if (!$vive) {
                    // El puerto sin cifrar ni siquiera contesta: no hay copia
                    // insegura que valga. Marcarlo como fallo sería mentir.
                    $r[] = self::h('http_a_https', 'seguridad', self::BIEN, 10,
                        'No existe una copia del sitio sin cifrar', '', '', 'solo HTTPS');
                } else {
                    $ok = $red !== '' && stripos($red, 'https://') === 0;
                    $r[] = self::h('http_a_https', 'seguridad', $ok ? self::BIEN : self::MAL, 10,
                        $ok ? 'Quien entra por la dirección sin cifrar es redirigido' : 'La dirección sin cifrar sigue abierta',
                        'Existen dos copias del sitio a la vez, una segura y otra no. Google las trata como páginas distintas y reparte el posicionamiento entre ambas, y quien llega a la insegura ve el aviso de "No es seguro".',
                        'Añade una redirección permanente de http a https en el archivo .htaccess.',
                        $ok ? 'redirige' : 'no redirige');
                }
            }
        }

        // --- Contenido mixto --------------------------------------------------------
        if ($esHttps) {
            $mixto = $p->contenidoMixto();
            if ($mixto) {
                $r[] = self::h('contenido_mixto', 'seguridad', self::MAL, 10,
                    count($mixto) . ' ' . (count($mixto) === 1 ? 'archivo se carga' : 'archivos se cargan') . ' sin cifrar dentro de una página cifrada',
                    'El navegador bloquea esos archivos o rompe el candado. Se ven imágenes que no cargan o partes del diseño descolocadas, y el visitante cree que el sitio está roto.',
                    'Cambia esas direcciones de http:// a https:// en el código.',
                    count($mixto) . ' recursos');
            }
        }

        // --- Formularios que envían sin cifrar --------------------------------------
        $forms = $p->formularios();
        if ($forms['inseguros'] > 0) {
            $r[] = self::h('formulario_inseguro', 'seguridad', self::MAL, 12,
                'Hay un formulario que envía los datos sin cifrar',
                'Todo lo que alguien escriba ahí (su nombre, su teléfono, su correo) viaja en texto plano y puede leerse desde cualquier punto de la red. Además de ser un riesgo, incumple la normativa de protección de datos.',
                'Cambia la dirección de destino del formulario a https://.',
                (string) $forms['inseguros']);
        }

        // --- Cabeceras de protección --------------------------------------------------
        $cabeceras = [
            'strict-transport-security' => ['hsts', 'Obliga a usar siempre la conexión cifrada', 5],
            'content-security-policy'   => ['csp', 'Limita qué código puede ejecutarse en la página', 4],
            'x-content-type-options'    => ['nosniff', 'Impide que el navegador adivine tipos de archivo', 3],
            'x-frame-options'           => ['marco', 'Impide que otro sitio muestre el tuyo dentro de un marco', 4],
            'referrer-policy'           => ['referente', 'Controla qué información se envía al salir del sitio', 2],
        ];
        $faltan = [];
        foreach ($cabeceras as $nombre => [$clave, $para, $peso]) {
            $presente = isset($cab[$nombre]);
            // La política de marcos también puede venir dentro de la CSP.
            if (!$presente && $nombre === 'x-frame-options') {
                $presente = isset($cab['content-security-policy'])
                    && str_contains(strtolower($cab['content-security-policy']), 'frame-ancestors');
            }
            if (!$presente) { $faltan[] = $para; }
            $r[] = self::h('cab_' . $clave, 'seguridad', $presente ? self::BIEN : self::AVISO, $peso,
                ($presente ? 'Protección activa: ' : 'Falta una protección: ') . lcfirst($para),
                'Son instrucciones que el servidor le da al navegador para cerrar puertas conocidas. No se ven, pero son lo primero que mira cualquier auditoría de seguridad.',
                'Se añaden en el archivo .htaccess o en la configuración del servidor. Son unas pocas líneas.',
                $presente ? 'activa' : 'ausente');
        }

        // --- Versiones a la vista --------------------------------------------------------
        $expuesto = [];
        foreach (['server', 'x-powered-by'] as $nombre) {
            $v = (string) ($cab[$nombre] ?? '');
            if ($v !== '' && preg_match('~\d+\.\d+~', $v)) { $expuesto[] = $v; }
        }
        $gen = $p->meta('generator');
        if ($gen !== '' && preg_match('~\d+\.\d+~', $gen)) { $expuesto[] = $gen; }

        if ($expuesto) {
            $r[] = self::h('version_expuesta', 'seguridad', self::AVISO, 6,
                'El sitio anuncia qué programas y versiones usa',
                'Quien busca sitios que atacar no prueba al azar: busca versiones concretas con fallos conocidos. Decir la versión en voz alta es ponerse en esa lista.',
                'Oculta las cabeceras Server y X-Powered-By, y quita la etiqueta generator del HTML.',
                implode(' · ', array_slice($expuesto, 0, 2)));
        }

        return $r;
    }

    // =====================================================================
    //  4. GOOGLE (SEO)
    // =====================================================================

    private static function seo(array $d, Pagina $p): array
    {
        $r = [];

        // --- ¿Se deja encontrar? --------------------------------------------------
        $robotsMeta = strtolower($p->meta('robots'));
        $noindex = str_contains($robotsMeta, 'noindex')
                || str_contains(strtolower((string) ($d['cabeceras']['x-robots-tag'] ?? '')), 'noindex');
        if ($noindex) {
            $r[] = self::h('noindex', 'seo', self::MAL, 25,
                'La página le pide a Google que NO la incluya en los resultados',
                'Es el fallo más caro y el más invisible de todos: el sitio funciona perfecto, se ve bien, y sencillamente no existe en Google. Suele quedar puesto por descuido desde que el sitio estaba en construcción, y pueden pasar años sin que nadie lo note.',
                'Quita la instrucción noindex de la página. El efecto tarda unos días en verse.',
                'noindex activo');
        } else {
            $r[] = self::h('noindex', 'seo', self::BIEN, 25,
                'La página se deja incluir en Google', '', '', 'indexable');
        }

        // --- Título -----------------------------------------------------------------
        $titulo = $p->titulo();
        $largo  = mb_strlen($titulo, 'UTF-8');
        if ($titulo === '') {
            $r[] = self::h('titulo', 'seo', self::MAL, 16,
                'La página no tiene título',
                'El título es el renglón azul sobre el que la gente hace clic en Google, y el nombre que se guarda al marcar como favorito. Sin él, Google escribe lo que le parece y el resultado casi nunca es bueno.',
                'Escribe un título de entre 30 y 60 caracteres con el nombre del negocio y lo que vende.',
                'ausente');
        } else {
            $estado = ($largo >= 25 && $largo <= 65) ? self::BIEN : self::AVISO;
            $r[] = self::h('titulo', 'seo', $estado, 16,
                $estado === self::BIEN
                    ? 'El título tiene una longitud correcta'
                    : ($largo < 25 ? 'El título es demasiado corto' : 'El título es demasiado largo y Google lo va a cortar'),
                'Google enseña unos 60 caracteres. Lo que pase de ahí se sustituye por puntos suspensivos justo donde estaba lo importante.',
                'Deja el título entre 30 y 60 caracteres, con lo que vendes al principio.',
                $largo . ' caracteres');
        }

        // --- Descripción --------------------------------------------------------------
        $desc = $p->meta('description');
        $dlargo = mb_strlen($desc, 'UTF-8');
        if ($desc === '') {
            $r[] = self::h('descripcion', 'seo', self::MAL, 12,
                'La página no tiene descripción para Google',
                'Es el párrafo gris que aparece bajo el título en los resultados: tu único anuncio gratis en Google. Si no lo escribes tú, Google recorta un trozo cualquiera de la página, y ese trozo suele ser el menú o el aviso de cookies.',
                'Escribe una descripción de entre 70 y 160 caracteres que invite a entrar.',
                'ausente');
        } else {
            $estado = ($dlargo >= 70 && $dlargo <= 165) ? self::BIEN : self::AVISO;
            $r[] = self::h('descripcion', 'seo', $estado, 12,
                $estado === self::BIEN ? 'La descripción tiene una longitud correcta'
                    : ($dlargo < 70 ? 'La descripción se queda corta' : 'La descripción es larga y se va a cortar'),
                'Es el texto que decide si hacen clic en tu resultado o en el del competidor de abajo.',
                'Ajusta la descripción a entre 70 y 160 caracteres.',
                $dlargo . ' caracteres');
        }

        // --- Encabezados ------------------------------------------------------------------
        $enc = $p->encabezados();
        $nh1 = count($enc[1]);
        $estadoH1 = $nh1 === 1 ? self::BIEN : ($nh1 === 0 ? self::MAL : self::AVISO);
        $r[] = self::h('h1', 'seo', $estadoH1, 10,
            $nh1 === 1 ? 'La página tiene un titular principal claro'
                : ($nh1 === 0 ? 'La página no tiene titular principal' : 'La página tiene ' . $nh1 . ' titulares principales'),
            'El titular principal le dice a Google de qué va la página. Si falta, o si hay varios compitiendo, Google tiene que adivinarlo, y adivina mal.',
            'Deja exactamente un titular principal por página, con el tema de esa página.',
            $nh1 . ' titulares');

        if (count($enc[2]) === 0 && $p->palabras() > 400) {
            $r[] = self::h('subtitulos', 'seo', self::AVISO, 4,
                'El texto no tiene subtítulos',
                'Un muro de texto sin subtítulos no se lee: se abandona. Y Google usa los subtítulos para entender qué temas cubre la página.',
                'Reparte el contenido en secciones con subtítulos.',
                'sin subtítulos');
        }

        // --- Texto alternativo de las imágenes -----------------------------------------------
        $imgs = $p->imagenes();
        if ($imgs) {
            $sinAlt = count(array_filter($imgs, static fn($i) => empty($i['alt'])));
            $prop   = $sinAlt / count($imgs);
            $estado = $sinAlt === 0 ? self::BIEN : ($prop <= 0.3 ? self::AVISO : self::MAL);
            $r[] = self::h('alt', 'seo', $estado, 7,
                $sinAlt === 0 ? 'Todas las imágenes tienen texto alternativo'
                    : $sinAlt . ' de ' . count($imgs) . ' imágenes no tienen texto alternativo',
                'El texto alternativo es lo que Google lee de una imagen y lo que oye quien navega con lector de pantalla. Sin él, las fotos no aportan nada al posicionamiento y el sitio queda fuera de la normativa de accesibilidad.',
                'Describe en pocas palabras lo que se ve en cada foto.',
                $sinAlt . ' sin describir');
        }

        // --- Dirección canónica ------------------------------------------------------------
        // El canonical vive en un <link>, no en un <a>, así que se busca aquí.
        $canonical = '';
        if (preg_match('~<link[^>]+rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']([^"\']+)~i', $d['html'] ?? '', $m)) {
            $canonical = trim($m[1]);
        }
        $r[] = self::h('canonical', 'seo', $canonical !== '' ? self::BIEN : self::AVISO, 5,
            $canonical !== '' ? 'La página declara cuál es su dirección oficial' : 'La página no declara cuál es su dirección oficial',
            'Sin esta indicación, la misma página con y sin "www", con y sin barra final, o con parámetros de campaña, cuenta como páginas distintas. El posicionamiento se reparte entre todas en vez de sumarse.',
            'Añade la etiqueta canonical apuntando a la dirección definitiva de cada página.',
            $canonical !== '' ? 'declarada' : 'ausente');

        // --- Idioma --------------------------------------------------------------------------
        $lang = $p->idioma();
        $r[] = self::h('idioma', 'seo', $lang !== '' ? self::BIEN : self::AVISO, 4,
            $lang !== '' ? 'El idioma está declarado (' . $lang . ')' : 'El idioma de la página no está declarado',
            'Google lo usa para decidir a quién le enseña el sitio, y el traductor del navegador para no traducir de más.',
            'Añade lang="es" a la etiqueta html.',
            $lang !== '' ? $lang : 'ausente');

        // --- robots.txt y mapa del sitio ------------------------------------------------------
        $robots = $d['robots'] ?? [];
        if (!empty($robots['existe'])) {
            $texto = strtolower((string) ($robots['texto'] ?? ''));
            // Bloqueo total: "Disallow: /" bajo un User-agent que nos incluya.
            $bloquea = (bool) preg_match('~user-agent:\s*\*[^\n]*\n(?:[^\n]*\n)*?\s*disallow:\s*/\s*$~mi', $texto);
            $r[] = self::h('robots', 'seo', $bloquea ? self::MAL : self::BIEN, 7,
                $bloquea ? 'El archivo robots.txt le prohíbe la entrada a Google' : 'El archivo robots.txt existe y deja pasar a Google',
                'Una sola línea mal puesta ahí saca el sitio entero de Google. Es otro de esos fallos que quedan del montaje y nadie revisa.',
                'Revisa el archivo robots.txt y quita el bloqueo general.',
                $bloquea ? 'bloquea todo' : 'correcto');
        } else {
            $r[] = self::h('robots', 'seo', self::AVISO, 7,
                'No hay archivo robots.txt',
                'No es grave por sí mismo, pero es donde se indica dónde está el mapa del sitio y qué zonas no hay que rastrear.',
                'Crea un archivo robots.txt en la raíz con la dirección de tu mapa del sitio.',
                'ausente');
        }

        $sitemap = $d['sitemap'] ?? [];
        $r[] = self::h('sitemap', 'seo', !empty($sitemap['existe']) ? self::BIEN : self::AVISO, 7,
            !empty($sitemap['existe']) ? 'El sitio tiene mapa para Google' : 'El sitio no tiene mapa para Google',
            'El mapa del sitio es la lista de todas tus páginas. Sin él Google tiene que ir descubriéndolas a base de seguir enlaces, y las que están a tres clics de la portada pueden tardar meses en aparecer, o no aparecer nunca.',
            'Genera un sitemap.xml y decláralo en robots.txt y en Google Search Console.',
            !empty($sitemap['existe']) ? 'presente' : 'ausente');

        // --- Cómo se ve al compartir por WhatsApp -----------------------------------------------
        $og = $p->meta('og:title') !== '' || $p->meta('og:image') !== '';
        $ogCompleto = $p->meta('og:title') !== '' && $p->meta('og:description') !== '' && $p->meta('og:image') !== '';
        $r[] = self::h('og', 'seo', $ogCompleto ? self::BIEN : ($og ? self::AVISO : self::MAL), 9,
            $ogCompleto ? 'El enlace se ve bien al compartirlo' : 'El enlace se ve mal al compartirlo por WhatsApp o Facebook',
            'Cuando alguien pega tu dirección en WhatsApp debería aparecer una tarjeta con foto, título y descripción. Si falta, sale la dirección pelada en gris, que nadie toca. Es publicidad gratis que se está tirando, y en Guatemala casi todo se comparte por WhatsApp.',
            'Añade las etiquetas Open Graph: og:title, og:description y og:image con una imagen de 1200 × 630 píxeles.',
            $ogCompleto ? 'completo' : ($og ? 'incompleto' : 'ausente'));

        // --- Cantidad de contenido ----------------------------------------------------------------
        $palabras = $p->palabras();
        $estado = $palabras >= 500 ? self::BIEN : ($palabras >= 200 ? self::AVISO : self::MAL);
        $r[] = self::h('contenido', 'seo', $estado, 8,
            'La página tiene ' . number_format($palabras, 0, ',', '.') . ' palabras',
            'Google no puede posicionar lo que no puede leer. Una página con cuatro frases y muchas fotos no tiene por dónde entrar en ninguna búsqueda.',
            'Explica por escrito qué haces, para quién y dónde. Con 500 palabras útiles ya se compite.',
            number_format($palabras, 0, ',', '.') . ' palabras');

        // --- Enlaces rotos ------------------------------------------------------------------------
        $rotos    = $d['enlaces_rotos'] ?? [];
        $probados = (int) ($d['enlaces_probados'] ?? 0);
        if ($probados > 0) {
            $estado = count($rotos) === 0 ? self::BIEN : (count($rotos) <= 1 ? self::AVISO : self::MAL);
            $r[] = self::h('enlaces_rotos', 'seo', $estado, 8,
                count($rotos) === 0 ? 'Los enlaces revisados funcionan'
                    : count($rotos) . ' ' . (count($rotos) === 1 ? 'enlace roto' : 'enlaces rotos'),
                'Un enlace roto manda al visitante a una página de error. Quien buscaba tus precios o tu contacto se encuentra un "no encontrado" y se va con la impresión de que el negocio está abandonado.',
                'Corrige o quita esos enlaces.',
                count($rotos) . ' de ' . $probados . ' revisados');
        }

        // --- Favicon -------------------------------------------------------------------------------
        $tieneIcono = (bool) preg_match('~<link[^>]+rel\s*=\s*["\'][^"\']*icon~i', $d['html'] ?? '');
        $r[] = self::h('favicon', 'seo', $tieneIcono ? self::BIEN : self::AVISO, 3,
            $tieneIcono ? 'El sitio tiene su iconito en la pestaña' : 'El sitio no tiene iconito en la pestaña',
            'Es el detalle que separa un sitio cuidado de uno improvisado, y lo que permite reconocer tu pestaña entre veinte abiertas.',
            'Añade un favicon a la raíz del sitio.',
            $tieneIcono ? 'presente' : 'ausente');

        return $r;
    }

    // =====================================================================
    //  5. CONTACTO Y VENTAS
    // =====================================================================

    private static function negocio(array $d, Pagina $p): array
    {
        $r     = [];
        $html  = $d['html'] ?? '';
        $enlaces = $p->enlaces();

        // --- WhatsApp -----------------------------------------------------------
        $wa = false;
        foreach ($enlaces as $e) {
            if (preg_match('~(wa\.me|api\.whatsapp\.com|whatsapp://|web\.whatsapp\.com)~i', $e['url'])) { $wa = true; break; }
        }
        $r[] = self::h('whatsapp', 'negocio', $wa ? self::BIEN : self::MAL, 20,
            $wa ? 'Hay un botón para escribir por WhatsApp' : 'No hay forma de escribir por WhatsApp',
            'En Guatemala el cliente no llama ni escribe correos: escribe por WhatsApp. Un botón que abre el chat con el mensaje ya escrito convierte muchísimo mejor que cualquier formulario, y su ausencia es la fuga de clientes más común y más fácil de tapar.',
            'Añade un botón flotante con un enlace wa.me a tu número, con un mensaje inicial preparado.',
            $wa ? 'sí' : 'no');

        // --- Teléfono para tocar -------------------------------------------------
        $tel = false;
        foreach ($enlaces as $e) {
            if (stripos($e['url'], 'tel:') === 0) { $tel = true; break; }
        }
        $r[] = self::h('telefono', 'negocio', $tel ? self::BIEN : self::AVISO, 12,
            $tel ? 'El teléfono se puede marcar de un toque' : 'El teléfono no se puede marcar de un toque',
            'Desde el celular, un número que no es un enlace obliga a memorizarlo, salir del sitio y teclearlo. Mucha gente no llega al final de ese recorrido.',
            'Envuelve el número en un enlace tel: para que se marque solo.',
            $tel ? 'sí' : 'no');

        // --- Correo o formulario --------------------------------------------------
        $mail = false;
        foreach ($enlaces as $e) {
            if (stripos($e['url'], 'mailto:') === 0) { $mail = true; break; }
        }
        $forms = $p->formularios();
        $hayContacto = $mail || $forms['con_campos'] > 0;
        $r[] = self::h('contacto', 'negocio', $hayContacto ? self::BIEN : self::MAL, 14,
            $hayContacto ? 'Hay una forma de dejar un mensaje' : 'No hay forma de dejar un mensaje',
            'Quien entra de noche o fuera de horario necesita poder dejar sus datos. Sin formulario ni correo visible, esa visita se pierde entera.',
            'Pon un formulario de contacto corto (nombre, teléfono y mensaje) o al menos un correo visible.',
            $hayContacto ? ($forms['con_campos'] > 0 ? 'formulario' : 'correo') : 'nada');

        // --- Dirección y mapa -------------------------------------------------------
        $mapa = (bool) preg_match('~(google\.com/maps|maps\.google|goo\.gl/maps|maps\.app\.goo\.gl|openstreetmap)~i', $html);
        $direccion = $p->mencionaAlguna(['zona ', 'avenida', 'calzada', 'boulevard', 'guatemala', 'dirección', 'direccion', 'ubicación', 'ubicacion', 'km ', 'local ']);
        $ubicado = $mapa || $direccion;
        $r[] = self::h('ubicacion', 'negocio', $ubicado ? self::BIEN : self::AVISO, 10,
            $ubicado ? 'El sitio dice dónde está el negocio' : 'El sitio no dice dónde está el negocio',
            'Es de las primeras cosas que alguien busca, y lo que Google necesita para enseñarte en las búsquedas de "cerca de mí" y en el mapa.',
            'Escribe la dirección completa en texto e incorpora un mapa.',
            $mapa ? 'con mapa' : ($direccion ? 'dirección en texto' : 'ausente'));

        // --- Redes sociales -----------------------------------------------------------
        $redes = [];
        foreach ($enlaces as $e) {
            if (preg_match('~(facebook\.com|instagram\.com|tiktok\.com|linkedin\.com|youtube\.com|x\.com|twitter\.com)~i', $e['url'], $m)) {
                $redes[strtolower($m[1])] = true;
            }
        }
        $r[] = self::h('redes', 'negocio', $redes ? self::BIEN : self::AVISO, 8,
            $redes ? 'El sitio enlaza sus redes sociales (' . count($redes) . ')' : 'El sitio no enlaza ninguna red social',
            'La red social es donde el cliente comprueba que el negocio sigue vivo: mira la última publicación y la fecha. Un sitio sin redes enlazadas parece abandonado aunque no lo esté.',
            'Enlaza al menos Facebook e Instagram desde el pie de página.',
            $redes ? implode(', ', array_map(static fn($k) => explode('.', $k)[0], array_keys($redes))) : 'ninguna');

        // --- Llamado a la acción ---------------------------------------------------------
        $cta = $p->mencionaAlguna([
            'cotiza', 'cotización', 'cotizacion', 'contáctanos', 'contactanos', 'contáctenos',
            'escríbenos', 'escribenos', 'llámanos', 'llamanos', 'agenda', 'solicita',
            'pide tu', 'compra', 'reserva', 'inscríbete', 'inscribete', 'más información', 'mas informacion',
        ]);
        $r[] = self::h('cta', 'negocio', $cta ? self::BIEN : self::AVISO, 10,
            $cta ? 'La página invita a dar el siguiente paso' : 'La página no dice qué hacer a continuación',
            'Una página que solo describe deja al visitante sin saber cómo seguir, y el que no sabe qué hacer se va. Hay que pedir la acción con todas las letras.',
            'Pon un botón claro arriba y otro al final: "Cotiza aquí", "Escríbenos por WhatsApp".',
            $cta ? 'sí' : 'no');

        // --- ¿Mide algo? -------------------------------------------------------------------
        $analitica = [];
        if (preg_match('~(googletagmanager\.com/gtag|google-analytics\.com|gtag\(|GoogleAnalyticsObject)~i', $html)) { $analitica[] = 'Google Analytics'; }
        if (preg_match('~googletagmanager\.com/gtm~i', $html)) { $analitica[] = 'Tag Manager'; }
        if (preg_match('~connect\.facebook\.net|fbq\(~i', $html)) { $analitica[] = 'Píxel de Meta'; }
        $r[] = self::h('analitica', 'negocio', $analitica ? self::BIEN : self::MAL, 12,
            $analitica ? 'El sitio mide sus visitas (' . implode(', ', $analitica) . ')' : 'El sitio no mide nada',
            'Sin medición no se sabe cuánta gente entra, de dónde viene ni en qué página se va. Se decide a ciegas y no hay forma de saber si algo que se cambió funcionó. Además, sin el píxel de Meta no se puede volver a mostrar anuncios a quien ya visitó el sitio, que es la publicidad más barata que existe.',
            'Instala Google Analytics 4 y, si haces publicidad, el píxel de Meta.',
            $analitica ? implode(', ', $analitica) : 'nada instalado');

        return $r;
    }

    // =====================================================================
    //  6. VISIBILIDAD EN IA
    // =====================================================================

    private static function ia(array $d, Pagina $p): array
    {
        $r = [];

        // --- ¿Bloquea a los robots de IA? ----------------------------------------
        $robotsTxt = strtolower((string) ($d['robots']['texto'] ?? ''));
        $bots = ['gptbot' => 'ChatGPT', 'oai-searchbot' => 'ChatGPT', 'claudebot' => 'Claude',
                 'perplexitybot' => 'Perplexity', 'google-extended' => 'Gemini'];
        $bloqueados = [];
        foreach ($bots as $bot => $quien) {
            // "User-agent: GPTBot" seguido de un Disallow: / antes del siguiente bloque.
            if (preg_match('~user-agent:\s*' . preg_quote($bot, '~') . '\s*\n(?:(?!user-agent:)[^\n]*\n)*?\s*disallow:\s*/\s*(?:\n|$)~i', $robotsTxt . "\n")) {
                $bloqueados[$quien] = true;
            }
        }
        if ($bloqueados) {
            $r[] = self::h('bots_ia', 'ia', self::MAL, 22,
                'El sitio le cierra la puerta a ' . implode(' y ', array_keys($bloqueados)),
                'Cada vez más gente pregunta a ChatGPT en vez de buscar en Google. Si el robot no puede entrar, el negocio no puede ser recomendado nunca: no es que salga abajo, es que no existe para esa conversación.',
                'Quita esas reglas del archivo robots.txt, salvo que quieras quedar fuera a propósito.',
                implode(', ', array_keys($bloqueados)));
        } else {
            $r[] = self::h('bots_ia', 'ia', self::BIEN, 22,
                'Las inteligencias artificiales pueden leer el sitio', '', '', 'acceso libre');
        }

        // --- Datos estructurados --------------------------------------------------
        $tipos = $p->tiposSchema();
        if (!$tipos) {
            $r[] = self::h('schema', 'ia', self::MAL, 22,
                'El sitio no tiene datos estructurados',
                'Los datos estructurados son una ficha oculta que le dice a Google y a las IA qué es este negocio, dónde está, qué vende y en qué horario. Sin ella tienen que adivinarlo leyendo el texto, y se equivocan. Es también lo que hace que aparezcan las estrellas, los precios y los horarios directamente en los resultados de búsqueda.',
                'Añade una ficha JSON-LD de tipo LocalBusiness u Organization con el nombre, la dirección, el teléfono y el horario.',
                'ninguno');
        } else {
            $estado = count($tipos) >= 3 ? self::BIEN : self::AVISO;
            $r[] = self::h('schema', 'ia', $estado, 22,
                'El sitio declara ' . count($tipos) . ' ' . (count($tipos) === 1 ? 'tipo de dato estructurado' : 'tipos de datos estructurados'),
                'Las páginas con tres o más tipos de datos estructurados tienen bastante más probabilidad de ser citadas por una IA. Es la diferencia entre que te mencionen por tu nombre o que mencionen a tu competencia.',
                'Suma fichas de tipo FAQPage, Product o Service, según lo que vendas.',
                implode(', ', array_slice($tipos, 0, 5)));
        }

        // --- Ficha del negocio ------------------------------------------------------
        $negocio = array_intersect($tipos, ['localbusiness', 'organization', 'store', 'restaurant', 'school',
                                            'educationalorganization', 'professionalservice', 'medicalorganization']);
        $r[] = self::h('schema_negocio', 'ia', $negocio ? self::BIEN : self::AVISO, 14,
            $negocio ? 'El negocio está identificado para buscadores e IA' : 'El negocio no está identificado como tal',
            'Sin esta ficha, para un buscador el sitio es un montón de texto sin dueño. Con ella, es un negocio concreto con nombre, dirección y teléfono que puede recomendarse a quien pregunta por la zona.',
            'Añade una ficha LocalBusiness con el nombre exacto, la dirección, el teléfono y el horario.',
            $negocio ? implode(', ', $negocio) : 'ausente');

        // --- Preguntas frecuentes ------------------------------------------------------
        $faq = in_array('faqpage', $tipos, true) || in_array('question', $tipos, true)
            || $p->mencionaAlguna(['preguntas frecuentes', 'dudas frecuentes']);
        $r[] = self::h('faq', 'ia', $faq ? self::BIEN : self::AVISO, 10,
            $faq ? 'El sitio responde preguntas frecuentes' : 'El sitio no responde preguntas frecuentes',
            'Las IA responden preguntas, así que citan a quien ya tiene la respuesta escrita en forma de pregunta. Una sección de preguntas frecuentes es la forma más barata de entrar en esas respuestas.',
            'Añade una sección de preguntas y respuestas con lo que más te preguntan los clientes, y márcala como FAQPage.',
            $faq ? 'sí' : 'no');

        // --- Contenido citable -----------------------------------------------------------
        $tl = $p->tablasYListas();
        $citable = $tl['tablas'] > 0 || $tl['listas'] >= 2;
        $r[] = self::h('citable', 'ia', $citable ? self::BIEN : self::AVISO, 8,
            $citable ? 'El contenido está en un formato fácil de citar' : 'El contenido es difícil de citar',
            'Las listas y las tablas se extraen y se citan mucho mejor que un párrafo largo, tanto por las IA como por los fragmentos destacados de Google.',
            'Convierte en listas y tablas lo que hoy son párrafos: servicios, precios, horarios, requisitos.',
            $tl['tablas'] . ' tablas · ' . $tl['listas'] . ' listas');

        // --- Datos de contacto en texto plano ------------------------------------------------
        $texto = $p->texto();
        $nap = (bool) preg_match('~\+?\d[\d\s\-\.\(\)]{6,}~', $texto);
        $r[] = self::h('nap', 'ia', $nap ? self::BIEN : self::AVISO, 8,
            $nap ? 'Los datos de contacto están en texto' : 'Los datos de contacto no aparecen como texto',
            'Un teléfono metido dentro de una imagen no lo lee nadie: ni Google, ni una IA, ni un lector de pantalla, ni se puede copiar. Es la forma más silenciosa de perder llamadas.',
            'Escribe el teléfono y la dirección como texto normal, nunca solo dentro de una imagen.',
            $nap ? 'sí' : 'no');

        // --- llms.txt ------------------------------------------------------------------------
        // Se informa, pero pesa poco a propósito: hoy por hoy no hay evidencia
        // de que mejore las citas, y prometer lo contrario sería vender humo.
        $llms = !empty($d['llms']['existe']);
        $r[] = self::h('llms', 'ia', $llms ? self::BIEN : self::AVISO, 3,
            $llms ? 'El sitio tiene archivo llms.txt' : 'El sitio no tiene archivo llms.txt',
            'Es un resumen del negocio pensado para que lo lean las IA. Todavía es pronto para saber cuánto ayuda de verdad, así que cuenta poco en la nota: es un extra, no una urgencia.',
            'Crea un archivo llms.txt en la raíz con quién eres, qué vendes y los enlaces importantes.',
            $llms ? 'presente' : 'ausente');

        return $r;
    }

    // =====================================================================
    //  7. CÓDIGO MALICIOSO
    // =====================================================================

    /**
     * Lo que se puede saber mirando el sitio desde fuera.
     *
     * Aquí la redacción se cuida más que en ningún otro sitio: decirle a un
     * posible cliente que tiene un virus que no tiene cuesta la relación
     * entera. Por eso nada dice "está infectado" salvo cuando lo afirma Google;
     * lo demás se enuncia como lo que es, código que hay que mirar.
     */
    private static function malware(array $d, Pagina $p): array
    {
        $r = [];
        $s = Malware::senales($d, $p);

        // --- Lo que dice Google ------------------------------------------
        $ln = $s['lista_negra'];
        if (!empty($ln['consultado'])) {
            if (!empty($ln['marcado'])) {
                $r[] = self::h('lista_negra', 'malware', self::MAL, 30,
                    'GOOGLE TIENE ESTE SITIO MARCADO COMO PELIGROSO',
                    'Chrome, Firefox y Safari enseñan una pantalla roja a toda página antes de dejar entrar, y casi nadie pasa de ahí. El sitio está caído a efectos comerciales aunque el servidor funcione, y el correo que salga del dominio se va a la carpeta de no deseado. Esto no espera: cada día así son clientes que se van con la competencia.',
                    'Hay que limpiar el sitio, cambiar todas las contraseñas y después pedirle a Google la revisión desde Search Console. Mientras no se limpie, volver a pedir la revisión no sirve de nada.',
                    implode(' · ', (array) $ln['tipos']), true);
            } else {
                $r[] = self::h('lista_negra', 'malware', self::BIEN, 30,
                    'Google no tiene el sitio marcado como peligroso', '', '', 'limpio en la lista de Google');
            }
        } else {
            // Sin clave de Google no se puede preguntar. Se dice, y no se
            // puntúa: no se puede suspender a nadie por algo que no se miró.
            $r[] = self::h('lista_negra', 'malware', self::NA, 30,
                'No se pudo consultar la lista de sitios peligrosos de Google',
                '', 'Añade tu clave gratuita de Google en Ajustes y activa «Safe Browsing API» en el mismo proyecto.',
                (string) ($ln['error'] ?? 'sin clave de Google'));
        }

        // --- Lo que dicen los demás motores antivirus ----------------------
        $vt = $s['virustotal'];
        if (!empty($vt['consultado'])) {
            $n = (int) $vt['detectan'];
            if ($n > 0) {
                $r[] = self::h('virustotal', 'malware', self::MAL, 26,
                    $n . ' de ' . (int) $vt['motores'] . ' motores antivirus marcan este sitio',
                    'No es una sospecha de una sola herramienta: son motores independientes que coinciden. Cuando varios señalan lo mismo, el sitio tiene algo, y los navegadores y los filtros de correo acaban haciéndoles caso.',
                    'Hay que limpiar el sitio y luego pedir la revisión a cada servicio que lo tenga marcado.',
                    $vt['cuales'] ? implode(', ', array_slice((array) $vt['cuales'], 0, 4)) : (string) $n . ' motores', true);
            } else {
                $r[] = self::h('virustotal', 'malware', self::BIEN, 26,
                    'Los ' . (int) $vt['motores'] . ' motores antivirus consultados lo dan por limpio',
                    '', '', 'sin detecciones');
            }
        } elseif (!empty($vt['error'])) {
            $r[] = self::h('virustotal', 'malware', self::NA, 26,
                'No se pudo consultar a los motores antivirus',
                '', 'Pon tu clave gratuita de VirusTotal en Ajustes: son setenta motores de una sola consulta.',
                (string) $vt['error']);
        }

        // --- Los archivos de código, abiertos uno a uno ---------------------
        $cod = $s['codigo'];
        if ((int) ($cod['revisados'] ?? 0) > 0) {
            if (!empty($cod['familias'])) {
                $r[] = self::h('familias', 'malware', self::MAL, 28,
                    'Se reconoció código de una campaña conocida: ' . implode(', ', (array) $cod['familias']),
                    'No es una sospecha por la pinta del código: coincide con el rastro que deja una campaña concreta, de las que han infectado cientos de miles de sitios. Si está esto, casi con seguridad hay también una puerta trasera en el servidor que no se ve desde fuera.',
                    'Hay que limpiar el sitio a fondo desde el servidor, cambiar TODAS las contraseñas (hosting, base de datos, administradores) y actualizar los plugins. Restaurar una copia anterior sin cerrar por dónde entraron solo retrasa el problema.',
                    implode(' · ', (array) $cod['familias']), true);
            } elseif ((int) $cod['ofuscados'] > 0) {
                $r[] = self::h('familias', 'malware', self::AVISO, 28,
                    (int) $cod['ofuscados'] . ' de ' . (int) $cod['revisados'] . ' archivos de código llevan partes ofuscadas',
                    'Ofuscar es escribir el código para que no se entienda al leerlo. Hay librerías viejas y algún plugin de pago que lo hacen por costumbre, así que no es una condena; pero tampoco es normal, y hay que mirarlo antes de descartarlo.',
                    'Que alguien revise esos archivos y confirme qué son. Si no se reconocen, el sitio está comprometido.',
                    (string) ($cod['peor']['url'] ?? ''));
            } else {
                $r[] = self::h('familias', 'malware', self::BIEN, 28,
                    'Se abrieron ' . (int) $cod['revisados'] . ' archivos de código y ninguno trae nada raro',
                    '', '', (int) $cod['revisados'] . ' archivos revisados');
            }
        }

        // --- Contenido distinto para Google (encubrimiento) ----------------
        $ck = $s['cloaking'];
        if (!empty($ck['consultado'])) {
            if (!empty($ck['distinto'])) {
                $r[] = self::h('cloaking', 'malware', self::MAL, 22,
                    'El sitio le enseña a Google algo distinto que a las personas',
                    'Es la infección más difícil de ver y la más dañina: el dueño entra a su página, la ve perfecta, y no se entera de nada. Mientras tanto a Google se le sirve spam en su nombre. Acaba siempre igual: el sitio desaparece de las búsquedas y recuperar la posición cuesta meses.',
                    'El sitio está comprometido. Hay que revisar los archivos del servidor, buscar código añadido y cambiar todas las contraseñas.',
                    (string) $ck['detalle'], true);
            } else {
                $r[] = self::h('cloaking', 'malware', self::BIEN, 22,
                    'A Google se le sirve lo mismo que a las personas', '', '', 'sin encubrimiento');
            }
        }

        // --- Redirección solo para celulares -------------------------------
        $mv = $s['movil'];
        if (!empty($mv['consultado'])) {
            $destino = (string) ($mv['destino'] ?? '');
            if ($destino !== '') {
                $r[] = self::h('redirige_movil', 'malware', self::MAL, 20,
                    'Quien entra desde el celular acaba en otro sitio web',
                    'Siete de cada diez visitas llegan desde un teléfono, y a todas se las está mandando a otra parte. Desde la computadora no se nota nada, así que el dueño puede llevar meses regalando sus visitas sin saberlo.',
                    'Es código metido por alguien. Hay que revisar el archivo .htaccess y los archivos del sitio, y cambiar las contraseñas.',
                    mb_substr($destino, 0, 70), true);
            } else {
                $r[] = self::h('redirige_movil', 'malware', self::BIEN, 20,
                    'Desde el celular se llega al sitio correcto', '', '', 'sin redirección extraña');
            }
        }

        // --- Enlaces de spam escondidos -------------------------------------
        $oc = $s['ocultos'];
        if ((int) $oc['bloques'] > 0) {
            $detalle = $oc['enlaces'] . ' enlaces';
            if ($oc['dominios']) { $detalle .= ' hacia ' . implode(', ', array_slice($oc['dominios'], 0, 3)); }
            $r[] = self::h('enlaces_ocultos', 'malware', self::MAL, 20,
                'Hay ' . $oc['enlaces'] . ' enlaces escondidos que llevan fuera del sitio',
                'Alguien está usando el prestigio del dominio para colocar enlaces invisibles hacia sitios de apuestas o de farmacia. El visitante no ve nada; Google sí, y lo castiga como si el dueño lo hubiera hecho a propósito.',
                'Hay que encontrar y quitar esos bloques del código, y averiguar por dónde entraron: si no, vuelven solos en unos días.',
                $detalle, true);
        } else {
            $r[] = self::h('enlaces_ocultos', 'malware', self::BIEN, 20,
                'No hay bloques de enlaces escondidos', '', '', 'ninguno');
        }

        // --- Marcos invisibles -----------------------------------------------
        $ifr = $s['iframes'];
        if ($ifr) {
            $primero = $ifr[0];
            $r[] = self::h('iframes_ocultos', 'malware', self::MAL, 18,
                count($ifr) === 1 ? 'Hay un marco invisible que carga otro sitio'
                                  : 'Hay ' . count($ifr) . ' marcos invisibles que cargan otros sitios',
                'Un marco de tamaño cero apuntando fuera es la forma clásica de colgar contenido ajeno de una página sin que el dueño lo note. Suele servir para repartir programas maliciosos entre los visitantes.',
                'Quitar esos marcos del código y revisar cómo llegaron ahí.',
                mb_substr((string) $primero['src'], 0, 60) . ' (' . $primero['motivo'] . ')', true);
        } else {
            $r[] = self::h('iframes_ocultos', 'malware', self::BIEN, 18,
                'No hay marcos invisibles cargando otros sitios', '', '', 'ninguno');
        }

        // --- Minero de criptomonedas -------------------------------------------
        if (!empty($s['minero']['encontrado'])) {
            $r[] = self::h('minero', 'malware', self::MAL, 16,
                'La página pone a trabajar la computadora del visitante',
                'Es un minero de criptomonedas metido en el sitio: usa el procesador y la batería de quien entra para generarle dinero a otro. Al visitante se le calienta el teléfono y se va pensando que la página está rota.',
                'Quitar ese código y revisar por dónde entró.',
                (string) $s['minero']['cual'], true);
        }

        // --- Página tomada -----------------------------------------------------
        if (!empty($s['defacement']['encontrado'])) {
            $r[] = self::h('defacement', 'malware', self::MAL, 16,
                'La página parece haber sido tomada por alguien',
                'El texto del sitio contiene la firma que suelen dejar quienes entran a una página ajena. Si es así, lo que ven los clientes ahora mismo no es lo que el dueño puso.',
                'Restaurar desde una copia de seguridad limpia y cambiar todas las contraseñas antes de volver a publicar.',
                (string) $s['defacement']['cual'], true);
        }

        // --- Spam en el título o la descripción ----------------------------------
        if (!empty($s['spam']['encontrado'])) {
            $r[] = self::h('spam_titulo', 'malware', self::MAL, 14,
                'El título o la descripción contienen palabras de spam',
                'Es lo que Google enseña de este sitio en sus resultados. Con esas palabras ahí, el negocio aparece asociado a farmacias o a apuestas delante de sus propios clientes.',
                'Revisar el código del sitio: ese texto lo puso algo que no debería estar ahí.',
                (string) $s['spam']['cual'], true);
        }

        // --- Código escrito para no entenderse -------------------------------------
        $of = $s['ofuscado'];
        if ((int) $of['n'] > 0) {
            // Aquí NO se afirma que haya una infección: hay librerías viejas y
            // algún plugin que también hacen esto. Se señala para que se mire.
            $estado = (int) $of['n'] >= 2 ? self::MAL : self::AVISO;
            $r[] = self::h('ofuscado', 'malware', $estado, 14,
                'Hay código escrito para que no se entienda al leerlo',
                'Nadie ofusca el código de su propia página de contacto. Cuando aparece, suele ser algo metido por alguien de fuera; alguna vez es un plugin viejo que lo hace por costumbre. En cualquiera de los dos casos hay que mirarlo antes de descartarlo.',
                'Que alguien revise esos bloques de código y confirme qué son. Si no se reconocen, el sitio está comprometido.',
                implode(' · ', (array) $of['muestras']));
        } else {
            $r[] = self::h('ofuscado', 'malware', self::BIEN, 14,
                'No hay código oculto ni ofuscado', '', '', 'limpio');
        }

        // --- De quién se fía el sitio -------------------------------------------------
        $t = $s['terceros'];
        if ((int) $t['n'] > 0) {
            $estado = (int) $t['n'] > 8 ? self::AVISO : self::BIEN;
            $r[] = self::h('terceros', 'malware', $estado, 8,
                'El sitio carga código de ' . $t['n'] . ' ' . ($t['n'] === 1 ? 'dominio ajeno' : 'dominios ajenos'),
                'No es malo por sí mismo (Google, Facebook y las tipografías vienen de fuera), pero cada uno de esos dominios puede cambiar mañana lo que envía. Cuantos más haya, más puertas hay que vigilar.',
                'Quitar los que ya no se usen. Cada plugin desinstalado que deja su código atrás es una puerta abierta de balde.',
                implode(', ', array_slice((array) $t['dominios'], 0, 4)));
        }

        return $r;
    }
}
