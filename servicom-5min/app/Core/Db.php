<?php
declare(strict_types=1);
namespace S5\Core;

use PDO;
use PDOStatement;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            if (!$c) {
                throw new \RuntimeException('Base de datos no configurada.');
            }
            $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost') . ';dbname=' . $c['name'] . ';charset=utf8mb4';
            if (!empty($c['socket'])) {
                $dsn = 'mysql:unix_socket=' . $c['socket'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
            }
            self::$pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone='+00:00'",
            ]);
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function prefix(): string
    {
        return (string) Config::get('db.prefix', 's5_');
    }

    public static function t(string $name): string
    {
        return '`' . self::prefix() . preg_replace('/[^a-z0-9_]/i', '', $name) . '`';
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function val(string $sql, array $params = [])
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    private static function col(string $c): string
    {
        return '`' . preg_replace('/[^a-z0-9_]/i', '', $c) . '`';
    }

    public static function insert(string $table, array $row): int
    {
        $cols = array_map([self::class, 'col'], array_keys($row));
        $ph = implode(',', array_fill(0, count($row), '?'));
        self::q('INSERT INTO ' . self::t($table) . ' (' . implode(',', $cols) . ') VALUES (' . $ph . ')', array_values($row));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $row, string $where, array $params = []): int
    {
        if (!$row) {
            return 0;
        }
        $set = [];
        foreach (array_keys($row) as $c) {
            $set[] = self::col($c) . '=?';
        }
        $st = self::q('UPDATE ' . self::t($table) . ' SET ' . implode(',', $set) . ' WHERE ' . $where, array_merge(array_values($row), $params));
        return $st->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::q('DELETE FROM ' . self::t($table) . ' WHERE ' . $where, $params)->rowCount();
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
