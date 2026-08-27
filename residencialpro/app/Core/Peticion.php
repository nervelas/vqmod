<?php
declare(strict_types=1);

namespace App\Core;

final class Peticion
{
    public static function metodo(): string
    {
        $m = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($m === 'POST' && isset($_POST['_metodo'])) {
            $m = strtoupper((string) $_POST['_metodo']);
        }
        return $m;
    }

    public static function uri(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        $uri  = rawurldecode($uri);
        $base = Url::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', (string) $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function esHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return ((int) ($_SERVER['SERVER_PORT'] ?? 80)) === 443;
    }

    public static function esAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public static function agente(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function texto(string $campo, string $porDefecto = ''): string
    {
        $v = $_POST[$campo] ?? $_GET[$campo] ?? $porDefecto;
        if (is_array($v)) {
            return $porDefecto;
        }
        $v = str_replace("\0", '', (string) $v);
        return trim($v);
    }

    public static function entero(string $campo, int $porDefecto = 0): int
    {
        $v = $_POST[$campo] ?? $_GET[$campo] ?? null;
        return (is_scalar($v) && $v !== '') ? (int) $v : $porDefecto;
    }

    public static function decimal(string $campo, float $porDefecto = 0.0): float
    {
        $v = self::texto($campo, '');
        if ($v === '') {
            return $porDefecto;
        }
        $v = str_replace([',', 'Q', ' '], '', $v);
        return is_numeric($v) ? (float) $v : $porDefecto;
    }

    public static function bool(string $campo): bool
    {
        $v = $_POST[$campo] ?? $_GET[$campo] ?? null;
        return in_array((string) $v, ['1', 'on', 'true', 'si', 'sí'], true);
    }

    public static function arreglo(string $campo): array
    {
        $v = $_POST[$campo] ?? $_GET[$campo] ?? [];
        return is_array($v) ? $v : [];
    }

    public static function json(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $raw   = file_get_contents('php://input') ?: '';
        $d     = json_decode($raw, true);
        $cache = is_array($d) ? $d : [];
        return $cache;
    }
}
