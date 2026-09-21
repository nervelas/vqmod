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
            'hero_placeholder'    => 'https://ejemplo.com  ·  varias webs, una por línea  ·  o unas palabras para buscar',
            'hero_boton'          => 'Capturar contactos',
            'hero_etiqueta'       => 'Pega una web, una lista o una búsqueda',
            'aviso_legal'         => 'Usa Kaptor solo sobre sitios propios o con autorización. No envíes correo no solicitado.',

            // --- Paleta "Radar de lujo" ------------------------------------
            'tema_color'          => 'oro',   // paleta elegida en el panel
            'color_fondo'         => '#06070A',
            'color_fondo2'        => '#0D0F14',   // segundo tono del degradado del fondo
            'color_oro'           => '#D8B36A',
            'color_oro2'          => '#B8873A',   // segundo color del degradado
            'color_neon'          => '#F0C674',
            // Colores del modo claro (los define también la paleta elegida)
            'color_fondo_claro'   => '#FFFFFF',
            'color_fondo2_claro'  => '#F4F6FA',
            'color_texto_claro'   => '#111418',
            'color_oro_claro'     => '#816D46',
            'color_oro2_claro'    => '#8D6931',
            'color_neon_claro'    => '#816D46',
            'color_texto'         => '#F2F4F7',
            'tema_por_defecto'    => 'oscuro',

            // --- Motor de extracción ---------------------------------------
            'timeout'             => '20',    // segundos por página
            'max_paginas'         => '30',    // páginas por rastreo profundo
            'max_profundidad'     => '2',     // niveles de enlaces internos
            'max_correos'         => '1000',  // tope de correos por escaneo
            'max_telefonos'       => '500',   // tope de teléfonos por escaneo
            // --- Búsqueda y lotes -----------------------------------------
            'redes_sociales'      => '1',     // entra en Facebook e Instagram
            'seguir_redes'        => '1',     // visita el Facebook/Instagram que enlaza la web
            'buscar_redes'        => '0',     // incluye perfiles sociales en las búsquedas
            'buscar_activo'       => '1',     // permite buscar por palabras
            'buscador_motor'      => 'auto',  // auto | duckduckgo | bing | google
            'buscador_max'        => '100',   // webs que se traen de la búsqueda
            'max_sitios_lote'     => '300',   // webs por escaneo en lote
            'paginas_por_sitio'   => '4',     // páginas que se miran de cada web
            'buscar_whatsapp'     => '1',     // detectar WhatsApp y teléfonos
            'prefijo_pais'        => '',      // p. ej. 502; permite leer números locales
            'max_bytes'           => '3000000',

            // --- Auditor web ------------------------------------------------
            'auditor_activo'      => '1',
            'auditor_timeout'     => '25',    // segundos por página auditada
            'auditor_max_lote'    => '50',    // sitios por tanda
            'psi_activo'          => '1',     // pedir la nota a Google
            'malware_activo'      => '1',     // buscar código malicioso
            'seo_max_paginas'     => '100',   // páginas que recorre el análisis del sitio
            'vt_clave'            => '',      // clave de VirusTotal (opcional)
            'psi_clave'           => '',      // clave propia: 25.000 al día
            'informe_lema'        => 'Diagnóstico técnico de tu sitio web',
            'informe_contacto'    => '',      // lo que aparece al pie del informe
            'informe_cta'         => 'Podemos corregir todo esto. Escríbenos y te decimos cuánto cuesta y cuánto tarda.',

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
