<?php
/**
 * Kaptor - Búsqueda en buscadores.
 *
 * Convierte unas palabras ("colegios privados Guatemala correo") o el enlace
 * de una búsqueda ya hecha en Google, Bing o DuckDuckGo en una lista de webs
 * que después el rastreador recorre una por una.
 *
 * Sobre los buscadores, con honestidad: Google bloquea con frecuencia las
 * peticiones automáticas desde servidores de hosting y responde con una
 * página de "consentimiento" o un captcha. Por eso se consulta en cadena:
 * primero DuckDuckGo, que es el más permisivo, luego Bing y por último
 * Google. Se usa el primero que conteste resultados de verdad, y si ninguno
 * lo hace se dice claramente en lugar de devolver una lista vacía.
 */
declare(strict_types=1);

final class Buscador
{
    /** ¿Esta búsqueda quiere perfiles de Facebook o Instagram? */
    private static bool $conRedes = false;

    /** Extensiones pedidas en la búsqueda inteligente (['edu.gt', ...]). */
    private static array $extensiones = [];

    /** Resultados que pide cada página a los buscadores. */
    private const POR_PAGINA = 10;

    /** Dominios que nunca interesan como resultado. */
    private const DESCARTAR = [
        'google.', 'bing.com', 'duckduckgo.com', 'yahoo.com', 'youtube.com',
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'tiktok.com',
        'pinterest.', 'linkedin.com', 'wikipedia.org', 'amazon.', 'ebay.',
        'microsoft.com', 'apple.com', 'blogspot.com', 'wordpress.com',
        'gstatic.com', 'googleusercontent.com', 'w3.org', 'schema.org',
    ];

    /**
     * ¿El texto es el enlace de una búsqueda? Devuelve las palabras buscadas.
     *
     * Reconoce google.com/search?q=, bing.com/search?q=, duckduckgo.com/?q=
     * y también las variantes por país (google.com.gt, google.es...).
     */
    public static function consultaDeUrl(string $texto): string
    {
        $texto = trim($texto);
        if (!preg_match('~^https?://~i', $texto)) { return ''; }

        $partes = parse_url($texto);
        $host   = strtolower((string) ($partes['host'] ?? ''));
        if ($host === '') { return ''; }

        $esBuscador = preg_match('~(^|\.)google\.[a-z.]+$~', $host)
                   || preg_match('~(^|\.)(bing|duckduckgo|ecosia|brave|startpage|mojeek)\.[a-z.]+$~', $host)
                   || preg_match('~(^|\.)search\.(yahoo|marcia)\.[a-z.]+$~', $host);
        if (!$esBuscador) { return ''; }

        parse_str((string) ($partes['query'] ?? ''), $parametros);
        foreach (['q', 'query', 'p', 'text', 'search'] as $clave) {
            if (!empty($parametros[$clave]) && is_string($parametros[$clave])) {
                return trim($parametros[$clave]);
            }
        }
        return '';
    }

    /** ¿El texto son palabras de búsqueda y no una dirección web? */
    public static function pareceConsulta(string $texto): bool
    {
        $texto = trim($texto);
        if ($texto === '' || mb_strlen($texto) > 200) { return false; }
        if (preg_match('~^https?://~i', $texto)) { return false; }
        // "colegios.com" sin espacios es un dominio, no una búsqueda.
        if (!str_contains($texto, ' ') && preg_match('~^[a-z0-9.\-]+\.[a-z]{2,}$~i', $texto)) { return false; }
        return true;
    }

    /**
     * Busca y devuelve las webs encontradas.
     *
     * @return array{ok:bool,urls?:string[],motor?:string,error?:string,paginas?:int}
     */
    /**
     * Motores, en el orden en que se prueban.
     *
     * Van primero los que responden HTML sencillo y admiten que se les
     * consulte sin navegador. El RSS de Bing es el más fiable de todos: no
     * lleva JavaScript, no cambia de formato y rara vez bloquea.
     *
     * @var array<string,string>
     */
    private const MOTORES = [
        'ddg_lite'  => 'DuckDuckGo',
        'ddg_html'  => 'DuckDuckGo (HTML)',
        'bing_rss'  => 'Bing (RSS)',
        'bing'      => 'Bing',
        'mojeek'    => 'Mojeek',
        'google'    => 'Google',
    ];

