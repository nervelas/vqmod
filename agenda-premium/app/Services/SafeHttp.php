<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Cliente HTTP con protección contra SSRF. Todo acceso a la red del sistema pasa por aquí.
 *  - Solo http/https, sin credenciales en la URL.
 *  - Se resuelve el DNS una vez, se rechaza cualquier IP privada/loopback/link-local/reservada/de metadatos
 *    (IPv4 e IPv6, incluidas las notaciones decimal, hex y octal y las IPv4 mapeadas en IPv6)
 *    y la conexión se fija a esa IP (evita el "DNS rebinding").
 *  - Cada redirección se valida de nuevo (máx. 3). Límite de 2 MB y 10 s.
 * Excepción: Config 'allow_private_http' === true (solo pruebas).
 */
final class SafeHttp
{
    private const MAX_BYTES = 2097152;
    private const MAX_SECONDS = 10;
    private const MSG_PRIVATE = 'Por seguridad no se permiten direcciones internas o privadas.';
    private const MSG_INVALID = 'La dirección no es válida. Debe empezar con http:// o https://.';

    private const V4_BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '168.63.129.16/32',
    ];
    private const V6_BLOCKED = [
        '::/96', '100::/64', '2001::/23', '2001:db8::/32', '64:ff9b:1::/48', '3fff::/20', '5f00::/16',
        'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    /** Devuelve un mensaje de error en español, o null si la dirección es segura. */
    public static function validateUrl(string $url): ?string
    {
        return self::inspect($url)['error'];
    }

    /** @return array{ok:bool,status:int,body:string,error:?string,headers:array<string,string>,url:string} */
    public static function get(string $url, array $opts = []): array
    {
        return self::request('GET', $url, null, $opts['headers'] ?? [], $opts + ['max_redirects' => 3]);
    }

    /** @return array{ok:bool,status:int,body:string,error:?string,headers:array<string,string>,url:string} */
    public static function post(string $url, string $body, array $headers = [], array $opts = []): array
    {
        return self::request('POST', $url, $body, $headers, $opts + ['max_redirects' => 0]);
    }

    // ------------------------------------------------------------ validación

    private static function allowPrivate(): bool
    {
        return Config::get('allow_private_http') === true;
    }

    /** @return array{error:?string,scheme:string,host:string,port:int,ip:string,target:string} */
    private static function inspect(string $url): array
    {
        $fail = static fn (string $m): array => ['error' => $m, 'scheme' => '', 'host' => '', 'port' => 0, 'ip' => '', 'target' => ''];
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            return $fail(self::MSG_INVALID);
        }
        $p = parse_url($url);
        if ($p === false || !isset($p['scheme'])) {
            return $fail(self::MSG_INVALID);
        }
        $scheme = strtolower((string) $p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $fail('Solo se permiten direcciones http o https.');
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return $fail('La dirección no debe incluir usuario ni contraseña.');
        }
        $host = strtolower(rtrim(trim((string) ($p['host'] ?? ''), '[]'), '.'));
        if ($host === '' || strlen($host) > 253) {
            return $fail(self::MSG_INVALID);
        }
        $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            return $fail(self::MSG_INVALID);
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ips = [$host];
        } elseif (preg_match('/^(0x[0-9a-f]*|\d+)(\.(0x[0-9a-f]*|\d+)){0,3}$/', $host)) {
            // Notaciones numéricas (decimal, hex, octal, abreviadas): se normalizan como lo haría el sistema operativo
            $ip = self::legacyIpv4($host);
            if ($ip === null) {
                return $fail(self::MSG_INVALID);
            }
            $ips = [$ip];
            $host = $ip;
        } else {
            if (!preg_match('/^[a-z0-9]([a-z0-9_\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9_\-]*[a-z0-9])?)*$/', $host)) {
                $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
                if (!is_string($ascii) || $ascii === '' || !preg_match('/^[a-z0-9.\-_]+$/', $ascii)) {
                    return $fail(self::MSG_INVALID);
                }
                $host = $ascii;
            }
            if (!self::allowPrivate() && ($host === 'localhost' || str_ends_with($host, '.localhost'))) {
                return $fail(self::MSG_PRIVATE);
            }
            $ips = self::resolveHost($host);
            if ($ips === []) {
                return $fail('No encontramos ese sitio. Revisa que la dirección esté bien escrita.');
            }
        }
        if (!self::allowPrivate()) {
            foreach ($ips as $ip) {
                if (!self::isPublicIp($ip)) {
                    return $fail(self::MSG_PRIVATE);
                }
            }
        }
        // Prefiere IPv4 si hay de ambos tipos
        usort($ips, static fn (string $a, string $b): int => (strpos($a, ':') !== false) <=> (strpos($b, ':') !== false));
        $target = ($host !== '' && strpos($host, ':') !== false ? '[' . $host . ']' : $host) . ($port !== ($scheme === 'https' ? 443 : 80) ? ':' . $port : '');
        return ['error' => null, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $ips[0], 'target' => $target];
    }

    /** IPv4 en notación decimal/hex/octal/abreviada (estilo inet_aton) -> a.b.c.d, o null. */
    private static function legacyIpv4(string $host): ?string
    {
        $parts = explode('.', $host);
        $nums = [];
        foreach ($parts as $part) {
            if (preg_match('/^0x([0-9a-f]*)$/', $part, $m)) {
                $n = $m[1] === '' ? 0 : hexdec($m[1]);
            } elseif (preg_match('/^0\d+$/', $part)) {
                if (!preg_match('/^0[0-7]+$/', $part)) {
                    return null;
                }
                $n = octdec($part);
            } elseif (ctype_digit($part)) {
                $n = (int) $part;
            } else {
                return null;
            }
            if (!is_int($n) && !is_float($n)) {
                return null;
            }
            $nums[] = $n;
        }
        $last = array_pop($nums);
        foreach ($nums as $n) {
            if ($n > 255) {
                return null;
            }
        }
        if ($last >= 256 ** (4 - count($nums))) {
            return null;
        }
        $val = $last;
        foreach ($nums as $i => $n) {
            $val += $n * (256 ** (3 - $i));
        }
        return long2ip((int) $val) ?: null;
    }

    /** @return string[] */
    private static function resolveHost(string $host): array
    {
        $ips = [];
        if (function_exists('dns_get_record')) {
            foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $r) {
                $ip = $r['ip'] ?? $r['ipv6'] ?? null;
                if (is_string($ip)) {
                    $ips[] = $ip;
                }
            }
        }
        foreach ((array) @gethostbynamel($host) as $ip) {
            $ips[] = (string) $ip;
        }
        return array_values(array_unique($ips));
    }

    public static function isPublicIp(string $ip): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        if (strlen($bin) === 4) {
            return !self::inAny($bin, self::V4_BLOCKED);
        }
        $embedded = null;
        if (strncmp($bin, str_repeat("\0", 10) . "\xff\xff", 12) === 0) {
            $embedded = substr($bin, 12);                 // ::ffff:a.b.c.d
        } elseif (strncmp($bin, "\x00\x64\xff\x9b" . str_repeat("\0", 8), 12) === 0) {
            $embedded = substr($bin, 12);                 // 64:ff9b::/96 (NAT64)
        } elseif (strncmp($bin, "\x20\x02", 2) === 0) {
            $embedded = substr($bin, 2, 4);               // 2002::/16 (6to4)
        }
        if ($embedded !== null) {
            return self::isPublicIp((string) inet_ntop($embedded));
        }
        return !self::inAny($bin, self::V6_BLOCKED) && $bin !== str_repeat("\0", 15) . "\1" && $bin !== str_repeat("\0", 16);
    }

    private static function inAny(string $bin, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $nb = (string) inet_pton($net);
            if (strlen($nb) !== strlen($bin)) {
                continue;
            }
            $bits = (int) $bits;
            $bytes = intdiv($bits, 8);
            if ($bytes > 0 && strncmp($bin, $nb, $bytes) !== 0) {
                continue;
            }
            $rem = $bits % 8;
            if ($rem === 0 || ((ord($bin[$bytes]) ^ ord($nb[$bytes])) & (0xFF << (8 - $rem)) & 0xFF) === 0) {
                return true;
            }
        }
        return false;
    }

    // --------------------------------------------------------------- peticiones

    private static function request(string $method, string $url, ?string $body, $headers, array $opts): array
    {
        $timeout = max(1, min(self::MAX_SECONDS, (int) ($opts['timeout'] ?? self::MAX_SECONDS)));
        $maxBytes = max(1024, min(self::MAX_BYTES, (int) ($opts['max_bytes'] ?? self::MAX_BYTES)));
        $maxRedirects = max(0, min(3, (int) ($opts['max_redirects'] ?? 0)));
        $list = [];
        foreach ((array) $headers as $k => $v) {
            $h = is_int($k) ? (string) $v : $k . ': ' . $v;
            $list[] = str_replace(["\r", "\n"], '', $h);
        }
        $deadline = microtime(true) + $timeout;
        $redirects = 0;
        while (true) {
            $t = self::inspect($url);
            if ($t['error'] !== null) {
                return self::result(0, '', $t['error'], [], $url);
            }
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0.2) {
                return self::result(0, '', 'El sitio tardó demasiado en responder.', [], $url);
            }
            $r = (function_exists('curl_init') && Config::get('safehttp_no_curl') !== true)
                ? self::viaCurl($method, $url, $body, $list, $t, $remaining, $maxBytes)
                : self::viaStreams($method, $url, $body, $list, $t, $remaining, $maxBytes);
            if ($r['error'] !== null || !in_array($r['status'], [301, 302, 303, 307, 308], true) || $maxRedirects === 0) {
                return $r;
            }
            $loc = $r['headers']['location'] ?? '';
            if ($loc === '') {
                return $r;
            }
            if (++$redirects > $maxRedirects) {
                return self::result($r['status'], '', 'El sitio redirige demasiadas veces.', $r['headers'], $url);
            }
            $url = self::joinUrl($url, $loc);
            if ($method === 'POST' && in_array($r['status'], [301, 302, 303], true)) {
                $method = 'GET';
                $body = null;
            }
        }
    }

    private static function result(int $status, string $body, ?string $error, array $headers, string $url): array
    {
        $ok = $error === null && $status >= 200 && $status < 300;
        if ($error === null && !$ok && $status !== 304) {
            $error = match (true) {
                $status === 404 => 'La dirección no existe en ese sitio (error 404).',
                $status === 401 || $status === 403 => 'El sitio no permite el acceso (error ' . $status . ').',
                $status >= 500 => 'El sitio tuvo un problema al responder (error ' . $status . ').',
                default => 'El sitio respondió con un código inesperado (' . $status . ').',
            };
        }
        return ['ok' => $ok, 'status' => $status, 'body' => $body, 'error' => $error, 'headers' => $headers, 'url' => $url];
    }

    private static function joinUrl(string $base, string $loc): string
    {
        if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $loc)) {
            return $loc;
        }
        $b = parse_url($base) ?: [];
        $origin = ($b['scheme'] ?? 'http') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (strncmp($loc, '//', 2) === 0) {
            return ($b['scheme'] ?? 'http') . ':' . $loc;
        }
        if ($loc !== '' && $loc[0] === '/') {
            return $origin . $loc;
        }
        $dir = substr((string) ($b['path'] ?? '/'), 0, (int) strrpos((string) ($b['path'] ?? '/'), '/') + 1);
        return $origin . $dir . $loc;
    }

    private static function curlError(int $errno, bool $tooLarge): string
    {
        if ($tooLarge) {
            return 'El archivo es demasiado grande (el máximo es 2 MB).';
        }
        return match (true) {
            $errno === 28 => 'El sitio tardó demasiado en responder.',
            $errno === 6 => 'No encontramos ese sitio. Revisa que la dirección esté bien escrita.',
            $errno === 7 => 'No pudimos conectarnos con ese sitio. Revisa la dirección y el puerto.',
            in_array($errno, [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 83, 90, 91], true) => 'No se pudo establecer una conexión segura (HTTPS) con el sitio. Revisa su certificado.',
            default => 'No se pudo descargar la dirección indicada.',
        };
    }

    private static function viaCurl(string $method, string $url, ?string $body, array $headers, array $t, float $remaining, int $maxBytes): array
    {
        $ch = curl_init($url);
        $buf = '';
        $tooLarge = false;
        $resp = [];
        $opts = [
            CURLOPT_RESOLVE => [$t['host'] . ':' . $t['port'] . ':' . $t['ip']],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_TIMEOUT_MS => (int) ($remaining * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) (min(5, $remaining) * 1000),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'AgendaPremium/1.0',
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$resp, &$tooLarge, $maxBytes): int {
                $len = strlen($line);
                if (strncmp($line, 'HTTP/', 5) === 0) {
                    $resp = [];
                } elseif (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $resp[strtolower(trim($k))] = trim($v);
                    if (strtolower(trim($k)) === 'content-length' && (int) trim($v) > $maxBytes) {
                        $tooLarge = true;
                        return 0;
                    }
                }
                return $len;
            },
            CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use (&$buf, &$tooLarge, $maxBytes): int {
                if (strlen($buf) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $buf .= $chunk;
                return strlen($chunk);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS')) {
            $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = (string) $body;
        }
        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($errno !== 0 || $tooLarge) {
            return self::result($status, '', self::curlError($errno, $tooLarge), $resp, $url);
        }
        return self::result($status, $buf, null, $resp, $url);
    }

    /** Respaldo sin cURL: HTTP/1.1 mínimo sobre sockets, conectando a la IP ya validada. */
    private static function viaStreams(string $method, string $url, ?string $body, array $headers, array $t, float $remaining, int $maxBytes): array
    {
        $deadline = microtime(true) + $remaining;
        $https = $t['scheme'] === 'https';
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $t['host'], 'SNI_enabled' => true, 'SNI_server_name' => $t['host'],
        ]]);
        $ip = strpos($t['ip'], ':') !== false ? '[' . $t['ip'] . ']' : $t['ip'];
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(($https ? 'ssl://' : 'tcp://') . $ip . ':' . $t['port'], $errno, $errstr, min(5.0, $remaining), STREAM_CLIENT_CONNECT, $ctx);
        if (!is_resource($fp)) {
            $msg = stripos($errstr, 'ssl') !== false || stripos($errstr, 'certificate') !== false
                ? 'No se pudo establecer una conexión segura (HTTPS) con el sitio. Revisa su certificado.'
                : 'No pudimos conectarnos con ese sitio. Revisa la dirección y el puerto.';
            return self::result(0, '', $msg, [], $url);
        }
        $timedOut = static fn (): array => self::result(0, '', 'El sitio tardó demasiado en responder.', [], $url);
        $parts = parse_url($url) ?: [];
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $req = $method . ' ' . ($path === '' ? '/' : $path) . " HTTP/1.1\r\nHost: " . $t['target'] . "\r\nUser-Agent: AgendaPremium/1.0\r\nAccept-Encoding: identity\r\nConnection: close\r\n";
        foreach ($headers as $h) {
            $req .= $h . "\r\n";
        }
        if ($body !== null) {
            $req .= 'Content-Length: ' . strlen($body) . "\r\n";
        }
        $req .= "\r\n" . ($body ?? '');
        stream_set_timeout($fp, 1);
        $sent = 0;
        while ($sent < strlen($req)) {
            $n = @fwrite($fp, substr($req, $sent));
            if ($n === false || $n === 0) {
                if (microtime(true) > $deadline) {
                    fclose($fp);
                    return $timedOut();
                }
                if ($n === false) {
                    fclose($fp);
                    return self::result(0, '', 'No se pudo descargar la dirección indicada.', [], $url);
                }
                continue;
            }
            $sent += $n;
        }
        $raw = '';
        $tooLarge = false;
        while (!feof($fp)) {
            $chunk = @fread($fp, 8192);
            if ($chunk !== false && $chunk !== '') {
                $raw .= $chunk;
                if (strlen($raw) > $maxBytes + 16384) {
                    $tooLarge = true;
                    break;
                }
            }
            if (microtime(true) > $deadline) {
                fclose($fp);
                return $timedOut();
            }
        }
        fclose($fp);
        $sep = strpos($raw, "\r\n\r\n");
        if ($sep === false || !preg_match('~^HTTP/\d(?:\.\d)? (\d{3})~', $raw, $m)) {
            return self::result(0, '', 'El sitio respondió algo que no se entiende.', [], $url);
        }
        $resp = [];
        foreach (array_slice(explode("\r\n", substr($raw, 0, $sep)), 1) as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $resp[strtolower(trim($k))] = trim($v);
            }
        }
        $data = substr($raw, $sep + 4);
        if (stripos($resp['transfer-encoding'] ?? '', 'chunked') !== false) {
            $decoded = '';
            while ($data !== '') {
                $eol = strpos($data, "\r\n");
                if ($eol === false) {
                    break;
                }
                $size = (int) hexdec(trim(explode(';', substr($data, 0, $eol))[0]));
                if ($size === 0) {
                    break;
                }
                $decoded .= substr($data, $eol + 2, $size);
                $data = (string) substr($data, $eol + 2 + $size + 2);
            }
            $data = $decoded;
        } elseif (isset($resp['content-length']) && ctype_digit($resp['content-length'])) {
            if ((int) $resp['content-length'] > $maxBytes) {
                $tooLarge = true;
            }
            $data = substr($data, 0, (int) $resp['content-length']);
        }
        if ($tooLarge || strlen($data) > $maxBytes) {
            return self::result((int) $m[1], '', self::curlError(0, true), $resp, $url);
        }
        return self::result((int) $m[1], $data, null, $resp, $url);
    }
}
