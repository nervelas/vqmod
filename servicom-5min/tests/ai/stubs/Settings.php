<?php
declare(strict_types=1);
namespace S5\Core;

/** STUB de pruebas (en memoria) con la API del contrato §2. */
class Settings
{
    public static array $data = [];
    public static function get(string $k, $default = null) { return array_key_exists($k, self::$data) ? self::$data[$k] : $default; }
    public static function set(string $k, $v, bool $secret = false): void { self::$data[$k] = $v; }
    public static function int(string $k, int $d = 0): int { return (int)self::get($k, $d); }
    public static function float(string $k, float $d = 0.0): float { return (float)self::get($k, $d); }
    public static function bool(string $k, bool $d = false): bool { return (bool)self::get($k, $d); }
}
