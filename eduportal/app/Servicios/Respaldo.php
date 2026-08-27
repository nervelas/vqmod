<?php
declare(strict_types=1);

namespace App\Servicios;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use PDO;

/** Respaldo de la base de datos en PHP puro (no requiere mysqldump). */
final class Respaldo
{
    public static function generar(?string $destino = null): ?string
    {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        $destino = $destino ?: $dir . '/respaldo-' . date('Ymd-His') . '.sql.gz';
        try {
            $sql = self::volcado();
        } catch (\Throwable $e) {
            Logger::error('Fallo el respaldo', ['e' => $e->getMessage()]);
            return null;
        }
        $datos = function_exists('gzencode') ? gzencode($sql, 6) : $sql;
        if ($datos === false || @file_put_contents($destino, $datos) === false) {
            return null;
        }
        @chmod($destino, 0640);
        self::purgar($dir, 12);
        return $destino;
    }

    private static function purgar(string $dir, int $conservar): void
    {
        $archivos = glob($dir . '/respaldo-*.sql.gz') ?: [];
        if (count($archivos) <= $conservar) {
            return;
        }
        usort($archivos, static fn($a, $b) => filemtime($a) <=> filemtime($b));
        foreach (array_slice($archivos, 0, count($archivos) - $conservar) as $viejo) {
            @unlink($viejo);
        }
    }

    public static function volcado(): string
    {
        $pdo = Database::pdo();
        $driver = (string)Config::get('db.driver', 'mysql');
        $out = "-- Respaldo EduPortal\n-- Generado: " . date('Y-m-d H:i:s') . "\n"
             . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tablas = [];
        $consulta = $driver === 'sqlite'
            ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            : 'SHOW TABLES';
        foreach ($pdo->query($consulta) as $fila) {
            $tablas[] = (string)array_values($fila)[0];
        }

        foreach ($tablas as $tabla) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla)) {
                continue;
            }
            $out .= "\n-- Tabla: {$tabla}\nDROP TABLE IF EXISTS `{$tabla}`;\n";
            if ($driver === 'sqlite') {
                $crear = (string)Database::value(
                    "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = :t",
                    ['t' => $tabla],
                    ''
                );
                $out .= $crear . ";\n";
            } else {
                $fila = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch(PDO::FETCH_ASSOC);
                $out .= ($fila['Create Table'] ?? '') . ";\n";
            }
            $st = $pdo->query("SELECT * FROM `{$tabla}`");
            $lote = [];
            $columnas = null;
            foreach ($st as $registro) {
                if ($columnas === null) {
                    $columnas = '`' . implode('`, `', array_keys($registro)) . '`';
                }
                $valores = [];
                foreach ($registro as $valor) {
                    if ($valor === null) {
                        $valores[] = 'NULL';
                    } elseif (is_int($valor) || is_float($valor)) {
                        $valores[] = (string)$valor;
                    } else {
                        $valores[] = $pdo->quote((string)$valor);
                    }
                }
                $lote[] = '(' . implode(', ', $valores) . ')';
                if (count($lote) >= 200) {
                    $out .= "INSERT INTO `{$tabla}` ({$columnas}) VALUES\n" . implode(",\n", $lote) . ";\n";
                    $lote = [];
                }
            }
            if ($lote !== []) {
                $out .= "INSERT INTO `{$tabla}` ({$columnas}) VALUES\n" . implode(",\n", $lote) . ";\n";
            }
        }
        return $out . "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    }
}
