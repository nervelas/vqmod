<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Tz;
use Throwable;

/**
 * Calendarios externos (enlaces .ics): descarga segura, bloqueo de horarios y estado para el panel.
 * Nunca lanza: si algo falla se conserva lo último que funcionó y se anota el aviso.
 */
final class ExternalCalendarService
{
    private const PAST_DAYS = 1;
    private const FUTURE_DAYS = 180;
    /** Espera (min) tras cada fallo seguido; del 5.º en adelante, 6 horas. */
    private const BACKOFF_MINUTES = [5, 15, 30, 60];
    private const LONG_BACKOFF_MINUTES = 360;
    /** Cada cuántas horas se vuelve a leer el calendario completo aunque el servidor diga "sin cambios". */
    private const FULL_REFRESH_HOURS = 12;

    /** @return array{ok:bool,error:?string,events:int,unchanged:bool} */
    public static function sync(int $calendarId): array
    {
        try {
            $cal = Db::one('SELECT * FROM external_calendars WHERE id = ?', [$calendarId]);
            if (!$cal) {
                return ['ok' => false, 'error' => 'No encontramos ese calendario.', 'events' => 0, 'unchanged' => false];
            }
            return self::run($cal);
        } catch (Throwable $e) {
            Logger::error('Calendario externo #' . $calendarId . ': error inesperado', $e);
            return ['ok' => false, 'error' => 'No se pudo actualizar el calendario por un problema interno.', 'events' => 0, 'unchanged' => false];
        }
    }

    /** Sincroniza los calendarios activos que ya toca revisar. @return array{synced:int,failed:int} */
    public static function syncDue(int $limit = 10): array
    {
        $res = ['synced' => 0, 'failed' => 0];
        $now = Clock::utc();
        $ids = Db::col(
            'SELECT id FROM external_calendars WHERE active = 1 AND (next_fetch_at IS NULL OR next_fetch_at <= ?) ORDER BY next_fetch_at, id LIMIT ' . max(1, min(100, $limit)),
            [$now]
        );
        foreach ($ids as $id) {
            // Reserva el turno para que dos ejecuciones no descarguen el mismo calendario a la vez
            $claimed = Db::exec(
                'UPDATE external_calendars SET next_fetch_at = ? WHERE id = ? AND active = 1 AND (next_fetch_at IS NULL OR next_fetch_at <= ?)',
                [Clock::utc(Clock::now() + 300), $id, $now]
            );
            if ($claimed !== 1) {
                continue;
            }
            $r = self::sync((int) $id);
            $r['ok'] ? $res['synced']++ : $res['failed']++;
        }
        return $res;
    }

    /** Comprueba un enlace sin guardar nada. @return array{ok:bool,error:?string,events:int} */
    public static function testUrl(string $url): array
    {
        try {
            $r = SafeHttp::get(self::normalizeUrl($url), ['headers' => ['Accept: text/calendar, text/plain, */*']]);
            if (!$r['ok']) {
                return ['ok' => false, 'error' => (string) $r['error'], 'events' => 0];
            }
            if (!IcsService::isCalendar($r['body'])) {
                return ['ok' => false, 'error' => 'El enlace no devuelve un calendario .ics. Copia la dirección secreta en formato iCal de tu calendario.', 'events' => 0];
            }
            $now = Clock::now();
            $n = count(IcsService::parse($r['body'], $now - self::PAST_DAYS * 86400, $now + self::FUTURE_DAYS * 86400));
            return ['ok' => true, 'error' => null, 'events' => $n];
        } catch (Throwable $e) {
            Logger::error('Calendario externo: error al probar un enlace', $e);
            return ['ok' => false, 'error' => 'No se pudo probar el enlace por un problema interno.', 'events' => 0];
        }
    }

