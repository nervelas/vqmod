<?php
/**
 * Kaptor - Puesta al día de la base de datos.
 *
 * Kaptor se actualiza extrayendo el ZIP encima de la carpeta: nadie ejecuta
 * el instalador otra vez. Esta clase se encarga de que las tablas antiguas
 * reciban las columnas nuevas sin perder nada y sin tocar nada a mano.
 *
 * Coste en una instalación al día: CERO consultas (el número de esquema ya
 * está en los ajustes, que se cargan de una sola vez al arrancar).
 */
declare(strict_types=1);

final class Esquema
{
    /** Se sube de uno en uno cada vez que cambia la estructura. */
    public const VERSION = 2;

    /** Aplica los cambios pendientes. Se llama desde bootstrap.php. */
    public static function actualizar(): void
    {
        $actual = (int) Ajustes::obtener('esquema', '0');
        if ($actual >= self::VERSION) { return; }

        try {
            // 2: filtro de extensiones del escaneo (búsqueda inteligente).
            if ($actual < 2) {
                self::columna('cr_escaneos', 'filtro_ext', "VARCHAR(190) NULL DEFAULT NULL AFTER `host`");
            }

            Ajustes::guardar('esquema', (string) self::VERSION);
        } catch (Throwable $e) {
            // Si el usuario de la base de datos no tiene permiso de ALTER, la
            // aplicación sigue funcionando; solo se pierde la función nueva.
            error_log('Kaptor / esquema: ' . $e->getMessage());
        }
    }

    /**
     * Anade una columna solo si todavía no existe.
     *
     * Se consulta information_schema y no "SHOW COLUMNS ... LIKE ?": MariaDB
     * no admite parámetros preparados en las sentencias SHOW.
     */
    private static function columna(string $tabla, string $columna, string $definicion): void
    {
        $fila = BD::fila(
            'SELECT COUNT(*) AS n FROM `information_schema`.`COLUMNS`
              WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?',
            [$tabla, $columna]
        );
        if ($fila && (int) $fila['n'] > 0) { return; }

        BD::ejecutar('ALTER TABLE `' . $tabla . '` ADD COLUMN `' . $columna . '` ' . $definicion);
    }
}
