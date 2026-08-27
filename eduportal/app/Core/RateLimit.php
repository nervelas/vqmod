<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitador de intentos. Login: 5 intentos -> bloqueo 15 minutos por IP y por usuario.
 */
final class RateLimit
{
    public const MAX_INTENTOS = 5;
    public const BLOQUEO_MIN  = 15;

    public static function hit(string $llave, bool $exito, ?string $ip = null): void
    {
        Database::run(
            'INSERT INTO login_attempts (llave, exito, ip) VALUES (:l, :e, :i)',
            ['l' => substr($llave, 0, 190), 'e' => $exito ? 1 : 0, 'i' => $ip]
        );
    }

    public static function failures(string $llave): int
    {
        $desde = date('Y-m-d H:i:s', time() - self::BLOQUEO_MIN * 60);
        return (int)Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE llave = :l AND exito = 0 AND creado_en >= :d',
            ['l' => substr($llave, 0, 190), 'd' => $desde],
            0
        );
    }

    public static function blocked(string $llave): bool
    {
        return self::failures($llave) >= self::MAX_INTENTOS;
    }

    public static function remaining(string $llave): int
    {
        return max(0, self::MAX_INTENTOS - self::failures($llave));
    }

    public static function clear(string $llave): void
    {
        Database::run('DELETE FROM login_attempts WHERE llave = :l', ['l' => substr($llave, 0, 190)]);
    }

    public static function purge(): void
    {
        Database::run('DELETE FROM login_attempts WHERE creado_en < :d', ['d' => date('Y-m-d H:i:s', time() - 86400)]);
    }

    /** Limitador simple en sesion para formularios publicos. */
    public static function throttleSession(string $bucket, int $max, int $segundos): bool
    {
        $now = time();
        $key = '_thr_' . $bucket;
        $data = $_SESSION[$key] ?? ['t' => $now, 'n' => 0];
        if (($now - (int)$data['t']) > $segundos) {
            $data = ['t' => $now, 'n' => 0];
        }
        $data['n']++;
        $_SESSION[$key] = $data;
        return $data['n'] <= $max;
    }
}
