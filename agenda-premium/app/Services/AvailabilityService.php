<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Core\Tz;
use App\Repositories\BookingRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Motor de disponibilidad. Todo se calcula con timestamps UTC; las horas laborales se interpretan en la zona del horario
 * (por eso los cambios de horario de verano de cualquier país se respetan).
 */
final class AvailabilityService
{
    /** @var array<int,array> caché por petición de horarios */
    private static array $scheduleCache = [];

    /**
     * Horarios libres de un evento.
     * @param array $opts host_id, exclude_booking_id, now (ts), ignore_notice, nocache
     * @return array<int,array{start:string,end:string,host_ids:int[],resource_ids:int[],seats_left:?int}>
     */
    public static function slots(array $event, int $duration, string $fromUtc, string $toUtc, array $opts = []): array
    {
        if (!in_array($duration, $event['duration_list'] ?? [(int) $event['default_duration']], true)) {
            return [];
        }
        $now = (int) ($opts['now'] ?? Clock::now());
        $from = Tz::ts($fromUtc);
        $to = Tz::ts($toUtc);
        $bizTz = Settings::tz();

        if (empty($opts['ignore_notice'])) {
            $from = max($from, $now + (int) $event['min_notice_minutes'] * 60);
            $to = min($to, $now + (int) $event['max_advance_days'] * 86400);
        }
        if (!empty($event['window_start'])) {
            $from = max($from, Tz::localToTs($event['window_start'] . ' 00:00:00', $bizTz));
        }
        if (!empty($event['window_end'])) {
            $to = min($to, Tz::localToTs(date('Y-m-d', strtotime($event['window_end'] . ' +1 day')) . ' 00:00:00', $bizTz));
        }
        if (!empty($event['expires_at'])) {
            $to = min($to, Tz::ts((string) $event['expires_at']));
        }
        if ($to <= $from) {
            return [];
        }

        $cacheKey = null;
        if (empty($opts['nocache'])) {
            $cacheKey = 'slots|' . md5(json_encode([$event['id'], $event['updated_at'], $duration, $from, $to, $opts['host_id'] ?? 0, $opts['exclude_booking_id'] ?? 0, Cache::availabilityVersion()]));
            $hit = Cache::get($cacheKey);
            if (is_array($hit)) {
                return $hit;
            }
        }

        $exclude = !empty($opts['exclude_booking_id']) ? (int) $opts['exclude_booking_id'] : null;
        $hosts = $event['hosts'] ?? [];
        if (!empty($opts['host_id'])) {
            $hosts = array_values(array_filter($hosts, static fn (array $h): bool => (int) $h['id'] === (int) $opts['host_id']));
        }
        $kind = (string) $event['kind'];
        if (in_array($kind, ['individual', 'group'], true)) {
            $hosts = array_slice($hosts, 0, 1);
        }
        if (!$hosts) {
            return [];
        }

        $isGroup = $kind === 'group';
        $respectHolidays = (int) $event['respect_holidays'] === 1;
        $pad = max((int) $event['buffer_before'], (int) $event['buffer_after'], (int) $event['travel_minutes']) * 60 + 86400;

        // Intervalos de trabajo por anfitrión
        $work = [];
        foreach ($hosts as $h) {
            $work[(int) $h['id']] = self::workIntervals(self::scheduleFor($event, $h), $from, $to, $respectHolidays);
        }

        // Colectivo: la cuadrícula se arma sobre la intersección de las horas de trabajo de todos
        if ($kind === 'collective') {
            $common = null;
            foreach ($work as $w) {
                $common = $common === null ? $w : self::intersect($common, $w);
            }
            foreach ($work as $hid => $_) {
                $work[$hid] = $common ?? [];
            }
        }

        $perHost = [];
        foreach ($hosts as $h) {
            $hid = (int) $h['id'];
            $busy = BookingRepository::busyForHost($hid, $from - $pad, $to + $pad, $exclude);
            $perHost[$hid] = self::hostSlots($event, $duration, $work[$hid], $busy, $from, $to, $isGroup);
        }

        // Unir por tipo de evento
        $merged = [];
        if ($kind === 'collective') {
            $first = true;
            $common = [];
            foreach ($perHost as $hid => $list) {
                if ($first) {
                    $common = $list;
                    $first = false;
                } else {
                    $common = array_intersect_key($common, $list);
                }
            }
            foreach ($common as $s => $info) {
                $merged[$s] = ['host_ids' => array_keys($perHost), 'seats_left' => null];
            }
        } else {
            foreach ($perHost as $hid => $list) {
                foreach ($list as $s => $info) {
                    $merged[$s]['host_ids'][] = $hid;
                    $merged[$s]['seats_left'] = $info['seats_left'];
                }
            }
        }
        ksort($merged);

        // Límites diarios/semanales del evento
        $dailyLimit = $event['daily_limit'] !== null ? (int) $event['daily_limit'] : 0;
        $weeklyLimit = $event['weekly_limit'] !== null ? (int) $event['weekly_limit'] : 0;
        if (($dailyLimit > 0 || $weeklyLimit > 0) && $merged) {
            $counts = BookingRepository::limitCounts((int) $event['id'], $isGroup, $from, $to, $bizTz, $exclude);
            foreach ($merged as $s => $info) {
                $existing = false;
                if ($isGroup) {
                    foreach ($info['host_ids'] as $hid) {
                        if (isset($counts['slots'][$hid . '|' . Tz::fromTs($s)])) {
                            $existing = true;
                        }
                    }
                }
                if ($existing) {
                    continue; // sumarse a una sesión grupal existente no cuenta como una cita nueva
                }
                $d = Tz::formatTs($s, $bizTz, 'Y-m-d');
                $w = Tz::formatTs($s, $bizTz, 'o-W');
                if (($dailyLimit > 0 && ($counts['day'][$d] ?? 0) >= $dailyLimit) || ($weeklyLimit > 0 && ($counts['week'][$w] ?? 0) >= $weeklyLimit)) {
                    unset($merged[$s]);
                }
            }
        }

        // Recursos (salas/equipos)
        $resourceIds = [];
        if (!empty($event['resources'])) {
            $busyRes = [];
            foreach ($event['resources'] as $r) {
                $busyRes[(int) $r['id']] = BookingRepository::busyForResource((int) $r['id'], $from - $pad, $to + $pad, $exclude);
            }
            foreach ($merged as $s => $info) {
                [$bs, $be] = self::blockedRange($event, $s, $duration);
                $free = [];
                foreach ($event['resources'] as $r) {
                    $rid = (int) $r['id'];
                    $used = 0;
                    foreach ($busyRes[$rid] as $b) {
                        if ($b['s'] < $be && $b['e'] > $bs) {
                            $used++;
                        }
                    }
                    if ($used < max(1, (int) $r['capacity'])) {
                        $free[] = $rid;
                    }
                }
                if (!$free) {
                    unset($merged[$s]);
                } else {
                    $resourceIds[$s] = $free;
                }
            }
        }

        $out = [];
        foreach ($merged as $s => $info) {
            $out[] = [
                'start' => Tz::fromTs($s),
                'end' => Tz::fromTs($s + $duration * 60),
                'host_ids' => $info['host_ids'],
                'resource_ids' => $resourceIds[$s] ?? [],
                'seats_left' => $info['seats_left'],
            ];
        }
        if ($cacheKey !== null) {
            Cache::put($cacheKey, $out, 20);
        }
        return $out;
    }

