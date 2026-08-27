<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Capa PDO. Todas las consultas usan sentencias preparadas.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $driver = $cfg['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . $cfg['database'];
        } else {
            $port = (int)($cfg['port'] ?? 3306);
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? 'localhost',
                $port,
                $cfg['database'] ?? '',
                $cfg['charset'] ?? 'utf8mb4'
            );
        }
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];
        self::$pdo = new PDO($dsn, (string)($cfg['user'] ?? ''), (string)($cfg['password'] ?? ''), $options);
        if ($driver === 'sqlite') {
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::compatibilidadSqlite(self::$pdo);
        } else {
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
        return self::$pdo;
    }

    /**
     * Registra funciones equivalentes a las de MySQL para poder evaluar el
     * sistema sobre SQLite (entornos de prueba sin servidor MySQL).
     */
    private static function compatibilidadSqlite(PDO $pdo): void
    {
        if (!method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }
        $pdo->sqliteCreateFunction('CONCAT', static function (...$partes): string {
            return implode('', array_map(static fn($p) => (string)$p, $partes));
        });
        $pdo->sqliteCreateFunction('YEAR', static function ($fecha): ?int {
            $t = strtotime((string)$fecha);
            return $t === false ? null : (int)date('Y', $t);
        }, 1);
        $pdo->sqliteCreateFunction('MONTH', static function ($fecha): ?int {
            $t = strtotime((string)$fecha);
            return $t === false ? null : (int)date('n', $t);
        }, 1);
        $pdo->sqliteCreateFunction('DAY', static function ($fecha): ?int {
            $t = strtotime((string)$fecha);
            return $t === false ? null : (int)date('j', $t);
        }, 1);
        $pdo->sqliteCreateFunction('DATEDIFF', static function ($a, $b): ?int {
            $ta = strtotime((string)$a);
            $tb = strtotime((string)$b);
            return ($ta === false || $tb === false) ? null : (int)floor(($ta - $tb) / 86400);
        }, 2);
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new PDOException('La base de datos no ha sido inicializada.');
        }
        return self::$pdo;
    }

    public static function isConnected(): bool
    {
        return self::$pdo instanceof PDO;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : ':' . ltrim((string)$k, ':');
            $type = PDO::PARAM_STR;
            if (is_int($v)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($v)) {
                $type = PDO::PARAM_INT;
                $v = $v ? 1 : 0;
            } elseif ($v === null) {
                $type = PDO::PARAM_NULL;
            }
            $st->bindValue($key, $v, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    public static function begin(): void
    {
        if (!self::pdo()->inTransaction()) {
            self::pdo()->beginTransaction();
        }
    }

    public static function commit(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}