    /**
     * Busca y devuelve las webs encontradas.
     *
     * @return array{ok:bool,urls?:string[],motor?:string,error?:string,detalle?:string}
     */
    public static function buscar(string $consulta, int $resultados = 50, array $extensiones = []): array
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return ['ok' => false, 'error' => 'Escribe qué quieres buscar.'];
        }

        // Búsqueda inteligente: si se pide ".edu.gt", la consulta se reescribe
        // para que el buscador solo devuelva webs de ese tipo de dominio.
        $consultas = self::consultasDirigidas($consulta, $extensiones);
        self::$extensiones = $extensiones;

        // Si la consulta apunta expresamente a una red (site:facebook.com,
        // "instagram colegios"...), sus perfiles dejan de descartarse: es
        // justo lo que se está buscando.
        self::$conRedes = Ajustes::activo('buscar_redes')
            || (bool) preg_match('~\b(site:\s*)?(www\.)?(facebook|instagram|fb)\.(com|me)\b~i', $consulta);

        $resultados = max(10, min($resultados, Ajustes::entero('buscador_max', 100, 10, 300)));
        $paginas    = (int) ceil($resultados / self::POR_PAGINA);

        $motores   = self::MOTORES;
        $preferido = (string) Ajustes::obtener('buscador_motor', 'auto');
        $alias     = ['duckduckgo' => 'ddg_lite', 'bing' => 'bing_rss', 'google' => 'google', 'mojeek' => 'mojeek'];
        if (isset($alias[$preferido]) && isset($motores[$alias[$preferido]])) {
            $clave   = $alias[$preferido];
            $motores = [$clave => $motores[$clave]] + $motores;
        }

        $diagnostico = [];
        foreach ($motores as $motor => $nombre) {
            $urls   = [];
            $caido  = false;   // el motor no contesta o pide captcha: no insistir

          foreach ($consultas as $iConsulta => $consultaMotor) {
            if ($caido) { break; }
            for ($pagina = 0; $pagina < $paginas; $pagina++) {
                $peticion = self::peticion($motor, $consultaMotor, $pagina);
                $resp = Http::obtener($peticion['url'], [
                    'timeout'   => Ajustes::entero('timeout', 20, 3, 180),
                    'cabeceras' => self::cabeceras(),
                    'datos'     => $peticion['datos'] ?? null,
                    'referer'   => $peticion['referer'] ?? '',
                ]);

                // Diagnóstico honesto: qué contestó exactamente cada motor.
                if ($pagina === 0 && $iConsulta === 0) {
                    $diagnostico[] = $nombre . ': ' . self::resumen($resp);
                }

                // Si el motor no responde o pide captcha, no tiene sentido
                // probar con él las demás consultas dirigidas.
                if (!$resp['ok'] || $resp['cuerpo'] === '')  { $caido = true; break; }
                if (self::pareceBloqueo($resp['cuerpo']))    { $caido = true; break; }

                $nuevas = self::enlaces($motor, $resp['cuerpo']);
                if (!$nuevas) { break; }

                foreach ($nuevas as $u) { $urls[$u] = true; }
                if (count($urls) >= $resultados) { break; }

                usleep(400000);   // un respiro entre páginas
            }
            if (count($urls) >= $resultados) { break; }
          }

            // Con filtro de extensiones, las webs que no lo cumplen sobran:
            // el rastreador no perdería el tiempo entrando en ellas.
            $lista = array_keys($urls);
            if ($extensiones) {
                $propias = array_values(array_filter($lista, static function ($u) use ($extensiones) {
                    return Depurador::coincide(cr_host_de_url($u), $extensiones);
                }));
                // Si el buscador no respetó el site:, se conserva lo que haya:
                // muchas webs .com publican correos .edu.gt de sus clientes.
                if ($propias) { $lista = $propias; }
            }

            $urls = array_slice($lista, 0, $resultados);
            if ($urls) {
                return ['ok' => true, 'urls' => $urls, 'motor' => $nombre];
            }
        }

        return [
            'ok'      => false,
            'error'   => 'Ningún buscador devolvió resultados. Prueba de nuevo en unos minutos, '
                       . 'cambia de buscador en Ajustes → Motor, o pega directamente la lista de webs.',
            'detalle' => implode(' · ', $diagnostico),
        ];
    }

    /** Resume en una línea qué contestó un buscador. */
    private static function resumen(array $resp): string
    {
        if ($resp['error'] !== '' && (int) $resp['codigo'] === 0) {
            return 'no se pudo conectar (' . $resp['error'] . ')';
        }
        $codigo = (int) $resp['codigo'];
        if ($codigo === 0)                    { return 'sin respuesta'; }
        if (in_array($codigo, [429, 403], true)) { return 'HTTP ' . $codigo . ', bloqueó la consulta'; }
        if ($codigo >= 400)                   { return 'HTTP ' . $codigo; }
        if ((int) $resp['bytes'] < 1000)      { return 'HTTP ' . $codigo . ', respuesta vacía'; }
        return 'HTTP ' . $codigo . ', ' . number_format((int) $resp['bytes'] / 1024, 0) . ' KB sin enlaces reconocibles';
    }

    /**
     * Dirección, método y datos de cada motor.
     *
     * @return array{url:string,datos?:array<string,string>,referer?:string}
     */
    /**
     * Convierte "colegios Guatemala" + ['edu.gt'] en varias consultas que los
     * buscadores entienden como "solo dominios .edu.gt":
     *
     *   site:edu.gt colegios Guatemala
     *   "@edu.gt" colegios Guatemala
     *
     * Si no se piden extensiones (o la consulta ya trae su propio site:),
     * se devuelve la consulta tal cual.
     *
     * @param string[] $extensiones
     * @return string[]
     */
    public static function consultasDirigidas(string $consulta, array $extensiones): array
    {
        if (!$extensiones || preg_match('~\bsite:~i', $consulta)) { return [$consulta]; }

        $lista = [];
        // Como mucho tres extensiones: mas consultas = mas lento y mas bloqueos.
        foreach (array_slice($extensiones, 0, 3) as $ext) {
            $lista[] = 'site:' . $ext . ' ' . $consulta;
            $lista[] = '"@' . $ext . '" ' . $consulta;
        }
        // Y al final la consulta limpia, por si el buscador ignora los operadores.
        $lista[] = $consulta;

        return array_values(array_unique($lista));
    }

    private static function peticion(string $motor, string $consulta, int $pagina): array
    {
        $q      = rawurlencode($consulta);
        $desde  = $pagina * self::POR_PAGINA;

        switch ($motor) {
            // DuckDuckGo solo contesta de verdad por POST a sus versiones
            // sencillas; por GET devuelve una página vacía.
            case 'ddg_lite':
                return [
                    'url'     => 'https://lite.duckduckgo.com/lite/',
                    'datos'   => ['q' => $consulta, 'kl' => 'es-es'] + ($pagina > 0 ? ['s' => (string) ($pagina * 30), 'dc' => (string) ($pagina * 30 + 1)] : []),
                    'referer' => 'https://lite.duckduckgo.com/',
                ];
            case 'ddg_html':
                return [
                    'url'     => 'https://html.duckduckgo.com/html/',
                    'datos'   => ['q' => $consulta, 'kl' => 'es-es'] + ($pagina > 0 ? ['s' => (string) ($pagina * 30), 'dc' => (string) ($pagina * 30 + 1)] : []),
                    'referer' => 'https://html.duckduckgo.com/',
                ];
            // El RSS de Bing devuelve XML limpio: es el más fiable.
            case 'bing_rss':
                return ['url' => 'https://www.bing.com/search?q=' . $q . '&format=rss&count=' . self::POR_PAGINA . '&first=' . ($desde + 1) . '&setlang=es'];
            case 'bing':
                return ['url' => 'https://www.bing.com/search?q=' . $q . '&first=' . ($desde + 1) . '&setlang=es', 'referer' => 'https://www.bing.com/'];
            case 'mojeek':
                return ['url' => 'https://www.mojeek.com/search?q=' . $q . '&s=' . $desde];
            case 'google':
            default:
                return ['url' => 'https://www.google.com/search?q=' . $q . '&start=' . $desde . '&hl=es&num=' . self::POR_PAGINA];
        }
    }

    /** Cabeceras de navegador: sin ellas los buscadores responden con un muro. */
    private static function cabeceras(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.7',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Upgrade-Insecure-Requests: 1',
        ];
    }

    /** ¿La respuesta es un captcha o una página de consentimiento? */
    private static function pareceBloqueo(string $html): bool
    {
        $muestra = mb_strtolower(mb_substr($html, 0, 4000));
        foreach (['captcha', 'unusual traffic', 'trafico inusual', 'tráfico inusual',
                  'consent.google', 'antes de continuar a google', 'before you continue',
                  'are you a robot', 'verifying you are human'] as $senal) {
            if (str_contains($muestra, $senal)) { return true; }
        }
        return false;
    }

    /**
     * Saca las webs de una página de resultados.
     *
     * @return string[]
     */
    private static function enlaces(string $motor, string $html): array
    {
        $encontradas = [];

        // RSS de Bing: los enlaces van dentro de <link> en el XML.
        if (str_contains($motor, 'rss') || str_contains(mb_substr($html, 0, 500), '<rss')) {
            if (preg_match_all('~<link>\s*(?:<!\[CDATA\[)?\s*(https?://[^<\]\s]+)~i', $html, $mr)) {
                foreach ($mr[1] as $u) { $encontradas[] = $u; }
            }
        }

        // DuckDuckGo envuelve los enlaces en /l/?uddg=<url codificada>.
        if (preg_match_all('~uddg=([^&"\']+)~i', $html, $m)) {
            foreach ($m[1] as $codificada) {
                $encontradas[] = rawurldecode($codificada);
            }
        }
        // Google, en su HTML sin JavaScript, usa /url?q=<url>&sa=...
        if (preg_match_all('~/url\?q=(https?[^&"\']+)~i', $html, $m)) {
            foreach ($m[1] as $codificada) {
                $encontradas[] = rawurldecode($codificada);
            }
        }
        // Bing a veces entrega sus resultados a través de un redirector propio:
        // .../ck/a?...&u=a1<url en base64url>. Se descifra para quedarse con
        // la dirección de verdad.
        if (preg_match_all('~[?&]u=a1([A-Za-z0-9_\-]+)~', $html, $mb)) {
            foreach ($mb[1] as $codificada) {
                $plano = base64_decode(strtr($codificada, '-_', '+/') . str_repeat('=', (4 - strlen($codificada) % 4) % 4), true);
                if (is_string($plano) && preg_match('~^https?://~i', $plano)) { $encontradas[] = $plano; }
            }
        }

        // Bing y el resto: enlaces normales dentro de los resultados.
        if (preg_match_all('~<a[^>]+href="(https?://[^"]+)"~i', $html, $m)) {
            foreach ($m[1] as $u) { $encontradas[] = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
        }

        $limpias = [];
        $porHost = [];
        foreach ($encontradas as $u) {
            $u = trim($u);
            if ($u === '' || !preg_match('~^https?://~i', $u)) { continue; }

            $host = strtolower((string) parse_url($u, PHP_URL_HOST));
            if ($host === '') { continue; }
            // El puerto forma parte del sitio: dos servicios en la misma
            // máquina y puertos distintos son webs distintas.
            $puerto = parse_url($u, PHP_URL_PORT);
            $sitio  = $host . ($puerto ? ':' . $puerto : '');

            // En Facebook e Instagram todas las páginas comparten dominio, así
            // que ahí lo que distingue un sitio de otro es el nombre de la
            // página: sin esto, de veinte colegios solo entraría uno.
            $perfil = Social::usuario($u);
            if ($perfil !== '' && Social::tipo($u) !== '') {
                $sitio .= '/' . strtolower($perfil);
            }

            $saltar = false;
            foreach (self::DESCARTAR as $d) {
                if (str_contains($host, $d)) { $saltar = true; break; }
            }
            // Los perfiles de Facebook e Instagram se descartan salvo que se
            // estén buscando a propósito: si no, acaban en muro de acceso y
            // gastarían el escaneo sin dar nada.
            if ($saltar && self::$conRedes
                && preg_match('~(^|\.)(facebook\.com|instagram\.com)$~', $host)) {
                $saltar = false;
            }
            if ($saltar) { continue; }

            // Se guarda el enlace tal cual sale en los resultados: es la página
            // que el buscador considera relevante y suele ser la del colegio o
            // su sección de contacto. Desde ahí el rastreo sigue por dentro del
            // mismo sitio. Solo se guarda el primer enlace de cada web, para no
            // gastar el escaneo entero en un único dominio.
            $u = strtok($u, '#');
            if (!isset($porHost[$sitio])) {
                $porHost[$sitio] = true;
                $limpias[$u] = true;
            }
        }

        return array_keys($limpias);
    }
}
