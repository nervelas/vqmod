<?php
declare(strict_types=1);

namespace Aurea\Core;

use PDO;
use PDOStatement;

/** Envoltura mínima de PDO: todo se ejecuta con sentencias preparadas. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . (int)($cfg['port'] ?? 3306)
            . ';dbname=' . $cfg['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
        ]);
        return self::$pdo = $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Base de datos no inicializada');
        }
        return self::$pdo;
    }

    public static function set(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
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

    public static function val(string $sql, array $params = [])
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    private static function ident(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            throw new \InvalidArgumentException('Identificador inválido');
        }
        return '`' . $name . '`';
    }

    /** Inserta una fila ($data: columna => valor) y devuelve el id. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_map([self::class, 'ident'], array_keys($data));
        $ph = implode(',', array_fill(0, count($data), '?'));
        self::q('INSERT INTO ' . self::ident($table) . ' (' . implode(',', $cols) . ') VALUES (' . $ph . ')', array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): void
    {
        $set = [];
        foreach (array_keys($data) as $c) {
            $set[] = self::ident($c) . '=?';
        }
        $vals = array_values($data);
        $vals[] = $id;
        self::q('UPDATE ' . self::ident($table) . ' SET ' . implode(',', $set) . ' WHERE id=?', $vals);
    }

    public static function delete(string $table, int $id): int
    {
        return self::exec('DELETE FROM ' . self::ident($table) . ' WHERE id=?', [$id]);
    }

    /** Placeholders para IN (...) a partir de una lista. */
    public static function in(array $values): string
    {
        return implode(',', array_fill(0, max(1, count($values)), '?'));
    }

    public static function begin(): void { if (!self::pdo()->inTransaction()) { self::pdo()->beginTransaction(); } }
    public static function commit(): void { if (self::pdo()->inTransaction()) { self::pdo()->commit(); } }
    public static function rollback(): void { if (self::pdo()->inTransaction()) { self::pdo()->rollBack(); } }
}