    /**
     * Horarios agrupados por fecha LOCAL del invitado.
     * @return array<string,array<int,array>> 'Y-m-d' => slots (cada uno con local_date y local_time 'H:i')
     */
    public static function slotsByDay(array $event, int $duration, string $fromDate, string $toDate, string $guestTz, array $opts = []): array
    {
        $guestTz = Tz::safe($guestTz);
        $from = Tz::localToUtc($fromDate . ' 00:00:00', $guestTz);
        $to = Tz::localToUtc(date('Y-m-d', strtotime($toDate . ' +1 day')) . ' 00:00:00', $guestTz);
        $out = [];
        foreach (self::slots($event, $duration, $from, $to, $opts) as $s) {
            $ts = Tz::ts($s['start']);
            $day = Tz::formatTs($ts, $guestTz, 'Y-m-d');
            $s['local_date'] = $day;
            $s['local_time'] = Tz::formatTs($ts, $guestTz, 'H:i');
            $out[$day][] = $s;
        }
        return $out;
    }

    /** Primer horario libre (busca en ventanas de 14 días hasta la anticipación máxima, con tope de un año). */
    public static function next(array $event, int $duration, array $opts = []): ?array
    {
        $now = (int) ($opts['now'] ?? Clock::now());
        $limit = $now + min((int) $event['max_advance_days'], 365) * 86400;
        $cursor = $now;
        while ($cursor < $limit) {
            $end = min($cursor + 14 * 86400, $limit);
            $list = self::slots($event, $duration, Tz::fromTs($cursor), Tz::fromTs($end), $opts);
            if ($list) {
                return $list[0];
            }
            $cursor = $end;
        }
        return null;
    }

