<?php
/**
 * Kaptor - En qué puesto sale una web para una palabra clave.
 *
 * La pregunta que hace todo cliente —«¿en qué posición estoy?»— no se puede
 * contestar con una cifra suelta, porque esa cifra no existe: los buscadores
 * dan resultados distintos según el país, el idioma, el aparato y el momento.
 * Lo que sí existe, y es lo que hace esto, es una MEDICIÓN: para esta palabra,
 * en este buscador, desde este país, en este idioma, desde un ordenador o un
 * teléfono, a esta hora, la web sale en este puesto.
 *
 * Y para que valga de algo, tiene que poder comprobarse. De cada medición se
 * guardan tres cosas:
 *
 *   1. La DIRECCIÓN EXACTA que se pidió. Se abre en cualquier navegador y se
 *      cuentan los resultados a mano.
 *   2. La LISTA ENTERA de resultados en su orden, con su título y su enlace,
 *      marcando cuál es el que se buscaba. No hace falta fiarse del número:
 *      está el puesto 1, el 2, el 3... hasta donde se miró.
 *   3. La PÁGINA TAL CUAL LA DEVOLVIÓ EL BUSCADOR, guardada comprimida. Es la
 *      prueba de verdad: el HTML original, sin tocar, con su fecha.
 *
 * Qué cuenta como puesto: solo los resultados ORGÁNICOS. Los anuncios no
 * cuentan, igual que no cuentan en ninguna herramienta seria del mercado, y
 * los bloques de mapas, vídeos o «la gente también pregunta» tampoco: no son
 * un resultado al que se pueda llegar escalando posiciones.
 */
declare(strict_types=1);

final class Posiciones
{
    /**
     * De dónde salen los resultados.
     *
     * Hay dos caminos y conviene no confundirlos:
     *
     *   · «directo» pide la página al buscador como lo haría un navegador. Es
     *     gratis y no hay que registrarse en nada, pero Google lleva años
     *     cerrándoselo a los servidores: contesta con una página de consentimiento,
     *     con un captcha o con un armazón vacío que necesita JavaScript. Cuando
     *     eso pasa no se puede medir, y Kaptor lo dice en vez de callárselo.
     *
     *   · los demás son servicios que hacen la búsqueda por ti y devuelven los
     *     resultados ya ordenados, con su puesto. Cuestan dinero (poco) y hay que
     *     pegar una clave, pero funcionan siempre y dan el google.com de verdad.
     *     Es lo que usan por dentro todas las herramientas de pago del mercado.
     */
    public const PROVEEDORES = [
        'directo' => 'Preguntar al buscador directamente (gratis)',
        'serper'  => 'Serper.dev (Google real · 2.500 búsquedas gratis)',
        'serpapi' => 'SerpApi (Google real · 100 al mes gratis)',
        'cse'     => 'Google Custom Search (oficial · 100 al día gratis)',
    ];

    /** Resultados por página, que es como cuenta la gente. */
    public const POR_PAGINA = 10;

    /** Páginas que se miran de serie: los diez primeros puestos. */
    public const PAGINAS = 3;

    /** Tope duro: más allá del puesto 100 nadie recibe visitas. */
    public const MAX_PAGINAS = 10;

    /** Agentes de usuario, para poder medir también lo que se ve en el móvil. */
    private const AGENTES = [
        'escritorio' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                      . '(KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'movil'      => 'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 '
                      . '(KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36',
    ];

    /** Lo que nunca es un resultado: el propio buscador y sus servicios. */
    private const PROPIOS = [
        'google.com', 'google.es', 'googleusercontent.com', 'gstatic.com',
        'bing.com', 'microsoft.com', 'live.com', 'msn.com',
        'duckduckgo.com', 'spreadprivacy.com', 'mojeek.com',
        'accounts.google.com', 'policies.google.com', 'support.google.com',
    ];

    // =====================================================================
    //  Medir
    // =====================================================================

