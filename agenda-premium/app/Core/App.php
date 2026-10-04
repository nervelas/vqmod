<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Núcleo: arranca la aplicación, enruta y aplica seguridad (autenticación, CSRF, permisos, API). */
final class App
{
    public static function run(?Request $req = null): void
    {
        $req = $req ?? new Request();
        $GLOBALS['__request'] = $req;
        $response = null;
        try {
            $response = self::handle($req);
        } catch (HttpException $e) {
            $response = self::errorResponse($req, $e->status, $e->getMessage());
        } catch (Throwable $e) {
            Logger::error('Error no controlado en ' . $req->method . ' ' . $req->path, $e);
            $response = self::errorResponse($req, 500, 'Algo salió mal de nuestro lado. Ya quedó registrado; inténtalo de nuevo en unos minutos.');
        }
        $response->send($req);
        self::afterResponse($req);
    }

    private static function handle(Request $req): Response
    {
        if (!Config::installed()) {
            if (strpos($req->path, '/instalar') === 0) {
                throw new HttpException(404);
            }
            return Response::redirect(url('/instalar/'));
        }
        try {
            Db::connect();
        } catch (Throwable $e) {
            Logger::error('Sin conexión a la base de datos', $e);
            return self::errorResponse($req, 503, 'No pudimos conectar con la base de datos. Inténtalo de nuevo en unos minutos.');
        }
        self::autoMigrate();

        $router = new Router();
        $routeFiles = glob(APP_ROOT . '/app/routes/*.php') ?: [];
        sort($routeFiles, SORT_STRING);
        foreach ($routeFiles as $file) {
            $fn = require $file;
            $fn($router);
        }
        $m = $router->match($req->method, $req->path);
        if ($m === null) {
            throw new HttpException(404);
        }
        if (isset($m['methodNotAllowed'])) {
            throw new HttpException(405);
        }
        $opts = $m['opts'];
        $isApi = isset($opts['api']);

        if (!empty($opts['embed'])) {
            Security::allowEmbed();
        }

        // --- API con clave ---
        if ($isApi) {
            $auth = ApiAuth::authenticate($req, (string) $opts['api']);
            if (!$auth['ok']) {
                return Response::json(['error' => $auth['error']], (int) $auth['status']);
            }
            $req->server['__api_key'] = $auth['key'];
        }

        // --- Sesión, autenticación y permisos del panel ---
        $csrfMode = $opts['csrf'] ?? ($req->method === 'POST' && !$isApi ? 'session' : false);
        $needsSession = !empty($opts['auth']) || $csrfMode === 'session' || !empty($opts['session']);
        if ($needsSession) {
            Session::start($req);
        }
        if (!empty($opts['auth'])) {
            if (!Auth::check()) {
                if ($req->wantsJson()) {
                    return Response::json(['ok' => false, 'error' => 'Tu sesión terminó. Inicia sesión de nuevo.'], 401);
                }
                return Response::redirect(url('/admin/login', ['next' => $req->path]));
            }
            if (isset($opts['area']) && !Auth::can((string) $opts['area'])) {
                throw new HttpException(403);
            }
            if (!empty($opts['roles']) && !in_array(Auth::role(), (array) $opts['roles'], true)) {
                throw new HttpException(403);
            }
        }

        // --- CSRF ---
        if ($req->method === 'POST' || $req->method === 'PUT' || $req->method === 'DELETE') {
            if ($csrfMode === 'session' && !Csrf::check($req)) {
                throw new HttpException(419);
            }
            if ($csrfMode === 'public' && !Csrf::checkPublic($req, (string) ($opts['scope'] ?? 'public'))) {
                throw new HttpException(419);
            }
        }

        [$class, $method] = $m['handler'];
        $controller = new $class();
        $result = $controller->$method($req, $m['params']);
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    public static function errorResponse(Request $req, int $status, string $message): Response
    {
        if ($req->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }
        $titles = [403 => 'Acceso restringido', 404 => 'Página no encontrada', 405 => 'Acción no permitida', 419 => 'La página caducó', 429 => 'Un momento, por favor', 503 => 'Estamos en mantenimiento'];
        try {
            $html = View::render('errors/error', ['status' => $status, 'title' => $titles[$status] ?? 'Algo salió mal', 'message' => $message]);
        } catch (Throwable $e) {
            $html = '<!doctype html><meta charset="utf-8"><title>Error</title><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        return Response::html($html, $status);
    }

    /** Aplica migraciones pendientes automáticamente (actualizar = subir archivos). */
    private static function autoMigrate(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $pending = Migrator::pending();
            if ($pending) {
                $lock = (int) Db::val("SELECT GET_LOCK('ap_migrate', 10)");
                if ($lock === 1) {
                    try {
                        Migrator::run();
                    } finally {
                        Db::q("SELECT RELEASE_LOCK('ap_migrate')");
                    }
                    Settings::flush();
                }
            }
        } catch (Throwable $e) {
            Logger::error('Falló una migración', $e);
        }
    }

    /** Respaldo de cron por visitas: cada ~5 minutos ejecuta tareas ligeras tras enviar la respuesta. */
    private static function afterResponse(Request $req): void
    {
        if (strpos($req->path, '/instalar') === 0 || $req->method !== 'GET' || !Config::installed()) {
            return;
        }
        try {
            if (!class_exists('App\\Services\\CronService')) {
                return;
            }
            $last = (int) Settings::get('cron_last_run', '0');
            if (Clock::now() - $last < 300) {
                return;
            }
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            \App\Services\CronService::run('visita');
        } catch (Throwable $e) {
            Logger::error('Cron por visitas falló', $e);
        }
    }
}
