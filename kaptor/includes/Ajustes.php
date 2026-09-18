<?php
/**
 * Kaptor - Ajustes del sitio (tabla cr_ajustes).
 *
 * Todo lo configurable desde el panel pasa por aquí: textos, colores, logo,
 * límites del rastreo, permisos de acceso, etc. Se cargan una sola vez por
 * petición y se cachean en memoria.
 */
declare(strict_types=1);

final class Ajustes
{
    /** @var array<string,string> */
    private static array $valores = [];
    private static bool $cargado = false;

    /**
     * Valores por defecto. Sirven de respaldo si la clave no existe todavia
     * en la base de datos (por ejemplo tras una actualización).
     *
     * @return array<string,string>
     */
    public static function porDefecto(): array
    {
        return [
            // --- Identidad -------------------------------------------------
            'sitio_nombre'        => 'Kaptor',
            'sitio_lema'          => 'Capta correos y WhatsApp de cualquier web',
            'sitio_descripcion'   => 'Extrae todos los correos y números de WhatsApp de cualquier página web en un solo clic, y envía campañas desde tu propio dominio.',
            'logo'                => '',
            'favicon'             => '',
            'pie_texto'           => '© Kaptor. Uso responsable: extrae solo datos públicos y respeta la legislación de protección de datos.',

            // --- Textos de la portada --------------------------------------
            'hero_titulo'         => 'Capta cada correo y WhatsApp de cualquier web',
            'hero_subtitulo'      => 'Pega un enlace y Kaptor barre el código, los enlaces mailto, las ofuscaciones y hasta los correos protegidos por Cloudflare.',
            'hero_placeholder'    => 'https://ejemplo.com/contacto',
            'hero_boton'          => 'Capturar contactos',
            'hero_etiqueta'       => 'Pega tu enlace',
            'aviso_legal'         => 'Usa Kaptor solo sobre sitios propios o con autorización. No envíes correo no solicitado.',

            // --- Paleta "Radar de lujo" ------------------------------------
            'tema_color'          => 'obsidiana',   // paleta elegida en el panel
            'color_fondo'         => '#07080A',
            'color_oro'           => '#D8B36A',
            'color_oro2'          => '#F3D89A',   // segundo color del degradado
            'color_neon'          => '#6EF3A5',
            // Colores del modo claro (los define también la paleta elegida)
            'color_fondo_claro'   => '#FBF8F0',
            'color_texto_claro'   => '#171512',
            'color_oro_claro'     => '#7E682F',
            'color_oro2_claro'    => '#8D7A40',
            'color_neon_claro'    => '#3B7F55',
            'color_texto'         => '#EDEAE3',
            'tema_por_defecto'    => 'oscuro',

            // --- Motor de extracción ---------------------------------------
            'timeout'             => '20',    // segundos por página
            'max_paginas'         => '30',    // páginas por rastreo profundo
            'max_profundidad'     => '2',     // niveles de enlaces internos
            'max_correos'         => '1000',  // tope de correos por escaneo
            'max_telefonos'       => '500',   // tope de teléfonos por escaneo
            'buscar_whatsapp'     => '1',     // detectar WhatsApp y teléfonos
            'prefijo_pais'        => '',      // p. ej. 502; permite leer números locales
            'max_bytes'           => '3000000',
            'rastreo_profundo'    => '1',
            'analizar_js_css'     => '1',
            'analizar_sitemap'    => '1',
            'analizar_json'       => '1',
            'verificar_mx'        => '0',
            'tld_estricto'        => '1',
            'permitir_privadas'   => '0',
            'ssl_estricto'        => '0',
            'dominios_excluidos'  => '',
            'user_agent'          => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'headless_activo'     => '0',
            'headless_binario'    => '',
            'pausa_ms'            => '0',

            // --- Acceso y límites ------------------------------------------
            'acceso_publico'      => '0',   // 1 = cualquier visitante puede extraer
            'registro_publico'    => '0',   // 1 = cualquiera puede crearse una cuenta
            'limite_ip_hora'      => '30',  // 0 = sin límite
            'guardar_historial'   => '1',
            'retencion_dias'      => '90',  // 0 = no borrar nunca

            // --- Campañas de correo ----------------------------------------
            'campanas_activas'      => '1',
            'remitente_postal'      => '',   // dirección física para el pie legal
            'seguimiento_aperturas' => '1',
            'seguimiento_clics'     => '1',
            'smtp_timeout'          => '20',
            'cron_clave'            => '',   // la genera el instalador
            'lote_envio'            => '25', // tope de correos por llamada al motor
        ];
    }

    /** Carga todos los ajustes desde la base de datos. */
    public static function cargar(bool $forzar = false): void
    {
        if (self::$cargado && !$forzar) { return; }
        self::$valores = self::porDefecto();

        try {
            foreach (BD::todos('SELECT `clave`, `valor` FROM `cr_ajustes`') as $fila) {
                self::$valores[$fila['clave']] = (string) $fila['valor'];
            }
        } catch (PDOException $e) {
            // Sin tabla todavia (instalación en curso): seguimos con los valores por defecto.
            error_log('Kaptor / ajustes: ' . $e->getMessage());
        }
        self::$cargado = true;
    }

    /** Devuelve un ajuste como texto. */
    public static function obtener(string $clave, string $defecto = ''): string
    {
        if (!self::$cargado) { self::cargar(); }
        return self::$valores[$clave] ?? $defecto;
    }

    /** Devuelve un ajuste como entero, con límites opcionales. */
    public static function entero(string $clave, int $defecto = 0, ?int $min = null, ?int $max = null): int
    {
        $valor = (int) self::obtener($clave, (string) $defecto);
        if ($min !== null && $valor < $min) { $valor = $min; }
        if ($max !== null && $valor > $max) { $valor = $max; }
        return $valor;
    }

    /** Devuelve un ajuste como booleano ("1" = verdadero). */
    public static function activo(string $clave, bool $defecto = false): bool
    {
        $valor = self::obtener($clave, $defecto ? '1' : '0');
        return $valor === '1' || $valor === 'on' || $valor === 'true';
    }

    /** Guarda (o crea) un ajuste. */
    public static function guardar(string $clave, string $valor): void
    {
        BD::ejecutar(
            'INSERT INTO `cr_ajustes` (`clave`,`valor`,`actualizado`) VALUES (?,?,NOW())
             ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`), `actualizado` = NOW()',
            [$clave, $valor]
        );
        self::$valores[$clave] = $valor;
    }

    /** Guarda varios ajustes de una vez. */
    public static function guardarVarios(array $pares): void
    {
        foreach ($pares as $clave => $valor) {
            self::guardar((string) $clave, (string) $valor);
        }
    }

    /** Todos los ajustes actuales. @return array<string,string> */
    public static function todos(): array
    {
        if (!self::$cargado) { self::cargar(); }
        return self::$valores;
    }

    /**
     * Paletas de color disponibles (includes/datos/temas.php).
     *
     * @return array<string,array{nombre:string,pista:string,fondo:string,oro:string,neon:string,texto:string}>
     */
    public static function temas(): array
    {
        static $temas = null;
        if ($temas === null) {
            $ruta  = CR_RAIZ . '/includes/datos/temas.php';
            $temas = is_file($ruta) ? (array) require $ruta : [];
        }
        return $temas;
    }
}