    /** webcal:// es el mismo enlace por https. */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        return preg_match('~^webcals?://~i', $url) ? (string) preg_replace('~^webcals?://~i', 'https://', $url) : $url;
    }

    private static function run(array $cal): array
    {
        $now = Clock::now();
        $headers = ['Accept: text/calendar, text/plain, */*'];
        $parsedAt = $cal['last_parse_at'] ? Tz::ts((string) $cal['last_parse_at']) : 0;
        if ($now - $parsedAt < self::FULL_REFRESH_HOURS * 3600) {
            if (!empty($cal['etag'])) {
                $headers[] = 'If-None-Match: ' . $cal['etag'];
            }
            if (!empty($cal['last_modified'])) {
                $headers[] = 'If-Modified-Since: ' . $cal['last_modified'];
            }
        }
        $r = SafeHttp::get(self::normalizeUrl((string) $cal['url']), ['headers' => $headers]);

        if ($r['status'] === 304 && $r['error'] === null) {
            self::success($cal, null, null, false);
            return ['ok' => true, 'error' => null, 'events' => 0, 'unchanged' => true];
        }
        if (!$r['ok']) {
            return self::failure($cal, (string) $r['error']);
        }
        if (!IcsService::isCalendar($r['body'])) {
            return self::failure($cal, 'El enlace no devuelve un calendario .ics válido.');
        }

        $host = Db::one('SELECT timezone FROM hosts WHERE id = ?', [$cal['host_id']]);
        $tz = Tz::safe($host['timezone'] ?? null, Settings::tz());
        $events = IcsService::parseEvents($r['body'], $now - self::PAST_DAYS * 86400, $now + self::FUTURE_DAYS * 86400, $tz);

        Db::tx(static function () use ($cal, $events): void {
            Db::delete('external_busy', 'calendar_id = ?', [$cal['id']]);
            foreach (array_chunk($events, 200) as $chunk) {
                $sql = 'INSERT INTO external_busy (calendar_id, host_id, starts_at, ends_at, uid) VALUES ' . implode(',', array_fill(0, count($chunk), '(?,?,?,?,?)'));
                $params = [];
                foreach ($chunk as $e) {
                    array_push($params, $cal['id'], $cal['host_id'], Clock::utc($e['start']), Clock::utc($e['end']), $e['uid'] !== null ? mb_substr($e['uid'], 0, 190) : null);
                }
                Db::q($sql, $params);
            }
        });
        self::success($cal, $r['headers']['etag'] ?? null, $r['headers']['last-modified'] ?? null, true);
        Cache::bumpAvailability();
        return ['ok' => true, 'error' => null, 'events' => count($events), 'unchanged' => false];
    }

    private static function success(array $cal, ?string $etag, ?string $lastModified, bool $full): void
    {
        $now = Clock::now();
        $minutes = max(5, Settings::int('ics_cache_minutes', 30));
        $data = [
            'last_fetch_at' => Clock::utc($now),
            'last_ok_at' => Clock::utc($now),
            'last_status' => 'ok',
            'last_error' => null,
            'fail_count' => 0,
            'next_fetch_at' => Clock::utc($now + $minutes * 60),
        ];
        if ($full) {
            $data['etag'] = $etag !== null ? mb_substr($etag, 0, 255) : null;
            $data['last_modified'] = $lastModified !== null ? mb_substr($lastModified, 0, 80) : null;
            $data['last_parse_at'] = Clock::utc($now);
        }
        Db::update('external_calendars', $data, 'id = ?', [$cal['id']]);
    }

    private static function failure(array $cal, string $error): array
    {
        $fails = (int) $cal['fail_count'] + 1;
        $wait = $fails <= count(self::BACKOFF_MINUTES) ? self::BACKOFF_MINUTES[$fails - 1] : self::LONG_BACKOFF_MINUTES;
        $now = Clock::now();
        Db::update('external_calendars', [
            'last_fetch_at' => Clock::utc($now),
            'last_status' => 'error',
            'last_error' => mb_substr($error, 0, 255),
            'fail_count' => $fails,
            'next_fetch_at' => Clock::utc($now + $wait * 60),
        ], 'id = ?', [$cal['id']]);
        Logger::info('Calendario externo #' . $cal['id'] . ' no se pudo actualizar (' . $fails . ' seguidos): ' . $error);
        return ['ok' => false, 'error' => $error, 'events' => 0, 'unchanged' => false];
    }
}
