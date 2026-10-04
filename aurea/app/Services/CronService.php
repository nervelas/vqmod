<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;
use Aurea\Core\Logger;
use Aurea\Core\RateLimit;
use Aurea\Core\Settings;

/** Tareas programadas: recordatorios, cola de mensajes, lista de espera, pendientes vencidos y limpieza. */
final class CronService
{
    /** Ejecuta todo. $force ignora el candado de 'ya corrió hace poco'. */
    public static function run(bool $force = false): array
    {
        $now = time();
        // Candado atómico para que dos ejecuciones simultáneas no dupliquen trabajo
        // Se lee directamente de la BD (no de la caché de archivo) para que el candado sea fiable
        $last = (int)Db::val("SELECT svalue FROM settings WHERE skey='cron_last_run'");
        if (!$force && $now - $last < 50) { return ['skipped' => 'ya se ejecutó hace menos de un minuto']; }
        $upd = Db::exec("UPDATE settings SET svalue=?, updated_at=? WHERE skey='cron_last_run' AND CAST(svalue AS UNSIGNED)=?", [(string)$now, date('Y-m-d H:i:s'), $last]);
        if ($upd === 0) {
            if (Db::val("SELECT COUNT(*) FROM settings WHERE skey='cron_last_run'")) { return ['skipped' => 'otra ejecución en curso']; }
            Db::exec('INSERT IGNORE INTO settings (skey,svalue,updated_at) VALUES (?,?,?)', ['cron_last_run', (string)$now, date('Y-m-d H:i:s')]);
        }
        Settings::flush();
        $res = [];
        try { $res['recordatorios'] = self::ensureReminders(); } catch (\Throwable $e) { Logger::exception($e); $res['recordatorios'] = 'error'; }
        try { $res['mensajes'] = NotificationService::process(60); } catch (\Throwable $e) { Logger::exception($e); $res['mensajes'] = 'error'; }
        try { $res['pendientes_vencidos'] = self::expirePending(); } catch (\Throwable $e) { Logger::exception($e); $res['pendientes_vencidos'] = 'error'; }
        try { $res['lista_espera'] = WaitlistService::expireOffers(); } catch (\Throwable $e) { Logger::exception($e); $res['lista_espera'] = 'error'; }
        try { $res['limpieza'] = self::cleanup(); } catch (\Throwable $e) { Logger::exception($e); $res['limpieza'] = 'error'; }
        Settings::set('cron_last_ok', date('Y-m-d H:i:s'));
        return $res;
    }

    /** Garantiza recordatorios para citas confirmadas próximas (por si se crearon antes de activar la opción). */
    private static function ensureReminders(): int
    {
        $n = 0;
        $rows = Db::all("SELECT id FROM appointments WHERE status='confirmed' AND start_at BETWEEN ? AND ?", [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() + 2 * 86400)]);
        foreach ($rows as $r) { NotificationService::scheduleReminders((int)$r['id']); $n++; }
        return $n;
    }

    private static function expirePending(): int
    {
        $n = 0;
        foreach (Db::all("SELECT id FROM appointments WHERE status='pending' AND pending_expires_at IS NOT NULL AND pending_expires_at<?", [date('Y-m-d H:i:s')]) as $r) {
            [$ok] = BookingService::setStatus((int)$r['id'], 'cancelled', 'Venció el tiempo de confirmación');
            if ($ok) { $n++; }
        }
        return $n;
    }

    private static function cleanup(): int
    {
        RateLimit::purge();
        Db::exec('UPDATE users SET reset_token=NULL, reset_expires=NULL WHERE reset_expires IS NOT NULL AND reset_expires<?', [date('Y-m-d H:i:s')]);
        Db::exec("DELETE FROM notifications_queue WHERE status IN ('sent','skipped') AND created_at<?", [date('Y-m-d H:i:s', time() - 90 * 86400)]);
        $n = 0;
        $dir = AUREA_ROOT . '/storage/sessions';
        foreach (glob($dir . '/sess_*') ?: [] as $f) {
            if (filemtime($f) < time() - 86400 * 2) { @unlink($f); $n++; }
        }
        return $n;
    }

    /** Modo de respaldo: si no hay cron real, se ejecuta tras visitas (como máximo cada 5 minutos). */
    public static function maybeRunOnVisit(): void
    {
        if (!Settings::bool('cron_fallback', true)) { return; }
        if (time() - (int)Settings::get('cron_last_run', '0') < 300) { return; }
        register_shutdown_function(static function (): void {
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
            try { self::run(); } catch (\Throwable $e) { Logger::exception($e); }
        });
    }
}
