<?php
declare(strict_types=1);

namespace App\Core;

/** Límite de frecuencia por "cubeta" (IP, clave de API, etc.) con ventanas fijas en la tabla rate_limits. */
final class RateLimiter
{
    /** Registra un intento. Devuelve true si está dentro del límite. */
    public static function hit(string $bucket, int $limit, int $windowSeconds): bool
    {
        $bucket = substr($bucket, 0, 190);
        $win = intdiv(Clock::now(), $windowSeconds) * $windowSeconds;
        Db::q('INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', [$bucket, $win]);
        $hits = (int) Db::val('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?', [$bucket, $win]);
        return $hits <= $limit;
    }

    /** Intentos acumulados en la ventana actual sin registrar uno nuevo. */
    public static function count(string $bucket, int $windowSeconds): int
    {
        $win = intdiv(Clock::now(), $windowSeconds) * $windowSeconds;
        return (int) Db::val('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?', [substr($bucket, 0, 190), $win]);
    }

    public static function purge(): int
    {
        return Db::exec('DELETE FROM rate_limits WHERE window_start < ?', [Clock::now() - 86400]);
    }
}
