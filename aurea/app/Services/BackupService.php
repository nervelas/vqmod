<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;

/** Respaldo de la base de datos en un archivo .sql (estructura + datos). */
final class BackupService
{
    public static function dump(): string
    {
        $pdo = Db::pdo();
        $out = "-- AUREA respaldo " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $t = (string)$t;
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', $t) . '`')->fetch(\PDO::FETCH_NUM);
            $out .= 'DROP TABLE IF EXISTS `' . $t . "`;\n" . $create[1] . ";\n\n";
            $st = $pdo->query('SELECT * FROM `' . str_replace('`', '', $t) . '`');
            $batch = [];
            $cols = null;
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                if ($cols === null) { $cols = '`' . implode('`,`', array_keys($row)) . '`'; }
                $vals = [];
                foreach ($row as $v) { $vals[] = $v === null ? 'NULL' : $pdo->quote((string)$v); }
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= 200) {
                    $out .= 'INSERT INTO `' . $t . '` (' . $cols . ") VALUES\n" . implode(",\n", $batch) . ";\n";
                    $batch = [];
                }
            }
            if ($batch) { $out .= 'INSERT INTO `' . $t . '` (' . $cols . ") VALUES\n" . implode(",\n", $batch) . ";\n"; }
            $out .= "\n";
        }
        return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
