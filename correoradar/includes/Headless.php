<?php
/**
 * CorreoRadar - Soporte opcional de navegador sin interfaz (headless).
 *
 * Algunas webs cargan el correo por JavaScript después de pintar la página.
 * CorreoRadar ya cubre la mayoria de esos casos sin navegador (decodifica
 * base64, ROT13, fromCharCode, concatenaciones y además revisa los archivos JS
 * y los endpoints JSON). Cuando eso no basta y el hosting lo permite, se puede
 * activar un navegador real para renderizar la página.
 *
 * Si el hosting no lo permite -lo habitual en un alojamiento compartido- la
 * aplicación avisa al usuario de forma elegante en lugar de fallar.
 */
declare(strict_types=1);

final class Headless
{
    /** Rutas habituales de Chrome/Chromium en servidores Linux. */
    private const BINARIOS = [
        '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable', '/usr/local/bin/chromium', '/opt/google/chrome/chrome',
        '/opt/pw-browsers/chromium',
    ];

    /** ¿Puede este servidor ejecutar procesos externos? */
    public static function puedeEjecutar(): bool
    {
        if (!function_exists('proc_open')) { return false; }
        $deshabilitadas = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $deshabilitadas, true);
    }

    /** Ruta del navegador disponible, o '' si no hay ninguno. */
    public static function binario(): string
    {
        $configurado = trim(Ajustes::obtener('headless_binario'));
        if ($configurado !== '' && is_file($configurado) && is_executable($configurado)) {
            return $configurado;
        }
        foreach (self::BINARIOS as $ruta) {
            if (is_file($ruta) && is_executable($ruta)) { return $ruta; }
        }
        return '';
    }

    /** ¿Esta disponible y activado el modo navegador? */
    public static function disponible(): bool
    {
        return Ajustes::activo('headless_activo') && self::puedeEjecutar() && self::binario() !== '';
    }

    /**
     * Renderiza una página con el navegador y devuelve el HTML resultante.
     * Devuelve '' si no se pudo (y entonces se usa la descarga normal).
     */
    public static function renderizar(string $url, int $timeout = 25): string
    {
        if (!self::disponible()) { return ''; }

        $binario = self::binario();
        $comando = escapeshellcmd($binario) . ' --headless=new --disable-gpu --no-sandbox --disable-dev-shm-usage'
            . ' --virtual-time-budget=6000 --timeout=' . ($timeout * 1000)
            . ' --user-agent=' . escapeshellarg(Ajustes::obtener('user_agent'))
            . ' --dump-dom ' . escapeshellarg($url) . ' 2>/dev/null';

        $descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proceso = @proc_open($comando, $descriptores, $tuberias);
        if (!is_resource($proceso)) { return ''; }

        $html = (string) stream_get_contents($tuberias[1]);
        foreach ($tuberias as $t) { if (is_resource($t)) { fclose($t); } }
        proc_close($proceso);

        return strlen($html) > 200 ? $html : '';
    }

    /**
     * Mensaje informativo para el usuario sobre el contenido cargado por
     * JavaScript. Devuelve null cuando no hay nada que avisar.
     */
    public static function aviso(): ?string
    {
        if (self::disponible()) {
            return 'Modo navegador activo: también se leera el contenido que la web genera con JavaScript.';
        }
        if (Ajustes::activo('headless_activo')) {
            return 'Este hosting no permite abrir un navegador interno, así que el contenido generado por JavaScript se analiza leyendo los archivos JS, los endpoints JSON y el sitemap del sitio.';
        }
        return null;
    }
}
