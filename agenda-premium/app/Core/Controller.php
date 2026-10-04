<?php
declare(strict_types=1);

namespace App\Core;

/** Base de los controladores. Cada acción recibe (Request $req, array $params) y devuelve Response|string|array. */
abstract class Controller
{
    protected function view(string $tpl, array $data = [], ?string $layout = null): Response
    {
        return Response::html(View::render($tpl, $data, $layout));
    }

    protected function json($data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path, array $query = []): Response
    {
        return Response::redirect(url($path, $query));
    }

    protected function flash(string $type, string $msg): void
    {
        if (Session::started()) {
            Session::flash($type, $msg);
        }
    }

    protected function abort(int $status, string $msg = ''): void
    {
        throw new HttpException($status, $msg);
    }

    /** Respuesta de error de validación: JSON si es AJAX, si no vuelve con mensaje. */
    protected function fail(Request $req, string $msg, string $backPath, int $status = 422): Response
    {
        if ($req->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $msg], $status);
        }
        $this->flash('error', $msg);
        return Response::redirect(url($backPath));
    }
}
