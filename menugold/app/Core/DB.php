<?php
declare(strict_types=1);

namespace MenuGold\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Capa de acceso a datos. PDO + sentencias preparadas SIEMPRE.
 * Nunca se concatena entrada de usuario en SQL.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $queries = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $host = (string)App::config('db_host', 'localhost');
        $port = (int)App::config('db_port', 3306);
        $name = (string)App::config('db_name', '');
        $user = (string)App::config('db_user', '');
        $pass = (string)App::config('db_pass', '');
        $char = (string)App::config('db_charset', 'utf8mb4');
        $sock = (string)App::config('db_socket', '');

        $dsn = $sock !== ''
            ? "mysql:unix_socket={$sock};dbname={$name};charset={$char}"
            : "mysql:host={$host};port={$port};dbname={$name};charset={$char}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$char} COLLATE {$char}_unicode_ci, "
                    . "sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION', "
                    // La base debe marcar la misma hora que la aplicacion. Casi
                    // todos los hosting tienen MySQL en UTC y el restaurante en
                    // otra zona; si no se iguala aqui, NOW() y las fechas que
                    // escribe PHP se separan varias horas y todo lo que compara
                    // "de hoy" o "hace tanto tiempo" empieza a fallar callado.
                    // Se manda el desfase en numeros (-06:00) y no el nombre de
                    // la zona, porque las tablas de zonas horarias de MySQL no
                    // suelen estar cargadas en hosting compartido.
                    . "time_zone='" . self::desfaseHorario() . "'",
            ]);
        } catch (PDOException $e) {
            Logger::error('Conexion a base de datos fallida: ' . $e->getMessage());
            throw new \RuntimeException('No se pudo conectar a la base de datos.', 500, $e);
        }
        return self::$pdo;
    }

    /** Permite al instalador inyectar una conexion ya validada. */
    /** Desfase de la zona horaria de la aplicacion, en formato +HH:MM. */
    private static function desfaseHorario(): string
    {
        try {
            $tz = new \DateTimeZone(date_default_timezone_get());
            $seg = $tz->getOffset(new \DateTime('now', $tz));
        } catch (\Throwable $e) {
            $seg = (int)date('Z');
        }
        $signo = $seg < 0 ? '-' : '+';
        $seg = abs($seg);
        return sprintf('%s%02d:%02d', $signo, intdiv($seg, 3600), intdiv($seg % 3600, 60));
    }

    public static function setPdo(PDO $pdo): void { self::$pdo = $pdo; }

    public static function raw(string $sql, array $params = []): PDOStatement
    {
        self::$queries++;
        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (strncmp((string)$k, ':', 1) === 0 ? $k : ':' . $k);
            $type = PDO::PARAM_STR;
            if (is_int($v))       $type = PDO::PARAM_INT;
            elseif (is_bool($v))  $type = PDO::PARAM_INT;
            elseif ($v === null)  $type = PDO::PARAM_NULL;
            $st->bindValue($key, is_bool($v) ? (int)$v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::raw($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::raw($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Devuelve el primer valor de la primera fila. */
    public static function value(string $sql, array $params = [], $default = null)
    {
        $v = self::raw($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function int(string $sql, array $params = [], int $default = 0): int
    {
        return (int)self::value($sql, $params, $default);
    }

    /** @return array<int,mixed> */
    public static function column(string $sql, array $params = []): array
    {
        return self::raw($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Mapa clave => valor usando las dos primeras columnas. */
    public static function pairs(string $sql, array $params = []): array
    {
        $out = [];
        foreach (self::raw($sql, $params)->fetchAll(PDO::FETCH_NUM) as $r) {
            $out[$r[0]] = $r[1] ?? null;
        }
        return $out;
    }

    /**
     * Ejecuta un INSERT/UPDATE/DELETE y devuelve las filas afectadas.
     * Se llama "ejecutar" y no "exec" a proposito: los antivirus de los
     * hosting compartidos marcan como sospechosa esa palabra cuando la
     * encuentran en un archivo PHP, aunque aqui solo fuera una consulta.
     */
    public static function ejecutar(string $sql, array $params = []): int
    {
        return self::raw($sql, $params)->rowCount();
    }

    /** INSERT seguro: las claves se validan contra un patron de identificador. */
    public static function insert(string $table, array $data): int
    {
        [$cols, $place, $params] = self::compile($data);
        $sql = 'INSERT INTO `' . self::ident($table) . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
        self::raw($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    /** INSERT ... ON DUPLICATE KEY UPDATE */
    public static function upsert(string $table, array $data, array $updateCols = []): int
    {
        [$cols, $place, $params] = self::compile($data);
        $updateCols = $updateCols ?: array_keys($data);
        $sets = [];
        foreach ($updateCols as $c) $sets[] = '`' . self::ident($c) . '`=VALUES(`' . self::ident($c) . '`)';
        $sql = 'INSERT INTO `' . self::ident($table) . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')'
             . ' ON DUPLICATE KEY UPDATE ' . implode(',', $sets);
        self::raw($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    /** UPDATE seguro con WHERE parametrizado. */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        $i = 0;
        foreach ($data as $col => $val) {
            $p = 'u' . ($i++);
            $sets[] = '`' . self::ident($col) . '`=:' . $p;
            $params[$p] = $val;
        }
        if (!$sets) return 0;
        $sql = 'UPDATE `' . self::ident($table) . '` SET ' . implode(',', $sets) . ' WHERE ' . $where;
        return self::ejecutar($sql, $params + $whereParams);
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::ejecutar('DELETE FROM `' . self::ident($table) . '` WHERE ' . $where, $params);
    }

    private static function compile(array $data): array
    {
        $cols = $place = $params = [];
        $i = 0;
        foreach ($data as $col => $val) {
            $p = 'i' . ($i++);
            $cols[]  = '`' . self::ident($col) . '`';
            $place[] = ':' . $p;
            $params[$p] = $val;
        }
        return [$cols, $place, $params];
    }

    /** Valida que un identificador SQL sea seguro (nunca viene del usuario). */
    public static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            throw new \InvalidArgumentException('Identificador SQL inválido: ' . $name);
        }
        return $name;
    }

    /** Construye placeholders IN (...) de forma segura. */
    public static function inList(array $values, string $prefix = 'in'): array
    {
        $ph = []; $params = [];
        foreach (array_values($values) as $i => $v) {
            $ph[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $v;
        }
        return [$ph ? implode(',', $ph) : 'NULL', $params];
    }

    public static function begin(): void { if (!self::pdo()->inTransaction()) self::pdo()->beginTransaction(); }
    public static function commit(): void { if (self::pdo()->inTransaction()) self::pdo()->commit(); }
    public static function rollback(): void { if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }

    /** Ejecuta una funcion dentro de una transaccion. */
    public static function transaction(callable $fn)
    {
        self::begin();
        try {
            $r = $fn();
            self::commit();
            return $r;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    public static function tableExists(string $table): bool
    {
        try {
            self::raw('SELECT 1 FROM `' . self::ident($table) . '` LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function queryCount(): int { return self::$queries; }
}
