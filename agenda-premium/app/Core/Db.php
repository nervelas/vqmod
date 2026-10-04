<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/** Envoltorio de PDO. Todas las consultas usan sentencias preparadas. */
final class Db
{
    private static ?PDO $pdo = null;
    private static int $txDepth = 0;

    public static function connect(?array $cfg = null): PDO
    {
        if (self::$pdo !== null && $cfg === null) {
            return self::$pdo;
        }
        $cfg = $cfg ?? (array) Config::get('db', []);
        $dsn = 'mysql:host=' . ($cfg['host'] ?? 'localhost')
            . (!empty($cfg['port']) ? ';port=' . (int) $cfg['port'] : '')
            . ';dbname=' . ($cfg['name'] ?? '') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string) ($cfg['user'] ?? ''), (string) ($cfg['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 15');
        if ($cfg !== null && self::$pdo === null) {
            self::$pdo = $pdo;
        }
        return $pdo;
    }

    /** Usado por el instalador y las pruebas para fijar una conexión ya creada. */
    public static function use(PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$txDepth = 0;
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$txDepth = 0;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ?? self::connect();
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    /** Primera columna de la primera fila (o null). */
    public static function val(string $sql, array $params = [])
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    /** Lista de la primera columna. */
    public static function col(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    /** INSERT con arreglo asociativo. Devuelve el id autoincremental. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        self::assertIdent($table);
        foreach ($cols as $c) {
            self::assertIdent($c);
        }
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::q($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** UPDATE con arreglo asociativo; $where usa ? y $whereParams. Devuelve filas afectadas. */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdent($table);
        $sets = [];
        foreach (array_keys($data) as $c) {
            self::assertIdent($c);
            $sets[] = '`' . $c . '` = ?';
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::q($sql, array_merge(array_values($data), array_values($whereParams)))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        self::assertIdent($table);
        return self::q('DELETE FROM `' . $table . '` WHERE ' . $where, $params)->rowCount();
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /** Transacción (anidable). READ COMMITTED para que, tras tomar bloqueos, se vean los datos confirmados más recientes. */
    public static function tx(callable $fn, bool $readCommitted = true)
    {
        $pdo = self::pdo();
        if (self::$txDepth > 0) {
            self::$txDepth++;
            try {
                return $fn();
            } finally {
                self::$txDepth--;
            }
        }
        if ($readCommitted) {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        $pdo->beginTransaction();
        self::$txDepth = 1;
        try {
            $res = $fn();
            $pdo->commit();
            return $res;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::$txDepth = 0;
        }
    }

    public static function inTx(): bool
    {
        return self::$txDepth > 0;
    }

    /** Detecta errores de bloqueo/deadlock para reintentar. */
    public static function isDeadlock(Throwable $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }
        $code = (string) ($e->errorInfo[1] ?? '');
        return $code === '1213' || $code === '1205';
    }

    private static function assertIdent(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Identificador SQL no válido.');
        }
    }
}
