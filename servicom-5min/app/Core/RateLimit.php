<?php
declare(strict_types=1);
namespace S5\Core;

/** Limitador de tasa en BD (ventana fija). */
final class RateLimit
{
    /** @return bool true si está permitido (y cuenta el intento) */
    public static function hit(string $key, int $max, int $windowSec): bool
    {
        $k = substr(hash('sha256', $key), 0, 40);
        $now = time();
        $row = Db::one('SELECT cnt, win_start FROM ' . Db::t('rate_limits') . ' WHERE k=?', [$k]);
        if (!$row || ($now - (int) $row['win_start']) >= $windowSec) {
            Db::q('REPLACE INTO ' . Db::t('rate_limits') . ' (k,cnt,win_start) VALUES (?,1,?)', [$k, $now]);
            return true;
        }
        if ((int) $row['cnt'] >= $max) {
            return false;
        }
        Db::q('UPDATE ' . Db::t('rate_limits') . ' SET cnt=cnt+1 WHERE k=?', [$k]);
        return true;
    }

    public static function count(string $key, int $windowSec): int
    {
        $k = substr(hash('sha256', $key), 0, 40);
        $row = Db::one('SELECT cnt, win_start FROM ' . Db::t('rate_limits') . ' WHERE k=?', [$k]);
        if (!$row || (time() - (int) $row['win_start']) >= $windowSec) {
            return 0;
        }
        return (int) $row['cnt'];
    }

    public static function reset(string $key): void
    {
        Db::q('DELETE FROM ' . Db::t('rate_limits') . ' WHERE k=?', [substr(hash('sha256', $key), 0, 40)]);
    }

    public static function purge(): void
    {
        Db::q('DELETE FROM ' . Db::t('rate_limits') . ' WHERE win_start < ?', [time() - 172800]);
    }
}
