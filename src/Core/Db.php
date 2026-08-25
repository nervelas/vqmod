<?php
declare(strict_types=1);

namespace Fel\Core;

use PDO;

/**
 * Conexion PDO unica. Soporta MySQL (produccion en cPanel) y SQLite (pruebas).
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = (string) Config::get('db.driver', 'mysql');

        if ($driver === 'sqlite') {
            $archivo = (string) Config::requerido('db.archivo');
            $dsn = 'sqlite:' . $archivo;
            self::$pdo = new PDO($dsn, null, null, self::opciones());
            self::$pdo->exec('PRAGMA foreign_keys = ON');

            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) Config::get('db.host', 'localhost'),
            (int) Config::get('db.puerto', 3306),
            (string) Config::requerido('db.nombre')
        );

        self::$pdo = new PDO(
            $dsn,
            (string) Config::requerido('db.usuario'),
            (string) Config::get('db.clave', ''),
            self::opciones()
        );

        return self::$pdo;
    }

    public static function establecer(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function reiniciar(): void
    {
        self::$pdo = null;
    }

    /** @return array<int,mixed> */
    private static function opciones(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }
}
