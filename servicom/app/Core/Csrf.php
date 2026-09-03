<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        App::startSession();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function check(): bool
    {
        App::startSession();
        $sent = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $have = (string) ($_SESSION['_csrf'] ?? '');
        return $have !== '' && $sent !== '' && hash_equals($have, $sent);
    }

    /** Aborta la petición si el token no es válido. */
    public static function verify(): void
    {
        if (self::check()) {
            return;
        }
        ErrorHandler::log('CSRF inválido', ['ip' => App::ip(), 'uri' => $_SERVER['REQUEST_URI'] ?? '']);
        if (Request::isAjax()) {
            jsonOut(['ok' => false, 'error' => 'Sesión expirada. Recargue la página.'], 419);
        }
        Flash::error('Su sesión expiró por seguridad. Vuelva a intentarlo.');
        http_response_code(419);
        ErrorHandler::render(419);
    }
}
