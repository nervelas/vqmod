<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    private Router $router;
    private Request $req;

    public function __construct()
    {
        $this->router = new Router();
        $this->req    = new Request();
    }

    public function router(): Router { return $this->router; }
    public function request(): Request { return $this->req; }

    public function boot(): void
    {
        $cfgFile = BASE_PATH . '/config/config.php';
        if (!is_file($cfgFile)) {
            $this->redirigirInstalador();
            return;
        }
        /** @var array $cfg */
        $cfg = require $cfgFile;
        Config::load($cfg);

        $debug = (bool)Config::get('debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });

        date_default_timezone_set((string)Config::get('timezone', 'America/Guatemala'));
        mb_internal_encoding('UTF-8');
        setlocale(LC_TIME, 'es_GT.UTF-8', 'es_ES.UTF-8', 'es_GT', 'spanish');

        Database::connect((array)Config::get('db', []));
        Session::start();
        Settings::load();
        Security::headers();

        $tz = (string)Settings::get('zona_horaria', 'America/Guatemala');
        if (in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            date_default_timezone_set($tz);
        }

        View::share('u', Auth::user());
        View::share('cfgColegio', Settings::all());
    }

    private function redirigirInstalador(): void
    {
        if (is_dir(BASE_PATH . '/install') && !is_file(BASE_PATH . '/install/.lock')) {
            header('Location: ' . url('install/'), true, 302);
            exit;
        }
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>EduPortal</title>'
           . '<div style="font-family:system-ui;max-width:640px;margin:12vh auto;padding:2rem;'
           . 'border:1px solid #ddd;border-radius:16px">'
           . '<h1 style="margin:0 0 .5rem">Configuracion pendiente</h1>'
           . '<p>No se encontro <code>config/config.php</code> y el instalador ya fue bloqueado. '
           . 'Restaure el archivo de configuracion o elimine <code>install/.lock</code> para reinstalar.</p></div>';
        exit;
    }

    public function run(): void
    {
        $ruta = $this->router->match($this->req->method(), $this->req->path());
        try {
            if ($ruta === null) {
                $otros = $this->router->methodsFor($this->req->path());
                throw new HttpException($otros ? 405 : 404, $otros ? 'Metodo no permitido.' : 'Pagina no encontrada.');
            }
            [$clase, $metodo] = $ruta['action'];
            if (!class_exists($clase) || !method_exists($clase, $metodo)) {
                throw new HttpException(500, 'Controlador no disponible.');
            }
            $controlador = new $clase($this->req);
            echo (string)$controlador->{$metodo}(...array_values($ruta['params']));
        } catch (HttpException $e) {
            $this->manejarHttp($e);
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'archivo' => $e->getFile(),
                'linea'   => $e->getLine(),
                'ruta'    => $this->req->path(),
            ]);
            if (Config::get('debug', false)) {
                http_response_code(500);
                echo '<pre style="padding:2rem;font:13px ui-monospace">'
                   . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES) . '</pre>';
                return;
            }
            $this->manejarHttp(new HttpException(500, 'Ocurrio un error inesperado. El equipo tecnico ha sido notificado.'));
        }
    }

    private function manejarHttp(HttpException $e): void
    {
        $codigo = $e->status();
        if ($codigo === 401 && !$this->req->wantsJson()) {
            header('Location: ' . url('ingresar'), true, 302);
            return;
        }
        http_response_code($codigo);
        if ($this->req->wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            echo View::render('errors/http', [
                'codigo'  => $codigo,
                'mensaje' => $e->getMessage(),
            ], Database::isConnected() ? 'layouts/simple' : null);
        } catch (\Throwable) {
            echo '<!doctype html><meta charset="utf-8"><title>Error ' . $codigo . '</title>'
               . '<p style="font-family:system-ui;padding:2rem">' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        }
    }
}
