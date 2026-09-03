<?php
declare(strict_types=1);

/**
 * Capa de acceso a datos: PDO con consultas preparadas siempre.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
            self::$pdo = new PDO('sqlite:' . DB_FILE, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                defined('DB_PORT') && DB_PORT !== '' ? DB_PORT : '3306',
                DB_NAME,
                DB_CHARSET
            );
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        }

        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? $default : $row[0];
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO ' . self::ident($table) . ' ('
              . implode(', ', array_map([self::class, 'ident'], $cols))
              . ') VALUES (' . implode(', ', array_map(static fn($c) => ':' . $c, $cols)) . ')';
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = self::ident($c) . ' = :' . $c;
        }
        $sql = 'UPDATE ' . self::ident($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge($data, $params))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run('DELETE FROM ' . self::ident($table) . ' WHERE ' . $where, $params)->rowCount();
    }

    public static function ident(string $name): string
    {
        return '`' . (preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '') . '`';
    }

    public static function isSqlite(): bool
    {
        return defined('DB_DRIVER') && DB_DRIVER === 'sqlite';
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function tableExists(string $table): bool
    {
        try {
            self::run('SELECT 1 FROM ' . self::ident($table) . ' LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
