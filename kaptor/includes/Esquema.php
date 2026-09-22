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
    public const VERSION = 12;

    /** Aplica los cambios pendientes. Se llama desde bootstrap.php. */
    public static function actualizar(): void
    {
        $actual = (int) Ajustes::obtener('esquema', '0');

        // Pasos que fallaron en un arranque anterior. Todos los de aqui son
        // idempotentes (comprueban antes de tocar nada), asi que reintentarlos
        // no cuesta nada y no rompe nada.
        $pendientes = array_filter(array_map(
            'intval',
            explode(',', (string) Ajustes::obtener('esquema_pendiente', ''))
        ));

        if ($actual >= self::VERSION && !$pendientes) { return; }

        // Cada paso por separado. ANTES iban los doce dentro de un solo
        // try/catch: si un ALTER TABLE fallaba (hosting compartido donde el
        // usuario de la base no tiene ese permiso), se saltaba todo lo que
        // venia detras Y no se guardaba la version. En el siguiente arranque
        // volvia a fallar en el mismo sitio, asi que los cambios de texto y
        // de color no llegaban NUNCA, por muchas veces que se subiera el ZIP.
        //
        // Ahora un paso que falla solo se afecta a si mismo: los demas corren
        // igual, la version se guarda, y el que fallo queda apuntado para
        // reintentarlo en el siguiente arranque.
        $pasos = [
            // Filtro de extensiones del escaneo (busqueda inteligente).
            2 => static function (): void {
                self::columna('cr_escaneos', 'filtro_ext', "VARCHAR(190) NULL DEFAULT NULL AFTER `host`");
            },
            // Los textos que todavia llevaban el nombre anterior. Solo se
            // tocan si nadie los cambio.
            3 => static function (): void {
                self::renombrarTextos();
                // El tope por lote de fabrica era 100 y dejaba fuera media
                // lista sin decirlo. Si nadie lo cambio, se sube a 300.
                if (Ajustes::obtener('max_sitios_lote') === '100') {
                    Ajustes::guardar('max_sitios_lote', '300');
                }
            },
            // Niveles educativos detectados en cada sitio del escaneo.
            4 => static function (): void { self::tablaSitios(); },
            // Kaptor pasa a ser privado: hace falta sesion siempre.
            5 => static function (): void { Ajustes::guardar('acceso_publico', '0'); },
            // Auditor web.
            6 => static function (): void { self::tablaAuditorias(); },
            // El auditor gana modos y guarda el rastreo de paginas.
            7 => static function (): void {
                self::tablaAuditorias();   // por si se instalo justo en la 6
                self::columna('cr_auditorias', 'modo', "VARCHAR(12) NOT NULL DEFAULT 'completo' AFTER `papel`");
            },
            // El rediseno: de negra y dorada a blanca con tinta y azul.
            8 => static function (): void {
                self::paletaNueva();
                self::textosRediseno();
            },
            // Arregla el 8: alli el tema claro se ponia dentro de paletaNueva(),
            // que se salta a quien ya habia elegido paleta.
            9 => static function (): void { Ajustes::guardar('tema_por_defecto', 'claro'); },
            // El lema se quedo sin cambiar en la 6.0.
            10 => static function (): void { self::textosRediseno(); },
            // Los colores de marca.
            11 => static function (): void { self::coloresMarca(); },
            // Textos cortos y el lema, ahora por contenido y no frase a frase.
            12 => static function (): void { self::textosCortos(); },
        ];

        $fallaron = [];
        foreach ($pasos as $version => $paso) {
            if ($actual >= $version && !in_array($version, $pendientes, true)) { continue; }
            try {
                $paso();
            } catch (Throwable $e) {
                $fallaron[] = $version;
                error_log('Kaptor / esquema paso ' . $version . ': ' . $e->getMessage());
            }
        }

        // La version se guarda pase lo que pase. Lo que no se pudo hacer queda
        // anotado aparte para volver a intentarlo, en vez de atascar al resto.
        try {
            Ajustes::guardar('esquema', (string) self::VERSION);
            Ajustes::guardar('esquema_pendiente', implode(',', $fallaron));
        } catch (Throwable $e) {
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
              `modo`        VARCHAR(12) NOT NULL DEFAULT \'completo\',
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
     * Textos de portada del rediseno.
     *
     * Mismo criterio que la paleta: solo se cambia el texto que sigue siendo
     * el de fabrica. Si el usuario escribio el suyo, no se toca.
     */
    private static function textosRediseno(): void
    {
        $anteriores = [
            // El lema sale en la pestana del navegador, en el pie y en la app
            // instalable. Se quedo sin cambiar en la 6.0 y seguia diciendo lo
            // de antes por todo el sitio.
            'sitio_lema' => [
                'Capta correos y WhatsApp de cualquier web',
                'Encuentra cada correo de cualquier web',
                'Extrae correos de cualquier web',
                'Capta correos de cualquier web',
                'Correos y WhatsApp de cualquier web',
            ],
            'hero_titulo' => [
                'Capta cada correo y WhatsApp de cualquier web',
                'Capta cada correo de cualquier web',
                'Extrae cada correo de cualquier web',
            ],
            'hero_subtitulo' => [
                'Pega un enlace y Kaptor barre el codigo, los enlaces mailto, las ofuscaciones y hasta los correos protegidos por Cloudflare.',
                'Pega un enlace y Kaptor barre el código, los enlaces mailto, las ofuscaciones y hasta los correos protegidos por Cloudflare.',
            ],
            'hero_placeholder' => [
                'https://ejemplo.com  ·  varias webs, una por línea  ·  o unas palabras para buscar',
                'https://ejemplo.com/contacto',
            ],
        ];
        $nuevos = [
            'sitio_lema'       => 'Extractor Web Inteligente',
            'hero_titulo'      => 'Extracción web inteligente',
            'hero_subtitulo'   => 'Una web, una lista completa, una búsqueda o una página de Facebook.',
            'hero_placeholder' => '',
        ];

        $cambios = [];
        foreach ($anteriores as $clave => $valores) {
            if (in_array(trim(Ajustes::obtener($clave, '')), $valores, true)) {
                $cambios[$clave] = $nuevos[$clave];
            }
        }
        if ($cambios) { Ajustes::guardarVarios($cambios); }
    }

    /**
     * Pone la paleta de la casa a quien seguia con la de fabrica antigua.
     *
     * La comprobacion es por el nombre de la paleta, no por cada color: si
     * el usuario escogio "Azul electrico" o se pinto los suyos a mano, esto
     * no le toca nada. Solo se mueve al que nunca entro a Apariencia.
     */
    /**
     * Los colores de la marca: azul #133E92, azul profundo #072B72 y el
     * naranja de realce #FF4800. Se ponen si y solo si el usuario seguia
     * con los que traia Kaptor de fabrica; si los habia cambiado a mano,
     * no se le tocan.
     */
    /**
     * Textos mas cortos. La pagina estaba cargada de parrafos que nadie lee;
     * se cambian por una linea. Igual que arriba: solo se toca lo que el
     * usuario no haya reescrito por su cuenta.
     */
    private static function textosCortos(): void
    {
        $anteriores = [
            'hero_subtitulo' => [
                'Una web, una lista completa, una búsqueda o una página de Facebook.',
            ],
            // El pie se trata aparte, mas abajo: el instalador le mete delante
            // el año y el nombre del sitio, asi que comparar la frase entera
            // no vale.
            'aviso_legal' => [
                'Usa Kaptor solo sobre sitios propios o con autorización. No envíes correo no solicitado.',
                'Usa Kaptor solo sobre sitios propios o con autorizacion. No envies correo no solicitado.',
            ],
            'sitio_descripcion' => [
                'Extractor web inteligente: correos, WhatsApp, auditoría, SEO y búsqueda de virus, en un solo lugar.',
            ],
        ];
        $nuevos = [
            'hero_subtitulo'    => 'Una web, una lista o una búsqueda.',
            'pie_texto'         => '© Kaptor. Extrae solo datos públicos.',
            'aviso_legal'       => 'Úsalo solo sobre sitios propios o con autorización.',
            'sitio_descripcion' => 'Correos, WhatsApp, auditoría, SEO y virus en un solo lugar.',
        ];

        $cambios = [];
        foreach ($anteriores as $clave => $valores) {
            if (in_array(trim((string) Ajustes::obtener($clave, '')), $valores, true)) {
                $cambios[$clave] = $nuevos[$clave];
            }
        }

        // El pie se compara por el final: el instalador le pone delante el año
        // y el nombre del sitio, asi que la frase entera nunca coincide.
        $pie = trim((string) Ajustes::obtener('pie_texto', ''));
        foreach (['respeta la legislación de protección de datos.',
                  'respeta la legislacion de proteccion de datos.'] as $cola) {
            if (str_ends_with($pie, $cola)) {
                $nombre = trim((string) Ajustes::obtener('sitio_nombre', 'Kaptor'));
                $cambios['pie_texto'] = '© ' . date('Y') . ' ' . $nombre . '. Extrae solo datos públicos.';
                break;
            }
        }

        // El lema, igual. Las versiones viejas dejaron por ahi media docena de
        // variantes ("Encuentra cada correo de cualquier web", "Capta correos
        // y WhatsApp de cualquier web"...) y compararlas una a una se queda
        // corto siempre. Cualquiera que hable de "cualquier web" o de "cada
        // correo" es de fabrica; un lema escrito por el usuario no dice eso.
        $lema = trim((string) Ajustes::obtener('sitio_lema', ''));
        $viejo = mb_strtolower($lema, 'UTF-8');
        $delaCasa = $lema === ''
            || str_contains($viejo, 'cualquier web')
            || str_contains($viejo, 'cada correo')
            || str_contains($viejo, 'extractor web inteligente');
        if ($delaCasa) { $cambios['sitio_lema'] = 'Extracción web inteligente'; }

        if ($cambios) { Ajustes::guardarVarios($cambios); }
    }

    private static function coloresMarca(): void
    {
        $anteriores = [
            'color_oro_claro'  => ['#1B3FA8', '#7E682F', '#D8B36A', '#8D7A40'],
            'color_oro2_claro' => ['#2B52C4', '#8D7A40', '#F3D89A', '#7E682F'],
        ];
        $nuevos = [
            'color_oro_claro'  => '#133E92',
            'color_oro2_claro' => '#072B72',
        ];

        foreach ($anteriores as $clave => $viejos) {
            $actual = strtoupper(trim((string) Ajustes::obtener($clave, '')));
            if ($actual === '' || in_array($actual, array_map('strtoupper', $viejos), true)) {
                Ajustes::guardar($clave, $nuevos[$clave]);
            }
        }

        // El realce no existia antes de esta version: se crea siempre.
        if (trim((string) Ajustes::obtener('color_acento_claro', '')) === '') {
            Ajustes::guardar('color_acento_claro', '#FF4800');
        }
        if (trim((string) Ajustes::obtener('color_acento', '')) === '') {
            Ajustes::guardar('color_acento', '#FF6A33');
        }
    }

    private static function paletaNueva(): void
    {
        $temas = Ajustes::temas();
        if (!isset($temas['tinta'])) { return; }

        // 'oro' era la paleta de fabrica del diseno anterior.
        if (Ajustes::obtener('tema_color', 'oro') !== 'oro') { return; }

        $t = $temas['tinta'];
        Ajustes::guardarVarios([
            'tema_color'         => 'tinta',
            'color_fondo'        => $t['fondo'],
            'color_fondo2'       => $t['fondo2'],
            'color_texto'        => $t['texto'],
            'color_oro'          => $t['oro'],
            'color_oro2'         => $t['oro2'],
            'color_neon'         => $t['neon'],
            'color_fondo_claro'  => $t['fondo_claro'],
            'color_fondo2_claro' => $t['fondo2_claro'],
            'color_texto_claro'  => $t['texto_claro'],
            'color_oro_claro'    => $t['oro_claro'],
            'color_oro2_claro'   => $t['oro2_claro'],
            'color_neon_claro'   => $t['neon_claro'],
        ]);
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
