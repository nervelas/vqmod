<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Acceso a datos. 100% sentencias preparadas.
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $host   = (string) Config::get('db.host', 'localhost');
        $nombre = (string) Config::get('db.nombre', '');
        $puerto = (string) Config::get('db.puerto', '3306');
        $socket = (string) Config::get('db.socket', '');
        $dsn = $socket !== ''
            ? "mysql:unix_socket={$socket};dbname={$nombre};charset=utf8mb4"
            : "mysql:host={$host};port={$puerto};dbname={$nombre};charset=utf8mb4";
        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('db.usuario', ''),
                (string) Config::get('db.clave', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
            self::$pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            Log::error('Conexión BD: ' . $e->getMessage());
            throw new \RuntimeException('No se pudo conectar con la base de datos.', 0, $e);
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        // Los marcadores con nombre repetidos no son válidos en sentencias
        // preparadas nativas: se expanden a nombres únicos automáticamente.
        [$sql, $params] = self::expandirRepetidos($sql, $params);
        $st = self::conexion()->prepare($sql);
        foreach ($params as $k => $v) {
            $clave = is_int($k) ? $k + 1 : (str_starts_with((string) $k, ':') ? $k : ':' . $k);
            $tipo  = match (true) {
                is_int($v)  => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                is_null($v) => PDO::PARAM_NULL,
                default     => PDO::PARAM_STR,
            };
            $st->bindValue($clave, $v, $tipo);
        }
        $st->execute();
        return $st;
    }


    /**
     * Convierte  ... :b ... :b ...  en  ... :b ... :b__2 ...
     * y duplica el valor correspondiente.
     *
     * @return array{0:string,1:array}
     */
    private static function expandirRepetidos(string $sql, array $params): array
    {
        foreach ($params as $clave => $valor) {
            if (is_int($clave)) {
                continue;
            }
            $nombre = ltrim((string) $clave, ':');
            if ($nombre === '') {
                continue;
            }
            $patron = '/:' . preg_quote($nombre, '/') . '\b/';
            $veces  = preg_match_all($patron, $sql);
            if ($veces === false || $veces < 2) {
                continue;
            }
            $n = 0;
            $sql = preg_replace_callback($patron, static function () use (&$n, $nombre, &$params, $valor): string {
                $n++;
                if ($n === 1) {
                    return ':' . $nombre;
                }
                $alias = $nombre . '__' . $n;
                $params[$alias] = $valor;
                return ':' . $alias;
            }, $sql) ?? $sql;
        }
        return [$sql, $params];
    }

    public static function todos(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function uno(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function valor(string $sql, array $params = [], mixed $porDefecto = null): mixed
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? $porDefecto : $r;
    }

    public static function insertar(string $tabla, array $datos): int
    {
        $cols = array_keys($datos);
        $ph   = array_map(static fn($c) => ':' . $c, $cols);
        $sql  = 'INSERT INTO `' . $tabla . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $ph) . ')';
        self::q($sql, $datos);
        return (int) self::conexion()->lastInsertId();
    }

    public static function actualizar(string $tabla, array $datos, string $where, array $params = []): int
    {
        $sets = [];
        foreach (array_keys($datos) as $c) {
            $sets[] = "`{$c}` = :s_{$c}";
        }
        $bind = [];
        foreach ($datos as $c => $v) {
            $bind['s_' . $c] = $v;
        }
        $sql = 'UPDATE `' . $tabla . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::q($sql, array_merge($bind, $params))->rowCount();
    }

    public static function eliminar(string $tabla, string $where, array $params = []): int
    {
        return self::q('DELETE FROM `' . $tabla . '` WHERE ' . $where, $params)->rowCount();
    }

    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::conexion();
        $propia = !$pdo->inTransaction();
        if ($propia) {
            $pdo->beginTransaction();
        }
        try {
            $r = $fn();
            if ($propia) {
                $pdo->commit();
            }
            return $r;
        } catch (\Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function disponible(): bool
    {
        try {
            self::conexion();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
