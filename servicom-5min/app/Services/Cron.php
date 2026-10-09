<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Log;
use S5\Core\RateLimit;
use S5\Core\Settings;

/** Tareas periódicas: cron diario real (tools/cron.php) con respaldo "lazy cron". */
final class Cron
{
    public static function meta(string $k): ?string
    {
        $v = Db::val('SELECT v FROM ' . Db::t('meta') . ' WHERE k=?', [$k]);
        return $v === null ? null : (string) $v;
    }

    public static function setMeta(string $k, string $v): void
    {
        Db::q('INSERT INTO ' . Db::t('meta') . ' (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$k, $v]);
    }

    /** Respaldo: se dispara con el tráfico del portal como máximo una vez cada 6 h si el cron real no corrió. */
    public static function lazy(): void
    {
        if (PHP_SAPI === 'cli' || !\S5\Core\Config::installed()) {
            return;
        }
        $last = (int) (self::meta('cron_last') ?? 0);
        if (time() - $last < 6 * 3600) {
            return;
        }
        $got = Db::q('INSERT INTO ' . Db::t('meta') . ' (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=IF(CAST(v AS UNSIGNED)<?, VALUES(v), v)', ['cron_last', (string) time(), time() - 6 * 3600])->rowCount();
        if ($got < 1) {
            return;
        }
        self::run('lazy');
    }

    public static function run(string $origin = 'cron'): array
    {
        $out = [];
        self::setMeta('cron_last', (string) time());
        self::setMeta('cron_origin', $origin);
        try { $out['borrados'] = self::purgeExpired(); } catch (\Throwable $e) { Log::error('cron purge: ' . $e->getMessage()); $out['borrados'] = -1; }
        try { $out['renovaciones'] = self::renewals(); } catch (\Throwable $e) { Log::error('cron renov: ' . $e->getMessage()); }
        try { $out['reanudados'] = self::resumeStuck(); } catch (\Throwable $e) { Log::error('cron resume: ' . $e->getMessage()); }
        try { self::housekeeping(); } catch (\Throwable $e) { Log::error('cron hk: ' . $e->getMessage()); }
        Log::audit('cron', $origin . ' ' . json_encode($out));
        return $out;
    }

    /** Borra vistas previas sin pago vencidas (15 días) con todos sus archivos. */
    public static function purgeExpired(): int
    {
        $rows = Db::all('SELECT id FROM ' . Db::t('orders') . ' WHERE expires_at IS NOT NULL AND expires_at < ? AND status IN (?,?,?,?,?,?) AND is_demo=0 AND published_at IS NULL LIMIT 50',
            [Db::now(), Orders::ST_BORRADOR, Orders::ST_ANALIZANDO, Orders::ST_CONSTRUYENDO, Orders::ST_PREPARANDO, Orders::ST_LISTA, Orders::ST_ELIMINADA]);
        $n = 0;
        foreach ($rows as $r) {
            if (Lifecycle::deletePreview((int) $r['id'], 'vencida a los ' . Settings::int('preview_days', 15) . ' días')) {
                $n++;
            }
        }
        return $n;
    }

    public static function renewals(): int
    {
        $n = 0;
        $limit = gmdate('Y-m-d', time() + Settings::int('renewal_notice_days', 30) * 86400);
        foreach (Db::all('SELECT * FROM ' . Db::t('orders') . ' WHERE status IN (?,?) AND renewal_at IS NOT NULL AND renewal_at<=? AND renewal_notified=0', [Orders::ST_PUBLICADA, Orders::ST_VENCIDA, $limit]) as $o) {
            Orders::alert('warn', 'Renovación próxima: ' . ($o['business_name'] ?: $o['fqdn']) . ' vence el ' . $o['renewal_at'], (int) $o['id']);
            Notifier::renewalSoon($o);
            Orders::set((int) $o['id'], ['renewal_notified' => 1]);
            $n++;
        }
        foreach (Db::all('SELECT id FROM ' . Db::t('orders') . ' WHERE status=? AND renewal_at < ?', [Orders::ST_PUBLICADA, gmdate('Y-m-d')]) as $o) {
            Orders::setStatus((int) $o['id'], Orders::ST_VENCIDA);
        }
        return $n;
    }

    /** Continúa construcciones detenidas (cliente cerró el navegador). */
    public static function resumeStuck(): int
    {
        $n = 0;
        foreach (Db::all('SELECT id FROM ' . Db::t('orders') . ' WHERE status=? AND updated_at < ? LIMIT 3', [Orders::ST_CONSTRUYENDO, gmdate('Y-m-d H:i:s', time() - 120)]) as $r) {
            Pipeline::tick((int) $r['id'], 20);
            $n++;
        }
        return $n;
    }

    private static function housekeeping(): void
    {
        RateLimit::purge();
        Db::q('DELETE FROM ' . Db::t('meta') . ' WHERE k LIKE ? AND CAST(v AS UNSIGNED) < ?', ['asset-%', time() - 86400]);
        $sess = S5_ROOT . '/storage/sessions';
        foreach (glob($sess . '/sess_*') ?: [] as $f) {
            if (filemtime($f) < time() - 86400 * 2) { @unlink($f); }
        }
        // limpieza de audit_log antiguo (> 1 año)
        Db::q('DELETE FROM ' . Db::t('audit_log') . ' WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 400 * 86400)]);
        // trabajos huérfanos
        $jobs = Files::root() . '/jobs';
        if (is_dir($jobs)) {
            foreach (glob($jobs . '/*', GLOB_ONLYDIR) ?: [] as $d) {
                $oid = (int) basename($d);
                $o = $oid ? Orders::byId($oid) : null;
                if ((!$o || $o['status'] !== Orders::ST_CONSTRUYENDO) && filemtime($d) < time() - 86400 && Fs::inside($d, $jobs)) {
                    Fs::rmTreeSafe($d, [$jobs]);
                }
            }
        }
    }
}
