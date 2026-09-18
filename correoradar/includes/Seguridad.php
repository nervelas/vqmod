<?php
/**
 * CorreoRadar - Capa de seguridad.
 *
 * Reune en un solo sitio:
 *   - Cabeceras de seguridad HTTP.
 *   - Tokens CSRF para todos los formularios.
 *   - Protección SSRF (bloqueo de IPs privadas, localhost y puertos internos).
 *   - Límite de peticiones por IP.
 *   - Control de intentos de acceso fallidos.
 */
declare(strict_types=1);

final class Seguridad
{
    /** Puertos permitidos al descargar una web externa. */
    public const PUERTOS_PERMITIDOS = [80, 443, 8080, 8443, 8000, 3000];

    /** Nombres de host que nunca se descargan. */
    private const HOSTS_BLOQUEADOS = [
        'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback',
        'metadata.google.internal', 'metadata', 'instance-data',
    ];

    // ---------------------------------------------------------------- cabeceras

    /** Envia las cabeceras de seguridad recomendadas. */
    public static function cabeceras(): void
    {
        if (headers_sent()) { return; }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "form-action 'self'; "
            . "base-uri 'self'; "
            . "frame-ancestors 'self'; "
            . "object-src 'none'"
        );
        if (cr_es_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header_remove('X-Powered-By');
    }

    // --------------------------------------------------------------------- CSRF

