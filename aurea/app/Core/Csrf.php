<?php
declare(strict_types=1);

namespace Aurea\Core;

/**
 * CSRF. Panel: token ligado a la sesión. Público: token firmado (HMAC) sin estado,
 * necesario para que el widget embebido funcione dentro de iframes de terceros.
 */
final class Csrf
{
    public static function token(string $scope = 'admin'): string
    {
        if ($scope === 'public') {
            return self::signed();
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    private static function signed(): string
    {
        $payload = time() . '.' . bin2hex(random_bytes(8));
        return $payload . '.' . hash_hmac('sha256', $payload, (string)config('app_key', ''));
    }

    public static function valid(?string $token, string $scope = 'admin'): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        if ($scope === 'public') {
            $p = explode('.', $token);
            if (count($p) !== 3 || !ctype_digit($p[0])) { return false; }
            $age = time() - (int)$p[0];
            if ($age < -60 || $age > 21600) { return false; }
            return hash_equals(hash_hmac('sha256', $p[0] . '.' . $p[1], (string)config('app_key', '')), $p[2]);
        }
        return !empty($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], $token);
    }

    public static function check(Request $r, string $scope = 'admin'): bool
    {
        $t = $r->post['_csrf'] ?? $r->header('X-CSRF-Token');
        return self::valid(is_string($t) ? $t : null, $scope);
    }
}
