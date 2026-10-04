<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Calendario (día, semana, mes) y su fuente de datos JSON. */
final class CalendarController extends Controller
{
    private const MAX_DAYS = 45;

    public function index(Request $req, array $p): Response
    {
        $tz = Settings::tz();
        $hosts = A1Support::hosts();
        $events = A1Support::events();
        $date = $req->str('fecha', 10);
        $view = $req->str('vista', 10);
        $boot = [
            'tz' => $tz,
            'today' => A1Support::today(),
            'date' => Validator::date($date) ? $date : A1Support::today(),
            'view' => in_array($view, ['dia', 'semana', 'mes'], true) ? $view : '',
            'hosts' => array_map(static fn (array $h): array => ['id' => (int) $h['id'], 'name' => (string) $h['name'], 'color' => (string) $h['color']], $hosts),
            'events' => array_map(static fn (array $e): array => ['id' => (int) $e['id'], 'name' => (string) $e['name'], 'color' => (string) $e['color']], $events),
            'scoped' => Auth::scopedHostId() !== null,
            'urls' => [
                'data' => url('/admin/calendario/datos'),
                'new' => url('/admin/citas/nueva'),
                'booking' => url('/admin/citas'),
            ],
            'hours' => $this->businessHours(),
            'timeFormat' => (string) Settings::get('time_format', '12'),
        ];
        return $this->view('admin/calendar/index', [
            'title' => 'Calendario',
            'boot' => $boot,
            'hosts' => $hosts,
            'events' => $events,
            'scripts' => ['js/admin-bookings.js', 'js/admin-calendar.js'],
        ], 'layouts/admin');
    }

    /** Hora de apertura y cierre (horas enteras) según los horarios semanales, con margen. */
    private function businessHours(): array
    {
        $r = Db::one('SELECT MIN(start_time) AS a, MAX(end_time) AS b FROM schedule_rules');
        $start = $r && $r['a'] ? (int) substr((string) $r['a'], 0, 2) : 8;
        $end = $r && $r['b'] ? (int) substr((string) $r['b'], 0, 2) + ((int) substr((string) $r['b'], 3, 2) > 0 ? 1 : 0) : 19;
        return ['start' => max(0, min(23, $start - 1)), 'end' => min(24, max($start + 2, $end + 1))];
    }

    /** JSON por rango de fechas locales [from, to) en la zona del negocio; las horas viajan en UTC ISO. */
    public function data(Request $req, array $p): Response
    {
        $from = $req->str('from', 10);
        $to = $req->str('to', 10);
        if (!Validator::date($from) || !Validator::date($to) || $to <= $from) {
            return $this->json(['ok' => false, 'error' => 'El rango de fechas no es válido.'], 422);
        }
        if (strtotime($to) - strtotime($from) > self::MAX_DAYS * 86400) {
            return $this->json(['ok' => false, 'error' => 'El rango es demasiado largo.'], 422);
        }
        $tz = Settings::tz();
        [$fromUtc] = A1Support::rangeUtc($from, $from);
        [$toUtc] = A1Support::rangeUtc($to, $to);
        [$sql, $params] = A1Support::bookingScope('b');
        $statuses = $req->bool('canceladas') ? "'pending','confirmed','completed','no_show','cancelled','rejected'" : "'pending','confirmed','completed','no_show'";
        $sql .= " AND b.status IN ({$statuses}) AND b.starts_at < ? AND b.ends_at > ?";
        array_push($params, $toUtc, $fromUtc);
        $host = $req->int('host');
        if ($host > 0) {
            $sql .= ' AND (b.host_id = ? OR EXISTS (SELECT 1 FROM booking_hosts bhc WHERE bhc.booking_id = b.id AND bhc.host_id = ?))';
            array_push($params, $host, $host);
        }
        $ev = $req->int('event');
        if ($ev > 0) {
            $sql .= ' AND b.event_type_id = ?';
            $params[] = $ev;
        }
        $rows = Db::all('SELECT b.id, b.status, b.starts_at, b.ends_at, b.duration, b.host_id, b.event_type_id, b.guest_name, b.guest_phone, b.guest_email, b.total, b.payment_status, b.internal_note,
                                e.name AS event_name, e.color AS event_color, h.name AS host_name
                         FROM bookings b JOIN event_types e ON e.id = b.event_type_id JOIN hosts h ON h.id = b.host_id
                         WHERE ' . $sql . ' ORDER BY b.starts_at LIMIT 1500', $params);
        $bookings = [];
        foreach ($rows as $r) {
            $bookings[] = [
                'id' => (int) $r['id'],
                'status' => $r['status'],
                'status_label' => A1Support::statusLabel((string) $r['status']),
                'start' => Tz::iso((string) $r['starts_at']),
                'end' => Tz::iso((string) $r['ends_at']),
                'duration' => (int) $r['duration'],
                'host_id' => (int) $r['host_id'],
                'host_name' => $r['host_name'],
                'event_id' => (int) $r['event_type_id'],
                'event_name' => $r['event_name'],
                'guest' => $r['guest_name'],
                'phone' => $r['guest_phone'] ? Str::phoneDisplay($r['guest_phone']) : '',
                'email' => (string) $r['guest_email'],
                'total' => (float) $r['total'] > 0 ? money($r['total']) : '',
                'note' => (string) $r['internal_note'],
                'movable' => in_array($r['status'], ['pending', 'confirmed'], true),
            ];
        }
        $scope = Auth::scopedHostId();
        $toff = Db::all('SELECT host_id, starts_at, ends_at, reason FROM time_off WHERE ends_at > ? AND starts_at < ?' . ($scope !== null ? ' AND (host_id = ? OR host_id IS NULL)' : '') . ' ORDER BY starts_at LIMIT 200', $scope !== null ? [$fromUtc, $toUtc, $scope] : [$fromUtc, $toUtc]);
        $closed = Db::all('SELECT date, name, kind FROM holidays WHERE active = 1 AND date >= ? AND date < ? ORDER BY date', [$from, $to]);
        return $this->json([
            'ok' => true,
            'tz' => $tz,
            'from' => $from,
            'to' => $to,
            'bookings' => $bookings,
            'timeoff' => array_map(static fn (array $t): array => ['host_id' => $t['host_id'] === null ? null : (int) $t['host_id'], 'start' => Tz::iso((string) $t['starts_at']), 'end' => Tz::iso((string) $t['ends_at']), 'reason' => (string) $t['reason']], $toff),
            'holidays' => array_map(static fn (array $h): array => ['date' => $h['date'], 'name' => $h['name'], 'half' => $h['kind'] === 'half'], $closed),
        ]);
    }
}
