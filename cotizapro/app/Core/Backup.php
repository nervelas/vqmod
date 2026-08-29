<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Respaldo de la base de datos en SQL plano, sin usar exec/mysqldump
 * (los hostings compartidos suelen tenerlos deshabilitados).
 */
final class Backup
{
    public static function dir(): string
    {
        $d = STORAGE_PATH . '/backups';
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function create(string $kind = 'manual'): ?array
    {
        $dir  = self::dir();
        $name = 'cotizapro-' . date('Y-m-d-His') . '.sql';
        $path = $dir . '/' . $name;
        $fh = @fopen($path, 'wb');
        if (!$fh) {
            ErrorHandler::log('No se pudo crear el archivo de respaldo en ' . $path);
            return null;
        }
        fwrite($fh, "-- CotizaPro B2B · respaldo " . date('c') . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $pdo = DB::pdo();
        $tables = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_NUM) as $r) {
            $tables[] = (string) $r[0];
        }
        foreach ($tables as $t) {
            $safe = DB::ident($t);
            $create = $pdo->query("SHOW CREATE TABLE `{$safe}`")->fetch(\PDO::FETCH_NUM);
            fwrite($fh, "DROP TABLE IF EXISTS `{$safe}`;\n" . ($create[1] ?? '') . ";\n\n");

            $st = $pdo->query("SELECT * FROM `{$safe}`");
            $buffer = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } elseif (is_int($v) || is_float($v)) {
                        $vals[] = (string) $v;
                    } else {
                        $vals[] = $pdo->quote((string) $v);
                    }
                }
                $buffer[] = '(' . implode(',', $vals) . ')';
                if (count($buffer) >= 200) {
                    fwrite($fh, "INSERT INTO `{$safe}` VALUES\n" . implode(",\n", $buffer) . ";\n");
                    $buffer = [];
                }
            }
            if ($buffer) {
                fwrite($fh, "INSERT INTO `{$safe}` VALUES\n" . implode(",\n", $buffer) . ";\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        // Comprime si el servidor tiene zlib (casi siempre).
        if (function_exists('gzencode')) {
            $gz = $path . '.gz';
            $data = (string) file_get_contents($path);
            if (@file_put_contents($gz, gzencode($data, 6)) !== false) {
                @unlink($path);
                $path = $gz;
                $name .= '.gz';
            }
        }
        @chmod($path, 0600);
        $size = (int) filesize($path);
        DB::insert('backups', ['filename' => $name, 'size' => $size, 'kind' => $kind, 'created_at' => nowSql()]);
        self::prune();
        return ['name' => $name, 'path' => $path, 'size' => $size];
    }

    /** Conserva los 10 respaldos más recientes. */
    public static function prune(int $keep = 10): void
    {
        $rows = DB::all('SELECT * FROM backups ORDER BY id DESC');
        foreach (array_slice($rows, $keep) as $old) {
            $f = self::dir() . '/' . basename((string) $old['filename']);
            if (is_file($f)) {
                @unlink($f);
            }
            DB::delete('backups', 'id = :id', ['id' => (int) $old['id']]);
        }
    }

    public static function list(): array
    {
        $rows = DB::all('SELECT * FROM backups ORDER BY id DESC LIMIT 40');
        foreach ($rows as &$r) {
            $r['exists'] = is_file(self::dir() . '/' . basename((string) $r['filename']));
        }
        return $rows;
    }
}
