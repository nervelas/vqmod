<?php
/**
 * Thin PDO wrapper (singleton).
 * Supports MySQL/MariaDB for production and SQLite for local development/testing.
 * All queries use prepared statements.
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function init(array $cfg): void
    {
        if (self::$pdo instanceof PDO) {
            return;
        }
        self::$driver = $cfg['driver'] ?? 'mysql';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (self::$driver === 'sqlite') {
            $path = $cfg['sqlite_path'] ?: (dirname(__DIR__) . '/database/app.sqlite');
            self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? 'localhost',
                (int)($cfg['port'] ?? 3306),
                $cfg['name'] ?? '',
                $cfg['charset'] ?? 'utf8mb4'
            );
            self::$pdo = new PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', $options);
        }
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('Database not initialized');
        }
        return self::$pdo;
    }

    /** Run a query and return the statement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row (assoc) or null. */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch a single scalar value. */
    public static function scalar(string $sql, array $params = [])
    {
        return self::run($sql, $params)->fetchColumn();
    }

    /** Insert a row into $table from assoc $data; returns last insert id. */
    public static function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        $place = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::ident($table),
            implode(', ', array_map([self::class, 'ident'], $cols)),
            implode(', ', $place)
        );
        self::run($sql, self::bindable($data));
        return self::pdo()->lastInsertId();
    }

    /** Update $table setting $data where $where (assoc, AND-combined). */
    public static function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(fn($c) => self::ident($c) . ' = :s_' . $c, array_keys($data)));
        $cond = implode(' AND ', array_map(fn($c) => self::ident($c) . ' = :w_' . $c, array_keys($where)));
        $params = [];
        foreach (self::bindable($data) as $k => $v) { $params['s_' . $k] = $v; }
        foreach (self::bindable($where) as $k => $v) { $params['w_' . $k] = $v; }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', self::ident($table), $set, $cond);
        return self::run($sql, $params)->rowCount();
    }

    /** Delete rows from $table where $where. */
    public static function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(fn($c) => self::ident($c) . ' = :' . $c, array_keys($where)));
        $sql = sprintf('DELETE FROM %s WHERE %s', self::ident($table), $cond);
        return self::run($sql, self::bindable($where))->rowCount();
    }

    /** Quote an identifier safely (whitelist of word chars only). */
    public static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Invalid identifier: ' . $name);
        }
        return self::$driver === 'mysql' ? "`$name`" : '"' . $name . '"';
    }

    /** Normalize bool/null for binding. */
    private static function bindable(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_bool($v)) { $v = $v ? 1 : 0; }
            $out[$k] = $v;
        }
        return $out;
    }
}
