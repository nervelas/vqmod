<?php
namespace MenuGold\Core;

use PDO;
use PDOException;

/**
 * Envoltura PDO. Todas las consultas del sistema son preparadas.
 */
final class DB
{
    /** @var PDO|null */
    private static $pdo = null;
    /** @var int */
    public static $queryCount = 0;

    public static function pdo()
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            $dsn = 'mysql:host=' . $c['host'] . ';port=' . (isset($c['port']) ? $c['port'] : 3306)
                 . ';dbname=' . $c['name'] . ';charset=utf8mb4';
            try {
                self::$pdo = new PDO($dsn, $c['user'], $c['pass'], array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ));
                self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
            } catch (PDOException $e) {
                Logger::error('DB connection failed: ' . $e->getMessage());
                throw new \RuntimeException('No se pudo conectar a la base de datos.', 0, $e);
            }
        }
        return self::$pdo;
    }

    /** Permite inyectar una conexión ya creada (instalador / pruebas). */
    public static function setPdo($pdo)
    {
        self::$pdo = $pdo;
    }

    public static function isConnected()
    {
        return self::$pdo !== null;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @return \PDOStatement
     */
    public static function run($sql, array $params = array())
    {
        self::$queryCount++;
        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : ':' . ltrim((string)$k, ':');
            if (is_int($v)) {
                $st->bindValue($key, $v, PDO::PARAM_INT);
            } elseif (is_bool($v)) {
                $st->bindValue($key, $v ? 1 : 0, PDO::PARAM_INT);
            } elseif ($v === null) {
                $st->bindValue($key, null, PDO::PARAM_NULL);
            } else {
                $st->bindValue($key, (string)$v, PDO::PARAM_STR);
            }
        }
        $st->execute();
        return $st;
    }

    public static function all($sql, array $params = array())
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function first($sql, array $params = array())
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value($sql, array $params = array(), $default = null)
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        return ($row === false || !isset($row[0])) ? $default : $row[0];
    }

    public static function column($sql, array $params = array())
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public static function insert($table, array $data)
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
             . implode(',', array_map(function ($c) { return ':' . $c; }, $cols)) . ')';
        self::run($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    public static function update($table, array $data, $where, array $whereParams = array())
    {
        $sets = array();
        $params = array();
        foreach ($data as $k => $v) {
            $sets[] = '`' . $k . '` = :s_' . $k;
            $params['s_' . $k] = $v;
        }
        foreach ($whereParams as $k => $v) {
            $params[ltrim((string)$k, ':')] = $v;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, $params)->rowCount();
    }

    public static function delete($table, $where, array $params = array())
    {
        return self::run('DELETE FROM `' . $table . '` WHERE ' . $where, $params)->rowCount();
    }

    public static function begin()
    {
        if (!self::pdo()->inTransaction()) {
            self::pdo()->beginTransaction();
        }
    }

    public static function commit()
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollback()
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /** Marcadores posicionales seguros para cláusulas IN (...) */
    public static function placeholders(array $values)
    {
        return $values ? implode(',', array_fill(0, count($values), '?')) : 'NULL';
    }
}
