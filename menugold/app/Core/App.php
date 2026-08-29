<?php
namespace MenuGold\Core;

final class App
{
    /** @var Router */
    private $router;
    /** @var Request */
    private $request;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public static function isInstalled()
    {
        return is_file(MG_ROOT . '/config/config.php');
    }

    public function boot()
    {
        $this->registerErrorHandling();
        Config::load(require MG_ROOT . '/config/config.php');
        date_default_timezone_set((string)Config::get('app.timezone', 'America/Guatemala'));
        Session::start();
        Lang::setLocale(Session::get('lang', Config::get('app.locale', 'es')));
        return $this;
    }

    private function registerErrorHandling()
    {
        $debug = (bool)Config::get('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) { return false; }
            // Los avisos de obsolescencia se registran, pero no cortan la petición:
            // una librería de terceros no debe tumbar el menú de un restaurante.
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                Logger::warn('Deprecated: ' . $message . ' @ ' . $file . ':' . $line);
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function ($e) {
            Logger::error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->renderException($e)->send();
        });

        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
                Logger::error('FATAL: ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/html; charset=UTF-8');
                    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
                       . '<body style="background:#0C0B09;color:#F4EDE1;font-family:system-ui;display:grid;place-items:center;height:100vh;margin:0">'
                       . '<p>Ocurrió un error inesperado. El detalle quedó registrado.</p>';
                }
            }
        });
    }

    private function renderException($e)
    {
        $debug = (bool)Config::get('app.debug', false);
        $isJson = $this->request && $this->request->wantsJson();
        if ($isJson) {
            return Response::json(array(
                'ok'    => false,
                'error' => $debug ? $e->getMessage() : 'Ocurrió un error inesperado.',
            ), 500);
        }
        try {
            return Response::html(View::render('errors/500', array(
                'detail' => $debug ? ($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()) : '',
            )), 500);
        } catch (\Throwable $inner) {
            return Response::html('<!doctype html><meta charset="utf-8"><body style="background:#0C0B09;color:#F4EDE1;font-family:system-ui;padding:40px">'
                . '<h1>Error</h1><p>' . ($debug ? Security::e($e->getMessage()) : 'Ocurrió un error inesperado.') . '</p>', 500);
        }
    }

    public function run(Request $request)
    {
        $this->request = $request;
        View::share('view_lang', Lang::locale());
        View::share('auth_user', null);

        $match = $this->router->match($request->method, $request->path);
        if ($match === null) {
            if ($this->router->pathExists($request->path)) {
                return Response::html(View::render('errors/404', array('message' => 'Método no permitido para esta dirección.')), 405);
            }
            return Response::html(View::render('errors/404', array('message' => 'La página que buscas no existe o cambió de dirección.')), 404);
        }

        list($action, $params) = $match;

        if (is_callable($action) && !is_string($action)) {
            $result = call_user_func($action, $request, $params);
            return $this->toResponse($result);
        }

        list($class, $method) = explode('@', (string)$action);
        $fqcn = 'MenuGold\\Controllers\\' . str_replace('/', '\\', $class);
        if (!class_exists($fqcn)) {
            throw new \RuntimeException('Controlador no encontrado: ' . $fqcn);
        }
        $controller = new $fqcn();
        $controller->setRequest($request);
        if (!method_exists($controller, $method)) {
            throw new \RuntimeException('Acción no encontrada: ' . $fqcn . '::' . $method);
        }
        $result = $controller->$method($params);
        return $this->toResponse($result);
    }

    private function toResponse($result)
    {
        if ($result instanceof Response) { return $result; }
        if (is_array($result)) { return Response::json($result); }
        return Response::html((string)$result);
    }
}