    /**
     * Busca la palabra clave y dice en qué puesto sale el dominio.
     *
     * @param array{
     *   consulta:string, dominio:string, motor?:string, pais?:string,
     *   idioma?:string, dispositivo?:string, paginas?:int, exacta?:bool
     * } $p
     * @return array Medición lista para guardar y para enseñar.
     */
    public static function medir(array $p): array
    {
        $consulta = trim((string) ($p['consulta'] ?? ''));
        $dominio  = self::normalizarDominio((string) ($p['dominio'] ?? ''));

        if ($consulta === '') { return self::fallo('Escribe la palabra o frase que quieres medir.'); }
        if ($dominio === '')  { return self::fallo('Escribe el dominio de la web, por ejemplo midominio.com.'); }

        $motor  = (string) ($p['motor'] ?? 'google');
        if (!isset(Buscador::motores()[$motor])) { $motor = 'google'; }

        $proveedor = (string) ($p['proveedor'] ?? Ajustes::obtener('pos_proveedor', 'directo'));
        if (!isset(self::PROVEEDORES[$proveedor])) { $proveedor = 'directo'; }
        // Los servicios con clave hablan siempre con Google: el motor que se
        // eligiera para raspar deja de pintar nada, y decir otra cosa en el
        // informe sería mentir sobre lo que se midió.
        if ($proveedor !== 'directo') { $motor = 'google'; }

        $pais   = self::codigo((string) ($p['pais'] ?? ''), 'gt');
        $idioma = self::codigo((string) ($p['idioma'] ?? ''), 'es');
        $aparato = ($p['dispositivo'] ?? 'escritorio') === 'movil' ? 'movil' : 'escritorio';
        $agente  = self::AGENTES[$aparato];

        $paginas = (int) ($p['paginas'] ?? self::PAGINAS);
        $paginas = max(1, min($paginas, self::MAX_PAGINAS));
        $exacta  = !empty($p['exacta']);   // la URL completa, no el dominio

        $resultados = [];   // puesto global => ['url','titulo','pagina','en_pagina']
        $peticiones = [];   // una por página pedida: la prueba de qué se pidió
        $snapshots  = [];   // el HTML de cada página, para guardarlo aparte
        $encontrado = null;
        $error      = '';

        $leido = false;   // ¿se llegó a leer ALGUNA lista de resultados?

        for ($i = 0; $i < $paginas; $i++) {
            $r = self::pagina($proveedor, $motor, $consulta, $i, [
                'pais' => $pais, 'idioma' => $idioma,
                'agente' => $agente, 'dispositivo' => $aparato,
            ]);

            $peticiones[] = [
                'consulta' => $i + 1,
                'pagina'  => $i + 1,
                'desde'   => count($resultados) + 1,
                'hasta'   => count($resultados),
                'url'     => $r['url'],
                'codigo'  => $r['codigo'],
                'bytes'   => $r['bytes'],
                'ms'      => $r['ms'],
                'bloqueo' => $r['bloqueo'],
                'error'   => $r['error'],
                'diagnostico' => $r['diagnostico'],
            ];

            // Lo que llegó se guarda SIEMPRE, también cuando no sirvió: si
            // Google contestó con un captcha, esa es justamente la prueba de
            // por qué no se pudo medir.
            if ($r['crudo'] !== '') { $snapshots[$i + 1] = $r['crudo']; }

            if ($r['bloqueo']) {
                $error = 'No se pudo medir: el buscador pidió verificación («no soy un robot») '
                       . 'en la consulta ' . ($i + 1) . '.';
                break;
            }
            if (!$r['ok']) {
                $error = 'No se pudo medir: ' . $r['diagnostico'] . ' (consulta ' . ($i + 1) . ').';
                break;
            }

            $pagina = $r['resultados'];

            // Esta es la diferencia que lo cambia todo. Si el buscador contesta
            // pero no se le puede leer ni un resultado, lo honrado es decir que
            // NO SE PUDO MIRAR. Decir «no apareces» con cero resultados en la
            // mano es exactamente la clase de mentira por la que una
            // herramienta de estas no vale nada.
            if (!$pagina) {
                if (!$leido) {
                    $error = 'No se pudo medir: ' . $r['diagnostico'] . '.';
                }
                break;
            }
            $leido = true;

            foreach ($pagina as $res) {
                // La página y el puesto dentro de ella NO son los de la
                // petición: hay buscadores que entregan treinta resultados de
                // una vez. La gente cuenta de diez en diez —«la segunda página
                // de Google»— y es así como hay que decirlo, salga como salga.
                $puesto = count($resultados) + 1;
                $enPag  = (int) ceil($puesto / self::POR_PAGINA);
                $fila = [
                    'puesto'    => $puesto,
                    'pagina'    => $enPag,
                    'en_pagina' => $puesto - ($enPag - 1) * self::POR_PAGINA,
                    'url'       => $res['url'],
                    'titulo'    => $res['titulo'],
                    'nuestro'   => false,
                ];
                if ($encontrado === null && self::coincide($res['url'], $dominio, $exacta)) {
                    $fila['nuestro'] = true;
                    $encontrado = $fila;
                }
                $resultados[] = $fila;
            }

            $peticiones[count($peticiones) - 1]['hasta'] = count($resultados);

            if ($encontrado !== null) { break; }
            usleep(500000);   // un respiro entre páginas, para no parecer un robot
        }

        return [
            'ok'          => $encontrado !== null || ($error === '' && $resultados !== []),
            'medible'     => $leido,          // ¿se llegó a leer la lista?
            'proveedor'   => $proveedor,
            'proveedor_nombre' => self::PROVEEDORES[$proveedor],
            'encontrado'  => $encontrado !== null,
            'posicion'    => $encontrado['puesto']    ?? null,
            'pagina'      => $encontrado['pagina']    ?? null,
            'en_pagina'   => $encontrado['en_pagina'] ?? null,
            'url_hallada' => $encontrado['url']       ?? '',
            'titulo'      => $encontrado['titulo']    ?? '',
            'consulta'    => $consulta,
            'dominio'     => $dominio,
            'motor'       => $motor,
            'motor_nombre' => Buscador::motores()[$motor],
            'pais'        => $pais,
            'idioma'      => $idioma,
            'dispositivo' => $aparato,
            'agente'      => $agente,
            'exacta'      => $exacta,
            'revisados'   => count($resultados),
            'paginas_vistas' => count($peticiones),
            'resultados'  => $resultados,
            'peticiones'  => $peticiones,
            'snapshots'   => $snapshots,
            'error'       => $error,
            'fecha'       => date('Y-m-d H:i:s'),
        ];
    }

