<?php
declare(strict_types=1);

/** Utilidades generales: carpetas, entorno, escape, respuestas. */
final class Support
{
    public const DIRS = ['logs', 'db', 'images', 'thumbs', 'sessions', 'tmp'];

    /** Crea las carpetas de almacenamiento y sus protecciones. Idempotente. */
    public static function prepareStorage(): void
    {
        if (!is_dir(PF_STORAGE)) {
            @mkdir(PF_STORAGE, 0750, true);
        }
        foreach (self::DIRS as $dir) {
            $path = PF_STORAGE . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0750, true);
            }
        }
        $guard = PF_STORAGE . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, self::denyRules());
        }
        $appGuard = PF_APP . '/.htaccess';
        if (!is_file($appGuard)) {
            @file_put_contents($appGuard, self::denyRules());
        }
        $viewGuard = PF_VIEWS . '/.htaccess';
        if (is_dir(PF_VIEWS) && !is_file($viewGuard)) {
            @file_put_contents($viewGuard, self::denyRules());
        }
        $index = PF_STORAGE . '/index.php';
        if (!is_file($index)) {
            @file_put_contents($index, "<?php\n// Carpeta privada de PixelForge.\nhttp_response_code(404);\n");
        }
    }

    /**
     * Carpeta privada con nombre irrepetible dentro de storage/db.
     * Añade una capa de protección en hostings sin .htaccess (nginx, por ejemplo).
     */
    public static function vaultDir(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $root = PF_STORAGE . '/db';
        if (!is_dir($root)) {
            @mkdir($root, 0750, true);
        }
        $found = @glob($root . '/v-*');
        if (is_array($found)) {
            foreach ($found as $dir) {
                if (is_dir($dir)) {
                    $cached = $dir;
                    return $cached;
                }
            }
        }
        try {
            $suffix = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $suffix = substr(hash('sha256', uniqid('pf', true) . PF_ROOT), 0, 16);
        }
        $dir = $root . '/v-' . $suffix;
        @mkdir($dir, 0750, true);
        @file_put_contents($dir . '/.htaccess', self::denyRules());
        $cached = is_dir($dir) ? $dir : $root;
        return $cached;
    }

    private static function denyRules(): string
    {
        return "# Acceso directo denegado\n"
            . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
    }

    /** Comprueba extensiones de PHP y devuelve el diagnóstico en español. */
    public static function environment(): array
    {
        $php = PHP_VERSION;
        $checks = [];
        $checks['php'] = [
            'ok' => version_compare($php, '8.0.0', '>='),
            'label' => 'PHP ' . $php,
            'detail' => version_compare($php, '8.0.0', '>=')
                ? 'Versión compatible.'
                : 'Se requiere PHP 8.0 o superior. Cámbialo en el panel de tu hosting.',
            'critical' => true,
        ];
        $hasCurl = function_exists('curl_init');
        $hasStreams = (bool) ini_get('allow_url_fopen');
        $checks['red'] = [
            'ok' => $hasCurl || $hasStreams,
            'label' => $hasCurl ? 'cURL disponible' : ($hasStreams ? 'cURL ausente, se usa allow_url_fopen' : 'Sin salida a internet'),
            'detail' => $hasCurl
                ? 'Las llamadas a los proveedores usarán cURL.'
                : ($hasStreams
                    ? 'No hay cURL: se usará el método alternativo de flujos de PHP.'
                    : 'Activa la extensión cURL o allow_url_fopen para poder generar imágenes.'),
            'critical' => !($hasCurl || $hasStreams),
        ];
        $gd = extension_loaded('gd');
        $imagick = class_exists('Imagick');
        $checks['imagen'] = [
            'ok' => $gd || $imagick,
            'label' => $imagick ? 'Imagick disponible' : ($gd ? 'GD disponible' : 'Sin GD ni Imagick'),
            'detail' => ($gd || $imagick)
                ? 'Se puede ajustar cada imagen al tamaño exacto solicitado.'
                : 'Sin GD ni Imagick las imágenes se guardarán tal cual las entregue el proveedor, sin ajuste de tamaño.',
            'critical' => false,
        ];
        $checks['openssl'] = [
            'ok' => extension_loaded('openssl'),
            'label' => extension_loaded('openssl') ? 'OpenSSL disponible' : 'OpenSSL ausente',
            'detail' => extension_loaded('openssl')
                ? 'Las API keys se guardan cifradas con AES-256-GCM.'
                : 'Sin OpenSSL las API keys se guardan ofuscadas en una carpeta protegida; añade la extensión si vas a usar keys.',
            'critical' => false,
        ];
        $checks['sqlite'] = [
            'ok' => class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true),
            'label' => (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true))
                ? 'SQLite disponible' : 'SQLite ausente, se usan archivos JSON',
            'detail' => 'El historial y los ajustes funcionan con cualquiera de los dos.',
            'critical' => false,
        ];
        $checks['zip'] = [
            'ok' => true,
            'label' => class_exists('ZipArchive') ? 'ZipArchive disponible' : 'ZipArchive ausente, se usa empaquetador propio',
            'detail' => 'La descarga en ZIP funciona en ambos casos.',
            'critical' => false,
        ];
        $writable = is_writable(PF_STORAGE);
        $checks['escritura'] = [
            'ok' => $writable,
            'label' => $writable ? 'Carpeta storage/ con permisos de escritura' : 'storage/ sin permisos de escritura',
            'detail' => $writable
                ? 'Todo listo para guardar imágenes e historial.'
                : 'Da permisos 755 (o 775) a la carpeta storage/ desde tu administrador de archivos.',
            'critical' => !$writable,
        ];
        return $checks;
    }

    public static function criticalProblems(): array
    {
        $out = [];
        foreach (self::environment() as $check) {
            if (!empty($check['critical']) && empty($check['ok'])) {
                $out[] = $check['detail'];
            }
        }
        return $out;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function json(array $data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonError(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge(['ok' => false, 'error' => $message], $extra), $status);
    }

    public static function redirect(string $path): void
    {
        if (!headers_sent()) {
            header('Location: ' . $path);
        }
        exit;
    }

    public static function view(string $name, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        $viewFile = PF_VIEWS . '/' . $name . '.php';
        if (!is_file($viewFile)) {
            self::fatalScreen();
        }
        require $viewFile;
    }

    /** Pantalla de error amable: la app nunca queda en blanco. */
    public static function fatalScreen(): void
    {
        if (headers_sent()) {
            exit;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        if (self::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Ocurrió un error interno. Se registró en storage/logs/app.log.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>PixelForge</title><style>body{background:#0b0b0c;color:#e8e3da;font-family:ui-monospace,Menlo,Consolas,monospace;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}'
            . 'div{max-width:520px}h1{color:#f0a12e;font-size:20px;letter-spacing:.08em;text-transform:uppercase}'
            . 'a{color:#f0a12e}</style></head><body><div><h1>Algo falló</h1>'
            . '<p>No pudimos completar la operación. El detalle quedó registrado en <code>storage/logs/app.log</code>.</p>'
            . '<p><a href="./">Volver al inicio</a></p></div></body></html>';
        exit;
    }

    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        return $xhr || str_contains((string) $accept, 'application/json');
    }

    public static function uuid(): string
    {
        try {
            $bytes = random_bytes(16);
        } catch (Throwable $e) {
            $bytes = pack('N4', mt_rand(), mt_rand(), mt_rand(), mt_rand());
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) ? substr($ip, 0, 45) : '0.0.0.0';
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** Base URL de la app (funciona en subcarpetas). */
    public static function baseUrl(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if (basename(dirname($script)) === 'admin') {
            $dir = rtrim(dirname($dir), '/');
        }
        return $dir === '' ? '' : $dir;
    }

    public static function bytesHuman(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return round($value, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
