<?php
namespace MenuGold\Core;

/**
 * Descarga fotografía real desde bancos de imágenes libres.
 *
 * No lleva direcciones fijas: consulta la API en el momento y se queda con la
 * mejor foto disponible para cada platillo. Así no hay enlaces que caduquen.
 *
 * Fuentes, en orden:
 *   1. Wikimedia Commons — sin clave, licencias CC/dominio público.
 *   2. Openverse (WordPress) — sin clave, agrega Flickr, museos y más.
 *
 * De cada imagen se guarda autor, licencia y enlace de origen para poder dar
 * el crédito que exige la licencia.
 */
final class PhotoFetcher
{
    const AGENTE = 'MenuGold/1.0 (menu digital para restaurantes; descarga de fotografia con licencia libre)';

    /** Extremos de las APIs. Se pueden cambiar en config/config.php si el
     *  hosting usa una réplica interna o un proxy corporativo. */
    public static function extremo($cual)
    {
        $porOmision = array(
            'commons'   => 'https://commons.wikimedia.org/w/api.php',
            'openverse' => 'https://api.openverse.org/v1/images/',
        );
        $cfg = Config::get('photos.' . $cual . '_endpoint', '');
        return $cfg !== '' ? $cfg : $porOmision[$cual];
    }

    /** Escalones de búsqueda en Commons, del más selecto al más amplio. */
    private static $escalones = array(
        'incategory:"Quality images of food"',
        'incategory:"Food photographs"',
        '',                                    // búsqueda abierta, con filtro de pertinencia
    );

    /** Proporciones aceptables: fuera de aquí no encaja en una tarjeta de menú. */
    const RATIO_MIN = 0.55;
    const RATIO_MAX = 2.40;

    /**
     * Busca candidatos para un término.
     *
     * @return array<int,array{url:string,titulo:string,autor:string,licencia:string,origen:string,ancho:int,fuente:string}>
     */
    public static function buscar($termino, $minAncho = 1200, $limite = 6, array $sinonimos = array())
    {
        $out = array();
        // Para juzgar la pertinencia valen el término en inglés y el nombre
        // original del platillo: en Commons hay archivos titulados en español.
        $paraPertinencia = trim($termino . ' ' . implode(' ', $sinonimos));
        foreach (self::$escalones as $i => $filtro) {
            // En la búsqueda abierta se exige que el título tenga que ver con el platillo.
            $exigirPertinencia = ($filtro === '');
            $out = array_merge($out, self::buscarCommons($termino, $minAncho, $limite, $filtro,
                $exigirPertinencia ? $paraPertinencia : ''));
            if (count($out) >= 2) { break; }
        }
        if (!$out) {
            $out = self::buscarOpenverse($termino, $minAncho, $limite);
        }
        return $out;
    }

    /**
     * ¿El título de la imagen tiene que ver con lo que se buscó?
     * Evita que una búsqueda abierta devuelva algo sin relación.
     */
    public static function esPertinente($titulo, $termino)
    {
        $normalizar = function ($t) {
            $t = mb_strtolower($t);
            $t = strtr($t, array('á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u'));
            return preg_replace('/[^a-z0-9 ]+/', ' ', $t);
        };
        $palabras = array_filter(preg_split('/\s+/', $normalizar($termino)), function ($w) {
            return mb_strlen($w) >= 4 && !in_array($w, array('food','plate','dish','fresh','with'), true);
        });
        if (!$palabras) { return true; }          // sin palabras significativas, no se descarta
        $t = ' ' . $normalizar($titulo) . ' ';
        foreach ($palabras as $w) {
            if (strpos($t, $w) !== false) { return true; }
            // Coincidencia por raíz: "steaks" vale para "steak".
            if (mb_strlen($w) > 5 && strpos($t, mb_substr($w, 0, mb_strlen($w) - 1)) !== false) { return true; }
        }
        return false;
    }

