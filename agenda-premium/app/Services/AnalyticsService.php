<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Tz;
use App\Core\Validator;

/**
 * Analítica propia, sin cookies ni IP. Convenciones del informe:
 *  - Las fechas son de la zona del negocio (Settings::tz()), inclusivas.
 *  - Las citas se cuentan por su fecha de inicio (starts_at); embudo y visitas, por la fecha del evento de analítica;
 *    ingresos, por la fecha del pago (verificados menos reembolsos).
 */
final class AnalyticsService
{
    private const BOT = '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python|httpclient|java\/|go-http|scrapy/i';

    /** step: view|slot|booked. ctx: event_type_id, host_id, visit_id, utm_*, referrer_host (o URL), device, user_agent. Nunca lanza. */
    public static function track(string $step, array $ctx): void
    {
        try {
            if (!in_array($step, ['view', 'slot', 'booked'], true)) {
                return;
            }
            $ua = (string) ($ctx['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '');
            if (trim($ua) === '' || preg_match(self::BOT, $ua)) {
                return;
            }
            $visit = (string) ($ctx['visit_id'] ?? '');
            if (!preg_match('/^[A-Za-z0-9]{16}$/', $visit)) {
                $visit = bin2hex(random_bytes(8));
            }
            $event = !empty($ctx['event_type_id']) ? (int) $ctx['event_type_id'] : null;
            $host = !empty($ctx['host_id']) ? (int) $ctx['host_id'] : null;
            $device = (string) ($ctx['device'] ?? '');
            if (!in_array($device, ['mobile', 'desktop'], true)) {
                $device = preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $ua) ? 'mobile' : 'desktop';
            }
            if (Db::val('SELECT id FROM analytics_events WHERE visit_id = ? AND step = ? AND event_type_id <=> ? LIMIT 1', [$visit, $step, $event]) !== null) {
                return;
            }
            Db::insert('analytics_events', [
                'event_type_id' => $event,
                'host_id' => $host,
                'visit_id' => $visit,
                'step' => $step,
                'utm_source' => self::tag($ctx['utm_source'] ?? null, 100),
                'utm_medium' => self::tag($ctx['utm_medium'] ?? null, 100),
                'utm_campaign' => self::tag($ctx['utm_campaign'] ?? null, 100),
                'referrer_host' => self::host($ctx['referrer_host'] ?? null),
                'device' => $device,
                'created_at' => Clock::utc(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Analítica: no se pudo registrar el paso ' . $step, $e);
        }
    }

    private static function tag($v, int $max): ?string
    {
        $s = trim(preg_replace('/[\x00-\x1F\x7F<>"\'`]/u', '', (string) $v) ?? '');
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    /** Solo el host del referente (nunca la ruta ni parámetros). */
    private static function host($v): ?string
    {
        $s = strtolower(trim((string) $v));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, '://')) {
            $s = (string) parse_url($s, PHP_URL_HOST);
        } else {
            $s = (string) preg_replace('/[\/?#:].*$/', '', $s);
        }
        return preg_match('/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/', $s) ? substr($s, 0, 190) : null;
    }

    /**
     * filters: event_type_id, host_id, group (day|week|month, por defecto day).
     * @return array con period, kpis (value/previous/delta_pct), funnel, by_event, by_source, by_period, peak, occupancy, devices
     */
    public static function report(string $fromDate, string $toDate, array $filters = []): array
    {
        $tz = Settings::tz();
        if (!Validator::date($fromDate) || !Validator::date($toDate) || $fromDate > $toDate) {
            $toDate = Tz::formatTs(Clock::now(), $tz, 'Y-m-d');
            $fromDate = Tz::formatTs(Clock::now() - 29 * 86400, $tz, 'Y-m-d');
        }
        $days = min(731, (int) round((strtotime($toDate . ' UTC') - strtotime($fromDate . ' UTC')) / 86400) + 1);
        $toDate = gmdate('Y-m-d', strtotime($fromDate . ' UTC') + ($days - 1) * 86400);
        $prevTo = gmdate('Y-m-d', strtotime($fromDate . ' UTC') - 86400);
        $prevFrom = gmdate('Y-m-d', strtotime($prevTo . ' UTC') - ($days - 1) * 86400);
        $f = [
            'event' => !empty($filters['event_type_id']) ? (int) $filters['event_type_id'] : null,
            'host' => !empty($filters['host_id']) ? (int) $filters['host_id'] : null,
        ];
        $cur = self::kpis($fromDate, $toDate, $f, $tz);
        $prev = self::kpis($prevFrom, $prevTo, $f, $tz);
        $kpis = [];
        foreach ($cur as $k => $v) {
            $p = $prev[$k];
            $kpis[$k] = ['value' => $v, 'previous' => $p, 'delta_pct' => $p == 0 ? ($v == 0 ? 0.0 : null) : round(($v - $p) / abs($p) * 100, 1)];
        }
        [$a, $b] = self::range($fromDate, $toDate, $tz);
        $rows = self::bookingRows($a, $b, $f);
        return [
            'period' => ['from' => $fromDate, 'to' => $toDate, 'days' => $days, 'previous_from' => $prevFrom, 'previous_to' => $prevTo],
            'kpis' => $kpis,
            'funnel' => self::funnel($a, $b, $f),
            'by_event' => self::byEvent($a, $b, $f, $rows),
            'by_source' => self::bySource($a, $b, $f, $rows),
            'by_period' => self::byPeriod($rows, $tz, (string) ($filters['group'] ?? 'day'), $fromDate, $toDate),
            'peak' => self::peaks($rows, $tz),
            'occupancy' => self::occupancy($rows, $fromDate, $toDate, $f),
            'devices' => self::devices($a, $b, $f),
        ];
    }

    /** Rango [inicio, fin) en UTC para fechas locales inclusivas. */
    private static function range(string $from, string $to, string $tz): array
    {
        return [Tz::localToUtc($from . ' 00:00:00', $tz), Tz::localToUtc(gmdate('Y-m-d', strtotime($to . ' UTC') + 86400) . ' 00:00:00', $tz)];
    }

    /** Condición SQL de filtros para una tabla con event_type_id y host_id (alias $t). */
    private static function where(array $f, string $t): array
    {
        $sql = '';
        $p = [];
        if ($f['event'] !== null) {
            $sql .= " AND $t.event_type_id = ?";
            $p[] = $f['event'];
        }
        if ($f['host'] !== null) {
            $sql .= " AND $t.host_id = ?";
            $p[] = $f['host'];
        }
        return [$sql, $p];
    }

    private static function kpis(string $from, string $to, array $f, string $tz): array
    {
        [$a, $b] = self::range($from, $to, $tz);
        [$wa, $pa] = self::where($f, 'a');
        $steps = ['view' => 0, 'slot' => 0, 'booked' => 0];
        foreach (Db::all('SELECT a.step, COUNT(DISTINCT a.visit_id) AS n FROM analytics_events a WHERE a.created_at >= ? AND a.created_at < ?' . $wa . ' GROUP BY a.step', array_merge([$a, $b], $pa)) as $r) {
            $steps[$r['step']] = (int) $r['n'];
        }
        [$wb, $pb] = self::where($f, 'b');
        $st = [];
        foreach (Db::all('SELECT b.status, COUNT(*) AS n FROM bookings b WHERE b.starts_at >= ? AND b.starts_at < ?' . $wb . ' GROUP BY b.status', array_merge([$a, $b], $pb)) as $r) {
            $st[$r['status']] = (int) $r['n'];
        }
        $bookings = array_sum($st);
        if ($f['event'] === null && $f['host'] === null) {
            $rev = Db::all("SELECT p.status, SUM(ROUND(p.amount * 100)) AS c FROM payments p WHERE p.created_at >= ? AND p.created_at < ? AND p.status IN ('verified','refunded') GROUP BY p.status", [$a, $b]);
        } else {
            [$wp, $pp] = self::where($f, 'b');
            $rev = Db::all("SELECT p.status, SUM(ROUND(p.amount * 100)) AS c FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE p.created_at >= ? AND p.created_at < ? AND p.status IN ('verified','refunded')" . $wp . ' GROUP BY p.status', array_merge([$a, $b], $pp));
        }
        $c = ['verified' => 0, 'refunded' => 0];
        foreach ($rev as $r) {
            $c[$r['status']] = (int) $r['c'];
        }
        return [
            'views' => $steps['view'],
            'slot_visits' => $steps['slot'],
            'booked_visits' => $steps['booked'],
            'conversion_pct' => $steps['view'] > 0 ? round($steps['booked'] * 100 / $steps['view'], 1) : 0.0,
            'bookings' => $bookings,
            'confirmed' => ($st['confirmed'] ?? 0) + ($st['completed'] ?? 0),
            'cancelled' => $st['cancelled'] ?? 0,
            'no_show' => $st['no_show'] ?? 0,
            'no_show_pct' => $bookings > 0 ? round(($st['no_show'] ?? 0) * 100 / $bookings, 1) : 0.0,
            'cancel_pct' => $bookings > 0 ? round(($st['cancelled'] ?? 0) * 100 / $bookings, 1) : 0.0,
            'revenue' => round(($c['verified'] - $c['refunded']) / 100, 2),
        ];
    }

    private static function funnel(string $a, string $b, array $f): array
    {
        [$wa, $pa] = self::where($f, 'a');
        $n = ['view' => 0, 'slot' => 0, 'booked' => 0];
        foreach (Db::all('SELECT a.step, COUNT(DISTINCT a.visit_id) AS n FROM analytics_events a WHERE a.created_at >= ? AND a.created_at < ?' . $wa . ' GROUP BY a.step', array_merge([$a, $b], $pa)) as $r) {
            $n[$r['step']] = (int) $r['n'];
        }
        return [
            ['step' => 'view', 'label' => 'Vieron la página', 'count' => $n['view'], 'pct' => $n['view'] > 0 ? 100.0 : 0.0],
            ['step' => 'slot', 'label' => 'Eligieron un horario', 'count' => $n['slot'], 'pct' => $n['view'] > 0 ? round($n['slot'] * 100 / $n['view'], 1) : 0.0],
            ['step' => 'booked', 'label' => 'Reservaron', 'count' => $n['booked'], 'pct' => $n['view'] > 0 ? round($n['booked'] * 100 / $n['view'], 1) : 0.0],
        ];
    }

    /** Citas del periodo (sin id de otras tablas) para agregaciones en PHP. */
    private static function bookingRows(string $a, string $b, array $f): array
    {
        [$w, $p] = self::where($f, 'b');
        return Db::all(
            'SELECT b.id, b.event_type_id, b.host_id, b.starts_at, b.duration, b.status, COALESCE(NULLIF(b.utm_source, \'\'), NULLIF(b.referrer_host, \'\'), \'directo\') AS source
             FROM bookings b WHERE b.starts_at >= ? AND b.starts_at < ?' . $w . ' ORDER BY b.starts_at',
            array_merge([$a, $b], $p)
        );
    }

    private static function byEvent(string $a, string $b, array $f, array $rows): array
    {
        [$wa, $pa] = self::where($f, 'a');
        $out = [];
        foreach (Db::all('SELECT a.event_type_id AS id, a.step, COUNT(DISTINCT a.visit_id) AS n FROM analytics_events a WHERE a.event_type_id IS NOT NULL AND a.created_at >= ? AND a.created_at < ?' . $wa . ' GROUP BY a.event_type_id, a.step', array_merge([$a, $b], $pa)) as $r) {
            $out[(int) $r['id']][$r['step']] = (int) $r['n'];
        }
        $bk = [];
        foreach ($rows as $r) {
            $id = (int) $r['event_type_id'];
            $bk[$id] = ($bk[$id] ?? 0) + 1;
        }
        $names = Db::all('SELECT id, name FROM event_types');
        $names = array_column($names, 'name', 'id');
        $res = [];
        foreach (array_unique(array_merge(array_keys($out), array_keys($bk))) as $id) {
            $v = $out[$id]['view'] ?? 0;
            $bo = $out[$id]['booked'] ?? 0;
            $res[] = ['event_type_id' => $id, 'name' => $names[$id] ?? 'Cita eliminada', 'views' => $v, 'slot_visits' => $out[$id]['slot'] ?? 0, 'booked_visits' => $bo, 'bookings' => $bk[$id] ?? 0, 'conversion_pct' => $v > 0 ? round($bo * 100 / $v, 1) : 0.0];
        }
        usort($res, static fn ($x, $y) => [$y['bookings'], $y['views']] <=> [$x['bookings'], $x['views']]);
        return $res;
    }

    private static function bySource(string $a, string $b, array $f, array $rows): array
    {
        [$wa, $pa] = self::where($f, 'a');
        $src = [];
        foreach (Db::all("SELECT COALESCE(NULLIF(a.utm_source, ''), NULLIF(a.referrer_host, ''), 'directo') AS s, a.step, COUNT(DISTINCT a.visit_id) AS n FROM analytics_events a WHERE a.created_at >= ? AND a.created_at < ?" . $wa . ' GROUP BY s, a.step', array_merge([$a, $b], $pa)) as $r) {
            $src[(string) $r['s']][$r['step']] = (int) $r['n'];
        }
        $bk = [];
        foreach ($rows as $r) {
            $bk[(string) $r['source']] = ($bk[(string) $r['source']] ?? 0) + 1;
        }
        $res = [];
        foreach (array_unique(array_merge(array_keys($src), array_keys($bk))) as $s) {
            $v = $src[$s]['view'] ?? 0;
            $bo = $src[$s]['booked'] ?? 0;
            $res[] = ['source' => (string) $s, 'views' => $v, 'booked_visits' => $bo, 'bookings' => $bk[$s] ?? 0, 'conversion_pct' => $v > 0 ? round($bo * 100 / $v, 1) : 0.0];
        }
        usort($res, static fn ($x, $y) => [$y['bookings'], $y['views']] <=> [$x['bookings'], $x['views']]);
        return $res;
    }

    /** Citas por día/semana (lunes)/mes con todos los periodos del rango, aunque estén vacíos. */
    private static function byPeriod(array $rows, string $tz, string $group, string $from, string $to): array
    {
        $group = in_array($group, ['day', 'week', 'month'], true) ? $group : 'day';
        $key = static function (string $date) use ($group): string {
            $t = strtotime($date . ' UTC');
            if ($group === 'week') {
                return gmdate('Y-m-d', $t - ((int) gmdate('N', $t) - 1) * 86400);
            }
            return $group === 'month' ? substr($date, 0, 7) : $date;
        };
        $out = [];
        for ($t = strtotime($from . ' UTC'), $end = strtotime($to . ' UTC'); $t <= $end; $t += 86400) {
            $out[$key(gmdate('Y-m-d', $t))] ??= ['period' => $key(gmdate('Y-m-d', $t)), 'bookings' => 0, 'cancelled' => 0, 'no_show' => 0];
        }
        foreach ($rows as $r) {
            $k = $key(Tz::format((string) $r['starts_at'], $tz, 'Y-m-d'));
            if (!isset($out[$k])) {
                continue;
            }
            $out[$k]['bookings']++;
            if ($r['status'] === 'cancelled') {
                $out[$k]['cancelled']++;
            } elseif ($r['status'] === 'no_show') {
                $out[$k]['no_show']++;
            }
        }
        return ['group' => $group, 'rows' => array_values($out)];
    }

    /** Horas y días pico en la zona del negocio (citas no canceladas ni rechazadas). */
    private static function peaks(array $rows, string $tz): array
    {
        $h = array_fill(0, 24, 0);
        $d = array_fill(1, 7, 0);
        foreach ($rows as $r) {
            if (in_array($r['status'], ['cancelled', 'rejected'], true)) {
                continue;
            }
            $ts = Tz::ts((string) $r['starts_at']);
            $h[(int) Tz::formatTs($ts, $tz, 'G')]++;
            $d[(int) Tz::formatTs($ts, $tz, 'N')]++;
        }
        $top = static function (array $a): ?int {
            $m = max($a);
            return $m > 0 ? (int) array_search($m, $a, true) : null;
        };
        return ['by_hour' => $h, 'by_weekday' => $d, 'peak_hour' => $top($h), 'peak_weekday' => $top($d)];
    }

    private static function devices(string $a, string $b, array $f): array
    {
        [$wa, $pa] = self::where($f, 'a');
        $out = ['mobile' => 0, 'desktop' => 0];
        foreach (Db::all("SELECT a.device, COUNT(DISTINCT a.visit_id) AS n FROM analytics_events a WHERE a.step = 'view' AND a.created_at >= ? AND a.created_at < ?" . $wa . ' GROUP BY a.device', array_merge([$a, $b], $pa)) as $r) {
            if (isset($out[$r['device']])) {
                $out[$r['device']] = (int) $r['n'];
            }
        }
        return $out;
    }

    /** Minutos reservados / minutos laborables por anfitrión (horario semanal, excepciones y feriados completos o de medio día). */
    private static function occupancy(array $rows, string $from, string $to, array $f): array
    {
        $hosts = Db::all('SELECT h.id, h.name, h.schedule_id FROM hosts h WHERE h.active = 1' . ($f['host'] !== null ? ' AND h.id = ' . (int) $f['host'] : '') . ' ORDER BY h.sort_order, h.name');
        $default = Db::val('SELECT id FROM schedules WHERE is_default = 1 ORDER BY id LIMIT 1');
        $booked = [];
        foreach ($rows as $r) {
            if (in_array($r['status'], ['cancelled', 'rejected'], true)) {
                continue;
            }
            $ids = [(int) $r['host_id']];
            foreach (Db::col('SELECT host_id FROM booking_hosts WHERE booking_id = ?', [(int) $r['id']]) as $h) {
                $ids[] = (int) $h;
            }
            foreach (array_unique($ids) as $h) {
                $booked[$h] = ($booked[$h] ?? 0) + (int) $r['duration'];
            }
        }
        $holidays = [];
        if (Settings::bool('holidays_enabled')) {
            foreach (Db::all("SELECT date, kind, half_day_end FROM holidays WHERE active = 1 AND date BETWEEN ? AND ?", [$from, $to]) as $h) {
                $holidays[(string) $h['date']] = $h;
            }
        }
        $cap = [];
        $out = [];
        foreach ($hosts as $h) {
            $sid = $h['schedule_id'] !== null ? (int) $h['schedule_id'] : ($default !== null ? (int) $default : 0);
            if (!isset($cap[$sid])) {
                $cap[$sid] = self::capacity($sid, $from, $to, $holidays);
            }
            $bm = $booked[(int) $h['id']] ?? 0;
            $out[] = ['host_id' => (int) $h['id'], 'name' => $h['name'], 'booked_minutes' => $bm, 'capacity_minutes' => $cap[$sid], 'pct' => $cap[$sid] > 0 ? round($bm * 100 / $cap[$sid], 1) : null];
        }
        return $out;
    }

    private static function capacity(int $scheduleId, string $from, string $to, array $holidays): int
    {
        if ($scheduleId <= 0) {
            return 0;
        }
        $rules = [];
        foreach (Db::all('SELECT weekday, start_time, end_time FROM schedule_rules WHERE schedule_id = ?', [$scheduleId]) as $r) {
            $rules[(int) $r['weekday']][] = [$r['start_time'], $r['end_time']];
        }
        $over = [];
        foreach (Db::all('SELECT date, is_open, start_time, end_time FROM schedule_overrides WHERE schedule_id = ? AND date BETWEEN ? AND ?', [$scheduleId, $from, $to]) as $o) {
            $over[(string) $o['date']][] = $o;
        }
        $min = static function (string $t): int {
            return (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        };
        $total = 0;
        for ($t = strtotime($from . ' UTC'), $end = strtotime($to . ' UTC'); $t <= $end; $t += 86400) {
            $date = gmdate('Y-m-d', $t);
            if (isset($over[$date])) {
                $blocks = [];
                foreach ($over[$date] as $o) {
                    if ((int) $o['is_open'] === 1 && $o['start_time'] !== null && $o['end_time'] !== null) {
                        $blocks[] = [$o['start_time'], $o['end_time']];
                    }
                }
            } else {
                $blocks = $rules[(int) gmdate('N', $t)] ?? [];
            }
            $limit = 1440;
            if (isset($holidays[$date])) {
                if ($holidays[$date]['kind'] === 'full') {
                    continue;
                }
                $limit = $holidays[$date]['half_day_end'] !== null ? $min((string) $holidays[$date]['half_day_end']) : 720;
            }
            foreach ($blocks as [$s, $e]) {
                $total += max(0, min($min((string) $e), $limit) - $min((string) $s));
            }
        }
        return $total;
    }
}
