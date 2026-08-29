<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitador de intentos basado en base de datos (funciona en hosting compartido).
 */
final class RateLimit
{
    public static function hit(string $action, string $key, int $max, int $windowSeconds): bool
    {
        self::gc();
        $id = self::id($action, $key);
        $row = DB::one('SELECT id, hits, blocked_until, window_start FROM rate_limits WHERE bucket = ? LIMIT 1', [$id]);
        $now = time();

        if ($row && $row['blocked_until'] && strtotime((string) $row['blocked_until']) > $now) {
            return false;
        }
        if (!$row) {
            DB::insert('rate_limits', [
                'bucket'       => $id,
                'hits'         => 1,
                'window_start' => date('Y-m-d H:i:s', $now),
                'created_at'   => nowSql(),
            ]);
            return true;
        }
        $windowStart = strtotime((string) $row['window_start']);
        if (($now - $windowStart) > $windowSeconds) {
            DB::update('rate_limits', [
                'hits'          => 1,
                'window_start'  => date('Y-m-d H:i:s', $now),
                'blocked_until' => null,
            ], 'id = :id', ['id' => (int) $row['id']]);
            return true;
        }
        $hits = (int) $row['hits'] + 1;
        $data = ['hits' => $hits];
        if ($hits > $max) {
            $data['blocked_until'] = date('Y-m-d H:i:s', $now + $windowSeconds);
        }
        DB::update('rate_limits', $data, 'id = :id', ['id' => (int) $row['id']]);
        return $hits <= $max;
    }

    public static function remaining(string $action, string $key, int $max): int
    {
        $row = DB::one('SELECT hits FROM rate_limits WHERE bucket = ? LIMIT 1', [self::id($action, $key)]);
        return $row ? max(0, $max - (int) $row['hits']) : $max;
    }

    public static function blockedFor(string $action, string $key): int
    {
        $row = DB::one('SELECT blocked_until FROM rate_limits WHERE bucket = ? LIMIT 1', [self::id($action, $key)]);
        if (!$row || !$row['blocked_until']) {
            return 0;
        }
        return max(0, strtotime((string) $row['blocked_until']) - time());
    }

    public static function clear(string $action, string $key): void
    {
        DB::delete('rate_limits', 'bucket = :b', ['b' => self::id($action, $key)]);
    }

    private static function id(string $action, string $key): string
    {
        return substr(hash('sha256', $action . '|' . $key), 0, 48);
    }

    private static function gc(): void
    {
        if (random_int(1, 40) !== 1) {
            return;
        }
        DB::run('DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY) AND (blocked_until IS NULL OR blocked_until < NOW())');
    }
}
