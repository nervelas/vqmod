<?php
declare(strict_types=1);

namespace App\Core;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        App::startSession();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function ok(string $m): void
    {
        self::set('ok', $m);
    }

    public static function error(string $m): void
    {
        self::set('error', $m);
    }

    public static function warn(string $m): void
    {
        self::set('warn', $m);
    }

    public static function pull(): array
    {
        App::startSession();
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : [];
    }

    /** Guarda los datos del formulario para repoblarlo tras un error. */
    public static function keep(array $data): void
    {
        App::startSession();
        unset($data['_token'], $data['password'], $data['password2'], $data['captcha']);
        $_SESSION['_old'] = $data;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        App::startSession();
        return $_SESSION['_old'][$key] ?? $default;
    }

    public static function clearOld(): void
    {
        App::startSession();
        unset($_SESSION['_old']);
    }
}
