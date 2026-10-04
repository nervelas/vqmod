<?php
declare(strict_types=1);

namespace App\Core;

/** Caché simple en archivos (storage/cache) con caducidad y "versión" global de disponibilidad. */
final class Cache
{
    private static function file(string $key): string
    {
        return APP_ROOT . '/storage/cache/' . hash('sha256', $key) . '.cache';
    }

    public static function get(string $key)
    {
        $f = self::file($key);
        if (!is_file($f)) {
            return null;
        }
        $raw = @file_get_contents($f);
        if ($raw === false) {
            return null;
        }
        $d = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($d) || ($d['exp'] ?? 0) < Clock::now()) {
            @unlink($f);
            return null;
        }
        return $d['val'];
    }

    public static function put(string $key, $value, int $ttl = 60): void
    {
        $dir = APP_ROOT . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $tmp = self::file($key) . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, serialize(['exp' => Clock::now() + $ttl, 'val' => $value])) !== false) {
            @rename($tmp, self::file($key));
        }
    }

    public static function forget(string $key): void
    {
        @unlink(self::file($key));
    }

    /** Invalida toda la caché de disponibilidad (cualquier reserva/horario/ausencia la cambia). */
    public static function bumpAvailability(): void
    {
        Settings::set('avail_version', (string) (microtime(true) * 1000 | 0));
    }

    public static function availabilityVersion(): string
    {
        return (string) Settings::get('avail_version', '1');
    }

    public static function clearAll(): int
    {
        $n = 0;
        foreach (glob(APP_ROOT . '/storage/cache/*.cache') ?: [] as $f) {
            if (@unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
