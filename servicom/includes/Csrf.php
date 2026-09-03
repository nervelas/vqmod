<?php
declare(strict_types=1);

/** Proteccion CSRF por token de sesion. */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function check(?string $token = null): bool
    {
        $token = $token ?? (string) ($_POST['_token'] ?? '');
        $valid = $_SESSION[self::KEY] ?? '';
        return is_string($valid) && $valid !== '' && hash_equals($valid, $token);
    }

    /** Aborta la peticion si el token no es valido. */
    public static function verify(): void
    {
        if (!self::check()) {
            http_response_code(419);
            exit('Sesion expirada o token invalido. Vuelva a cargar la pagina.');
        }
    }
}