    private static function fallo(string $mensaje): array
    {
        return ['ok' => false, 'medible' => false, 'encontrado' => false, 'error' => $mensaje,
                'resultados' => [], 'peticiones' => [], 'snapshots' => []];
    }

    // =====================================================================
    //  Una página de resultados, venga de donde venga
    // =====================================================================

    /**
     * Pide una página de resultados al proveedor que toque.
     *
     * Devuelve siempre la misma forma, de modo que a medir() le da igual si
     * los resultados vinieron de raspar Google o de un servicio de pago: lo
     * que cambia es la fiabilidad, no el formato.
     *
     * @return array{ok:bool,url:string,codigo:int,crudo:string,bytes:int,ms:int,
     *               bloqueo:bool,error:string,resultados:array,diagnostico:string}
     */
    public static function pagina(string $proveedor, string $motor, string $consulta,
                                  int $pagina, array $op): array
    {
        if ($proveedor !== 'directo') {
            return self::porApi($proveedor, $consulta, $pagina, $op);
        }

        $r = Buscador::serp($motor, $consulta, $pagina, $op);
        $res = $r['ok'] && !$r['bloqueo'] ? self::resultados($motor, $r['html']) : [];

        return [
            'ok'          => $r['ok'],
            'url'         => $r['url'],
            'codigo'      => $r['codigo'],
            'crudo'       => $r['html'],
            'bytes'       => $r['bytes'],
            'ms'          => $r['ms'],
            'bloqueo'     => $r['bloqueo'],
            'error'       => $r['error'],
            'resultados'  => $res,
            'diagnostico' => self::diagnostico($r, $res),
        ];
    }

    /**
     * Qué pasó exactamente, en una línea que se pueda enseñar.
     *
     * Cuando un buscador contesta 200 y aun así no hay resultados que leer,
     * decir «no apareces» sería mentir: lo que ha pasado es que no se pudo
     * mirar. Esta frase es la diferencia entre las dos cosas.
     */
    private static function diagnostico(array $r, array $res): string
    {
        if (!$r['ok']) {
            return $r['error'] !== '' ? 'no se pudo conectar: ' . $r['error'] : 'respuesta vacía';
        }
        if ($r['bloqueo']) {
            return 'el buscador pidió verificación (captcha o aviso de cookies)';
        }
        if (!$res) {
            $muestra = mb_strtolower(mb_substr(strip_tags($r['html']), 0, 600));
            if (str_contains($muestra, 'javascript')) {
                return 'el buscador devolvió una página que necesita JavaScript, sin resultados dentro';
            }
            if ($r['bytes'] < 20000) {
                return 'el buscador contestó con una página demasiado corta para traer resultados ('
                     . number_format($r['bytes'] / 1024, 1, ',', '.') . ' KB)';
            }
            return 'el buscador contestó, pero su página no trae resultados que se puedan leer';
        }
        return count($res) . ' resultados leídos';
    }

