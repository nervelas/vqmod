<?php
/**
 * CorreoRadar - Descarga de páginas con cURL.
 *
 * Caracteristicas:
 *   - Sigue redirecciones MANUALMENTE y revalida cada salto contra la protección
 *     SSRF (así nadie puede redirigir el escaner hacia una IP interna).
 *   - User-agent de navegador real, HTTP/2 cuando esta disponible y gzip/brotli.
 *   - Límite de bytes descargados para no agotar la memoria del hosting.
 *   - Conversión de la respuesta a UTF-8 leyendo el charset de la cabecera,
 *     de la etiqueta <meta> o detectandolo automáticamente.
 */
declare(strict_types=1);

final class Http
{
    /** Número máximo de redirecciones que se siguen. */
    public const MAX_SALTOS = 6;

    /**
     * Descarga una URL.
     *
     * @param array{timeout?:int,max_bytes?:int,agente?:string,permitir_privadas?:bool,solo_cabeceras?:bool,referer?:string} $opciones
     * @return array{ok:bool,código:int,url_final:string,tipo:string,cuerpo:string,bytes:int,ms:int,error:string,saltos:int}
     */
    public static function obtener(string $url, array $opciones = []): array
    {
        $timeout   = (int) ($opciones['timeout']   ?? Ajustes::entero('timeout', 20, 3, 180));
        $maxBytes  = (int) ($opciones['max_bytes'] ?? Ajustes::entero('max_bytes', 3000000, 50000, 20000000));
        $agente    = (string) ($opciones['agente'] ?? Ajustes::obtener('user_agent'));
        $privadas  = (bool) ($opciones['permitir_privadas'] ?? Ajustes::activo('permitir_privadas'));
        $inicio    = microtime(true);

        $resultado = [
            'ok' => false, 'codigo' => 0, 'url_final' => $url, 'tipo' => '',
            'cuerpo' => '', 'bytes' => 0, 'ms' => 0, 'error' => '', 'saltos' => 0,
        ];

        if (!function_exists('curl_init')) {
            $resultado['error'] = 'La extension cURL de PHP no esta disponible en este servidor.';
            return $resultado;
        }

        $actual = $url;
        for ($salto = 0; $salto <= self::MAX_SALTOS; $salto++) {
            // 1) Cada salto se válida de nuevo: protege frente a redirecciones maliciosas.
            $val = Seguridad::validarUrl($actual, $privadas);
            if (!$val['ok']) {
                $resultado['error']  = $val['error'] ?? 'Dirección no permitida.';
                $resultado['saltos'] = $salto;
                $resultado['ms']     = (int) round((microtime(true) - $inicio) * 1000);
                return $resultado;
            }
            $actual = $val['url'];

            $respuesta = self::peticion($actual, $timeout, $maxBytes, $agente, (string) ($opciones['referer'] ?? ''));
            $resultado['codigo']    = $respuesta['codigo'];
            $resultado['url_final'] = $actual;
            $resultado['saltos']    = $salto;

            if ($respuesta['error'] !== '') {
                $resultado['error'] = $respuesta['error'];
                $resultado['ms']    = (int) round((microtime(true) - $inicio) * 1000);
                return $resultado;
            }

            // 2) Redirección: calculamos el destino absoluto y repetimos el bucle.
            if (in_array($respuesta['codigo'], [301, 302, 303, 307, 308], true) && $respuesta['ubicacion'] !== '') {
                $destino = self::urlAbsoluta($respuesta['ubicacion'], $actual);
                if ($destino === '' || $destino === $actual) { break; }
                $actual = $destino;
                continue;
            }

            // 3) Respuesta final.
            $cuerpo = self::aUtf8($respuesta['cuerpo'], $respuesta['tipo']);
            $resultado['ok']     = $respuesta['codigo'] >= 200 && $respuesta['codigo'] < 300;
            $resultado['tipo']   = $respuesta['tipo'];
            $resultado['cuerpo'] = $cuerpo;
            $resultado['bytes']  = strlen($respuesta['cuerpo']);
            $resultado['ms']     = (int) round((microtime(true) - $inicio) * 1000);
            if (!$resultado['ok'] && $resultado['error'] === '') {
                $resultado['error'] = 'El servidor respondio con el código HTTP ' . $respuesta['codigo'] . '.';
            }
            return $resultado;
        }

        $resultado['error'] = 'Demasiadas redirecciones.';
        $resultado['ms']    = (int) round((microtime(true) - $inicio) * 1000);
        return $resultado;
    }

