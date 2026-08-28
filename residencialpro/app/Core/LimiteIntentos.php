<?php
declare(strict_types=1);

namespace App\Core;

final class LimiteIntentos
{
    /** Devuelve true si la acción está permitida. */
    public static function permitido(string $llave, int $maximo, int $minutos): bool
    {
        return self::contar($llave, $minutos) < $maximo;
    }

    public static function contar(string $llave, int $minutos): int
    {
        try {
            return (int) DB::valor(
                'SELECT COUNT(*) FROM intentos_acceso WHERE llave = :l AND creado_en > (NOW() - INTERVAL :m MINUTE)',
                ['l' => $llave, 'm' => $minutos],
                0
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function registrar(string $llave): void
    {
        try {
            DB::insertar('intentos_acceso', ['llave' => $llave, 'ip' => Peticion::ip()]);
            if (random_int(1, 25) === 1) {
                DB::q('DELETE FROM intentos_acceso WHERE creado_en < (NOW() - INTERVAL 1 DAY)');
            }
        } catch (\Throwable $e) {
            Log::error('LimiteIntentos: ' . $e->getMessage());
        }
    }

    public static function limpiar(string $llave): void
    {
        try {
            DB::eliminar('intentos_acceso', 'llave = :l', ['l' => $llave]);
        } catch (\Throwable) {
        }
    }

    public static function minutosRestantes(string $llave, int $minutos): int
    {
        try {
            $ultimo = DB::valor(
                'SELECT creado_en FROM intentos_acceso WHERE llave = :l ORDER BY id DESC LIMIT 1',
                ['l' => $llave]
            );
            if (!$ultimo) {
                return 0;
            }
            $resta = $minutos * 60 - (time() - strtotime((string) $ultimo));
            return max(1, (int) ceil($resta / 60));
        } catch (\Throwable) {
            return $minutos;
        }
    }
}
