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
    public const VERSION = 6;

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

            // 3: los textos guardados que todavía llevaban el nombre anterior.
            //    Solo se tocan si nadie los cambió: si el usuario escribió los
            //    suyos, se respetan tal cual.
            if ($actual < 3) {
                self::renombrarTextos();

                // El tope por lote de fábrica era 100 y dejaba fuera media
                // lista sin decirlo. Si nadie lo cambió, se sube a 300.
                if (Ajustes::obtener('max_sitios_lote') === '100') {
                    Ajustes::guardar('max_sitios_lote', '300');
                }
            }

            // 4: niveles educativos detectados en cada sitio del escaneo.
            if ($actual < 4) { self::tablaSitios(); }

            // 5: Kaptor pasa a ser privado. Si alguien tenía el acceso libre
            //    encendido, se apaga: ahora hace falta sesión siempre.
            if ($actual < 5) { Ajustes::guardar('acceso_publico', '0'); }

            // 6: auditor web.
            if ($actual < 6) { self::tablaAuditorias(); }

            Ajustes::guardar('esquema', (string) self::VERSION);
        } catch (Throwable $e) {
            // Si el usuario de la base de datos no tiene permiso de ALTER, la
            // aplicación sigue funcionando; solo se pierde la función nueva.
            error_log('Kaptor / esquema: ' . $e->getMessage());
        }
    }

    /**
     * Cambia los textos que siguen siendo los de fábrica del nombre anterior.
     *
     * La plataforma se llamaba CorreoRadar y esos textos viven en la base de
     * datos, no en el código: actualizar por ZIP no los tocaba y el sitio
     * seguía enseñando el nombre viejo. Se sustituyen UNO A UNO y solo cuando
     * el valor guardado es exactamente el antiguo de fábrica; cualquier texto
     * escrito por el usuario se queda como está.
     */
    private static function renombrarTextos(): void
    {
        $antiguos = [
            'sitio_nombre'     => ['CorreoRadar'],
            'sitio_lema'       => ['Extrae correos de cualquier web', 'Capta correos y WhatsApp de cualquier web'],
            'hero_titulo'      => ['Extrae cada correo de cualquier web', 'Capta cada correo de cualquier web'],
            'hero_etiqueta'    => ['Pega tu enlace'],
            'hero_boton'       => ['Extraer correos'],
            'hero_placeholder' => ['https://ejemplo.com/contacto'],
        ];

        $nuevos = [
            'sitio_nombre'     => 'Kaptor',
            'sitio_lema'       => 'Capta correos y WhatsApp de cualquier web',
            'hero_titulo'      => 'Capta cada correo y WhatsApp de cualquier web',
            'hero_etiqueta'    => 'Pega una web, una lista o una búsqueda',
            'hero_boton'       => 'Capturar contactos',
            'hero_placeholder' => 'https://ejemplo.com  ·  varias webs, una por línea  ·  o unas palabras para buscar',
        ];

        $cambios = [];
        foreach ($antiguos as $clave => $valores) {
            $actual = Ajustes::obtener($clave);
            if ($actual !== '' && in_array($actual, $valores, true)) {
                $cambios[$clave] = $nuevos[$clave];
            }
        }

        // Los textos largos solo cambian el nombre dentro de la frase.
        foreach (['sitio_descripcion', 'hero_subtitulo', 'pie_texto', 'aviso_legal'] as $clave) {
            $actual = Ajustes::obtener($clave);
            if ($actual !== '' && str_contains($actual, 'CorreoRadar')) {
                $cambios[$clave] = str_replace('CorreoRadar', 'Kaptor', $actual);
            }
        }

        if ($cambios) {
            Ajustes::guardarVarios($cambios);
            Ajustes::cargar();
        }
    }

    /**
     * Tabla con un renglón por web visitada dentro de un escaneo, donde se
     * apuntan los niveles educativos que menciona (primaria, básicos,
     * diversificado...). Así se puede escribir solo a los colegios que dan
     * el nivel que interesa.
     */
    private static function tablaSitios(): void
    {
        BD::ejecutar(
            'CREATE TABLE IF NOT EXISTS `cr_sitios` (
              `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `escaneo_id` INT UNSIGNED NOT NULL,
              `host`       VARCHAR(190) NOT NULL,
              `niveles`    VARCHAR(190) NOT NULL DEFAULT \'\',
              `titulo`     VARCHAR(255) NULL,
              `paginas`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_sitio` (`escaneo_id`, `host`),
              KEY `idx_escaneo` (`escaneo_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Tabla del auditor web: un renglón por sitio analizado.
     *
     * Lo medido y los hallazgos se guardan como JSON en la misma fila en vez
     * de repartirlos en tablas aparte. Una auditoría se escribe una vez y se
     * lee entera cada vez, así que partirla solo añadiría consultas.
     *
     * `lote` agrupa un sitio con sus competidores; `token` permite enseñarle
     * el informe a un cliente con un enlace, sin darle acceso al panel.
     */
    private static function tablaAuditorias(): void
    {
        BD::ejecutar(
            'CREATE TABLE IF NOT EXISTS `cr_auditorias` (
              `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `usuario_id`  INT UNSIGNED NULL,
              `lote`        VARCHAR(40) NOT NULL DEFAULT \'\',
              `papel`       VARCHAR(12) NOT NULL DEFAULT \'principal\',
              `url`         VARCHAR(500) NOT NULL,
              `host`        VARCHAR(190) NOT NULL DEFAULT \'\',
              `titulo`      VARCHAR(255) NULL,
              `estado`      VARCHAR(16) NOT NULL DEFAULT \'cola\',
              `fase`        VARCHAR(20) NOT NULL DEFAULT \'portada\',
              `nota`        TINYINT UNSIGNED NULL,
              `notas_area`  VARCHAR(255) NOT NULL DEFAULT \'\',
              `error`       VARCHAR(255) NOT NULL DEFAULT \'\',
              `datos`       LONGTEXT NULL,
              `hallazgos`   LONGTEXT NULL,
              `token`       CHAR(32) NOT NULL,
              `creado`      DATETIME NOT NULL,
              `actualizado` DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_token` (`token`),
              KEY `idx_lote` (`lote`),
              KEY `idx_usuario` (`usuario_id`, `id`),
              KEY `idx_host` (`host`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
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
