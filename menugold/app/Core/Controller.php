<?php
namespace MenuGold\Core;

abstract class Controller
{
    /** @var Request */
    protected $request;

    public function setRequest(Request $r)
    {
        $this->request = $r;
    }

    protected function view($template, array $data = array(), $status = 200)
    {
        return Response::html(View::render($template, $data), $status);
    }

    protected function json($data, $status = 200)
    {
        return Response::json($data, $status);
    }

    protected function ok(array $data = array())
    {
        return Response::json(array_merge(array('ok' => true), $data));
    }

    protected function fail($message, $status = 400, array $extra = array())
    {
        return Response::json(array_merge(array('ok' => false, 'error' => $message), $extra), $status);
    }

    protected function redirect($to, $status = 302)
    {
        return Response::redirect(strpos($to, 'http') === 0 ? $to : Url::to($to), $status);
    }

    protected function back($fallback = '/')
    {
        $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if ($ref !== '' && strpos($ref, Url::host()) !== false) {
            return Response::redirect($ref);
        }
        return $this->redirect($fallback);
    }

    protected function notFound($message = 'Página no encontrada')
    {
        return Response::html(View::render('errors/404', array('message' => $message)), 404);
    }

    protected function denied($message = 'No tienes permiso para ver esta página')
    {
        return Response::html(View::render('errors/403', array('message' => $message)), 403);
    }

    /** Valida el token CSRF de una petición POST; devuelve null si todo está bien. */
    protected function guardCsrf()
    {
        $token = $this->request->str('_token', '');
        if ($token === '') {
            $token = (string)$this->request->header('X-CSRF-Token');
        }
        if (Csrf::check($token)) {
            return null;
        }
        if ($this->request->wantsJson()) {
            return $this->fail('Tu sesión expiró. Recarga la página e inténtalo de nuevo.', 419);
        }
        Session::flash('error', 'Tu sesión expiró. Vuelve a intentarlo.');
        return $this->back();
    }

    protected function flash($type, $message)
    {
        Session::flash($type, $message);
    }
}
