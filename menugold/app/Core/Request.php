<?php
declare(strict_types=1);

namespace MenuGold\Core;

final class Request
{
    private static ?array $json = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool { return self::method() === 'POST'; }

    public static function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
    }

    /** @return array<string,mixed> */
    public static function json(): array
    {
        if (self::$json !== null) return self::$json;
        $raw = file_get_contents('php://input') ?: '';
        $d = json_decode($raw, true);
        self::$json = is_array($d) ? $d : [];
        return self::$json;
    }

    /** Lee de POST, GET o cuerpo JSON. */
    public static function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) return self::clean($_POST[$key]);
        $j = self::json();
        if (array_key_exists($key, $j)) return self::clean($j[$key]);
        if (array_key_exists($key, $_GET)) return self::clean($_GET[$key]);
        return $default;
    }

    public static function str(string $key, string $default = '', int $max = 5000): string
    {
        $v = self::input($key, $default);
        if (is_array($v)) return $default;
        $v = trim((string)$v);
        // Elimina caracteres de control salvo tab y salto de linea
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        return mb_substr($v, 0, $max);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::input($key, $default);
        return is_numeric($v) ? (int)$v : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::input($key, $default);
        if (is_string($v)) $v = str_replace([',', ' '], ['', ''], $v);
        return is_numeric($v) ? round((float)$v, 4) : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::input($key, null);
        if ($v === null) return $default;
        return in_array($v, [1, '1', true, 'true', 'on', 'si', 'yes'], true);
    }

    public static function arr(string $key, array $default = []): array
    {
        $v = self::input($key, $default);
        return is_array($v) ? $v : $default;
    }

    public static function email(string $key): string
    {
        $v = mb_strtolower(self::str($key, '', 190));
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
    }

    /** Uno de una lista blanca. */
    public static function enum(string $key, array $allowed, string $default = ''): string
    {
        $v = self::str($key);
        return in_array($v, $allowed, true) ? $v : $default;
    }

    public static function date(string $key, string $default = ''): string
    {
        $v = self::str($key, $default, 20);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
    }

    public static function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        return $f;
    }

    private static function clean($v)
    {
        return $v;
    }

    public static function userAgent(): string
    {
        return mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function fullUrl(): string
    {
        return App::baseUrl() . ($_SERVER['REQUEST_URI'] ?? '/');
    }
}
