<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Request $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return View::render($template, $data, $layout);
    }

    protected function json(array $data, int $code = 200): string
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function redirect(string $ruta): string
    {
        if (!headers_sent()) {
            header('Location: ' . (str_starts_with($ruta, 'http') ? $ruta : url($ruta)), true, 302);
        }
        return '';
    }

    protected function back(string $fallback = '/'): string
    {
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($ref !== '' && $host !== '' && str_contains($ref, $host)) {
            return $this->redirect($ref);
        }
        return $this->redirect($fallback);
    }

    protected function ok(string $mensaje): void { Session::flash('ok', $mensaje); }
    protected function error(string $mensaje): void { Session::flash('bad', $mensaje); }
    protected function aviso(string $mensaje): void { Session::flash('warn', $mensaje); }

    /** Verificacion CSRF obligatoria para POST/PUT/DELETE. */
    protected function requireCsrf(): void
    {
        $token = $this->req->raw('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::check(is_string($token) ? $token : null)) {
            Logger::warn('Token CSRF invalido', ['ruta' => $this->req->path(), 'ip' => $this->req->ip()]);
            throw new HttpException(419, 'La sesion expiro o el token de seguridad no es valido. Vuelva a intentarlo.');
        }
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            Session::set('_intento', $this->req->path());
            throw new HttpException(401, 'Debe iniciar sesion.');
        }
    }

    protected function requireRol(string ...$roles): void
    {
        $this->requireAuth();
        if (!Auth::is(...$roles)) {
            throw new HttpException(403, 'No tiene permisos para acceder a esta seccion.');
        }
    }

    protected function requirePermiso(string $permiso): void
    {
        $this->requireAuth();
        if (!Auth::can($permiso)) {
            throw new HttpException(403, 'No tiene permisos para realizar esta accion.');
        }
    }

    protected function pagina(int $porPagina = 25): array
    {
        $p = max(1, $this->req->int('p', 1));
        return [$p, $porPagina, ($p - 1) * $porPagina];
    }
}
