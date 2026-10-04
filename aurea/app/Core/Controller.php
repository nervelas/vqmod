<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Base de controladores: acceso a la petición y utilidades de respuesta. */
abstract class Controller
{
    protected Request $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    protected function html(string $layout, string $tpl, array $data = [], int $status = 200): Response
    {
        return Response::html(View::page($layout, $tpl, $data), $status);
    }

    protected function json($data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(strpos($path, 'http') === 0 ? $path : url($path));
    }

    protected function notFound(): Response
    {
        return App::notFound($this->req);
    }

    protected function flash(string $type, string $msg): void
    {
        $_SESSION['flash'][] = [$type, $msg];
    }

    protected function id(string $key = 'id'): int
    {
        return (int)($this->req->params[$key] ?? 0);
    }
}
