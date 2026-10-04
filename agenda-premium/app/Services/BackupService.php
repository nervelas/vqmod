<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Db;
use App\Core\Str;
use PDO;

/**
 * Respaldos de la base de datos como volcado SQL generado con PDO (sin mysqldump), guardados en storage/backups.
 * El archivo restaura con cualquier cliente MySQL/MariaDB (o phpMyAdmin) e incluye estructura y datos de todas las tablas.
 */
final class BackupService
{
    private const NAME_RE = '/^backup-\d{8}-\d{6}-[a-f0-9]{16}\.sql(\.gz)?$/';
    private const BATCH_ROWS = 300;
    private const BATCH_BYTES = 262144;

    public static function dir(): string
    {
        return APP_ROOT . '/storage/backups';
    }

    /** Crea un respaldo completo y devuelve la ruta del archivo (.sql o .sql.gz). */
    public static function create(): string
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new \RuntimeException('No pudimos crear la carpeta de respaldos. Revisa los permisos de la carpeta storage.');
        }
        self::protectDir($dir);

        $gz = function_exists('gzopen');
        $final = $dir . '/backup-' . gmdate('Ymd-His', Clock::now()) . '-' . Str::token(8) . '.sql' . ($gz ? '.gz' : '');
        $tmp = $final . '.part';
        $fh = $gz ? @gzopen($tmp, 'wb6') : @fopen($tmp, 'wb');
        if (!$fh) {
            throw new \RuntimeException('No pudimos escribir el respaldo. Revisa los permisos de la carpeta storage/backups.');
        }
        $write = $gz ? 'gzwrite' : 'fwrite';
        $close = $gz ? 'gzclose' : 'fclose';

        try {
            self::dump(static function (string $chunk) use ($fh, $write): void {
                if ($write($fh, $chunk) === false) {
                    throw new \RuntimeException('No pudimos escribir el respaldo. Revisa el espacio disponible en el servidor.');
                }
            });
        } catch (\Throwable $e) {
            $close($fh);
            @unlink($tmp);
            throw $e;
        }
        $close($fh);
        if (!@rename($tmp, $final)) {
            @unlink($tmp);
            throw new \RuntimeException('No pudimos guardar el respaldo.');
        }
        @chmod($final, 0640);
        return $final;
    }

    /** @return array<int,array{name:string,size:int,created_at:string,compressed:bool}> del más reciente al más antiguo */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/backup-*.sql*') ?: [] as $f) {
            $name = basename($f);
            if (!preg_match(self::NAME_RE, $name) || !is_file($f)) {
                continue;
            }
            $out[] = ['name' => $name, 'size' => (int) filesize($f), 'created_at' => gmdate('Y-m-d H:i:s', (int) filemtime($f)), 'compressed' => substr($name, -3) === '.gz'];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $out;
    }

    /** Ruta de un respaldo por nombre, o null si el nombre no es de un respaldo existente (evita recorridos de directorio). */
    public static function path(string $name): ?string
    {
        if (!preg_match(self::NAME_RE, $name) || basename($name) !== $name) {
            return null;
        }
        $base = realpath(self::dir());
        $full = $base === false ? false : realpath($base . '/' . $name);
        if ($base === false || $full === false || !is_file($full) || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }
        return $full;
    }

    /** Conserva los $keep respaldos más recientes (mínimo 1) y borra el resto. */
    public static function prune(int $keep): void
    {
        foreach (array_slice(self::list(), max(1, $keep)) as $b) {
            $p = self::path($b['name']);
            if ($p !== null) {
                @unlink($p);
            }
        }
    }

    // ------------------------------------------------------------------ volcado

    private static function dump(callable $out): void
    {
        $pdo = Db::pdo();
        $stream = self::streamConnection();
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        $names = array_map(static fn (array $r): string => (string) $r[0], $tables);
        sort($names, SORT_STRING);

        $out("-- Agenda Premium · respaldo de la base `" . $dbName . "`\n-- Generado el " . Clock::utc() . " UTC\n");
        $out("SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        $stream->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            foreach ($names as $t) {
                $q = '`' . str_replace('`', '``', $t) . '`';
                $create = $pdo->query('SHOW CREATE TABLE ' . $q)->fetch(PDO::FETCH_NUM);
                $out("DROP TABLE IF EXISTS {$q};\n" . $create[1] . ";\n\n");
                self::dumpRows($stream, $q, $out);
            }
        } finally {
            $stream->exec('COMMIT');
        }
        $out("SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
    }

    private static function dumpRows(PDO $stream, string $q, callable $out): void
    {
        $st = $stream->query('SELECT * FROM ' . $q);
        $cols = null;
        $rows = [];
        $bytes = 0;
        $flush = static function () use (&$rows, &$bytes, &$cols, $q, $out): void {
            if ($rows) {
                $out('INSERT INTO ' . $q . ' (' . $cols . ") VALUES\n" . implode(",\n", $rows) . ";\n");
                $rows = [];
                $bytes = 0;
            }
        };
        while (($row = $st->fetch(PDO::FETCH_NUM)) !== false) {
            if ($cols === null) {
                $cols = '';
                for ($i = 0, $n = $st->columnCount(); $i < $n; $i++) {
                    $cols .= ($i ? ',' : '') . '`' . str_replace('`', '``', (string) $st->getColumnMeta($i)['name']) . '`';
                }
            }
            $vals = [];
            foreach ($row as $v) {
                $vals[] = self::literal($stream, $v);
            }
            $line = '(' . implode(',', $vals) . ')';
            $rows[] = $line;
            $bytes += strlen($line);
            if (count($rows) >= self::BATCH_ROWS || $bytes >= self::BATCH_BYTES) {
                $flush();
            }
        }
        $st->closeCursor();
        $flush();
        if ($cols !== null) {
            $out("\n");
        }
    }

    /** Valor SQL: NULL, cadena escapada por el servidor o hexadecimal si no es UTF-8 válido. */
    private static function literal(PDO $pdo, $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        $s = (string) $v;
        if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
            return '0x' . bin2hex($s);
        }
        return $pdo->quote($s);
    }

    /** Conexión propia sin búfer para recorrer tablas grandes sin agotar la memoria. */
    private static function streamConnection(): PDO
    {
        $cfg = (array) Config::get('db', []);
        $dsn = 'mysql:host=' . ($cfg['host'] ?? 'localhost') . (!empty($cfg['port']) ? ';port=' . (int) $cfg['port'] : '') . ';dbname=' . ($cfg['name'] ?? '') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string) ($cfg['user'] ?? ''), (string) ($cfg['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    /** Impide la descarga directa de los respaldos si el servidor web llegara a servir la carpeta. */
    private static function protectDir(string $dir): void
    {
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }
}
