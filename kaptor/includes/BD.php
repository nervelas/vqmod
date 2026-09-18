<?php
/**
 * Kaptor - Capa de base de datos.
 * Envoltorio ligero sobre PDO con consultas preparadas en TODAS las operaciones.
 */
declare(strict_types=1);

final class BD
{
    private static ?PDO $pdo = null;

    /** Abre (una sola vez) la conexión PDO con la base de datos configurada. */
    public static function conectar(): PDO
    {
        if (self::$pdo instanceof PDO) { return self::$pdo; }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            CR_DB_HOST,
            CR_DB_PUERTO !== '' ? CR_DB_PUERTO : '3306',
            CR_DB_NOMBRE,
            CR_DB_CHARSET
        );

        try {
            self::$pdo = new PDO($dsn, CR_DB_USUARIO, CR_DB_CLAVE, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            // La zona horaria de MySQL se alinea con la de PHP. Sin esto, en los
            // hosting donde el servidor de base de datos va en UTC y PHP en la
            // hora local, las comparaciones con NOW() (límite por IP, intentos de
            // acceso, caché de MX, retención del historial) fallarían por horas.
            self::sincronizarZonaHoraria();

        } catch (PDOException $e) {
            error_log('Kaptor / conexión BD: ' . $e->getMessage());
            http_response_code(500);
            if (defined('CR_DEBUG') && CR_DEBUG) {
                exit('Error de conexión a la base de datos: ' . e($e->getMessage()));
            }
            exit('No se pudo conectar con la base de datos. Revisa config/config.php.');
        }

        return self::$pdo;
    }

    /**
     * Ajusta la zona horaria de la sesión de MySQL a la misma que usa PHP.
     * Si el servidor no lo permite, se continúa sin error: los cálculos de
     * fechas seguirán funcionando aunque con la hora del servidor.
     */
    private static function sincronizarZonaHoraria(): void
    {
        try {
            $ahora   = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
            $desfase = $ahora->format('P');            // por ejemplo -06:00
            self::$pdo->exec("SET time_zone = '" . $desfase . "'");
        } catch (Throwable $e) {
            error_log('Kaptor / zona horaria MySQL: ' . $e->getMessage());
        }
    }

    /** Acceso directo al objeto PDO. */
    public static function pdo(): PDO
    {
        return self::$pdo ?? self::conectar();
    }

    /** Ejecuta una consulta preparada y devuelve el statement. */
    public static function ejecutar(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Devuelve todas las filas de una consulta. */
    public static function todos(string $sql, array $params = []): array
    {
        return self::ejecutar($sql, $params)->fetchAll();
    }

    /** Devuelve la primera fila (o null). */
    public static function fila(string $sql, array $params = []): ?array
    {
        $fila = self::ejecutar($sql, $params)->fetch();
        return $fila === false ? null : $fila;
    }

    /** Devuelve el primer valor de la primera fila. */
    public static function valor(string $sql, array $params = [], $defecto = null)
    {
        $valor = self::ejecutar($sql, $params)->fetchColumn();
        return $valor === false ? $defecto : $valor;
    }

    /** Inserta una fila y devuelve el id generado. */
    public static function insertar(string $tabla, array $datos): int
    {
        $columnas = array_keys($datos);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $tabla,
            implode('`,`', $columnas),
            implode(',', array_fill(0, count($columnas), '?'))
        );
        self::ejecutar($sql, array_values($datos));
        return (int) self::pdo()->lastInsertId();
    }

    /** Actualiza filas según una condición WHERE con parámetros. */
    public static function actualizar(string $tabla, array $datos, string $where, array $paramsWhere = []): int
    {
        $sets = [];
        foreach (array_keys($datos) as $col) { $sets[] = "`$col` = ?"; }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $tabla, implode(', ', $sets), $where);
        $st = self::ejecutar($sql, array_merge(array_values($datos), $paramsWhere));
        return $st->rowCount();
    }

    /** Comprueba si existe una tabla del proyecto (usado por el instalador). */
    public static function existeTabla(string $tabla): bool
    {
        try {
            self::pdo()->query('SELECT 1 FROM `' . $tabla . '` LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
