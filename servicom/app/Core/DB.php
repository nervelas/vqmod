<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Capa de acceso a datos. 100% sentencias preparadas.
 * Ningún método concatena valores de usuario en el SQL.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $queries = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $host = (string) Config::get('db_host', 'localhost');
        $name = (string) Config::get('db_name', '');
        $user = (string) Config::get('db_user', '');
        $pass = (string) Config::get('db_pass', '');
        $port = (string) Config::get('db_port', '3306');
        $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        self::$pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        self::syncTimeZone(self::$pdo);
        return self::$pdo;
    }

    public static function connect(string $host, string $name, string $user, string $pass, string $port = '3306'): PDO
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        self::syncTimeZone($pdo);
        return $pdo;
    }

    /**
     * Alinea la zona horaria de MySQL con la de PHP. Sin esto, NOW() y las
     * fechas escritas por la aplicación pueden diferir varias horas y los
     * vencimientos (tokens, códigos 2FA, límites) fallan silenciosamente.
     */
    private static function syncTimeZone(PDO $pdo): void
    {
        try {
            $offset = (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->format('P');
            $pdo->exec("SET time_zone = '{$offset}'");
        } catch (\Throwable $e) {
            // Si el hosting no permite cambiar la zona, se continúa con la del servidor.
        }
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        self::$queries++;
        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with((string) $k, ':') ? $k : ':' . $k);
            $type = match (true) {
                is_int($v)  => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                is_null($v) => PDO::PARAM_NULL,
                default     => PDO::PARAM_STR,
            };
            $st->bindValue($key, $v, $type);
        }
        $st->execute();
        return $st;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $r = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $r === false ? $default : $r[0];
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $ph   = array_map(static fn ($c) => ':' . $c, $cols);
        $sql  = 'INSERT INTO `' . self::ident($table) . '` (`' . implode('`,`', array_map([self::class, 'ident'], $cols)) . '`) VALUES (' . implode(',', $ph) . ')';
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = '`' . self::ident($c) . '`=:s_' . $c;
        }
        $params = [];
        foreach ($data as $k => $v) {
            $params['s_' . $k] = $v;
        }
        foreach ($whereParams as $k => $v) {
            $params[$k] = $v;
        }
        $sql = 'UPDATE `' . self::ident($table) . '` SET ' . implode(',', $sets) . ' WHERE ' . $where;
        return self::run($sql, $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run('DELETE FROM `' . self::ident($table) . '` WHERE ' . $where, $params)->rowCount();
    }

    /** Sanea un identificador (tabla/columna). Nunca recibe entrada de usuario sin lista blanca. */
    public static function ident(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_]/', '', $name);
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

    public static function queryCount(): int
    {
        return self::$queries;
    }
}
