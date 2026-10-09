<?php
declare(strict_types=1);
namespace S5\Core;

/** STUB de pruebas: SQLite en memoria con las tablas orders y ai_usage (prefijo s5_). */
class Db
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            self::reset();
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = new \PDO('sqlite::memory:');
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        self::$pdo->exec('CREATE TABLE s5_orders (id INTEGER PRIMARY KEY, data TEXT, analysis TEXT, analysis_count INTEGER NOT NULL DEFAULT 0)');
        self::$pdo->exec('CREATE TABLE s5_ai_usage (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT, model TEXT, tokens_in INTEGER, tokens_out INTEGER, cost_usd REAL, order_id INTEGER, ok INTEGER, created_at TEXT)');
    }

    public static function t(string $name): string { return 's5_' . $name; }

    public static function q(string $sql, array $params = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }
    public static function one(string $sql, array $params = []): ?array { $r = self::q($sql, $params)->fetch(); return $r === false ? null : $r; }
    public static function all(string $sql, array $params = []): array { return self::q($sql, $params)->fetchAll(); }
    public static function val(string $sql, array $params = []) { $r = self::q($sql, $params)->fetch(\PDO::FETCH_NUM); return $r === false ? null : $r[0]; }
    public static function insert(string $tabla, array $arr): int
    {
        $c = array_keys($arr);
        self::q('INSERT INTO ' . $tabla . ' (' . implode(',', $c) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($arr));
        return (int)self::pdo()->lastInsertId();
    }
    public static function update(string $tabla, array $arr, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn($c) => $c . '=?', array_keys($arr)));
        return self::q('UPDATE ' . $tabla . ' SET ' . $set . ' WHERE ' . $where, array_merge(array_values($arr), $params))->rowCount();
    }
}
