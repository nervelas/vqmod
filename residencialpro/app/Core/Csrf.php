<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function valido(?string $token): bool
    {
        $esperado = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $esperado !== '' && hash_equals((string) $esperado, $token);
    }

    public static function verificar(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!is_string($token)) {
            $j = Peticion::json();
            $token = isset($j['csrf_token']) && is_string($j['csrf_token']) ? $j['csrf_token'] : null;
        }
        if (!self::valido($token)) {
            Log::aviso('CSRF inválido desde ' . Peticion::ip() . ' en ' . Peticion::uri());
            Respuesta::abortar(419, 'La sesión expiró o el formulario no es válido. Vuelva a intentarlo.');
        }
    }
}
