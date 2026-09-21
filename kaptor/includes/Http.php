<?php
/**
 * Kaptor - Descarga de páginas con cURL.
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
     * Códigos de cURL que indican un problema con el certificado.
     *
     * Se escriben con su número y no con la constante de PHP porque algunas
     * de esas constantes no existen en todas las versiones ni en todas las
     * compilaciones: usarlas directamente tumbaba la petición con un error
     * fatal en cuanto un sitio tenía el certificado mal.
     *   51 = el certificado del servidor no se pudo verificar
     *   60 = no se pudo verificar con el paquete de certificados local
     *   77 = el archivo de certificados del servidor no se puede leer
     */
    private const ERRORES_SSL = [51, 60, 77];

    /**
     * Descarga una URL.
     *
     * @param array{timeout?:int,max_bytes?:int,agente?:string,permitir_privadas?:bool,solo_cabeceras?:bool,referer?:string,cabeceras?:string[],datos?:array|string} $opciones
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

        // Modo detalle: lo pide el auditor, que necesita medir el servidor y no
        // solo leer la página. Se queda apagado por defecto porque pedir el
        // certificado a cURL cuesta tiempo y al extractor no le sirve de nada.
        $detalle = !empty($opciones['detalle']);
        if ($detalle) {
            $resultado += [
                'cabeceras' => [],   // nombre en minúsculas => valor
                'tiempos'   => [],   // dns, conexion, tls, ttfb, total (ms)
                'cert'      => [],   // emisor, vence, dias
                'ip'        => '',
                'version'   => '',   // 1.1, 2, 3
                'solo_cabeceras' => !empty($opciones['solo_cabeceras']),
            ];
        }

        if (!function_exists('curl_init')) {
            $resultado['error'] = 'La extension cURL de PHP no esta disponible en este servidor.';
            return $resultado;
        }

        // Cabeceras de la petición. Quien llama puede sustituirlas: los
        // buscadores, por ejemplo, necesitan las suyas para no devolver un muro.
        $cabecerasEnvio = $opciones['cabeceras'] ?? [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
        ];

        // Envío por POST, para los buscadores que solo responden así.
        $datosPost = $opciones['datos'] ?? null;
        if (is_array($datosPost)) { $datosPost = http_build_query($datosPost); }

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

            $respuesta = self::peticion($actual, $timeout, $maxBytes, $agente, (string) ($opciones['referer'] ?? ''), $cabecerasEnvio, $datosPost, $detalle, !empty($opciones['solo_cabeceras']));
            $resultado['codigo']    = $respuesta['codigo'];
            $resultado['url_final'] = $actual;
            $resultado['saltos']    = $salto;

            // Lo medido se guarda en CADA salto: si el sitio acaba redirigiendo,
            // lo que vale es el certificado y el tiempo del destino final, que
            // es el último que sobrescribe estos campos.
            if ($detalle) {
                $resultado['cabeceras'] = $respuesta['cabeceras'];
                $resultado['tiempos']   = $respuesta['tiempos'];
                $resultado['cert']      = $respuesta['cert'];
                $resultado['ip']        = $respuesta['ip'];
                $resultado['version']   = $respuesta['version'];
            }

            if ($respuesta['error'] !== '') {
                $resultado['error'] = $respuesta['error'];
                $resultado['ms']    = (int) round((microtime(true) - $inicio) * 1000);
                return $resultado;
            }

            // 2) Redirección: calculamos el destino absoluto y repetimos el bucle.
            if (in_array($respuesta['codigo'], [301, 302, 303, 307, 308], true) && $respuesta['ubicacion'] !== '') {
                $destino = self::urlAbsoluta($respuesta['ubicacion'], $actual);

                // Quien solo quiere saber SI redirige (y adónde) se baja aquí.
                if (!empty($opciones['sin_redirecciones'])) {
                    $resultado['ok']       = true;
                    $resultado['redirige'] = $destino;
                    $resultado['ms']       = (int) round((microtime(true) - $inicio) * 1000);
                    return $resultado;
                }

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
     * @param string[] $cabecerasEnvio Cabeceras de la petición; las fija quien
     *                                 llama, porque los buscadores necesitan
     *                                 las suyas para no devolver un muro.
     * @return array{código:int,cuerpo:string,tipo:string,ubicación:string,error:string}
     */
    private static function peticion(string $url, int $timeout, int $maxBytes, string $agente, string $referer, array $cabecerasEnvio = [], ?string $datosPost = null, bool $detalle = false, bool $soloCabeceras = false): array
    {
        $salida = [
            'codigo' => 0, 'cuerpo' => '', 'tipo' => '', 'ubicacion' => '', 'error' => '',
            'cabeceras' => [], 'tiempos' => [], 'cert' => [], 'ip' => '', 'version' => '',
        ];

        $cabeceras = '';
        $cuerpo    = '';
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,          // las redirecciones se controlan a mano
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, max(5, (int) ceil($timeout / 2))),
            CURLOPT_USERAGENT      => $agente !== '' ? $agente : 'Mozilla/5.0 (compatible; Kaptor/' . CR_VERSION . ')',
            CURLOPT_ENCODING       => '',             // acepta gzip, deflate y brotli
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $cabecerasEnvio ?: ['Accept: */*'],
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

        // Petición HEAD: para pesar una imagen o comprobar si un enlace vive no
        // hace falta descargar el cuerpo entero.
        if ($soloCabeceras) {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        // Datos del certificado. Va protegido: no todas las compilaciones de
        // cURL traen CURLOPT_CERTINFO y pedirlo a ciegas tumba la petición.
        if ($detalle && defined('CURLOPT_CERTINFO')) {
            curl_setopt($ch, CURLOPT_CERTINFO, true);
        }

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
        } elseif (defined('CURLPROTO_HTTP')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        if (defined('CURL_HTTP_VERSION_2TLS')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        }
        if ($datosPost !== null && $datosPost !== '') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $datosPost);
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
            if (in_array($errno, self::ERRORES_SSL, true) && !Ajustes::activo('ssl_estricto')) {
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
        if ($detalle) {
            $salida['tiempos'] = self::tiempos($ch);
            $salida['ip']      = (string) (curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: '');
            $salida['version'] = self::versionHttp($ch);
            $salida['cert']    = self::certificado($ch);
        }

        curl_close($ch);

        $salida['cuerpo'] = $cuerpo;
        if ($detalle) { $salida['cabeceras'] = self::partirCabeceras($cabeceras); }
        if (preg_match_all('~^location:\s*(.+)$~mi', $cabeceras, $m)) {
            $salida['ubicacion'] = trim(end($m[1]));
        }
        // El charset de la cabecera manda sobre el detectado en el HTML.
        if (preg_match('~^content-type:\s*(.+)$~mi', $cabeceras, $m2)) {
            $salida['tipo'] = trim((string) $m2[1]);
        }
        return $salida;
    }

    /**
     * Reparte las cabeceras crudas en un mapa nombre => valor.
     *
     * Solo se queda con las del ÚLTIMO bloque: si hubo redirecciones, cURL las
     * va concatenando y las de los saltos intermedios no describen la página
     * que de verdad se acabó sirviendo.
     *
     * @return array<string,string>
     */
    private static function partirCabeceras(string $crudas): array
    {
        $bloques = preg_split('~\r?\n\r?\n~', trim($crudas)) ?: [];
        $ultimo  = (string) end($bloques);
        $mapa    = [];

        foreach (preg_split('~\r?\n~', $ultimo) ?: [] as $linea) {
            $pos = strpos($linea, ':');
            if ($pos === false) { continue; }
            $nombre = strtolower(trim(substr($linea, 0, $pos)));
            $valor  = trim(substr($linea, $pos + 1));
            if ($nombre === '') { continue; }
            // Set-Cookie y compañía pueden repetirse: se unen con coma.
            $mapa[$nombre] = isset($mapa[$nombre]) ? $mapa[$nombre] . ', ' . $valor : $valor;
        }
        return $mapa;
    }

    /**
     * Tiempos de la conexión en milisegundos.
     *
     * ttfb (tiempo hasta el primer byte) es el que mide de verdad lo lento que
     * es el servidor: lo demás ya depende del tamaño de la página.
     *
     * @param CurlHandle|resource $ch
     * @return array<string,int>
     */
    private static function tiempos($ch): array
    {
        $ms = static fn(int $opcion): int
            => (int) round(((float) curl_getinfo($ch, $opcion)) * 1000);

        $dns   = $ms(CURLINFO_NAMELOOKUP_TIME);
        $con   = $ms(CURLINFO_CONNECT_TIME);
        $tls   = $ms(CURLINFO_APPCONNECT_TIME);
        $ttfb  = $ms(CURLINFO_STARTTRANSFER_TIME);
        $total = $ms(CURLINFO_TOTAL_TIME);

        return [
            'dns'      => $dns,
            'conexion' => max(0, $con - $dns),
            // En http:// no hay saludo TLS y APPCONNECT viene en cero.
            'tls'      => $tls > 0 ? max(0, $tls - $con) : 0,
            'ttfb'     => $ttfb,
            'total'    => $total,
            'descarga' => max(0, $total - $ttfb),
        ];
    }

    /** Versión del protocolo negociada ('1.1', '2', '3' o cadena vacía). */
    private static function versionHttp($ch): string
    {
        if (!defined('CURLINFO_HTTP_VERSION')) { return ''; }
        // Son los valores de CURL_HTTP_VERSION_*: 1_0=1, 1_1=2, 2_0=3 y 3=30.
        return match ((int) curl_getinfo($ch, CURLINFO_HTTP_VERSION)) {
            1  => '1.0',
            2  => '1.1',
            3  => '2',
            30 => '3',
            default => '',
        };
    }

    /**
     * Emisor y vencimiento del certificado HTTPS.
     *
     * Interesa sobre todo `dias`: un certificado que vence la semana que viene
     * tumba el sitio entero y casi nadie se entera hasta que pasa.
     *
     * @return array{emisor:string,vence:string,dias:int,valido:bool}|array{}
     */
    private static function certificado($ch): array
    {
        if (!defined('CURLINFO_CERTINFO')) { return []; }

        $info = curl_getinfo($ch, CURLINFO_CERTINFO);
        if (!is_array($info) || !isset($info[0]) || !is_array($info[0])) { return []; }

        // Las claves llegan con mayúsculas distintas según la versión de cURL.
        $campos = [];
        foreach ($info[0] as $clave => $valor) {
            if (is_string($clave)) { $campos[strtolower($clave)] = (string) $valor; }
        }

        $hasta = $campos['expire date'] ?? ($campos['expire_date'] ?? '');
        if ($hasta === '') { return []; }

        $marca = strtotime($hasta);
        if ($marca === false) { return []; }

        $dias = (int) floor(($marca - time()) / 86400);

        // El emisor viene como "C = US, O = Let's Encrypt, CN = R11".
        $emisor = $campos['issuer'] ?? '';
        if (preg_match('~(?:^|,)\s*O\s*=\s*([^,]+)~i', $emisor, $m)) {
            $emisor = trim($m[1]);
        }

        return [
            'emisor' => $emisor,
            'vence'  => date('Y-m-d', $marca),
            'dias'   => $dias,
            'valido' => $dias >= 0,
        ];
    }

    /** Traduce los errores de cURL a mensajes claros en español. */
    private static function mensajeCurl(int $errno, string $bruto): string
    {
        // Por número, no por constante: hay constantes de cURL que no existen
        // en todas las versiones de PHP y usarlas provocaba un error fatal.
        return match ($errno) {
            28 => 'La página tardó demasiado en responder (tiempo de espera agotado).',
            6  => 'No se pudo resolver el nombre del dominio.',
            7  => 'No se pudo conectar con el servidor.',
            47 => 'Demasiadas redirecciones.',
            51, 60, 77 => 'El certificado HTTPS del sitio no se pudo verificar.',
            35 => 'Fallo al establecer la conexión segura (HTTPS).',
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
