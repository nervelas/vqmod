<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Limitador de frecuencia por IP respaldado en base de datos. */
final class RateLimit
{
    /** Registra un evento y devuelve false si se excedió el límite (max eventos en $seconds). */
    public static function hit(string $bucket, string $ip, int $max, int $seconds): bool
    {
        if (config('rate_limit_disabled', false)) { return true; } // solo para pruebas automatizadas
        $since = date('Y-m-d H:i:s', time() - $seconds);
        $n = (int)Db::val('SELECT COUNT(*) FROM rate_limits WHERE bucket=? AND ip=? AND created_at>=?', [$bucket, $ip, $since]);
        if ($n >= $max) { return false; }
        Db::insert('rate_limits', ['bucket' => $bucket, 'ip' => $ip, 'created_at' => date('Y-m-d H:i:s')]);
        return true;
    }

    public static function purge(): void
    {
        Db::exec('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        Db::exec('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 7)]);
    }
}
