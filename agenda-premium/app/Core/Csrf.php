<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CSRF.
 *  - Panel / áreas autenticadas: token ligado a la sesión (Csrf::token / Csrf::check).
 *  - Páginas públicas (sin cookies): token firmado con HMAC, ligado a un alcance y con caducidad (Csrf::publicToken / Csrf::checkPublic).
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = Str::token(16);
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(Request $req): bool
    {
        $sent = (string) ($req->post['_csrf'] ?? $req->header('X-CSRF-Token') ?? $req->json()['_csrf'] ?? '');
        $real = (string) ($_SESSION['_csrf'] ?? '');
        return $real !== '' && $sent !== '' && hash_equals($real, $sent);
    }

    /** Token firmado sin sesión: ts.nonce.firma */
    public static function publicToken(string $scope, ?int $now = null): string
    {
        $ts = (string) ($now ?? Clock::now());
        $nonce = Str::token(6);
        return $ts . '.' . $nonce . '.' . substr(Crypto::sign('csrf|' . $scope . '|' . $ts . '|' . $nonce), 0, 40);
    }

    public static function checkPublic(Request $req, string $scope, int $ttl = 43200): bool
    {
        $sent = (string) ($req->post['_csrf'] ?? $req->header('X-CSRF-Token') ?? $req->json()['_csrf'] ?? '');
        $p = explode('.', $sent);
        if (count($p) !== 3 || !ctype_digit($p[0])) {
            return false;
        }
        $age = Clock::now() - (int) $p[0];
        if ($age < -60 || $age > $ttl) {
            return false;
        }
        $exp = substr(Crypto::sign('csrf|' . $scope . '|' . $p[0] . '|' . $p[1]), 0, 40);
        return hash_equals($exp, $p[2]);
    }
}
