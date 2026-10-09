<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Config;
use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Schema;
use S5\Core\Settings;

/** Página de diagnóstico del panel. */
final class Diagnostics
{
    private static function item(string $label, ?bool $ok, string $detail = ''): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }

    public static function run(): array
    {
        $out = [];
        $g = [];
        $g[] = self::item('Versión de PHP', PHP_VERSION_ID >= 80000, PHP_VERSION . ' (mínimo 8.0)');
        foreach (['zip', 'dom', 'xml', 'mbstring', 'fileinfo', 'curl', 'sodium', 'pdo_mysql'] as $e) {
            $g[] = self::item('Extensión ' . $e, extension_loaded($e));
        }
        $g[] = self::item('Extensión gd o imagick', extension_loaded('gd') || extension_loaded('imagick'));
        if (extension_loaded('gd')) {
            $g[] = self::item('GD con WebP', function_exists('imagewebp'), function_exists('imagewebp') ? '' : 'Sin WebP: se usarán JPG/PNG');
        }
        $g[] = self::item('proc_open disponible', function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true), 'Si no está, el análisis y la construcción usan el modo por HTTP');
        $g[] = self::item('upload_max_filesize', self::bytes((string) ini_get('upload_max_filesize')) >= 12 * 1048576, (string) ini_get('upload_max_filesize') . ' (recomendado ≥ 16M)');
        $g[] = self::item('post_max_size', self::bytes((string) ini_get('post_max_size')) >= 14 * 1048576, (string) ini_get('post_max_size') . ' (recomendado ≥ 20M)');
        $g[] = self::item('Tiempo máximo de ejecución', null, (string) ini_get('max_execution_time') . ' s (los pasos largos se dividen)');
        $out['Servidor'] = $g;

        $d = [];
        try {
            Db::val('SELECT 1');
            $d[] = self::item('Conexión a la base de datos', true);
            $missing = [];
            foreach (Schema::statements(Db::prefix()) as $sql) {
                if (preg_match('/EXISTS `([^`]+)`/', $sql, $m) && !Db::val('SHOW TABLES LIKE ?', [$m[1]])) {
                    $missing[] = $m[1];
                }
            }
            $d[] = self::item('Tablas del portal', !$missing, $missing ? 'Faltan: ' . implode(', ', $missing) : 'Completas');
        } catch (\Throwable $e) {
            $d[] = self::item('Conexión a la base de datos', false, 'No se pudo conectar');
        }
        $out['Base de datos'] = $d;

        $p = [];
        foreach (['storage', 'storage/logs', 'storage/sessions', 'storage/uploads', 'storage/jobs'] as $dir) {
            $path = S5_ROOT . '/' . $dir;
            @mkdir($path, 0750, true);
            $p[] = self::item('Escritura en ' . $dir, is_dir($path) && is_writable($path));
        }
        $dataFree = Fs::freeSpace(Files::root());
        $p[] = self::item('Espacio libre (almacenamiento del portal)', $dataFree < 0 ? null : $dataFree > 500 * 1048576, $dataFree < 0 ? 'No disponible' : self::human($dataFree));
        $p[] = self::item('Configuración fuera del docroot', Config::file() !== null && !str_starts_with((string) Config::file(), S5_ROOT . '/'), (string) Config::file());
        $p[] = self::item('install.php eliminado', !is_file(S5_ROOT . '/install.php'));
        $out['Archivos'] = $p;

        $h = [];
        foreach (Hosts::all() as $host) {
            try {
                $r = Hosts::driver((int) $host['id'])->ping();
                $h[] = self::item('Hosting «' . $host['name'] . '»: conexión y token', !empty($r['cpanel']['ok']), (string) ($r['cpanel']['message'] ?? ''));
                $h[] = self::item('«' . $host['name'] . '»: paquete base (WordPress ' . ($r['wp_version'] ?? '?') . ')', !empty($r['base_ok']), !empty($r['base_ok']) ? '' : 'Falta webs-clientes/_base: ejecute tools/build_base.php');
                $h[] = self::item('«' . $host['name'] . '»: Elementor', !empty($r['plugins']['elementor']));
                $h[] = self::item('«' . $host['name'] . '»: WooCommerce', !empty($r['plugins']['woocommerce']), 'Solo necesario para tiendas');
                $h[] = self::item('«' . $host['name'] . '»: tema y mu-plugin Servicom', !empty($r['theme']) && !empty($r['mu_plugin']));
                $h[] = self::item('«' . $host['name'] . '»: carpeta de webs escribible', !empty($r['webs_writable']), (string) ($r['webs_path'] ?? ''));
                $h[] = self::item('«' . $host['name'] . '»: espacio libre', ($r['disk_free'] ?? -1) < 0 ? null : $r['disk_free'] > 1024 * 1048576, ($r['disk_free'] ?? -1) < 0 ? '' : self::human((float) $r['disk_free']));
                $h[] = self::item('«' . $host['name'] . '»: PHP ' . ($r['php'] ?? ''), isset($r['php']) && version_compare((string) $r['php'], '8.0', '>='));
                $missingExt = array_keys(array_filter($r['extensions'] ?? [], fn($v, $k) => !$v && in_array($k, ['zip', 'dom', 'xml', 'mbstring', 'fileinfo', 'curl', 'mysqli'], true), ARRAY_FILTER_USE_BOTH));
                $h[] = self::item('«' . $host['name'] . '»: extensiones', !$missingExt, $missingExt ? 'Faltan: ' . implode(', ', $missingExt) : '');
            } catch (\Throwable $e) {
                $h[] = self::item('Hosting «' . $host['name'] . '»', false, $e->getMessage());
            }
        }
        if (!$h) {
            $h[] = self::item('Hostings configurados', false, 'Agregue al menos uno en Hostings');
        }
        $out['Hostings'] = $h;

        $a = [];
        $a[] = self::item('Clave de la IA configurada', Settings::has('ai_key'), Settings::has('ai_key') ? 'Guardada (cifrada)' : 'Sin clave: se usarán textos base');
        $s = AiBudget::resumen();
        $a[] = self::item('Consumo de IA', null, 'Hoy $' . number_format((float) ($s['dia']['total'] ?? 0), 4) . ' · Total $' . number_format((float) ($s['total']['total'] ?? 0), 4));
        $last = (int) (Cron::meta('cron_last') ?? 0);
        $a[] = self::item('Último cron', $last > 0 && time() - $last < 90000, $last ? gmdate('Y-m-d H:i', $last) . ' UTC (' . (Cron::meta('cron_origin') ?? '') . ')' : 'Nunca. Configure el cron diario (ver LEEME.md)');
        $a[] = self::item('Datos bancarios completos', Settings::get('banco_cuenta') !== '' && Settings::get('banco_titular') !== '');
        $a[] = self::item('Correo del dueño', Settings::get('owner_email') !== '');
        $a[] = self::item('WhatsApp de Servicom', Settings::get('wa_servicom') !== '');
        $a[] = self::item('Fotos de stock disponibles', array_sum(array_map('array_sum', Stock::available())) > 0, 'Si no hay, las tarjetas de servicio usan iconos (ver library/FALTANTES.md)');
        $out['Configuración'] = $a;
        return $out;
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        $n = (int) $v;
        $u = strtolower(substr($v, -1));
        return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
    }

    private static function human(float $b): string
    {
        return $b >= 1073741824 ? round($b / 1073741824, 1) . ' GB' : round($b / 1048576) . ' MB';
    }

    /** Prueba mínima de la API de IA. */
    public static function aiTest(): array
    {
        if (!Settings::has('ai_key')) {
            return ['ok' => false, 'msg' => 'No hay clave de IA guardada.'];
        }
        if (!AiBudget::puedeGastar('redaccion')) {
            return ['ok' => false, 'msg' => 'Se alcanzó el tope de gasto de IA; no se hizo la prueba.'];
        }
        $model = (string) Settings::get('ai_model_fallback', 'claude-haiku-5-5');
        $body = json_encode(['model' => $model, 'max_tokens' => 16, 'messages' => [['role' => 'user', 'content' => 'Responde solo con la palabra: listo']]]);
        $r = AiClient::transporteCurl('https://api.anthropic.com/v1/messages', ['x-api-key: ' . Settings::get('ai_key'), 'anthropic-version: 2023-06-01', 'content-type: application/json'], (string) $body);
        $j = json_decode((string) ($r['body'] ?? ''), true);
        $ok = ($r['status'] ?? 0) === 200 && is_array($j);
        if ($ok) {
            AiBudget::registrar('redaccion', $model, (int) ($j['usage']['input_tokens'] ?? 10), (int) ($j['usage']['output_tokens'] ?? 5), null, true);
        }
        return ['ok' => $ok, 'msg' => $ok ? 'La API respondió correctamente (' . $model . ').' : 'La API respondió HTTP ' . ($r['status'] ?? 0) . ': ' . mb_substr((string) ($j['error']['message'] ?? ($r['error'] ?? '')), 0, 200)];
    }
}
