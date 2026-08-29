<?php
namespace MenuGold\Core;

final class Csrf
{
    const KEY = '_csrf';

    public static function token()
    {
        if (!Session::has(self::KEY)) {
            Session::set(self::KEY, bin2hex(random_bytes(32)));
        }
        return Session::get(self::KEY);
    }

    public static function check($token)
    {
        $stored = Session::get(self::KEY);
        if (!is_string($stored) || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($stored, $token);
    }

    public static function field()
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Rota el token tras acciones sensibles (login, cambio de contraseña). */
    public static function rotate()
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }
}
