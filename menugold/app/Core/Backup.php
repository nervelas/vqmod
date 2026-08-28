<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Respaldo de la base de datos en PHP puro (sin mysqldump).
 * Genera un .sql completo con estructura y datos, listo para importar.
 */
final class Backup
{
    public const DIR = '/storage/backups';

    public static function dir(): string
    {
        $d = MG_ROOT . self::DIR;
        if (!is_dir($d)) @mkdir($d, 0750, true);
        return $d;
    }

    /** Crea el respaldo y devuelve la ruta absoluta del archivo. */
    public static function crear(string $prefijo = 'respaldo'): string
    {
        @set_time_limit(300);
        $prefijo = preg_replace('/[^A-Za-z0-9_-]/', '', $prefijo) ?: 'respaldo';
        $nombre = $prefijo . '-' . date('Ymd-His') . '.sql';
        $ruta = self::dir() . '/' . $nombre;

        $fh = @fopen($ruta, 'w');
        if (!$fh) throw new \RuntimeException('No se pudo escribir en /storage/backups. Revisa los permisos.');

        $bd = (string)App::config('db_name', '');
        fwrite($fh, "-- ============================================================\n");
        fwrite($fh, "--  Respaldo de MenuGold\n");
        fwrite($fh, "--  Base de datos: {$bd}\n");
        fwrite($fh, "--  Generado: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fh, "-- ============================================================\n\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tablas = DB::column('SHOW TABLES');
        foreach ($tablas as $tabla) {
            $tabla = (string)$tabla;
            $crear = DB::one('SHOW CREATE TABLE `' . DB::ident($tabla) . '`');
            $sql = (string)($crear['Create Table'] ?? '');
            if ($sql === '') continue;

            fwrite($fh, "-- ---------- Tabla `{$tabla}` ----------\n");
            fwrite($fh, "DROP TABLE IF EXISTS `{$tabla}`;\n{$sql};\n\n");

            $total = DB::int('SELECT COUNT(*) FROM `' . DB::ident($tabla) . '`');
            if ($total === 0) continue;

            $cols = DB::column('SHOW COLUMNS FROM `' . DB::ident($tabla) . '`');
            $listaCols = '`' . implode('`,`', array_map('strval', $cols)) . '`';
            $lote = 200;

            for ($off = 0; $off < $total; $off += $lote) {
                $filas = DB::all('SELECT * FROM `' . DB::ident($tabla) . '` LIMIT ' . $lote . ' OFFSET ' . $off);
                if (!$filas) break;
                $valores = [];
                foreach ($filas as $fila) {
                    $vals = [];
                    foreach ($cols as $c) {
                        $v = $fila[(string)$c] ?? null;
                        if ($v === null) $vals[] = 'NULL';
                        elseif (is_int($v) || is_float($v)) $vals[] = (string)$v;
                        else $vals[] = DB::pdo()->quote((string)$v);
                    }
                    $valores[] = '(' . implode(',', $vals) . ')';
                }
                fwrite($fh, "INSERT INTO `{$tabla}` ({$listaCols}) VALUES\n" . implode(",\n", $valores) . ";\n");
            }
            fwrite($fh, "\n");
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n-- Fin del respaldo\n");
        fclose($fh);
        @chmod($ruta, 0640);
        self::limpiar();
        return $ruta;
    }

    /** Conserva solo los 12 respaldos más recientes. */
    public static function limpiar(int $conservar = 12): void
    {
        $archivos = glob(self::dir() . '/*.sql') ?: [];
        if (count($archivos) <= $conservar) return;
        usort($archivos, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($archivos, $conservar) as $f) @unlink($f);
    }

    /** @return array<int,array{nombre:string,peso:string,fecha:string,bytes:int}> */
    public static function listar(): array
    {
        $archivos = glob(self::dir() . '/*.sql') ?: [];
        usort($archivos, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $out = [];
        foreach ($archivos as $f) {
            $out[] = [
                'nombre' => basename($f),
                'bytes'  => (int)filesize($f),
                'peso'   => self::formatoPeso((int)filesize($f)),
                'fecha'  => date('Y-m-d H:i:s', (int)filemtime($f)),
            ];
        }
        return $out;
    }

    /** Ruta segura de un respaldo por nombre. */
    public static function ruta(string $nombre): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+\.sql$/', $nombre)) return null;
        $f = self::dir() . '/' . $nombre;
        return is_file($f) ? $f : null;
    }

    public static function borrar(string $nombre): bool
    {
        $f = self::ruta($nombre);
        if (!$f) return false;
        return @unlink($f);
    }

    public static function espacio(): array
    {
        $archivos = glob(self::dir() . '/*.sql') ?: [];
        $bytes = 0;
        foreach ($archivos as $f) $bytes += (int)filesize($f);
        return ['archivos' => count($archivos), 'bytes' => $bytes, 'peso' => self::formatoPeso($bytes)];
    }

    public static function formatoPeso(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }
}
