<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Log;

/** Análisis de la presentación en segundo plano (proceso aparte si se puede) con sondeo de estado. */
final class Analysis
{
    private const LOCK_SEC = 150;

    /** Marca el análisis como pendiente. */
    public static function queue(int $orderId): void
    {
        Orders::set($orderId, ['analysis_state' => 'pendiente', 'analysis_msg' => null, 'status' => Orders::ST_ANALIZANDO]);
    }

    /** Intenta lanzar un proceso CLI en segundo plano. Devuelve true si se pudo. */
    public static function spawn(int $orderId): bool
    {
        if (getenv('S5_NO_SPAWN')) {
            return false;
        }
        if (!function_exists('proc_open')) {
            return false;
        }
        $dis = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('proc_open', $dis, true)) {
            return false;
        }
        $php = self::phpCli();
        if ($php === '') {
            return false;
        }
        $script = S5_ROOT . '/tools/worker.php';
        if (!is_file($script)) {
            return false;
        }
        $cmd = [$php, $script, 'analyze', (string) $orderId];
        $env = ['PATH' => '/usr/local/bin:/usr/bin:/bin'];
        foreach (array_keys(getenv()) as $k) {
            if (str_starts_with((string) $k, 'S5_')) { $env[$k] = (string) getenv($k); }
        }
        $p = @proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, S5_ROOT, $env);
        if (!is_resource($p)) {
            return false;
        }
        // no esperamos: el proceso sigue por su cuenta
        return true;
    }

    private static function phpCli(): string
    {
        $c = [];
        if (defined('PHP_BINARY') && PHP_BINARY && !preg_match('/fpm|cgi|lsphp|litespeed/i', PHP_BINARY)) { $c[] = PHP_BINARY; }
        foreach (['/opt/cpanel/ea-php84', '/opt/cpanel/ea-php83', '/opt/cpanel/ea-php82', '/opt/cpanel/ea-php81', '/opt/cpanel/ea-php80'] as $d) { $c[] = $d . '/root/usr/bin/php'; }
        array_push($c, '/usr/local/bin/php', '/usr/bin/php');
        foreach ($c as $p) { if (is_file($p) && is_executable($p)) { return $p; } }
        return '';
    }

    /** Ejecuta el análisis (bloqueante). Seguro de invocar varias veces: usa candado. */
    public static function run(int $orderId): void
    {
        $o = Orders::byId($orderId);
        if (!$o || !in_array($o['analysis_state'], ['pendiente', 'procesando'], true)) {
            return;
        }
        $got = Db::q('UPDATE ' . Db::t('orders') . ' SET analysis_state=?, analysis_lock=? WHERE id=? AND (analysis_state=? OR (analysis_state=? AND (analysis_lock IS NULL OR analysis_lock<?)))',
            ['procesando', gmdate('Y-m-d H:i:s', time() + self::LOCK_SEC), $orderId, 'pendiente', 'procesando', Db::now()])->rowCount();
        if ($got !== 1) {
            return;
        }
        @set_time_limit(170);
        $work = Files::root() . '/work/' . $orderId;
        try {
            $f = Files::forOrder($orderId, 'presentacion')[0] ?? null;
            if (!$f) {
                throw new AiUnavailable('No encontramos su archivo. Puede subirlo de nuevo o llenar los datos manualmente.');
            }
            Fs::mkdir($work, 0750);
            $tipo = ['application/pdf' => 'pdf', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'][$f['mime']] ?? '';
            $res = PresentationAnalyzer::analizar($orderId, Files::path($f), $tipo, $work);
            // Las imágenes extraídas pasan a ser archivos del pedido (kind presentacion_img), solo candidatas hasta que el cliente confirme
            $imgs = [];
            foreach (($res['imagenes'] ?? []) as $im) {
                $rel = is_array($im) ? (string) ($im['archivo'] ?? '') : '';
                $abs = $work . '/' . ltrim($rel, '/');
                if ($rel === '' || !is_file($abs) || !Fs::inside($abs, $work)) { continue; }
                $nf = Files::adoptImage($orderId, $abs, 'presentacion_img', 'presentacion', 'presentacion-' . (count($imgs) + 1));
                if ($nf) { $imgs[] = ['id' => (int) $nf['id'], 'w' => (int) $nf['w'], 'h' => (int) $nf['h'], 'logo' => !empty($im['logo']), 'pagina' => isset($im['pagina']) ? (int) $im['pagina'] : null]; }
                if (count($imgs) >= 40) { break; }
            }
            $res['imagenes'] = $imgs;
            // conflictos con lo que el cliente ya escribió (vista previa de la fusión)
            $o = Orders::byId($orderId);
            $m = Brief::mergePresentation(Orders::data($o), $res, ['nombre' => 1, 'contacto' => 1], [], $orderId);
            $res['conflictos'] = $m['conflictos'];
            Db::update('orders', ['analysis' => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'analysis_state' => 'lista', 'analysis_msg' => null, 'analysis_lock' => null, 'status' => Orders::ST_BORRADOR], 'id=?', [$orderId]);
            $d = Orders::data(Orders::byId($orderId));
            $d['presentacion']['estado'] = 'lista';
            Orders::saveData($orderId, $d);
        } catch (AiUnavailable $e) {
            self::fail($orderId, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Análisis falló: ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine());
            self::fail($orderId, 'No pudimos leer su presentación ahora; puede llenar los datos manualmente.');
        }
        // el archivo de trabajo se elimina siempre; el original se conserva hasta publicar o borrar (para reanalizar)
        if (is_dir($work) && Fs::inside($work, Files::root() . '/work')) {
            Fs::rmTreeSafe($work, [Files::root() . '/work']);
        }
    }

    private static function fail(int $orderId, string $msg): void
    {
        Db::update('orders', ['analysis_state' => 'error', 'analysis_msg' => mb_substr($msg, 0, 250), 'analysis_lock' => null, 'status' => Orders::ST_BORRADOR], 'id=?', [$orderId]);
        $o = Orders::byId($orderId);
        if ($o) {
            $d = Orders::data($o);
            $d['presentacion']['estado'] = 'error';
            Orders::saveData($orderId, $d);
        }
    }

    /** Si el análisis quedó colgado (proceso muerto), lo devuelve a pendiente o a error. */
    public static function reapStale(array $o): array
    {
        if ($o['analysis_state'] === 'procesando' && $o['analysis_lock'] && strtotime($o['analysis_lock'] . ' UTC') < time()) {
            self::fail((int) $o['id'], 'No pudimos leer su presentación ahora; puede llenar los datos manualmente.');
            return Orders::byId((int) $o['id']);
        }
        return $o;
    }
}
