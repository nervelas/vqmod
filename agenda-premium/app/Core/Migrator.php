<?php
declare(strict_types=1);

namespace App\Core;

/** Migraciones numeradas en database/migrations: NNN_nombre.sql o NNN_nombre.php (devuelve una función). */
final class Migrator
{
    public static function dir(): string
    {
        return APP_ROOT . '/database/migrations';
    }

    public static function files(): array
    {
        $files = array_merge(glob(self::dir() . '/*.sql') ?: [], glob(self::dir() . '/*.php') ?: []);
        sort($files, SORT_STRING);
        return $files;
    }

    public static function applied(): array
    {
        try {
            return Db::col('SELECT name FROM migrations');
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function pending(): array
    {
        $done = array_flip(self::applied());
        $out = [];
        foreach (self::files() as $f) {
            if (!isset($done[basename($f)])) {
                $out[] = $f;
            }
        }
        return $out;
    }

    /** @return string[] nombres aplicados */
    public static function run(): array
    {
        $ran = [];
        foreach (self::pending() as $file) {
            $name = basename($file);
            if (substr($file, -4) === '.php') {
                $fn = require $file;
                if (is_callable($fn)) {
                    $fn();
                }
            } else {
                $sql = (string) file_get_contents($file);
                foreach (self::split($sql) as $stmt) {
                    Db::pdo()->exec($stmt);
                }
            }
            Db::q('INSERT INTO migrations (name, applied_at) VALUES (?, ?)', [$name, Clock::utc()]);
            $ran[] = $name;
        }
        return $ran;
    }

    /** Divide un script SQL en sentencias (sin ; dentro de cadenas). Ignora comentarios de línea. */
    public static function split(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $clean = [];
        foreach ($lines as $l) {
            if (preg_match('/^\s*--/', $l)) {
                continue;
            }
            $clean[] = $l;
        }
        $parts = preg_split('/;\s*(?:\R|$)/', implode("\n", $clean)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }
}
