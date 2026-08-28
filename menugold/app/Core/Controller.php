<?php
declare(strict_types=1);

namespace MenuGold\Core;

abstract class Controller
{
    /** Renderiza una vista con layout. */
    protected function view(string $template, array $data = [], string $layout = 'panel'): void
    {
        View::share('usuario', Auth::user());
        View::share('restaurante', App::restaurant());
        View::share('flashes', flash());
        View::display($template, $data, $layout);
    }

    protected function json($data, int $status = 200): void
    {
        json_out($data, $status);
    }

    protected function ok(array $extra = [], string $mensaje = ''): void
    {
        json_out(['ok' => true, 'mensaje' => $mensaje] + $extra);
    }

    protected function fail(string $mensaje, int $status = 422, array $extra = []): void
    {
        json_out(['ok' => false, 'error' => $mensaje] + $extra, $status);
    }

    /** Verifica CSRF en peticiones POST. */
    protected function csrf(): void
    {
        if (Request::isPost()) Csrf::enforce();
    }

    /** Guarda datos del formulario para repoblarlo tras un error. */
    protected function keepOld(array $data): void
    {
        $_SESSION['_old'] = $data;
    }

    protected function back(string $fallback = '/'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref && strpos($ref, App::baseUrl()) === 0) {
            header('Location: ' . $ref);
            exit;
        }
        redirect($fallback);
    }

    /** Descarga de archivo con cabeceras seguras. */
    protected function download(string $contenido, string $nombre, string $mime = 'application/octet-stream'): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        $nombre = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?? 'archivo';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($contenido));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $contenido;
        exit;
    }

    protected function inline(string $contenido, string $nombre, string $mime): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $contenido;
        exit;
    }
}
