<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Controller;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Tz;

/** Inicio del panel: agenda de hoy, pendientes, huecos libres, KPIs y próximas citas. */
final class DashboardController extends Controller
{
    private const LIVE = "('pending','confirmed','completed','no_show')";

    public function index(Request $req, array $p): Response
    {
        $tz = Settings::tz();
        $now = Clock::now();
        $today = A1Support::today($now);
        [$todayStart, $todayEnd] = A1Support::rangeUtc($today, $today);
        $tomorrow = A1Support::addDays($today, 1);
        [$sql, $sp] = A1Support::bookingScope('b');

        $select = 'SELECT b.id, b.status, b.starts_at, b.ends_at, b.duration, b.guest_name, b.guest_phone, b.payment_status, b.total, b.host_id,
                          e.name AS event_name, e.color AS event_color, h.name AS host_name, h.color AS host_color
                   FROM bookings b JOIN event_types e ON e.id = b.event_type_id JOIN hosts h ON h.id = b.host_id WHERE ' . $sql;

        $agenda = Db::all($select . ' AND b.status IN ' . self::LIVE . ' AND b.starts_at >= ? AND b.starts_at < ? ORDER BY b.starts_at', array_merge($sp, [$todayStart, $todayEnd]));
        $pending = Db::all($select . " AND b.status = 'pending' AND b.ends_at >= ? ORDER BY b.starts_at LIMIT 8", array_merge($sp, [Clock::utc($now)]));
        $pendingTotal = (int) Db::val("SELECT COUNT(*) FROM bookings b WHERE {$sql} AND b.status = 'pending' AND b.ends_at >= ?", array_merge($sp, [Clock::utc($now)]));
        $upcoming = Db::all($select . " AND b.status IN ('pending','confirmed') AND b.starts_at >= ? ORDER BY b.starts_at LIMIT 6", array_merge($sp, [$todayEnd]));

        return $this->view('admin/dashboard/index', [
            'title' => 'Inicio',
            'tz' => $tz,
            'today' => $today,
            'tomorrow' => $tomorrow,
            'agenda' => $agenda,
            'pending' => $pending,
            'pendingTotal' => $pendingTotal,
            'upcoming' => $upcoming,
            'kpis' => $this->kpis($today, $tz, $sql, $sp),
            'free' => $this->freeSlots($now, $today, $tomorrow),
            'showOnboarding' => !Settings::bool('onboarding_done') && Auth::can('settings'),
            'canCreate' => Auth::can('bookings'),
            'userName' => (string) (Auth::user()['name'] ?? ''),
        ], 'layouts/admin');
    }

    private function kpis(string $today, string $tz, string $sql, array $sp): array
    {
        [$dayS, $dayE] = A1Support::rangeUtc($today, $today);
        $dow = (int) (new \DateTimeImmutable($today, new \DateTimeZone('UTC')))->format('N');
        $monday = A1Support::addDays($today, 1 - $dow);
        [$weekS, $weekE] = A1Support::rangeUtc($monday, A1Support::addDays($monday, 6));
        $monthFirst = substr($today, 0, 8) . '01';
        $monthLast = (new \DateTimeImmutable($monthFirst, new \DateTimeZone('UTC')))->modify('last day of this month')->format('Y-m-d');
        [$monS, $monE] = A1Support::rangeUtc($monthFirst, $monthLast);

        $count = static fn (string $from, string $to): int => (int) Db::val("SELECT COUNT(*) FROM bookings b WHERE {$sql} AND b.status IN " . self::LIVE . ' AND b.starts_at >= ? AND b.starts_at < ?', array_merge($sp, [$from, $to]));
        $k = [
            'today' => $count($dayS, $dayE),
            'week' => $count($weekS, $weekE),
            'noshow' => (int) Db::val("SELECT COUNT(*) FROM bookings b WHERE {$sql} AND b.status = 'no_show' AND b.starts_at >= ? AND b.starts_at < ?", array_merge($sp, [$monS, $monE])),
            'revenue' => null,
            'completed' => (int) Db::val("SELECT COUNT(*) FROM bookings b WHERE {$sql} AND b.status = 'completed' AND b.starts_at >= ? AND b.starts_at < ?", array_merge($sp, [$monS, $monE])),
            'occupancy' => $this->occupancy($today, $tz, $sql, $sp),
        ];
        if (Auth::can('payments')) {
            $k['revenue'] = (float) Db::val("SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.status = 'verified' AND p.created_at >= ? AND p.created_at < ?", [$monS, $monE]);
        }
        return $k;
    }