    /** ¿El anfitrión tiene algo (cita, ausencia, calendario externo) que choque con el rango? */
    public static function hostBusy(int $hostId, string $startUtc, string $endUtc, ?int $excludeBookingId = null): bool
    {
        $s = Tz::ts($startUtc);
        $e = Tz::ts($endUtc);
        foreach (BookingRepository::busyForHost($hostId, $s - 86400, $e + 86400, $excludeBookingId) as $b) {
            if ($b['s'] < $e && $b['e'] > $s) {
                return true;
            }
        }
        return false;
    }

    /** ¿Hay un recurso con capacidad libre en el rango (bloqueado)? */
    public static function resourceFree(int $resourceId, int $capacity, int $blockedStartTs, int $blockedEndTs, ?int $excludeBookingId = null): bool
    {
        $used = 0;
        foreach (BookingRepository::busyForResource($resourceId, $blockedStartTs - 86400, $blockedEndTs + 86400, $excludeBookingId) as $b) {
            if ($b['s'] < $blockedEndTs && $b['e'] > $blockedStartTs) {
                $used++;
            }
        }
        return $used < max(1, $capacity);
    }

    /** Rango bloqueado de una cita: inicio − margen antes … fin + margen después (en citas a domicilio, el traslado cuenta como margen mínimo). */
    public static function blockedRange(array $event, int $startTs, int $duration): array
    {
        $bb = (int) $event['buffer_before'];
        $ba = (int) $event['buffer_after'];
        if ($event['mode'] === 'home') {
            $bb = max($bb, (int) $event['travel_minutes']);
            $ba = max($ba, (int) $event['travel_minutes']);
        }
        return [$startTs - $bb * 60, $startTs + $duration * 60 + $ba * 60];
    }

    // ------------------------------------------------------------------ internos

    /** Horario efectivo: el asignado al evento, si no el del anfitrión, si no el predeterminado. */
    private static function scheduleFor(array $event, array $host): array
    {
        $id = (int) ($event['schedule_id'] ?: ($host['schedule_id'] ?: 0));
        if ($id === 0) {
            $id = (int) Db::val('SELECT id FROM schedules WHERE is_default = 1 ORDER BY id LIMIT 1');
        }
        if ($id === 0) {
            $id = (int) Db::val('SELECT id FROM schedules ORDER BY id LIMIT 1');
        }
        if ($id === 0) {
            return ['tz' => Settings::tz(), 'rules' => [], 'overrides' => []];
        }
        // El caché por petición se vacía cuando cambia la versión de disponibilidad
        $key = $id . '|' . Cache::availabilityVersion();
        if (!isset(self::$scheduleCache[$key])) {
            $s = Db::one('SELECT * FROM schedules WHERE id = ?', [$id]);
            $rules = [];
            foreach (Db::all('SELECT weekday, start_time, end_time FROM schedule_rules WHERE schedule_id = ? ORDER BY weekday, start_time', [$id]) as $r) {
                $rules[(int) $r['weekday']][] = [(string) $r['start_time'], (string) $r['end_time']];
            }
            $ov = [];
            foreach (Db::all('SELECT date, is_open, start_time, end_time FROM schedule_overrides WHERE schedule_id = ?', [$id]) as $r) {
                $ov[$r['date']][] = ['open' => (int) $r['is_open'] === 1, 'start' => $r['start_time'], 'end' => $r['end_time']];
            }
            self::$scheduleCache[$key] = ['tz' => Tz::safe((string) ($s['timezone'] ?? Settings::tz())), 'rules' => $rules, 'overrides' => $ov];
        }
        return self::$scheduleCache[$key];
    }

