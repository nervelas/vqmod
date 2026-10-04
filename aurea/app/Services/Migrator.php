<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;

/** Migraciones numeradas: database/migrations/NNN_nombre.sql (se aplican una sola vez, en orden). */
final class Migrator
{
    public static function pending(): array
    {
        $applied = [];
        try {
            foreach (Db::all('SELECT name FROM migrations') as $r) { $applied[$r['name']] = true; }
        } catch (\Throwable $e) { /* la tabla aún no existe */ }
        $files = glob(AUREA_ROOT . '/database/migrations/*.sql') ?: [];
        sort($files);
        return array_values(array_filter($files, static fn($f) => !isset($applied[basename($f)])));
    }

    /** @return string[] nombres aplicados */
    public static function run(): array
    {
        $done = [];
        foreach (self::pending() as $file) {
            $sql = (string)file_get_contents($file);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
            foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== '') { Db::pdo()->exec($stmt); }
            }
            Db::exec('INSERT INTO migrations (name, applied_at) VALUES (?,?)', [basename($file), date('Y-m-d H:i:s')]);
            $done[] = basename($file);
        }
        return $done;
    }
}
