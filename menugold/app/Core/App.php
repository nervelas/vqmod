<?php
declare(strict_types=1);

namespace MenuGold\Core;

use MenuGold\Models\Restaurant;
use Throwable;

/**
 * Contenedor principal de la aplicacion.
 * Detecta la base de la URL, arranca sesion, seguridad y router.
 */
final class App
{
    private static ?App $instance = null;

    /** @var array<string,mixed> */
    private static array $config = [];
    /** @var array<string,mixed>|null Restaurante activo (contexto publico o panel) */
    private static ?array $restaurant = null;

    private static string $basePath = '';
    private static string $baseUrl = '';
    private static string $uri = '/';

    private Router $router;

    private function __construct(private string $root) {}

    public static function boot(string $root): App
    {
        if (self::$instance) return self::$instance;
        $app = new self($root);
        self::$instance = $app;

        $app->loadConfig();
        $app->setupErrors();
        $app->detectBase();
        Security::sendHeaders();
        Session::start();
        Lang::boot();
        $app->router = new Router();
        Routes::define($app->router);
        return $app;
    }

    public static function i(): App
    {
        if (!self::$instance) throw new \RuntimeException('App no inicializada');
        return self::$instance;
    }

    // ------------------------------------------------------------------ config
    private function loadConfig(): void
    {
        $file = $this->root . '/config/config.php';
        self::$config = is_file($file) ? (array)require $file : [];
        $defaults = [
            'app_nombre'   => 'MenuGold',
            'zona_horaria' => 'America/Guatemala',
            'debug'        => false,
            'forzar_https' => true,
            'moneda'       => 'GTQ',
            'simbolo'      => 'Q',
            'db_host'      => 'localhost',
            'db_port'      => 3306,
            'db_charset'   => 'utf8mb4',
            'cron_token'   => '',
            'app_key'      => '',
        ];
        self::$config += $defaults;
        date_default_timezone_set((string)self::$config['zona_horaria']);
        setlocale(LC_TIME, 'es_GT.UTF-8', 'es_ES.UTF-8', 'es_MX.UTF-8', 'es');
        mb_internal_encoding('UTF-8');
    }

    public static function config(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }

    public static function installed(): bool
    {
        return is_file(MG_ROOT . '/config/config.php') && !empty(self::$config['db_name']);
    }

    // ------------------------------------------------------------------ errores
    private function setupErrors(): void
    {
        $debug = (bool)self::$config['debug'];
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        $logDir = $this->root . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
        ini_set('error_log', $logDir . '/php-' . date('Y-m') . '.log');
        error_reporting(E_ALL);

        set_exception_handler(static function (Throwable $ex): void {
            self::renderError($ex);
        });
        set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
            if (!(error_reporting() & $no)) return false;
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::renderError(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
            }
        });
    }

    public static function renderError(Throwable $ex): void
    {
        $isHttp = $ex instanceof HttpException;
        $code   = $isHttp ? $ex->getCode() : 500;
        if ($code < 400 || $code > 599) $code = 500;

        if (!$isHttp || $code >= 500) {
            Logger::error($ex->getMessage(), [
                'archivo' => $ex->getFile(), 'linea' => $ex->getLine(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '', 'traza' => $ex->getTraceAsString(),
            ]);
        }
        if (headers_sent()) return;
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code($code);

        $wantsJson = (($_SERVER['HTTP_ACCEPT'] ?? '') && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || strpos(self::$uri, '/api/') !== false
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        $mensaje = $isHttp ? $ex->getMessage() : 'Ocurrió un error inesperado. El equipo ya fue notificado.';
        if (self::config('debug') && !$isHttp) {
            $mensaje = $ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine();
        }
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $mensaje, 'codigo' => $code], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $file = MG_ROOT . '/app/Views/errors/error.php';
        if (is_file($file)) {
            $titulo = $code === 404 ? 'Página no encontrada' : ($code === 403 ? 'Acceso denegado' : 'Error del servidor');
            include $file;
        } else {
            echo '<h1>' . $code . '</h1><p>' . htmlspecialchars($mensaje) . '</p>';
        }
        exit;
    }

    // ------------------------------------------------------------------ URLs
    private function detectBase(): void
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base   = rtrim(str_replace('/index.php', '', $script), '/');
        self::$basePath = $base;

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        if ($base !== '' && strncmp($uri, $base, strlen($base)) === 0) {
            $uri = substr($uri, strlen($base));
        }
        self::$uri = '/' . trim($uri, '/');

        $https  = (($_SERVER['HTTPS'] ?? '') && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') == 443
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        self::$baseUrl = ($https ? 'https' : 'http') . '://' . $host . $base;
    }

    public static function uri(): string { return self::$uri; }
    public static function basePath(): string { return self::$basePath; }
    public static function baseUrl(): string { return self::$baseUrl; }
    public static function isSecure(): bool { return strncmp(self::$baseUrl, 'https', 5) === 0; }

    public static function url(string $path = '', array $query = []): string
    {
        $path = ltrim($path, '/');
        $u = self::$baseUrl . ($path === '' ? '/' : '/' . $path);
        if ($query) $u .= (strpos($u, '?') === false ? '?' : '&') . http_build_query($query);
        return $u;
    }

    public static function asset(string $path): string
    {
        $rel = 'assets/' . ltrim($path, '/');
        $file = MG_ROOT . '/' . $rel;
        $v = is_file($file) ? substr((string)filemtime($file), -6) : (string)self::config('version', '1');
        return self::$baseUrl . '/' . $rel . '?v=' . $v;
    }

    // ------------------------------------------------------------------ restaurante activo
    /** @return array<string,mixed>|null */
    public static function restaurant(): ?array { return self::$restaurant; }

    public static function setRestaurant(?array $r): void { self::$restaurant = $r; }

    public static function restaurantId(): int { return (int)(self::$restaurant['id'] ?? 0); }

    /** Detecta restaurante por dominio propio (mapeo en la tabla restaurants). */
    public static function restaurantByDomain(): ?array
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '' || !self::installed()) return null;
        static $cache = [];
        if (array_key_exists($host, $cache)) return $cache[$host];
        $r = (new Restaurant())->byDomain($host);
        $cache[$host] = $r;
        return $r;
    }

    // ------------------------------------------------------------------ run
    public function run(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') $method = 'GET';
        $this->router->dispatch($method, self::$uri);
    }

    public function router(): Router { return $this->router; }
}
