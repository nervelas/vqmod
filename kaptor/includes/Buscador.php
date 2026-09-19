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
    public static function buscar(string $consulta, int $resultados = 50): array
    {
        $consulta = trim($consulta);

        // Si la consulta apunta expresamente a una red (site:facebook.com,
        // "instagram colegios"...), sus perfiles dejan de descartarse: es
        // justo lo que se está buscando.
        self::$conRedes = Ajustes::activo('buscar_redes')
            || (bool) preg_match('~\b(site:\s*)?(www\.)?(facebook|instagram|fb)\.(com|me)\b~i', $consulta);
        if ($consulta === '') {
            return ['ok' => false, 'error' => 'Escribe qué quieres buscar.'];
        }

        $resultados = max(10, min($resultados, Ajustes::entero('buscador_max', 100, 10, 300)));
        $paginas    = (int) ceil($resultados / self::POR_PAGINA);

        $motores = ['duckduckgo' => 'DuckDuckGo', 'bing' => 'Bing', 'google' => 'Google'];
        $preferido = (string) Ajustes::obtener('buscador_motor', 'auto');
        if (isset($motores[$preferido])) {
            $motores = [$preferido => $motores[$preferido]] + $motores;
        }

        $fallos = [];
        foreach ($motores as $motor => $nombre) {
            $urls = [];
            $bloqueado = false;

            for ($pagina = 0; $pagina < $paginas; $pagina++) {
                $resp = Http::obtener(self::url($motor, $consulta, $pagina), [
                    'timeout'  => Ajustes::entero('timeout', 20, 3, 180),
                    'cabeceras' => self::cabeceras(),
                ]);

                if (!$resp['ok'] || $resp['cuerpo'] === '') {
                    if (in_array((int) $resp['codigo'], [429, 403, 503], true)) { $bloqueado = true; }
                    break;
                }
                if (self::pareceBloqueo($resp['cuerpo'])) { $bloqueado = true; break; }

                $nuevas = self::enlaces($motor, $resp['cuerpo']);
                if (!$nuevas) { break; }

                foreach ($nuevas as $u) { $urls[$u] = true; }
                if (count($urls) >= $resultados) { break; }

                // Un respiro entre páginas: ser educado evita bloqueos.
                usleep(350000);
            }

            $urls = array_slice(array_keys($urls), 0, $resultados);
            if ($urls) {
                return ['ok' => true, 'urls' => $urls, 'motor' => $nombre, 'paginas' => $paginas];
            }
            $fallos[] = $nombre . ($bloqueado ? ' (bloqueó la consulta)' : ' (sin resultados)');
        }

        return [
            'ok'    => false,
            'error' => 'Ningún buscador devolvió resultados: ' . implode(', ', $fallos)
                     . '. Los buscadores bloquean las consultas automáticas desde servidores; '
                     . 'prueba de nuevo en unos minutos o pega directamente la lista de webs.',
        ];
    }

    /** Dirección de consulta de cada buscador. */
    private static function url(string $motor, string $consulta, int $pagina): string
    {
        $q = rawurlencode($consulta);
        switch ($motor) {
            case 'bing':
                return 'https://www.bing.com/search?q=' . $q . '&first=' . ($pagina * self::POR_PAGINA + 1) . '&setlang=es';
            case 'google':
                return 'https://www.google.com/search?q=' . $q . '&start=' . ($pagina * self::POR_PAGINA) . '&hl=es&num=' . self::POR_PAGINA;
            case 'duckduckgo':
            default:
                $url = 'https://html.duckduckgo.com/html/?q=' . $q . '&kl=es-es';
                if ($pagina > 0) { $url .= '&s=' . ($pagina * 30) . '&dc=' . ($pagina * 30 + 1); }
                return $url;
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
