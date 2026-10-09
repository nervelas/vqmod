<?php
declare(strict_types=1);
namespace S5\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function valid(?string $given): bool
    {
        Session::start();
        return !empty($_SESSION['csrf']) && is_string($given) && $given !== '' && hash_equals($_SESSION['csrf'], $given);
    }

    /** Verifica cabecera X-CSRF o campo _csrf. */
    public static function check(): bool
    {
        $h = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['_csrf'] ?? '');
        return self::valid(is_string($h) ? $h : '');
    }
}
