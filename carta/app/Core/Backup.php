<?php
namespace MenuGold\Core;

use PDO;

/**
 * Respaldo de la base de datos en PHP puro: los hosting compartidos
 * casi nunca dejan ejecutar mysqldump.
 */
final class Backup
{
    public static function dir()
    {
        $dir = MG_STORAGE . '/backups';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        return $dir;
    }

    /** @return string ruta del archivo creado */
    public static function create($keep = 8)
    {
        $dir = self::dir();
        if (!is_writable($dir)) {
            throw new \RuntimeException('La carpeta /storage/backups no tiene permiso de escritura.');
        }
        $name = 'menugold-' . date('Y-m-d-His') . '.sql';
        $path = $dir . '/' . $name;

        $fh = fopen($path, 'w');
        if (!$fh) { throw new \RuntimeException('No se pudo crear el archivo de respaldo.'); }

        fwrite($fh, "-- MenúGold · respaldo " . date('Y-m-d H:i:s') . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        // Solo las tablas de MenúGold: la base puede estar compartida con
        // otras aplicaciones y no nos corresponde volcarlas.
        $tables = array_values(array_filter(DB::column('SHOW TABLES'), function ($t) {
            return strpos((string)$t, 'mg_') === 0;
        }));
        foreach ($tables as $table) {
            $create = DB::first('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`');
            $ddl = null;
            foreach ((array)$create as $k => $v) {
                if (stripos($k, 'Create Table') !== false) { $ddl = $v; break; }
            }
            fwrite($fh, "DROP TABLE IF EXISTS `" . $table . "`;\n");
            if ($ddl) { fwrite($fh, $ddl . ";\n"); }

            $st = DB::pdo()->prepare('SELECT * FROM `' . str_replace('`', '', $table) . '`');
            $st->execute();
            $batch = array();
            $columns = null;
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                if ($columns === null) { $columns = array_keys($row); }
                $values = array();
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : DB::pdo()->quote((string)$value);
                }
                $batch[] = '(' . implode(',', $values) . ')';
                if (count($batch) >= 200) {
                    self::flush($fh, $table, $columns, $batch);
                    $batch = array();
                }
            }
            if ($batch) { self::flush($fh, $table, $columns, $batch); }
            fwrite($fh, "\n");
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        // Se comprime si el servidor lo permite: los respaldos ocupan mucho menos.
        if (function_exists('gzencode')) {
            $gz = $path . '.gz';
            $data = @gzencode((string)file_get_contents($path), 6);
            if ($data !== false && @file_put_contents($gz, $data) !== false) {
                @unlink($path);
                $path = $gz;
            }
        }

        self::prune($keep);
        return $path;
    }

    private static function flush($fh, $table, $columns, array $batch)
    {
        fwrite($fh, 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES ' . "\n"
                  . implode(",\n", $batch) . ";\n");
    }

    /** Conserva solo los respaldos más recientes. */
    public static function prune($keep = 8)
    {
        $files = self::listFiles();
        $i = 0;
        foreach ($files as $f) {
            $i++;
            if ($i > $keep) { @unlink(self::dir() . '/' . $f['name']); }
        }
    }

    /** @return array<int,array{name:string,size:int,time:int}> del más nuevo al más viejo */
    public static function listFiles()
    {
        $dir = self::dir();
        $out = array();
        foreach ((array)@scandir($dir) as $f) {
            if (!preg_match('/^menugold-[\w\-]+\.sql(\.gz)?$/', (string)$f)) { continue; }
            $out[] = array('name' => $f, 'size' => (int)@filesize($dir . '/' . $f), 'time' => (int)@filemtime($dir . '/' . $f));
        }
        usort($out, function ($a, $b) { return $b['time'] - $a['time']; });
        return $out;
    }
}