    /** Token CSRF de la sesión actual (se crea si no existe). */
    public static function tokenCsrf(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = cr_aleatorio(32);
        }
        return (string) $_SESSION['_csrf'];
    }

    /** Campo oculto listo para pegar dentro de un <form>. */
    public static function campoCsrf(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::tokenCsrf()) . '">';
    }

    /** Comprueba un token CSRF en tiempo constante. */
    public static function verificarCsrf(?string $token = null): bool
    {
        $token ??= (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $valido = (string) ($_SESSION['_csrf'] ?? '');
        return $valido !== '' && $token !== '' && hash_equals($valido, $token);
    }

    /** Aborta la petición si el token CSRF no es válido. */
    public static function exigirCsrf(?string $token = null, bool $json = false): void
    {
        if (self::verificarCsrf($token)) { return; }
        if ($json) {
            cr_json(['ok' => false, 'error' => 'Sesión caducada. Recarga la página e inténtalo de nuevo.'], 419);
        }
        http_response_code(419);
        exit('Token de seguridad no válido. Vuelve atrás y recarga la página.');
    }

    // --------------------------------------------------------------------- SSRF

    /**
     * Válida y normaliza una URL antes de descargarla.
     *
     * @return array{ok:bool,url?:string,host?:string,ip?:string,error?:string}
     */
    public static function validarUrl(string $url, bool $permitirPrivadas = false): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'error' => 'Escribe una dirección web.'];
        }
        // Acepta "ejemplo.com" y le añade el esquema.
        if (!preg_match('~^[a-z][a-z0-9+.\-]*://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        if (strlen($url) > 1000) {
            return ['ok' => false, 'error' => 'La dirección es demasiado larga.'];
        }

        $partes = parse_url($url);
        if ($partes === false || empty($partes['host'])) {
            return ['ok' => false, 'error' => 'La dirección no tiene un formato válido.'];
        }

        $esquema = strtolower($partes['scheme'] ?? '');
        if (!in_array($esquema, ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'Solo se permiten direcciones http:// y https://.'];
        }
        if (isset($partes['user']) || isset($partes['pass'])) {
            return ['ok' => false, 'error' => 'No se permiten credenciales dentro de la dirección.'];
        }

        $host   = strtolower(trim($partes['host'], '.'));
        $puerto = (int) ($partes['port'] ?? ($esquema === 'https' ? 443 : 80));

        if (!$permitirPrivadas && !in_array($puerto, self::PUERTOS_PERMITIDOS, true)) {
            return ['ok' => false, 'error' => 'Puerto no permitido (' . $puerto . ').'];
        }
        if (!$permitirPrivadas && self::hostBloqueado($host)) {
            return ['ok' => false, 'error' => 'No se permite analizar direcciones internas.'];
        }

        // Resolución DNS: todas las IPs del host deben ser públicas.
        $ips = self::resolver($host);
        if (!$ips) {
            return ['ok' => false, 'error' => 'No se pudo resolver el dominio "' . $host . '".'];
        }
        if (!$permitirPrivadas) {
            foreach ($ips as $ip) {
                if (!self::ipPublica($ip)) {
                    return ['ok' => false, 'error' => 'La dirección apunta a una red privada o reservada.'];
                }
            }
        }

        // URL normalizada (sin fragmento).
        $normalizada = $esquema . '://' . $host;
        if (isset($partes['port'])) { $normalizada .= ':' . $puerto; }
        $normalizada .= $partes['path'] ?? '/';
        if (!empty($partes['query'])) { $normalizada .= '?' . $partes['query']; }

        return ['ok' => true, 'url' => $normalizada, 'host' => $host, 'ip' => $ips[0]];
    }

    /** ¿El nombre de host esta en la lista negra o apunta a la maquina local? */
    public static function hostBloqueado(string $host): bool
    {
        if (in_array($host, self::HOSTS_BLOQUEADOS, true)) { return true; }
        // Dominios internos tipicos de redes corporativas y contenedores.
        if (preg_match('~\.(local|localdomain|internal|intranet|lan|home|corp|test|localhost)$~', $host)) {
            return true;
        }
        return false;
    }

    /** Resuelve un host a la lista de IPs (v4 y v6). @return string[] */
    public static function resolver(string $host): array
    {
        // Si ya es una IP literal (incluido [::1]) no hace falta DNS.
        $limpio = trim($host, '[]');
        if (filter_var($limpio, FILTER_VALIDATE_IP)) { return [$limpio]; }

        $ips = [];
        $a = @dns_get_record($host, DNS_A);
        if (is_array($a)) {
            foreach ($a as $reg) { if (!empty($reg['ip'])) { $ips[] = $reg['ip']; } }
        }
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $reg) { if (!empty($reg['ipv6'])) { $ips[] = $reg['ipv6']; } }
        }
        if (!$ips) {
            $ip = @gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) { $ips[] = $ip; }
        }
        return array_values(array_unique($ips));
    }

    /** ¿La IP es pública (ni privada, ni reservada, ni de metadatos de nube)? */
    public static function ipPublica(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) { return false; }

        // Rangos privados y reservados según PHP (127/8, 10/8, 192.168/16, ::1, fc00::/7...).
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // Metadatos de nube y CGNAT, que PHP no marca como reservados.
        if ($ip === '169.254.169.254' || $ip === '100.100.100.200') { return false; }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $largo = ip2long($ip);
            if ($largo === false) { return false; }
            // 100.64.0.0/10 (CGNAT) y 192.0.0.0/24 (IETF).
            if (($largo & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) { return false; }
            if (($largo & 0xFFFFFF00) === (ip2long('192.0.0.0') & 0xFFFFFF00)) { return false; }
        } else {
            // IPv6 mapeada a IPv4 (::ffff:127.0.0.1) y direcciones locales unicas.
            $bajo = strtolower($ip);
            if (str_starts_with($bajo, '::ffff:')) {
                return self::ipPublica(substr($bajo, 7));
            }
            if (str_starts_with($bajo, 'fc') || str_starts_with($bajo, 'fd') || str_starts_with($bajo, 'fe80')) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------ límite por IP

    /** Cuenta las peticiones de una IP en la última hora. */
    public static function peticionesUltimaHora(string $accion = 'escaneo', ?string $ip = null): int
    {
        $ip ??= cr_ip();
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_peticiones` WHERE `ip` = ? AND `accion` = ? AND `creado` > (NOW() - INTERVAL 1 HOUR)',
            [$ip, $accion],
            0
        );
    }

    /** ¿La IP supero el límite configurado? (0 = sin límite) */
    public static function limiteAlcanzado(string $accion = 'escaneo'): bool
    {
        $limite = Ajustes::entero('limite_ip_hora', 30, 0, 100000);
        if ($limite <= 0) { return false; }
        return self::peticionesUltimaHora($accion) >= $limite;
    }

    /** Anota una petición para el control de límites y limpia las antiguas. */
    public static function registrarPeticion(string $accion = 'escaneo'): void
    {
        BD::insertar('cr_peticiones', ['ip' => cr_ip(), 'accion' => $accion, 'creado' => date('Y-m-d H:i:s')]);
        // Limpieza ocasional (1 de cada 20 peticiones) para no acumular basura.
        if (random_int(1, 20) === 1) {
            BD::ejecutar('DELETE FROM `cr_peticiones` WHERE `creado` < (NOW() - INTERVAL 2 DAY)');
        }
    }

    // ------------------------------------------------------- intentos de acceso

    /** Intentos fallidos de una IP en los últimos 15 minutos. */
    public static function intentosFallidos(?string $ip = null): int
    {
        $ip ??= cr_ip();
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_intentos` WHERE `ip` = ? AND `exito` = 0 AND `creado` > (NOW() - INTERVAL 15 MINUTE)',
            [$ip],
            0
        );
    }

    /** Registra un intento de acceso (correcto o fallido). */
    public static function registrarIntento(string $usuario, bool $exito): void
    {
        BD::insertar('cr_intentos', [
            'ip'      => cr_ip(),
            'usuario' => mb_substr($usuario, 0, 64),
            'exito'   => $exito ? 1 : 0,
            'creado'  => date('Y-m-d H:i:s'),
        ]);
        if (random_int(1, 20) === 1) {
            BD::ejecutar('DELETE FROM `cr_intentos` WHERE `creado` < (NOW() - INTERVAL 7 DAY)');
        }
    }
}