    /**
     * Petición cURL individual (sin seguir redirecciones).
     *
     * @return array{código:int,cuerpo:string,tipo:string,ubicación:string,error:string}
     */
    private static function peticion(string $url, int $timeout, int $maxBytes, string $agente, string $referer): array
    {
        $salida = ['codigo' => 0, 'cuerpo' => '', 'tipo' => '', 'ubicacion' => '', 'error' => ''];

        $cabeceras = '';
        $cuerpo    = '';
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,          // las redirecciones se controlan a mano
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, max(5, (int) ceil($timeout / 2))),
            CURLOPT_USERAGENT      => $agente !== '' ? $agente : 'Mozilla/5.0 (compatible; CorreoRadar/' . CR_VERSION . ')',
            CURLOPT_ENCODING       => '',             // acepta gzip, deflate y brotli
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_HEADERFUNCTION => static function ($ch, string $linea) use (&$cabeceras) {
                $cabeceras .= $linea;
                return strlen($linea);
            },
            CURLOPT_WRITEFUNCTION  => static function ($ch, string $trozo) use (&$cuerpo, $maxBytes) {
                $cuerpo .= $trozo;
                // Devolver menos bytes de los recibidos aborta la descarga: así limitamos el tamaño.
                return strlen($cuerpo) > $maxBytes ? 0 : strlen($trozo);
            },
        ]);

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
        } elseif (defined('CURLPROTO_HTTP')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        if (defined('CURL_HTTP_VERSION_2TLS')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        }
        if ($referer !== '') { curl_setopt($ch, CURLOPT_REFERER, $referer); }
        if (defined('CR_PROXY') && CR_PROXY !== '') { curl_setopt($ch, CURLOPT_PROXY, CR_PROXY); }

        curl_exec($ch);
        $errno = curl_errno($ch);
        $salida['codigo'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $salida['tipo']   = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');

        // Error 23 = la descarga se corto a proposito al llegar al límite de bytes.
        if ($errno !== 0 && $errno !== CURLE_WRITE_ERROR) {
            $mensaje = curl_error($ch);
            // Muchos hosting compartidos tienen el paquete de certificados desactualizado:
            // si el ajuste lo permite, se reintenta sin verificar el certificado.
            if (in_array($errno, [CURLE_SSL_CACERT, CURLE_PEER_FAILED_VERIFICATION, CURLE_SSL_CACERT_BADFILE], true)
                && !Ajustes::activo('ssl_estricto')) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $cuerpo = ''; $cabeceras = '';
                curl_exec($ch);
                $errno2 = curl_errno($ch);
                $salida['codigo'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $salida['tipo']   = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
                if ($errno2 !== 0 && $errno2 !== CURLE_WRITE_ERROR) {
                    $salida['error'] = self::mensajeCurl($errno2, curl_error($ch));
                    curl_close($ch);
                    return $salida;
                }
            } else {
                $salida['error'] = self::mensajeCurl($errno, $mensaje);
                curl_close($ch);
                return $salida;
            }
        }
        curl_close($ch);

        $salida['cuerpo'] = $cuerpo;
        if (preg_match_all('~^location:\s*(.+)$~mi', $cabeceras, $m)) {
            $salida['ubicacion'] = trim(end($m[1]));
        }
        // El charset de la cabecera manda sobre el detectado en el HTML.
        if (preg_match('~^content-type:\s*(.+)$~mi', $cabeceras, $m2)) {
            $salida['tipo'] = trim((string) $m2[1]);
        }
        return $salida;
    }

    /** Traduce los errores de cURL a mensajes claros en español. */
    private static function mensajeCurl(int $errno, string $bruto): string
    {
        return match ($errno) {
            CURLE_OPERATION_TIMEDOUT   => 'La página tardo demasiado en responder (tiempo de espera agotado).',
            CURLE_COULDNT_RESOLVE_HOST => 'No se pudo resolver el nombre del dominio.',
            CURLE_COULDNT_CONNECT      => 'No se pudo conectar con el servidor.',
            CURLE_TOO_MANY_REDIRECTS   => 'Demasiadas redirecciones.',
            CURLE_SSL_CACERT,
            CURLE_PEER_FAILED_VERIFICATION => 'El certificado HTTPS del sitio no se pudo verificar.',
            default => 'No se pudo descargar la página (' . ($bruto !== '' ? $bruto : 'error ' . $errno) . ').',
        };
    }

    /** Convierte una URL relativa en absoluta a partir de la página base. */
    public static function urlAbsoluta(string $enlace, string $base): string
    {
        $enlace = trim(html_entity_decode($enlace, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($enlace === '' || str_starts_with($enlace, '#')) { return ''; }
        if (preg_match('~^(javascript|mailto|tel|data|ftp|sms|whatsapp|callto|skype):~i', $enlace)) { return ''; }

        // Quita el fragmento (#sección): no cambia el contenido descargado.
        $enlace = (string) preg_replace('~#.*$~', '', $enlace);
        if ($enlace === '') { return ''; }

        if (preg_match('~^https?://~i', $enlace)) { return $enlace; }
        if (str_starts_with($enlace, '//')) {
            $esq = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $esq . ':' . $enlace;
        }

        $p = parse_url($base);
        if (!$p || empty($p['host'])) { return ''; }
        $raiz = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');

        if (str_starts_with($enlace, '/')) { return $raiz . $enlace; }

        // Ruta relativa: se resuelve contra el directorio de la página base.
        $dir = isset($p['path']) ? preg_replace('~/[^/]*$~', '/', $p['path']) : '/';
        $ruta = $dir . $enlace;
        // Normaliza ../ y ./
        $segmentos = [];
        foreach (explode('/', $ruta) as $seg) {
            if ($seg === '' || $seg === '.') { continue; }
            if ($seg === '..') { array_pop($segmentos); continue; }
            $segmentos[] = $seg;
        }
        return $raiz . '/' . implode('/', $segmentos);
    }

    /** Convierte el contenido descargado a UTF-8. */
    public static function aUtf8(string $contenido, string $contentType = ''): string
    {
        if ($contenido === '') { return ''; }
        $origen = '';

        if (preg_match('~charset\s*=\s*["\']?([a-z0-9_\-]+)~i', $contentType, $m)) {
            $origen = strtoupper($m[1]);
        }
        if ($origen === '' && preg_match('~<meta[^>]+charset\s*=\s*["\']?([a-z0-9_\-]+)~i', substr($contenido, 0, 4096), $m)) {
            $origen = strtoupper($m[1]);
        }
        if ($origen === '' && preg_match('~<\?xml[^>]+encoding\s*=\s*["\']([a-z0-9_\-]+)~i', substr($contenido, 0, 512), $m)) {
            $origen = strtoupper($m[1]);
        }

        $origen = match ($origen) {
            'UTF8', 'UTF-8', '' => 'UTF-8',
            'LATIN1', 'ISO-8859-1', 'ISO8859-1' => 'ISO-8859-1',
            default => $origen,
        };

        if ($origen !== 'UTF-8') {
            $convertido = @mb_convert_encoding($contenido, 'UTF-8', $origen);
            if (is_string($convertido) && $convertido !== '') { $contenido = $convertido; }
        } elseif (function_exists('mb_check_encoding') && !mb_check_encoding($contenido, 'UTF-8')) {
            // Declara UTF-8 pero no lo es: intentamos recuperarlo desde latin1/windows-1252.
            $convertido = @mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
            if (is_string($convertido) && $convertido !== '') { $contenido = $convertido; }
        }

        // Quita el BOM si lo hubiera.
        return preg_replace('~^\xEF\xBB\xBF~', '', $contenido) ?? $contenido;
    }
}
