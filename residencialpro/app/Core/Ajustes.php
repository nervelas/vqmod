<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Ajustes del condominio guardados en base de datos (clave/valor).
 */
final class Ajustes
{
    private static ?array $cache = null;

    public static function todos(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            foreach (DB::todos('SELECT clave, valor FROM ajustes') as $f) {
                self::$cache[$f['clave']] = $f['valor'];
            }
        } catch (\Throwable $e) {
            Log::error('Ajustes: ' . $e->getMessage());
        }
        return self::$cache;
    }

    public static function get(string $clave, string $porDefecto = ''): string
    {
        $t = self::todos();
        $v = $t[$clave] ?? null;
        return ($v === null || $v === '') ? $porDefecto : (string) $v;
    }

    public static function num(string $clave, float $porDefecto = 0): float
    {
        $v = self::get($clave, '');
        return $v === '' ? $porDefecto : (float) $v;
    }

    public static function esVerdadero(string $clave, bool $porDefecto = false): bool
    {
        $v = self::get($clave, $porDefecto ? '1' : '0');
        return in_array(strtolower($v), ['1', 'si', 'sí', 'true', 'on'], true);
    }

    public static function set(string $clave, string $valor, string $grupo = 'general'): void
    {
        DB::q(
            'INSERT INTO ajustes (clave, valor, grupo) VALUES (:c, :v, :g)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), grupo = VALUES(grupo)',
            ['c' => $clave, 'v' => $valor, 'g' => $grupo]
        );
        self::$cache[$clave] = $valor;
    }

    public static function setVarios(array $pares, string $grupo = 'general'): void
    {
        foreach ($pares as $k => $v) {
            self::set((string) $k, (string) $v, $grupo);
        }
    }

    public static function limpiarCache(): void
    {
        self::$cache = null;
    }
}
