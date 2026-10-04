<?php
declare(strict_types=1);

namespace Aurea\Core;

use Aurea\Services\CronService;
use Aurea\Services\Migrator;

final class App
{
    public static string $nonce = '';
    public static bool $embed = false;

    public static function run(): void
    {
        $req = new Request();
        self::$nonce = base64_encode(random_bytes(16));
        set_exception_handler([self::class, 'handleException']);
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) { return false; }
            Logger::error('PHP[' . $no . '] ' . $str . ' @ ' . basename($file) . ':' . $line);
            return true;
        });
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        if (!is_file(AUREA_ROOT . '/config/config.php')) {
            $to = is_dir(AUREA_ROOT . '/instalar') ? url('/instalar/') : null;
            if ($to) { Response::redirect($to)->send(); return; }
            Response::text('Sistema no instalado.', 'text/plain', 503)->send();
            return;
        }
        $cfg = (array)config('db', []);
        Db::connect($cfg);
        date_default_timezone_set((string)Settings::get('timezone', 'America/Guatemala'));
        self::autoMigrate();

        $router = new Router();
        Routes::register($router);
        $m = $router->match($req->method, $req->path);
        if ($m === null) { self::send($req, self::notFound($req)); return; }
        [$handler, $params] = $m;
        if ($handler[1] === '405') { self::send($req, Response::text('Método no permitido', 'text/plain', 405)); return; }
        $req->params = $params;

        $isAdmin = strpos($req->path, '/admin') === 0;
        if ($isAdmin) { Auth::startSession($req); }
        self::$embed = $req->path === '/embed';

        // CSRF obligatorio en TODA petición POST (panel: ligado a la sesión; público: firmado)
        if ($req->isPost()) {
            $scope = $isAdmin ? 'admin' : 'public';
            if (!Csrf::check($req, $scope)) {
                $r = $req->isAjax() || strpos($req->path, '/api/') === 0
                    ? Response::json(['ok' => false, 'error' => 'La sesión del formulario expiró. Recarga la página e intenta de nuevo.', 'code' => 'csrf'], 419)
                    : Response::html(View::page('public', 'public/error', ['code' => 419, 'message' => 'La sesión del formulario expiró. Vuelve atrás, recarga la página e intenta de nuevo.']), 419);
                self::send($req, $r);
                return;
            }
        }

        $class = $handler[0];
        try {
            $obj = new $class($req);
            $res = $obj->{$handler[1]}();
        } catch (HttpException $he) {
            $res = $he->response;
        }
        if (!$res instanceof Response) { $res = Response::html((string)$res); }
        self::send($req, $res);
        if (!$isAdmin && !$req->isPost() && $req->path !== '/cron.php') { CronService::maybeRunOnVisit(); }
    }

    private static function autoMigrate(): void
    {
        try {
            $files = glob(AUREA_ROOT . '/database/migrations/*.sql') ?: [];
            if (!$files) { return; }
            sort($files);
            if (basename((string)end($files)) !== Settings::get('schema_version', '')) {
                if (Migrator::pending()) { Migrator::run(); }
                Settings::set('schema_version', basename((string)end($files)));
            }
        } catch (\Throwable $e) {
            Logger::exception($e);
        }
    }

    public static function send(Request $req, Response $res): void
    {
        $https = $req->isHttps();
        $h = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];
        $cap = (string)Settings::get('captcha_provider', 'none');
        $capSrc = $cap === 'turnstile' ? ' https://challenges.cloudflare.com' : ($cap === 'hcaptcha' ? ' https://hcaptcha.com https://*.hcaptcha.com' : '');
        $frameAnc = "'self'";
        if (self::$embed) {
            $origins = trim((string)Settings::get('booking_embed_origins', '*'));
            $frameAnc = ($origins === '' || $origins === '*') ? '*' : "'self' " . implode(' ', array_filter(preg_split('/\s+/', $origins) ?: [], static fn($o) => preg_match('#^https?://[A-Za-z0-9.\-:*]+$#', $o)));
        } else {
            $h['X-Frame-Options'] = 'SAMEORIGIN';
        }
        if (!isset($res->headers['Content-Security-Policy'])) {
            $h['Content-Security-Policy'] = "default-src 'self'; script-src 'self'" . $capSrc . "; style-src 'self' 'nonce-" . self::$nonce . "'" . $capSrc
                . "; style-src-attr 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'" . $capSrc
                . "; frame-src " . ($capSrc !== '' ? "'self'" . $capSrc : "'none'") . "; frame-ancestors " . $frameAnc . "; base-uri 'self'; form-action 'self'; object-src 'none'";
        }
        if ($https) { $h['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains'; }
        $res->headers += $h;
        if (!isset($res->headers['Cache-Control']) && strpos($req->path, '/admin') === 0) { $res->headers['Cache-Control'] = 'no-store'; }
        $res->send();
    }

    public static function notFound(Request $req): Response
    {
        if (strpos($req->path, '/api/') === 0) { return Response::json(['ok' => false, 'error' => 'No encontrado'], 404); }
        return Response::html(View::page('public', 'public/error', ['code' => 404, 'message' => 'La página que buscas no existe o fue movida.']), 404);
    }

    public static function handleException(\Throwable $e): void
    {
        Logger::exception($e);
        if (!headers_sent()) { http_response_code(500); }
        $isApi = isset($_SERVER['REQUEST_URI']) && strpos((string)$_SERVER['REQUEST_URI'], '/api/') !== false;
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Ocurrió un error inesperado. Intenta de nuevo en unos minutos.']);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error</title>'
            . '<body style="font-family:Georgia,serif;background:#0B0A08;color:#F6F0E4;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center">'
            . '<div><h1 style="font-weight:400">Algo no salió como esperábamos</h1><p style="color:#c9bda3">Ya registramos el problema. Intenta de nuevo en unos minutos.</p></div></body></html>';
    }
}