    /** Wikimedia Commons. */
    private static function buscarCommons($termino, $minAncho, $limite, $filtro = '', $paraPertinencia = '')
    {
        $consulta = '"' . str_replace('"', '', $termino) . '" filetype:bitmap';
        if ($filtro !== '') { $consulta .= ' ' . $filtro; }
        $url = self::extremo('commons') . '?' . http_build_query(array(
            'action'      => 'query',
            'format'      => 'json',
            'formatversion' => 2,
            'generator'   => 'search',
            'gsrsearch'   => $consulta,
            'gsrnamespace'=> 6,                 // solo archivos
            'gsrlimit'    => max(6, $limite * 3),
            'prop'        => 'imageinfo',
            'iiprop'      => 'url|size|extmetadata|mime',
            'iiurlwidth'  => 1600,
        ));

        $json = self::pedir($url);
        if ($json === null) { return array(); }
        return self::leerCommons($json, $minAncho, $limite, $paraPertinencia !== '' ? $paraPertinencia : null);
    }

    /** Convierte la respuesta de Commons en candidatos. Separado para poder probarlo. */
    public static function leerCommons($json, $minAncho = 1200, $limite = 6, $terminoExigido = null)
    {
        $d = is_array($json) ? $json : json_decode((string)$json, true);
        if (!is_array($d) || empty($d['query']['pages'])) { return array(); }

        $out = array();
        foreach ($d['query']['pages'] as $pagina) {
            if (empty($pagina['imageinfo'][0])) { continue; }
            $info = $pagina['imageinfo'][0];
            $mime = isset($info['mime']) ? $info['mime'] : '';
            if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) { continue; }
            if (isset($info['width']) && (int)$info['width'] < $minAncho) { continue; }
            // Proporción utilizable en una tarjeta de menú.
            if (!empty($info['width']) && !empty($info['height'])) {
                $ratio = $info['width'] / $info['height'];
                if ($ratio < self::RATIO_MIN || $ratio > self::RATIO_MAX) { continue; }
            }
            $tituloArchivo = isset($pagina['title']) ? preg_replace('/^File:/', '', $pagina['title']) : '';
            if ($terminoExigido !== null && !self::esPertinente($tituloArchivo, $terminoExigido)) { continue; }

            $meta = isset($info['extmetadata']) ? $info['extmetadata'] : array();
            $valor = function ($clave) use ($meta) {
                return isset($meta[$clave]['value']) ? trim(strip_tags((string)$meta[$clave]['value'])) : '';
            };
            $licencia = $valor('LicenseShortName');
            // Se descartan licencias que no permiten uso comercial.
            if ($licencia !== '' && preg_match('/\bNC\b|NonCommercial|no comercial/i', $licencia)) { continue; }

            $out[] = array(
                'url'      => isset($info['thumburl']) ? $info['thumburl'] : (isset($info['url']) ? $info['url'] : ''),
                'titulo'   => $tituloArchivo,
                'autor'    => $valor('Artist') !== '' ? $valor('Artist') : 'Wikimedia Commons',
                'licencia' => $licencia !== '' ? $licencia : 'Ver Wikimedia Commons',
                'origen'   => isset($info['descriptionurl']) ? $info['descriptionurl'] : '',
                'ancho'    => isset($info['thumbwidth']) ? (int)$info['thumbwidth'] : (int)(isset($info['width']) ? $info['width'] : 0),
                'fuente'   => 'Wikimedia Commons',
            );
            if (count($out) >= $limite) { break; }
        }
        return $out;
    }

    /** Openverse: buscador de contenido con licencia libre de WordPress. */
    private static function buscarOpenverse($termino, $minAncho, $limite)
    {
        $url = self::extremo('openverse') . '?' . http_build_query(array(
            'q'             => $termino,
            'license_type'  => 'commercial',
            'page_size'     => max(5, $limite),
            'mature'        => 'false',
        ));
        $json = self::pedir($url);
        if ($json === null) { return array(); }
        return self::leerOpenverse($json, $minAncho, $limite);
    }

    /** Convierte la respuesta de Openverse en candidatos. Separado para poder probarlo. */
    public static function leerOpenverse($json, $minAncho = 1200, $limite = 6)
    {
        $d = is_array($json) ? $json : json_decode((string)$json, true);
        if (!is_array($d) || empty($d['results'])) { return array(); }
        $out = array();
        foreach ($d['results'] as $r) {
            if (empty($r['url'])) { continue; }
            if (isset($r['width']) && (int)$r['width'] < $minAncho) { continue; }
            if (!empty($r['width']) && !empty($r['height'])) {
                $ratio = $r['width'] / $r['height'];
                if ($ratio < self::RATIO_MIN || $ratio > self::RATIO_MAX) { continue; }
            }
            $out[] = array(
                'url'      => $r['url'],
                'titulo'   => isset($r['title']) ? (string)$r['title'] : '',
                'autor'    => isset($r['creator']) && $r['creator'] !== '' ? (string)$r['creator'] : 'Openverse',
                'licencia' => strtoupper((string)(isset($r['license']) ? $r['license'] : '')) . ' ' . (isset($r['license_version']) ? $r['license_version'] : ''),
                'origen'   => isset($r['foreign_landing_url']) ? (string)$r['foreign_landing_url'] : '',
                'ancho'    => (int)(isset($r['width']) ? $r['width'] : 0),
                'fuente'   => 'Openverse',
            );
            if (count($out) >= $limite) { break; }
        }
        return $out;
    }

    /** GET con cURL y, si no está, con envoltorios de flujo. */
    public static function pedir($url, $segundos = 20)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_TIMEOUT        => $segundos,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => self::AGENTE,
                CURLOPT_HTTPHEADER     => array('Accept: application/json'),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING       => '',
            ));
            $cuerpo = curl_exec($ch);
            $codigo = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error  = curl_error($ch);
            curl_close($ch);
            if ($cuerpo === false || $codigo >= 400) {
                Logger::warn('PhotoFetcher ' . $codigo . ' ' . $error . ' ' . $url);
                return null;
            }
            return $cuerpo;
        }

        $ctx = stream_context_create(array(
            'http' => array('timeout' => $segundos, 'user_agent' => self::AGENTE, 'follow_location' => 1, 'header' => "Accept: application/json\r\n"),
            'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
        ));
        $cuerpo = @file_get_contents($url, false, $ctx);
        return $cuerpo === false ? null : $cuerpo;
    }

    /**
     * Descarga una imagen a un archivo temporal y comprueba que sea una imagen.
     * @return string|null ruta temporal
     */
    public static function descargar($url, $maxBytes = 12582912)
    {
        $datos = self::pedirBinario($url, $maxBytes);
        if ($datos === null || strlen($datos) < 8192) { return null; }
        $tmp = tempnam(sys_get_temp_dir(), 'mgfoto');
        if ($tmp === false) { return null; }
        file_put_contents($tmp, $datos);
        $info = @getimagesize($tmp);
        if (!is_array($info) || !in_array($info['mime'], array('image/jpeg', 'image/png', 'image/webp'), true)) {
            @unlink($tmp);
            return null;
        }
        return $tmp;
    }

    private static function pedirBinario($url, $maxBytes)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => self::AGENTE,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_NOPROGRESS     => false,
                CURLOPT_PROGRESSFUNCTION => function ($res, $bajados) use ($maxBytes) {
                    return $bajados > $maxBytes ? 1 : 0;   // corta si se pasa del tamaño
                },
            ));
            $cuerpo = curl_exec($ch);
            $codigo = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return ($cuerpo === false || $codigo >= 400) ? null : $cuerpo;
        }
        $ctx = stream_context_create(array(
            'http' => array('timeout' => 45, 'user_agent' => self::AGENTE, 'follow_location' => 1),
            'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
        ));
        $cuerpo = @file_get_contents($url, false, $ctx, 0, $maxBytes);
        return $cuerpo === false ? null : $cuerpo;
    }

    /** ¿Hay salida a internet desde este servidor? */
    public static function hayInternet()
    {
        return self::pedir(self::extremo('commons') . '?action=query&format=json&meta=siteinfo', 8) !== null
            || self::pedir(self::extremo('openverse') . '?q=food&page_size=1', 8) !== null;
    }
}