    /**
     * Resultados a través de un servicio de búsqueda con clave.
     *
     * Los tres hablan JSON y todos dan la lista ya en orden, que es justo lo
     * que hace falta. La respuesta entera se guarda igual que se guarda el
     * HTML cuando se raspa: también es prueba.
     */
    private static function porApi(string $proveedor, string $consulta, int $pagina, array $op): array
    {
        $clave = trim((string) Ajustes::obtener('pos_api_clave', ''));
        $pais   = (string) ($op['pais'] ?? 'gt');
        $idioma = (string) ($op['idioma'] ?? 'es');
        $desde  = $pagina * self::POR_PAGINA;

        $base = [
            'ok' => false, 'url' => '', 'codigo' => 0, 'crudo' => '', 'bytes' => 0,
            'ms' => 0, 'bloqueo' => false, 'error' => '', 'resultados' => [], 'diagnostico' => '',
        ];

        if ($clave === '') {
            $base['error'] = 'Falta la clave del servicio de búsqueda. Se pone en '
                           . 'Panel → Ajustes → Auditor → Palabras clave.';
            $base['diagnostico'] = $base['error'];
            return $base;
        }

        // Http::obtener hace POST en cuanto se le da un cuerpo, así que no
        // hace falta decirle el método: con 'datos' va POST y sin él, GET.
        $cabeceras = ['Accept: application/json'];
        $datos = null;

        switch ($proveedor) {
            case 'serper':
                $url = 'https://google.serper.dev/search';
                $cabeceras[] = 'X-API-KEY: ' . $clave;
                $cabeceras[] = 'Content-Type: application/json';
                $datos = json_encode([
                    'q' => $consulta, 'gl' => $pais, 'hl' => $idioma,
                    'num' => self::POR_PAGINA, 'page' => $pagina + 1,
                ]);
                break;

            case 'serpapi':
                $url = 'https://serpapi.com/search.json?' . http_build_query([
                    'engine' => 'google', 'q' => $consulta, 'gl' => $pais, 'hl' => $idioma,
                    'num' => self::POR_PAGINA, 'start' => $desde, 'api_key' => $clave,
                    'device' => ($op['dispositivo'] ?? '') === 'movil' ? 'mobile' : 'desktop',
                ]);
                break;

            case 'cse':
            default:
                $cx = trim((string) Ajustes::obtener('pos_cse_cx', ''));
                if ($cx === '') {
                    $base['error'] = 'Falta el identificador del buscador (cx) de Google Custom Search.';
                    $base['diagnostico'] = $base['error'];
                    return $base;
                }
                $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
                    'key' => $clave, 'cx' => $cx, 'q' => $consulta,
                    'gl' => $pais, 'hl' => $idioma, 'num' => self::POR_PAGINA,
                    'start' => $desde + 1,
                ]);
                break;
        }

        $resp = Http::obtener($url, [
            'timeout'   => (int) ($op['timeout'] ?? 30),
            'cabeceras' => $cabeceras,
            'datos'     => $datos,
        ]);

        $cuerpo = (string) ($resp['cuerpo'] ?? '');
        $json   = json_decode($cuerpo, true);

        $base['url']    = self::sinClave($url, $clave);
        $base['codigo'] = (int) ($resp['codigo'] ?? 0);
        $base['crudo']  = self::sinClave($cuerpo, $clave);
        $base['bytes']  = strlen($cuerpo);
        $base['ms']     = (int) ($resp['ms'] ?? 0);
        $base['ok']     = !empty($resp['ok']) && is_array($json);

        if (!$base['ok']) {
            $base['error'] = (string) ($resp['error'] ?? '');
            if ($base['error'] === '' && is_array($json)) {
                $base['error'] = (string) ($json['error']['message'] ?? $json['error'] ?? '');
            }
            if ($base['error'] === '') { $base['error'] = 'el servicio contestó algo que no es JSON'; }
            $base['diagnostico'] = 'HTTP ' . $base['codigo'] . ' · ' . $base['error'];
            return $base;
        }

        // Un error del servicio llega con HTTP 200 más de lo que debería.
        $mensaje = (string) ($json['error']['message'] ?? $json['message'] ?? $json['error'] ?? '');
        if ($mensaje !== '') {
            $base['ok'] = false;
            $base['error'] = $mensaje;
            $base['diagnostico'] = 'el servicio devolvió un error: ' . $mensaje;
            return $base;
        }

        $base['resultados'] = self::deJson($proveedor, $json);
        $base['diagnostico'] = $base['resultados']
            ? count($base['resultados']) . ' resultados leídos'
            : 'el servicio no devolvió resultados para esta búsqueda';
        return $base;
    }

    /**
     * La lista ordenada que devuelve cada servicio.
     *
     * @return array<int,array{url:string,titulo:string}>
     */
    private static function deJson(string $proveedor, array $json): array
    {
        $crudos = match ($proveedor) {
            'serper'  => $json['organic'] ?? [],
            'serpapi' => $json['organic_results'] ?? [],
            default   => $json['items'] ?? [],
        };
        if (!is_array($crudos)) { return []; }

        $salida = [];
        foreach ($crudos as $r) {
            if (!is_array($r)) { continue; }
            $url = (string) ($r['link'] ?? $r['url'] ?? '');
            if (!preg_match('~^https?://~i', $url)) { continue; }
            $salida[] = [
                'url'    => $url,
                'titulo' => mb_substr(trim((string) ($r['title'] ?? '')), 0, 300),
            ];
        }
        return $salida;
    }

    /** Nunca se guarda ni se enseña una clave de API. */
    private static function sinClave(string $texto, string $clave): string
    {
        if ($clave === '') { return $texto; }
        return str_replace([$clave, rawurlencode($clave)], '«tu clave»', $texto);
    }

    // =====================================================================
    //  Leer una página de resultados
    // =====================================================================

    /**
     * Los resultados orgánicos de una página, en su orden y con su título.
     *
     * Cada buscador tiene su forma, así que primero se prueba la suya y, si
     * cambió de formato —que pasa—, se cae a una regla que vale en todos: el
     * título de un resultado siempre es un enlace dentro de un <h2> o un <h3>.
     *
     * @return array<int,array{url:string,titulo:string}>
     */
    public static function resultados(string $motor, string $html): array
    {
        if (str_contains($motor, 'rss') || str_contains(substr($html, 0, 500), '<rss')) {
            return self::deRss($html);
        }

        $xp = self::xpath($html);
        if ($xp === null) { return []; }

        $consultas = match (true) {
            str_starts_with($motor, 'ddg') => [
                '//a[contains(@class,"result__a") or contains(@class,"result-link")][@href]',
            ],
            str_starts_with($motor, 'bing') => [
                '//li[contains(concat(" ",normalize-space(@class)," ")," b_algo ")]//h2/a[@href]',
            ],
            $motor === 'mojeek' => [
                '//ul[contains(@class,"results")]//li//h2/a[@href]',
                '//li//h2/a[@href]',
            ],
            default => [   // Google, que cambia de maquetado cada temporada
                '//div[@id="search"]//a[@href][.//h3]',
                '//div[@id="rso"]//a[@href][.//h3]',
                '//div[contains(@class,"MjjYud")]//a[@href][.//h3]',
                '//div[contains(concat(" ",normalize-space(@class)," ")," g ")]//a[@href][.//h3]',
                '//a[@href][.//h3]',
                '//a[@href][.//*[@role="heading"]]',
            ],
        };
        // Último recurso, común a todos.
        $consultas[] = '//h2/a[@href] | //h3/a[@href] | //h2//a[@href][.//h3] | //a[@href][.//h3]';

        foreach ($consultas as $busqueda) {
            $lista = self::recoger($xp, $busqueda);
            if ($lista) { return $lista; }
        }
        return [];
    }

    /**
     * Recorre los nodos de una consulta XPath y arma la lista limpia.
     *
     * @return array<int,array{url:string,titulo:string}>
     */
    private static function recoger(DOMXPath $xp, string $busqueda): array
    {
        $nodos = @$xp->query($busqueda);
        if (!$nodos || $nodos->length === 0) { return []; }

        $salida = [];
        $vistas = [];
        foreach ($nodos as $a) {
            /** @var DOMElement $a */
            $url = self::limpiarEnlace(trim($a->getAttribute('href')));
            if ($url === '') { continue; }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || self::esPropio($host)) { continue; }

            // El mismo resultado puede venir dos veces (el título y un enlace
            // interno del mismo bloque). El puesto es el del primero.
            $sin = rtrim(strtolower($url), '/');
            if (isset($vistas[$sin])) { continue; }
            $vistas[$sin] = true;

            $titulo = trim(preg_replace('~\s+~u', ' ', $a->textContent) ?? '');
            $salida[] = ['url' => $url, 'titulo' => mb_substr($titulo, 0, 300)];
            if (count($salida) >= 50) { break; }
        }
        return $salida;
    }

    /** Los resultados de un RSS (el de Bing), en orden. */
    private static function deRss(string $xml): array
    {
        if (!preg_match_all('~<item\b.*?</item>~is', $xml, $items)) { return []; }

        $salida = [];
        foreach ($items[0] as $item) {
            if (!preg_match('~<link>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*</link>~is', $item, $mu)) { continue; }
            $url = self::limpiarEnlace(trim(html_entity_decode($mu[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($url === '') { continue; }
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || self::esPropio($host)) { continue; }

            $titulo = '';
            if (preg_match('~<title>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*</title>~is', $item, $mt)) {
                $titulo = trim(html_entity_decode(strip_tags($mt[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $salida[] = ['url' => $url, 'titulo' => mb_substr($titulo, 0, 300)];
        }
        return $salida;
    }

    /**
     * La dirección de verdad detrás del enlace del buscador.
     *
     * Ninguno enlaza directo: Google pasa por /url?q=, DuckDuckGo por
     * /l/?uddg= y Bing por /ck/a?...&u=a1<base64url>. Sin deshacer esos
     * envoltorios, todos los resultados parecerían del propio buscador.
     */
    private static function limpiarEnlace(string $href): string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) { return ''; }

        // Anuncios: Google los sirve por /aclk y /pagead, Bing por /ck/a con
        // 'ad' en la ruta. No son puestos que se puedan ganar escribiendo.
        if (preg_match('~^(?:https?://[^/]+)?/(?:aclk|pagead|url\?adurl)~i', $href)) { return ''; }

        if (preg_match('~[?&]uddg=([^&]+)~', $href, $m)) {
            $href = rawurldecode($m[1]);
        } elseif (preg_match('~^(?:https?://[^/]*google[^/]*)?/url\?~i', $href)) {
            parse_str((string) parse_url($href, PHP_URL_QUERY), $q);
            $href = (string) ($q['q'] ?? $q['url'] ?? '');
        } elseif (preg_match('~[?&]u=a1([A-Za-z0-9_\-]+)~', $href, $m)) {
            $plano = self::deBase64Url($m[1]);
            if ($plano !== '') { $href = $plano; }
        }

        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!preg_match('~^https?://~i', $href)) { return ''; }

        // Se quita lo que el buscador añade para su propio seguimiento: dos
        // enlaces al mismo sitio no pueden contar como dos puestos.
        $href = (string) preg_replace('~[?&](?:sa|ved|usg|rut|ei|source|cd|cad)=[^&]*~i', '', $href);
        return rtrim(str_replace(['?&', '&&'], ['?', '&'], $href), '?&');
    }

    /** Lo que Bing esconde en base64url detrás de su redirector. */
    private static function deBase64Url(string $cod): string
    {
        $plano = base64_decode(strtr($cod, '-_', '+/'), false);
        return is_string($plano) && preg_match('~^https?://~i', $plano) ? $plano : '';
    }

    /** ¿Este host es del propio buscador? */
    private static function esPropio(string $host): bool
    {
        foreach (self::PROPIOS as $p) {
            if ($host === $p || str_ends_with($host, '.' . $p)) { return true; }
        }
        return false;
    }

    /** Un DOMXPath del HTML, o null si no se pudo leer. */
    private static function xpath(string $html): ?DOMXPath
    {
        if (trim($html) === '') { return null; }
        $antes = libxml_use_internal_errors(true);
        $doc   = new DOMDocument();
        try {
            $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        } catch (Throwable $e) {
            $ok = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($antes);
        }
        return $ok ? new DOMXPath($doc) : null;
    }

    // =====================================================================
    //  Comparar con lo que se busca
    // =====================================================================

    /**
     * ¿Este resultado es la web que se está midiendo?
     *
     * Por dominio, www. da igual y los subdominios cuentan: quien mide
     * «colegio.edu.gt» quiere saber de blog.colegio.edu.gt también. En modo
     * exacto se compara la dirección entera, para medir una página concreta.
     */
    private static function coincide(string $url, string $buscado, bool $exacta): bool
    {
        if ($exacta) {
            return rtrim(strtolower(self::sinProtocolo($url)), '/')
                === rtrim(strtolower(self::sinProtocolo($buscado)), '/');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') { return false; }
        $host = preg_replace('~^www\.~', '', $host) ?? $host;
        $buscado = preg_replace('~^www\.~', '', strtolower($buscado)) ?? $buscado;

        return $host === $buscado || str_ends_with($host, '.' . $buscado);
    }

    private static function sinProtocolo(string $url): string
    {
        return (string) preg_replace('~^https?://(www\.)?~i', '', trim($url));
    }

    /**
     * Deja el dominio en su forma mínima: sin protocolo, sin ruta, sin www.
     *
     * En modo exacto se conserva la ruta, porque ahí se mide una página.
     */
    public static function normalizarDominio(string $entrada): string
    {
        $t = trim($entrada);
        if ($t === '') { return ''; }

        if (str_contains($t, '/') || str_contains($t, '://')) {
            $conProto = preg_match('~^https?://~i', $t) ? $t : 'https://' . $t;
            $host = (string) parse_url($conProto, PHP_URL_HOST);
            if ($host !== '') { $t = $host; }
        }
        $t = strtolower(preg_replace('~^www\.~i', '', trim($t, "/ \t\n\r")) ?? $t);
        return preg_match('~^[a-z0-9.\-]+\.[a-z]{2,}$~', $t) ? $t : '';
    }

    /** Un código de país o idioma de dos letras, o el de reserva. */
    private static function codigo(string $v, string $defecto): string
    {
        $v = strtolower(trim($v));
        return preg_match('~^[a-z]{2}$~', $v) ? $v : $defecto;
    }

    // =====================================================================
    //  Guardar la medición y su prueba
    // =====================================================================

    /**
     * Escribe la medición y deja el HTML de cada página en disco.
     *
     * El HTML va comprimido y fuera de la base de datos: una página de
     * resultados de Google ocupa medio mega, y guardar eso en una fila por
     * cada medición deja la tabla inservible en un mes.
     *
     * @return int el id de la medición, o 0 si no se pudo guardar
     */
    public static function guardar(array $m, ?int $claveId, ?int $usuarioId): int
    {
        try {
            $id = BD::insertar('cr_posiciones', [
                'clave_id'    => $claveId,
                'usuario_id'  => $usuarioId,
                'consulta'    => mb_substr((string) $m['consulta'], 0, 190),
                'dominio'     => mb_substr((string) $m['dominio'], 0, 190),
                'motor'       => mb_substr((string) $m['motor'], 0, 20),
                'proveedor'   => mb_substr((string) ($m['proveedor'] ?? 'directo'), 0, 20),
                'pais'        => (string) $m['pais'],
                'idioma'      => (string) $m['idioma'],
                'dispositivo' => (string) $m['dispositivo'],
                'posicion'    => $m['posicion'],
                'pagina'      => $m['pagina'],
                'en_pagina'   => $m['en_pagina'],
                'url_hallada' => mb_substr((string) $m['url_hallada'], 0, 500),
                'revisados'   => (int) $m['revisados'],
                'resultados'  => json_encode($m['resultados'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'peticiones'  => json_encode($m['peticiones'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'agente'      => mb_substr((string) $m['agente'], 0, 255),
                'error'       => mb_substr((string) $m['error'], 0, 255),
                'creado'      => $m['fecha'],
            ]);
        } catch (Throwable $e) {
            error_log('Kaptor / posiciones: ' . $e->getMessage());
            return 0;
        }

        self::guardarPruebas($id, $m['snapshots'] ?? []);
        return $id;
    }

    /** Deja el HTML original de cada página en storage/serp/<id>/. */
    private static function guardarPruebas(int $id, array $snapshots): void
    {
        if ($id <= 0 || !$snapshots) { return; }
        $dir = self::carpeta($id);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { return; }

        foreach ($snapshots as $pagina => $html) {
            $destino = $dir . '/p' . (int) $pagina . '.html.gz';
            $datos = function_exists('gzencode') ? gzencode($html, 6) : $html;
            @file_put_contents($destino, $datos === false ? $html : $datos);
        }
    }

    /** Dónde vive la prueba de una medición. */
    public static function carpeta(int $id): string
    {
        return CR_STORAGE . '/serp/' . $id;
    }

    /**
     * El HTML original de una página guardada, o '' si ya no está.
     */
    public static function prueba(int $id, int $pagina): string
    {
        $archivo = self::carpeta($id) . '/p' . $pagina . '.html.gz';
        if (!is_file($archivo)) { return ''; }
        $crudo = (string) @file_get_contents($archivo);
        if ($crudo === '') { return ''; }
        $plano = @gzdecode($crudo);
        return is_string($plano) ? $plano : $crudo;
    }

    /** Borra la medición y sus pruebas. */
    public static function borrar(int $id, ?int $usuarioId): bool
    {
        $fila = self::porId($id, $usuarioId);
        if (!$fila) { return false; }

        $dir = self::carpeta($id);
        if (is_dir($dir)) {
            foreach ((array) glob($dir . '/*') as $f) { @unlink((string) $f); }
            @rmdir($dir);
        }
        BD::ejecutar('DELETE FROM `cr_posiciones` WHERE `id` = ?', [$id]);
        return true;
    }

    // =====================================================================
    //  Consultas
    // =====================================================================

    /** Una medición, comprobando que sea de quien la pide. */
    public static function porId(int $id, ?int $usuarioId): ?array
    {
        $fila = BD::fila('SELECT * FROM `cr_posiciones` WHERE `id` = ?', [$id]);
        if (!$fila) { return null; }
        if ($usuarioId !== null && $fila['usuario_id'] !== null
            && (int) $fila['usuario_id'] !== $usuarioId && !Auth::esAdmin()) {
            return null;
        }
        return $fila;
    }

    /**
     * Las últimas mediciones, una por palabra clave y dominio.
     *
     * @return array<int,array>
     */
    public static function ultimas(?int $usuarioId, int $limite = 60): array
    {
        $donde = $usuarioId !== null ? 'WHERE `usuario_id` = ?' : '';
        $args  = $usuarioId !== null ? [$usuarioId] : [];

        return BD::todos(
            'SELECT `id`, `consulta`, `dominio`, `motor`, `proveedor`, `pais`, `idioma`, `dispositivo`,
                    `posicion`, `pagina`, `en_pagina`, `revisados`, `error`, `creado`
               FROM `cr_posiciones` ' . $donde . '
              ORDER BY `id` DESC LIMIT ' . max(1, min($limite, 200)),
            $args
        );
    }

    /**
     * El historial de una palabra clave: cómo se movió el puesto con el tiempo.
     *
     * @return array<int,array>
     */
    public static function historial(string $consulta, string $dominio, string $motor,
                                     ?int $usuarioId, int $limite = 30): array
    {
        $args  = [$consulta, $dominio, $motor];
        $donde = '';
        if ($usuarioId !== null) { $donde = ' AND `usuario_id` = ?'; $args[] = $usuarioId; }

        return array_reverse(BD::todos(
            'SELECT `id`, `posicion`, `pagina`, `en_pagina`, `creado`
               FROM `cr_posiciones`
              WHERE `consulta` = ? AND `dominio` = ? AND `motor` = ?' . $donde . '
              ORDER BY `id` DESC LIMIT ' . max(2, min($limite, 100)),
            $args
        ));
    }

    /** Cómo se dice un puesto en palabras: «el 5.º de la página 2». */
    public static function enPalabras(?int $posicion, ?int $pagina, ?int $enPagina): string
    {
        if ($posicion === null) { return 'sin aparecer'; }
        if ($pagina === null || $pagina <= 1) {
            return 'puesto ' . $posicion . ' de la primera página';
        }
        return 'puesto ' . $enPagina . ' de la página ' . $pagina
             . ' (el ' . $posicion . ' contando desde el principio)';
    }
}
