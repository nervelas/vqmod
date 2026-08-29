<?php
namespace MenuGold\Core;

/**
 * Limitador por ventana deslizante almacenado en base de datos.
 * Se usa en login, creación de pedidos y llamadas al mesero.
 */
final class RateLimiter
{
    /**
     * @return bool true si la acción está permitida
     */
    public static function attempt($bucket, $maxHits, $windowSeconds, $identifier = null)
    {
        $id = $identifier !== null ? $identifier : Security::clientIp();
        $key = substr($bucket . '|' . $id, 0, 190);
        $now = time();
        try {
            $row = DB::first('SELECT id, hits, window_start FROM rate_limits WHERE bucket_key = :k LIMIT 1', array('k' => $key));
            if (!$row) {
                DB::insert('rate_limits', array('bucket_key' => $key, 'hits' => 1, 'window_start' => $now));
                return true;
            }
            if ($now - (int)$row['window_start'] >= $windowSeconds) {
                DB::update('rate_limits', array('hits' => 1, 'window_start' => $now), 'id = :id', array('id' => (int)$row['id']));
                return true;
            }
            if ((int)$row['hits'] >= $maxHits) {
                return false;
            }
            DB::update('rate_limits', array('hits' => (int)$row['hits'] + 1), 'id = :id', array('id' => (int)$row['id']));
            return true;
        } catch (\Throwable $e) {
            Logger::warn('RateLimiter: ' . $e->getMessage());
            return true; // nunca bloquea el servicio por un fallo de la tabla auxiliar
        }
    }

    public static function retryAfter($bucket, $windowSeconds, $identifier = null)
    {
        $id = $identifier !== null ? $identifier : Security::clientIp();
        $key = substr($bucket . '|' . $id, 0, 190);
        $start = (int)DB::value('SELECT window_start FROM rate_limits WHERE bucket_key = :k', array('k' => $key), 0);
        $left = $windowSeconds - (time() - $start);
        return $left > 0 ? $left : 0;
    }

    public static function clear($bucket, $identifier = null)
    {
        $id = $identifier !== null ? $identifier : Security::clientIp();
        $key = substr($bucket . '|' . $id, 0, 190);
        try { DB::delete('rate_limits', 'bucket_key = :k', array('k' => $key)); } catch (\Throwable $e) {}
    }

    public static function prune()
    {
        try { DB::run('DELETE FROM rate_limits WHERE window_start < :t', array('t' => time() - 86400)); } catch (\Throwable $e) {}
    }
}
