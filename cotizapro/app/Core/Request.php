<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public static function path(): string
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = App::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '//' ? '/' : $path;
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_array($v)) {
            return $default;
        }
        return trim(str_replace(["\0", "\r"], '', (string) $v));
    }

    public static function raw(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_array($v) ? $default : (string) $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        return is_scalar($v) && $v !== '' ? (int) $v : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        if (!is_scalar($v) || $v === '') {
            return $default;
        }
        return (float) str_replace([',', ' '], '', (string) $v);
    }

    public static function bool(string $key): bool
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        return in_array((string) $v, ['1', 'on', 'true', 'si', 'yes'], true);
    }

    public static function arr(string $key): array
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    public static function email(string $key): string
    {
        $v = mb_strtolower(self::str($key));
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
    }

    public static function json(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $raw = file_get_contents('php://input') ?: '';
        $d = json_decode($raw, true);
        return $cache = is_array($d) ? $d : [];
    }

    public static function page(int $per = 25): array
    {
        $p = max(1, self::int('p', 1));
        return [$p, $per, ($p - 1) * $per];
    }
}
