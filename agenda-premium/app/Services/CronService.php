<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Settings;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Tareas programadas. Se ejecuta desde cron.php (CLI o URL con token) o, en su defecto, desde las visitas. */
final class CronService
{
    /** Nombre del bloqueo: 'ap_cron' + huella de la base, para que varias instalaciones en un mismo servidor MySQL no se bloqueen entre sí. */
    private const LOCK_SQL = "CONCAT('ap_cron_', MD5(DATABASE()))";

    /**
     * @param string $origen 'cli' | 'url' | 'visita'
     * @return array{ok:bool,locked:bool,origin:string,started_at:string,duration_ms:int,tasks:array,errors:array,error:?string}
     */
    public static function run(string $origen): array
    {
        $started = microtime(true);
        $res = ['ok' => true, 'locked' => false, 'origin' => $origen, 'started_at' => Clock::utc(), 'duration_ms' => 0, 'tasks' => [], 'errors' => [], 'error' => null];
        try {
            if ((int) Db::val('SELECT GET_LOCK(' . self::LOCK_SQL . ', 0)') !== 1) {
                return ['ok' => false, 'locked' => true, 'error' => 'Ya hay otra ejecución en curso.'] + $res;
            }
        } catch (Throwable $e) {
            Logger::error('Cron: no se pudo tomar el bloqueo', $e);
            return ['ok' => false, 'error' => 'No se pudo conectar con la base de datos.'] + $res;
        }
        @set_time_limit(110);
        try {
            foreach (self::tasks() as $name => $fn) {
                try {
                    $res['tasks'][$name] = $fn();
                } catch (Throwable $e) {
                    $res['errors'][$name] = 'Falló: ' . mb_substr($e->getMessage(), 0, 200);
                    Logger::error('Cron: falló la tarea ' . $name, $e);
                }
            }
            $res['ok'] = $res['errors'] === [];
            $res['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
            Settings::set('cron_last_run', Clock::utc());
            Settings::set('cron_last_result', (string) json_encode(['ok' => $res['ok'], 'origin' => $origen, 'duration_ms' => $res['duration_ms'], 'errors' => array_keys($res['errors'])]));
        } finally {
            Db::val('SELECT RELEASE_LOCK(' . self::LOCK_SQL . ')');
        }
        return $res;
    }

    /**
     * Orden pensado para que una sola pasada complete la cadena: primero lo que genera trabajo
     * (citas vencidas, lista de espera, resumen), luego flujos, correo y webhooks, y al final calendarios y limpieza.
     * @return array<string,callable>
     */
    private static function tasks(): array
    {
        $t = [];
        if (self::has(BookingService::class, 'expirePending')) {
            $t['citas_vencidas'] = static fn (): array => ['canceladas' => BookingService::expirePending()];
            $t['citas_completadas'] = static fn (): array => ['completadas' => BookingService::completeFinished()];
        }
        if (self::has('App\\Services\\WaitlistService', 'tick')) {
            $t['lista_espera'] = static fn (): array => (array) WaitlistService::tick();
        }
        if (self::has('App\\Services\\PrivacyService', 'applyRetention')) {
            $t['retencion'] = static fn (): array => ['anonimizados' => \App\Services\PrivacyService::applyRetention()];
        }
        if (self::has(HolidayService::class, 'ensureYear')) {
            $t['feriados'] = static function (): array {
                $y = (int) gmdate('Y', Clock::now());
                HolidayService::ensureYear($y);
                HolidayService::ensureYear($y + 1);
                return ['anio' => $y + 1];
            };
        }
        if (self::has('App\\Services\\ReportService', 'weeklySummary')) {
            $t['resumen_semanal'] = static fn (): array => self::weekly();
        }
        $t['flujos'] = static fn (): array => WorkflowService::runDue(100);
        $t['correo'] = static fn (): array => Mailer::processQueue(40);
        $t['webhooks'] = static fn (): array => WebhookService::deliverDue(40);
        $t['calendarios'] = static fn (): array => ExternalCalendarService::syncDue(10);
        $t['limpieza'] = static fn (): array => self::cleanup();
        return $t;
    }

    private static function has(string $class, string $method): bool
    {
        return class_exists($class) && method_exists($class, $method);
    }

    /** El resumen se manda el lunes por la mañana (hora del negocio); ReportService evita repetirlo en la misma semana. */
    private static function weekly(): array
    {
        $local = (new DateTimeImmutable('@' . Clock::now()))->setTimezone(new DateTimeZone(Settings::tz()));
        if ((int) $local->format('N') !== 1 || (int) $local->format('G') < 7 || (int) $local->format('G') >= 12) {
            return ['omitido' => 'No es lunes por la mañana.'];
        }
        \App\Services\ReportService::weeklySummary();
        return ['revisado' => true];
    }

    /** Cada limpieza va aparte: si una falla, las demás siguen. @return array<string,int|string> */
    private static function cleanup(): array
    {
        $now = Clock::now();
        $d = static fn (int $days): string => Clock::utc($now - $days * 86400);
        $jobs = [
            'limites' => static fn (): int => RateLimiter::purge(),
            'accesos' => static fn (): int => Db::exec('DELETE FROM login_attempts WHERE created_at < ?', [$d(30)]),
            'correos' => static fn (): int => Db::exec("DELETE FROM email_queue WHERE status IN ('sent','failed') AND COALESCE(sent_at, created_at) < ?", [$d(60)]),
            'ejecuciones_flujos' => static fn (): int => Db::exec("DELETE FROM workflow_runs WHERE status <> 'pending' AND created_at < ? AND scheduled_at < ?", [$d(90), $d(90)]),
            'entregas_webhooks' => static fn (): int => Db::exec("DELETE FROM webhook_deliveries WHERE status <> 'pending' AND created_at < ?", [$d(90)]),
            'mensajes' => static fn (): int => Db::exec("DELETE FROM message_queue WHERE status <> 'pending' AND created_at < ?", [$d(90)]),
            'cache' => static fn (): int => self::purgeCache($now),
        ];
        $out = [];
        foreach ($jobs as $name => $job) {
            try {
                $out[$name] = $job();
            } catch (Throwable $e) {
                $out[$name] = 'error';
                Logger::error('Cron: falló la limpieza de ' . $name, $e);
            }
        }
        return $out;
    }

    private static function purgeCache(int $now): int
    {
        $n = 0;
        foreach (glob(APP_ROOT . '/storage/cache/*') ?: [] as $f) {
            if (!is_file($f)) {
                continue;
            }
            if (substr($f, -4) === '.tmp') {
                $old = (int) @filemtime($f) < $now - 3600;
            } else {
                $d = @unserialize((string) @file_get_contents($f), ['allowed_classes' => false]);
                $old = !is_array($d) || (int) ($d['exp'] ?? 0) < $now;
            }
            if ($old && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
