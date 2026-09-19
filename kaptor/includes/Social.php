<?php
/**
 * Kaptor - Facebook e Instagram.
 *
 * Qué se puede y qué no, sin adornos:
 *
 * Meta sirve buena parte de los datos públicos de una página a quien no ha
 * iniciado sesión, pero metidos dentro de bloques JSON en el propio HTML, y
 * además levanta muros de acceso con frecuencia. Por eso aquí se hace lo
 * siguiente:
 *
 *   1. Se prueban varias direcciones de la misma página (la de "información",
 *      la versión móvil, la básica), porque unas responden cuando otras no.
 *   2. Si lo que llega es un muro de acceso, se dice con claridad en lugar de
 *      devolver cero correos como si la página no tuviera ninguno.
 *   3. Del HTML se saca el correo con el mismo motor de siempre (los datos van
 *      escapados como @ y \/ dentro del JSON, que el extractor entiende),
 *      y además la web propia del negocio, que es donde casi siempre está el
 *      correo de verdad: se añade a la cola y se rastrea también.
 *
 * Ni Facebook ni Instagram permiten el rastreo automático en sus condiciones
 * de uso. Esto sirve para consultar datos de contacto que la propia página
 * publica; usarlo con cabeza es cosa de quien lo maneja.
 */
declare(strict_types=1);

final class Social
{
    /** ¿La dirección es de Facebook o de Instagram? Devuelve '' si no. */
    public static function tipo(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') { return ''; }

        if (preg_match('~(^|\.)(facebook|fb)\.(com|me)$~', $host))  { return 'facebook'; }
        if (preg_match('~(^|\.)instagram\.com$~', $host))           { return 'instagram'; }
        return '';
    }

    /** Nombre de usuario o de la página dentro de la red. */
    public static function usuario(string $url): string
    {
        $ruta = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($ruta === '') { return ''; }

        $trozos = array_values(array_filter(explode('/', $ruta)));
        $primero = $trozos[0] ?? '';

        // /p/, /reel/, /profile.php, /pages/... no son el nombre de la página.
        if (in_array($primero, ['p', 'reel', 'reels', 'stories', 'explore', 'tv', 'share'], true)) { return ''; }
        if ($primero === 'pages' && isset($trozos[1])) { return $trozos[1]; }
        if (str_contains($primero, '.php')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            return (string) ($q['id'] ?? '');
        }
        return $primero;
    }

    /**
     * Direcciones que se prueban, en orden, para una misma página.
     *
     * @return string[]
     */
    public static function variantes(string $url): array
    {
        $tipo    = self::tipo($url);
        $usuario = self::usuario($url);
        if ($tipo === '' || $usuario === '') { return [$url]; }

        if ($tipo === 'facebook') {
            return [
                'https://www.facebook.com/' . $usuario . '/about_contact_and_basic_info',
                'https://www.facebook.com/' . $usuario . '/about',
                'https://mbasic.facebook.com/' . $usuario . '/about',
                'https://m.facebook.com/' . $usuario . '/about',
                'https://www.facebook.com/' . $usuario . '/',
            ];
        }

        return [
            'https://www.instagram.com/' . $usuario . '/',
            'https://www.instagram.com/' . $usuario . '/?hl=es',
        ];
    }

    /** ¿La respuesta es un muro de acceso en lugar de la página? */
    public static function muro(string $html): bool
    {
        if ($html === '') { return true; }

        $muestra = mb_strtolower(mb_substr($html, 0, 20000));
        $senales = [
            'iniciar sesión o registrarte', 'log in or sign up',
            'inicia sesión para continuar', 'log in to continue',
            'you must log in to continue', 'debes iniciar sesión para continuar',
            'entrar no facebook', 'connexion à facebook',
            'restricted_page', 'login_form', 'loginform',
            'esta página no está disponible', "this page isn't available",
        ];
        $encontradas = 0;
        foreach ($senales as $s) {
            if (str_contains($muestra, $s)) { $encontradas++; }
        }
        if ($encontradas === 0) { return false; }

        // Una página real también menciona "iniciar sesión" en su cabecera, así
        // que solo se considera muro si además apenas hay contenido propio.
        $texto = trim((string) preg_replace('~\s+~', ' ', strip_tags($html)));
        return $encontradas >= 2 || mb_strlen($texto) < 900;
    }

    /**
     * Webs propias que la página publica (el campo "Sitio web" de Facebook o
     * el enlace de la biografía de Instagram). Es donde suele estar el correo.
     *
     * @return string[]
     */
    public static function webs(string $html, string $base): array
    {
        $encontradas = [];

        // Facebook e Instagram enmascaran los enlaces salientes:
        //   l.facebook.com/l.php?u=<url codificada>
        //   l.instagram.com/?u=<url codificada>
        if (preg_match_all('~l\.(?:facebook|instagram)\.com/[^"\'\s]*?[?&]u=([^"&\'\s]+)~i', $html, $m)) {
            foreach ($m[1] as $codificada) {
                $encontradas[] = rawurldecode(str_replace('\\/', '/', $codificada));
            }
        }
        // Y dentro del JSON el sitio aparece tal cual, con las barras escapadas.
        if (preg_match_all('~"(?:website|external_url|url)"\s*:\s*"(https?:\\\\?/\\\\?/[^"]+)"~i', $html, $m2)) {
            foreach ($m2[1] as $u) { $encontradas[] = stripslashes($u); }
        }

        $limpias = [];
        foreach ($encontradas as $u) {
            $u = html_entity_decode(trim($u), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $u = strtok($u, '#');
            if (!preg_match('~^https?://~i', $u)) { continue; }

            $host = strtolower((string) parse_url($u, PHP_URL_HOST));
            // Ni enlaces internos de Meta ni las tiendas de aplicaciones.
            if ($host === '' || preg_match('~(facebook|instagram|fb|fbcdn|cdninstagram|messenger|threads|whatsapp|apps\.apple|play\.google|linktr)~', $host)) {
                continue;
            }
            $limpias[$u] = true;
            if (count($limpias) >= 3) { break; }
        }

        return array_keys($limpias);
    }

    /**
     * Texto útil de la página: biografía, descripción y bloques de contacto.
     * Se le pasa al extractor para que busque correos también ahí.
     */
    public static function texto(string $html): string
    {
        $trozos = [];

        foreach ([
            '~<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)~i',
            '~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)~i',
            '~"biography"\s*:\s*"([^"]{0,1200})"~i',
            '~"business_email"\s*:\s*"([^"]{0,300})"~i',
            '~"public_email"\s*:\s*"([^"]{0,300})"~i',
            '~"email"\s*:\s*"([^"]{0,300})"~i',
            '~"contact_email"\s*:\s*"([^"]{0,300})"~i',
        ] as $patron) {
            if (preg_match_all($patron, $html, $m)) {
                foreach ($m[1] as $t) {
                    // El JSON llega escapado: @ es la arroba y \n un salto.
                    $t = stripslashes($t);
                    $t = preg_replace_callback('~\\\\u([0-9a-fA-F]{4})~', static fn(array $c): string =>
                        mb_convert_encoding(pack('H*', $c[1]), 'UTF-8', 'UTF-16BE'), $t) ?? $t;
                    $trozos[] = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }

        return implode("\n", array_unique($trozos));
    }
}