    /** Ocupación de los próximos 7 días: minutos agendados / minutos abiertos según el horario semanal de cada anfitrión. */
    private function occupancy(string $today, string $tz, string $sql, array $sp): ?int
    {
        $hosts = A1Support::hosts();
        if (!$hosts) {
            return null;
        }
        $open = 0;
        for ($i = 0; $i < 7; $i++) {
            $d = A1Support::addDays($today, $i);
            $wd = (int) (new \DateTimeImmutable($d, new \DateTimeZone('UTC')))->format('N');
            foreach ($hosts as $h) {
                $rules = Db::all('SELECT r.start_time, r.end_time FROM schedule_rules r JOIN hosts hh ON hh.schedule_id = r.schedule_id WHERE hh.id = ? AND r.weekday = ?', [$h['id'], $wd]);
                foreach ($rules as $r) {
                    $open += max(0, (strtotime('1970-01-01 ' . $r['end_time'] . ' UTC') - strtotime('1970-01-01 ' . $r['start_time'] . ' UTC')) / 60);
                }
            }
        }
        if ($open <= 0) {
            return null;
        }
        [$from] = A1Support::rangeUtc($today, $today);
        [, $to] = A1Support::rangeUtc(A1Support::addDays($today, 6), A1Support::addDays($today, 6));
        $booked = (int) Db::val("SELECT COALESCE(SUM(b.duration), 0) FROM bookings b WHERE {$sql} AND b.status IN ('pending','confirmed','completed') AND b.starts_at >= ? AND b.starts_at < ?", array_merge($sp, [$from, $to]));
        return (int) min(100, round($booked / $open * 100));
    }

    /** Huecos libres de hoy y mañana por evento (usa el motor de disponibilidad; nunca rompe el inicio). */
    private function freeSlots(int $now, string $today, string $tomorrow): array
    {
        $out = [];
        if (!A1Support::hasBookingCore()) {
            return $out;
        }
        $tz = Settings::tz();
        [$from] = A1Support::rangeUtc($today, $today);
        [, $to] = A1Support::rangeUtc($tomorrow, $tomorrow);
        $scope = Auth::scopedHostId();
        foreach (array_slice(A1Support::events(), 0, 4) as $row) {
            try {
                $ev = \App\Services\EventRepository::find((int) $row['id']);
                if (!$ev) {
                    continue;
                }
                $opts = ['ignore_notice' => true];
                if ($scope !== null) {
                    $opts['host_id'] = $scope;
                }
                $slots = \App\Services\AvailabilityService::slots($ev, (int) $ev['default_duration'], $from, $to, $opts);
                $days = [$today => [], $tomorrow => []];
                foreach ($slots as $s) {
                    if (Tz::ts($s['start']) < $now) {
                        continue;
                    }
                    $d = Tz::format($s['start'], $tz, 'Y-m-d');
                    if (isset($days[$d]) && count($days[$d]) < 6) {
                        $days[$d][] = ['start' => $s['start'], 'host' => (int) ($s['host_ids'][0] ?? 0)];
                    }
                }
                if ($days[$today] || $days[$tomorrow]) {
                    $out[] = ['event' => ['id' => (int) $ev['id'], 'name' => (string) $ev['name'], 'color' => (string) $ev['color']], 'days' => $days];
                }
            } catch (\Throwable $e) {
                Logger::error('No se pudieron calcular los huecos libres del inicio', $e);
            }
        }
        return $out;
    }
}