    /**
     * Bloques de trabajo (UTC) en el rango, ya sin feriados ni días cerrados, fusionados.
     * @return array<int,array{0:int,1:int}>
     */
    public static function workIntervals(array $sched, int $fromTs, int $toTs, bool $respectHolidays): array
    {
        $tzName = $sched['tz'];
        $tz = new DateTimeZone($tzName);
        $d = (new DateTimeImmutable('@' . ($fromTs - 86400)))->setTimezone($tz)->setTime(0, 0, 0);
        $last = (new DateTimeImmutable('@' . ($toTs + 86400)))->setTimezone($tz)->setTime(0, 0, 0);
        $holidays = [];
        if ($respectHolidays && Settings::bool('holidays_enabled')) {
            $holidays = HolidayService::between($d->format('Y-m-d'), $last->format('Y-m-d'));
        }
        $out = [];
        $guard = 0;
        while ($d <= $last && $guard++ < 800) {
            $ds = $d->format('Y-m-d');
            $blocks = [];
            $explicit = false;
            if (isset($sched['overrides'][$ds])) {
                $explicit = true;
                foreach ($sched['overrides'][$ds] as $o) {
                    if ($o['open'] && $o['start'] !== null && $o['end'] !== null) {
                        $blocks[] = [(string) $o['start'], (string) $o['end']];
                    }
                }
            } else {
                $blocks = $sched['rules'][(int) $d->format('N')] ?? [];
            }
            if (!$explicit && isset($holidays[$ds])) {
                foreach ($holidays[$ds] as $h) {
                    if ($h['kind'] === 'full') {
                        $blocks = [];
                        break;
                    }
                    $cut = (string) ($h['half_day_end'] ?? '12:00:00');
                    $tmp = [];
                    foreach ($blocks as [$bs, $be]) {
                        $be = min($be, $cut);
                        if ($bs < $be) {
                            $tmp[] = [$bs, $be];
                        }
                    }
                    $blocks = $tmp;
                }
            }
            foreach ($blocks as [$bs, $be]) {
                $s = Tz::localToTs($ds . ' ' . $bs, $tzName);
                $e = Tz::localToTs($ds . ' ' . $be, $tzName);
                if ($e <= $s) {
                    $e = Tz::localToTs(date('Y-m-d', strtotime($ds . ' +1 day')) . ' ' . $be, $tzName);
                }
                if ($e > $s && $e > $fromTs - 86400 && $s < $toTs + 86400) {
                    $out[] = [$s, $e];
                }
            }
            $d = $d->modify('+1 day')->setTime(0, 0, 0);
        }
        return self::merge($out);
    }

    /** @return array<int,array{0:int,1:int}> */
    private static function merge(array $iv): array
    {
        usort($iv, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $out = [];
        foreach ($iv as $x) {
            if ($out && $x[0] <= $out[count($out) - 1][1]) {
                if ($x[1] > $out[count($out) - 1][1]) {
                    $out[count($out) - 1][1] = $x[1];
                }
            } else {
                $out[] = $x;
            }
        }
        return $out;
    }

    private static function intersect(array $a, array $b): array
    {
        $out = [];
        $i = $j = 0;
        while ($i < count($a) && $j < count($b)) {
            $s = max($a[$i][0], $b[$j][0]);
            $e = min($a[$i][1], $b[$j][1]);
            if ($e > $s) {
                $out[] = [$s, $e];
            }
            if ($a[$i][1] < $b[$j][1]) {
                $i++;
            } else {
                $j++;
            }
        }
        return $out;
    }

    /**
     * Horarios de UN anfitrión: [startTs => ['seats_left'=>?int]]
     * @param array $busy ordenado por inicio (ver BookingRepository::busyForHost)
     */
    private static function hostSlots(array $event, int $duration, array $work, array $busy, int $from, int $to, bool $isGroup): array
    {
        $step = max(5, (int) $event['slot_interval']) * 60;
        $dur = $duration * 60;
        $capacity = max(1, (int) $event['capacity']);
        $eventId = (int) $event['id'];

        $prefix = [];
        $max = PHP_INT_MIN;
        foreach ($busy as $i => $b) {
            $max = max($max, $b['e']);
            $prefix[$i] = $max;
        }
        $n = count($busy);
        $p = 0;
        $out = [];
        foreach ($work as [$ws, $we]) {
            if ($we <= $from || $ws >= $to) {
                continue;
            }
            $s = $ws;
            if ($s < $from) {
                $s += (int) ceil(($from - $ws) / $step) * $step;
            }
            for (; $s + $dur <= $we && $s < $to; $s += $step) {
                [$bs, $be] = self::blockedRange($event, $s, $duration);
                while ($p < $n && $prefix[$p] <= $bs) {
                    $p++;
                }
                $conflict = false;
                $seatsUsed = 0;
                for ($i = $p; $i < $n && $busy[$i]['s'] < $be; $i++) {
                    $b = $busy[$i];
                    if ($b['e'] <= $bs) {
                        continue;
                    }
                    if ($isGroup && $b['kind'] === 'booking' && $b['event'] === $eventId && $b['start'] === $s && $b['end'] === $s + $dur) {
                        $seatsUsed += $b['seats'];
                        continue;
                    }
                    $conflict = true;
                    break;
                }
                if ($conflict) {
                    continue;
                }
                if ($isGroup) {
                    if ($seatsUsed >= $capacity) {
                        continue;
                    }
                    $out[$s] = ['seats_left' => $capacity - $seatsUsed];
                } else {
                    $out[$s] = ['seats_left' => null];
                }
            }
        }
        return $out;
    }
}
