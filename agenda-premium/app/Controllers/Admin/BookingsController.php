<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Controller;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;
use App\Services\BookingException;

/** Citas: lista, alta manual, detalle y acciones (estado, mover, cancelar, nota). */
final class BookingsController extends Controller
{
    private const PER_PAGE = 25;
    private const EXPORT_MAX = 5000;

    /** Transiciones permitidas desde el panel. */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'rejected'],
        'confirmed' => ['completed', 'no_show'],
        'completed' => ['no_show'],
        'no_show' => ['completed'],
    ];

    // ------------------------------------------------------------------ Lista

    /** @return array{0:string,1:array,2:array} where, params, filtros normalizados */
    private function filters(Request $req): array
    {
        [$sql, $params] = A1Support::bookingScope('b');
        $f = ['estado' => '', 'anfitrion' => 0, 'evento' => 0, 'desde' => '', 'hasta' => '', 'q' => ''];
        $estado = $req->str('estado', 20);
        if (isset(A1Support::STATUS_LABELS[$estado])) {
            $f['estado'] = $estado;
            $sql .= ' AND b.status = ?';
            $params[] = $estado;
        }
        $host = $req->int('anfitrion');
        if ($host > 0) {
            $f['anfitrion'] = $host;
            $sql .= ' AND (b.host_id = ? OR EXISTS (SELECT 1 FROM booking_hosts bhf WHERE bhf.booking_id = b.id AND bhf.host_id = ?))';
            array_push($params, $host, $host);
        }
        $ev = $req->int('evento');
        if ($ev > 0) {
            $f['evento'] = $ev;
            $sql .= ' AND b.event_type_id = ?';
            $params[] = $ev;
        }
        $desde = $req->str('desde', 10);
        $hasta = $req->str('hasta', 10);
        if (Validator::date($desde)) {
            $f['desde'] = $desde;
            $sql .= ' AND b.starts_at >= ?';
            $params[] = A1Support::rangeUtc($desde, $desde)[0];
        }
        if (Validator::date($hasta)) {
            $f['hasta'] = $hasta;
            $sql .= ' AND b.starts_at < ?';
            $params[] = A1Support::rangeUtc($hasta, $hasta)[1];
        }
        $q = $req->str('q', 80);
        if ($q !== '') {
            $f['q'] = $q;
            $like = A1Support::like($q);
            $sql .= " AND (b.guest_name LIKE ? ESCAPE '|' OR b.guest_email LIKE ? ESCAPE '|' OR b.guest_phone LIKE ? ESCAPE '|' OR b.token LIKE ? ESCAPE '|'";
            array_push($params, $like, $like, $like, $like);
            if (preg_match('/^#?(\d{1,9})$/', $q, $m)) {
                $sql .= ' OR b.id = ?';
                $params[] = (int) $m[1];
            }
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            if (strlen($digits) >= 4) {
                $sql .= " OR b.guest_phone LIKE ? ESCAPE '|'";
                $params[] = A1Support::like($digits);
            }
            $sql .= ')';
        }
        return [$sql, $params, $f];
    }

    private const SELECT = 'SELECT b.id, b.status, b.starts_at, b.duration, b.guest_name, b.guest_email, b.guest_phone, b.total, b.paid_amount, b.payment_status, b.created_via, b.utm_source, b.created_at,
                   e.name AS event_name, e.color AS event_color, h.name AS host_name, h.color AS host_color
            FROM bookings b JOIN event_types e ON e.id = b.event_type_id JOIN hosts h ON h.id = b.host_id';

    public function index(Request $req, array $p): Response
    {
        [$where, $params, $f] = $this->filters($req);
        $total = (int) Db::val('SELECT COUNT(*) FROM bookings b WHERE ' . $where, $params);
        [$offset, $pages, $page] = A1Support::pages($total, $req->int('pagina', 1), self::PER_PAGE);
        $rows = Db::all(self::SELECT . ' WHERE ' . $where . ' ORDER BY b.starts_at DESC, b.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset, $params);
        $counts = [];
        foreach (Db::all("SELECT b.status, COUNT(*) AS n FROM bookings b WHERE " . A1Support::bookingScope('b')[0] . ' GROUP BY b.status', A1Support::bookingScope('b')[1]) as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $query = array_filter($f, static fn ($v): bool => $v !== '' && $v !== 0);
        return $this->view('admin/bookings/index', [
            'title' => 'Citas',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'f' => $f,
            'query' => $query,
            'counts' => $counts,
            'hosts' => A1Support::hosts(false),
            'events' => Db::all('SELECT id, name FROM event_types ORDER BY name'),
            'tz' => Settings::tz(),
            'today' => A1Support::today(),
        ], 'layouts/admin');
    }

    public function export(Request $req, array $p): Response
    {
        [$where, $params] = $this->filters($req);
        $rows = Db::all(self::SELECT . ' WHERE ' . $where . ' ORDER BY b.starts_at DESC, b.id DESC LIMIT ' . self::EXPORT_MAX, $params);
        $tz = Settings::tz();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r['id'],
                Tz::format($r['starts_at'], $tz, 'd/m/Y'),
                Tz::format($r['starts_at'], $tz, 'H:i'),
                $r['duration'],
                $r['guest_name'],
                $r['guest_email'],
                $r['guest_phone'],
                $r['event_name'],
                $r['host_name'],
                A1Support::statusLabel((string) $r['status']),
                number_format((float) $r['total'], 2, '.', ''),
                number_format((float) $r['paid_amount'], 2, '.', ''),
                (string) $r['created_via'],
            ];
        }
        Auth::audit('export_bookings', 'booking', null, count($out) . ' citas exportadas a CSV');
        $csv = A1Support::csv(['N.º', 'Fecha', 'Hora', 'Duración (min)', 'Nombre', 'Correo', 'Teléfono', 'Evento', 'Anfitrión', 'Estado', 'Total', 'Pagado', 'Origen'], $out);
        return Response::download($csv, 'text/csv', 'citas-' . A1Support::today() . '.csv');
    }

    // ------------------------------------------------------------------ Alta manual

    public function createForm(Request $req, array $p): Response
    {
        $tz = Settings::tz();
        $old = [
            'origen' => 'llamada', 'client_id' => 0, 'name' => '', 'email' => '', 'phone' => '', 'nit' => '', 'event_id' => $req->int('evento'),
            'host_id' => $req->int('anfitrion'), 'duration' => 0, 'fecha' => '', 'hora' => '', 'start_utc' => '', 'force' => false, 'status' => 'confirmed', 'notes' => '', 'internal_note' => '',
        ];
        $inicio = $req->str('inicio', 19);
        if (Validator::datetimeUtc($inicio)) {
            $old['fecha'] = Tz::format($inicio, $tz, 'Y-m-d');
            $old['hora'] = Tz::format($inicio, $tz, 'H:i');
            $old['start_utc'] = $inicio;
        } else {
            $fecha = $req->str('fecha', 10);
            $hora = $req->str('hora', 5);
            $old['fecha'] = Validator::date($fecha) ? $fecha : A1Support::today();
            $old['hora'] = Validator::time($hora) ? $hora : '';
        }
        $cid = $req->int('cliente');
        if ($cid > 0) {
            try {
                $c = A1Support::client($cid);
                $old = array_merge($old, ['client_id' => (int) $c['id'], 'name' => $c['name'], 'email' => (string) $c['email'], 'phone' => (string) $c['phone'], 'nit' => (string) $c['nit']]);
            } catch (HttpException $e) {
                // cliente fuera de alcance: se ignora
            }
        }
        return $this->form($old, '');
    }

    private function form(array $old, string $error, int $status = 200): Response
    {
        $events = A1Support::events();
        $hosts = A1Support::hosts();
        $map = [];
        $dur = [];
        foreach ($events as $e) {
            $map[(int) $e['id']] = array_map('intval', Db::col('SELECT host_id FROM event_hosts WHERE event_type_id = ?', [$e['id']]));
            $dur[(int) $e['id']] = ['list' => A1Support::durations($e), 'def' => (int) $e['default_duration'], 'kind' => $e['kind']];
        }
        $r = $this->view('admin/bookings/new', [
            'title' => 'Nueva cita',
            'old' => $old,
            'error' => $error,
            'events' => $events,
            'hosts' => $hosts,
            'eventHosts' => $map,
            'eventDur' => $dur,
            'tz' => Settings::tz(),
            'sources' => A1Support::SOURCES,
            'scoped' => Auth::scopedHostId() !== null,
            'coreReady' => A1Support::hasBookingCore(),
        ], 'layouts/admin');
        $r->status = $status;
        return $r;
    }

    public function create(Request $req, array $p): Response
    {
        $tz = Settings::tz();
        $old = [
            'origen' => $req->str('origen', 20), 'client_id' => $req->int('client_id'), 'name' => $req->str('name', 160), 'email' => strtolower($req->str('email', 190)),
            'phone' => $req->str('phone', 30), 'nit' => $req->str('nit', 30), 'event_id' => $req->int('event_id'), 'host_id' => $req->int('host_id'),
            'duration' => $req->int('duration'), 'fecha' => $req->str('fecha', 10), 'hora' => $req->str('hora', 5), 'start_utc' => $req->str('start_utc', 19),
            'force' => $req->bool('force'), 'status' => $req->str('status', 12), 'notes' => $req->str('notes', 1000), 'internal_note' => $req->str('internal_note', 1000),
        ];
        $err = fn (string $m): Response => $this->form($old, $m, 422);
        if (!A1Support::hasBookingCore() || !class_exists('App\\Services\\BookingService')) {
            return $err('El motor de reservas todavía no está disponible. Inténtalo de nuevo en unos minutos.');
        }
        if (!isset(A1Support::SOURCES[$old['origen']])) {
            $old['origen'] = 'llamada';
        }
        // Cliente existente (dentro de tu alcance) o nuevo
        $clientId = 0;
        if ($old['client_id'] > 0) {
            try {
                $c = A1Support::client($old['client_id']);
                $clientId = (int) $c['id'];
                $old['name'] = $old['name'] !== '' ? $old['name'] : (string) $c['name'];
                $old['email'] = $old['email'] !== '' ? $old['email'] : (string) $c['email'];
                $old['phone'] = $old['phone'] !== '' ? $old['phone'] : (string) $c['phone'];
                $old['nit'] = $old['nit'] !== '' ? $old['nit'] : (string) $c['nit'];
            } catch (HttpException $e) {
                return $err('Ese cliente no está disponible. Elige otro o escribe los datos de uno nuevo.');
            }
        }
        if ($old['name'] === '') {
            return $err('Escribe el nombre de la persona.');
        }
        if ($old['email'] !== '' && !Validator::email($old['email'])) {
            return $err('El correo no parece válido. Revísalo o déjalo vacío.');
        }
        $phone = null;
        if ($old['phone'] !== '') {
            $phone = Str::phone($old['phone'], (string) Settings::get('phone_cc', '502'));
            if ($phone === null) {
                return $err('El teléfono no es válido. Usa 8 dígitos (por ejemplo 5555 1234) o con código de país.');
            }
        }
        // Evento, anfitrión, duración
        $event = $old['event_id'] > 0 ? \App\Services\EventRepository::find($old['event_id']) : null;
        if (!$event || !in_array((int) $event['id'], array_map(static fn (array $e): int => (int) $e['id'], A1Support::events()), true)) {
            return $err('Elige el tipo de cita.');
        }
        $scope = Auth::scopedHostId();
        $hostId = $scope ?? $old['host_id'];
        $duration = $old['duration'] > 0 ? $old['duration'] : (int) $event['default_duration'];
        // Fecha y hora
        $startUtc = '';
        if (Validator::datetimeUtc($old['start_utc']) && $old['hora'] === '') {
            $startUtc = $old['start_utc'];
        } elseif (Validator::date($old['fecha']) && Validator::time($old['hora'])) {
            $startUtc = Tz::localToUtc($old['fecha'] . ' ' . substr($old['hora'], 0, 5) . ':00', $tz);
        }
        if ($startUtc === '') {
            return $err('Elige la fecha y la hora de la cita.');
        }
        $status = $old['status'] === 'pending' ? 'pending' : 'confirmed';
        try {
            $res = \App\Services\BookingService::create([
                'event_id' => (int) $event['id'],
                'duration' => $duration,
                'start' => $startUtc,
                'host_id' => $hostId > 0 ? $hostId : null,
                'timezone' => $tz,
                'name' => $old['name'],
                'email' => $old['email'] !== '' ? $old['email'] : null,
                'phone' => $phone,
                'nit' => $old['nit'],
                'notes' => $old['notes'],
                'seats' => 1,
                'consent' => true,
                'created_via' => 'admin',
                'utm_source' => $old['origen'],
                'utm_medium' => 'manual',
                'force' => $old['force'],
                'status' => $status,
                'created_by' => (int) (Auth::user()['id'] ?? 0) ?: null,
            ]);
        } catch (BookingException $e) {
            $msg = $e->getMessage();
            if ($e->errorCode === 'slot_unavailable' && !$old['force']) {
                $msg .= ' Si es una excepción, marca «Permitir fuera de horario».';
            }
            return $err($msg);
        } catch (\Throwable $e) {
            Logger::error('Falló la creación manual de una cita', $e);
            return $err('No pudimos crear la cita por un problema interno. Ya quedó registrado; inténtalo de nuevo.');
        }
        $booking = $res['booking'];
        $ids = array_map(static fn (array $b): int => (int) $b['id'], $res['bookings'] ?? [$booking]);
        if ($clientId > 0) {
            foreach ($ids as $bid) {
                Db::update('bookings', ['client_id' => $clientId], 'id = ? AND (client_id IS NULL OR client_id <> ?)', [$bid, $clientId]);
            }
        }
        if ($old['internal_note'] !== '') {
            Db::update('bookings', ['internal_note' => $old['internal_note']], 'id = ?', [(int) $booking['id']]);
        }
        \App\Core\Cache::bumpAvailability();
        Auth::audit('booking_create', 'booking', (int) $booking['id'], 'Cita manual (' . ($old['origen']) . ') de ' . $old['name'] . ($old['force'] ? ' · fuera de horario' : ''));
        $this->flash('success', 'Cita creada para ' . $old['name'] . '.');
        return $this->redirect('/admin/citas/' . (int) $booking['id']);
    }

    /** Horarios libres de un día (hora local del negocio) para el formulario y el reprogramador. */
    public function slots(Request $req, array $p): Response
    {
        if (!A1Support::hasBookingCore()) {
            return $this->json(['ok' => false, 'error' => 'El motor de disponibilidad no está disponible todavía.'], 503);
        }
        $date = $req->str('fecha', 10);
        if (!Validator::date($date)) {
            return $this->json(['ok' => false, 'error' => 'Elige una fecha válida.'], 422);
        }
        $event = \App\Services\EventRepository::find($req->int('evento'));
        if (!$event) {
            return $this->json(['ok' => false, 'error' => 'Elige primero el tipo de cita.'], 422);
        }
        $scope = Auth::scopedHostId();
        $host = $scope ?? $req->int('anfitrion');
        if ($scope !== null) {
            $allowed = array_map(static fn (array $h): int => (int) $h['id'], (array) ($event['hosts'] ?? []));
            if (!in_array($scope, $allowed, true)) {
                throw new HttpException(404);
            }
        }
        $duration = $req->int('duracion', (int) $event['default_duration']);
        $opts = ['ignore_notice' => true];
        if ($host > 0) {
            $opts['host_id'] = $host;
        }
        $exclude = $req->int('excluir');
        if ($exclude > 0) {
            A1Support::booking($exclude);
            $opts['exclude_booking_id'] = $exclude;
        }
        [$from, $to] = A1Support::rangeUtc($date, $date);
        $tz = Settings::tz();
        $now = Clock::now();
        $out = [];
        try {
            foreach (\App\Services\AvailabilityService::slots($event, $duration, $from, $to, $opts) as $s) {
                $out[] = [
                    'start' => $s['start'],
                    'label' => Fmt::time($s['start'], $tz),
                    'local' => Tz::format($s['start'], $tz, 'H:i'),
                    'hosts' => array_map('intval', $s['host_ids'] ?? []),
                    'past' => Tz::ts($s['start']) < $now,
                    'seats' => $s['seats_left'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Logger::error('Error al calcular horarios libres del panel', $e);
            return $this->json(['ok' => false, 'error' => 'No pudimos calcular los horarios. Inténtalo de nuevo.'], 500);
        }
        return $this->json(['ok' => true, 'tz' => $tz, 'slots' => $out]);
    }

    // ------------------------------------------------------------------ Detalle

    public function show(Request $req, array $p): Response
    {
        $b = A1Support::booking((int) $p['id']);
        $id = (int) $b['id'];
        $tz = Settings::tz();
        $event = Db::one('SELECT id, name, color, kind, location, mode, cancel_hours FROM event_types WHERE id = ?', [$b['event_type_id']]) ?? [];
        $host = Db::one('SELECT id, name, color, timezone FROM hosts WHERE id = ?', [$b['host_id']]) ?? [];
        $hosts = Db::all('SELECT h.id, h.name FROM booking_hosts bh JOIN hosts h ON h.id = bh.host_id WHERE bh.booking_id = ?', [$id]);
        $client = $b['client_id'] ? Db::one('SELECT id, name, noshow_count, blocked FROM clients WHERE id = ?', [$b['client_id']]) : null;
        $status = (string) $b['status'];
        $started = Tz::ts((string) $b['starts_at']) <= Clock::now();
        $actions = [];
        foreach (self::TRANSITIONS[$status] ?? [] as $to) {
            if (in_array($to, ['completed', 'no_show'], true) && !$started && $status === 'confirmed') {
                continue;
            }
            $actions[] = $to;
        }
        $text = Str::template('Hola {nombre}, te escribimos de {negocio} sobre tu cita de {evento} el {fecha} a las {hora}. ¿Nos confirmas tu asistencia? ¡Gracias!', [
            'nombre' => explode(' ', (string) $b['guest_name'])[0],
            'negocio' => (string) Settings::get('business_name', ''),
            'evento' => (string) ($event['name'] ?? 'cita'),
            'fecha' => Fmt::dateLong((string) $b['starts_at'], $tz),
            'hora' => Fmt::time((string) $b['starts_at'], $tz),
        ]);
        return $this->view('admin/bookings/show', [
            'title' => 'Cita #' . $id,
            'b' => $b,
            'event' => $event,
            'host' => $host,
            'hosts' => $hosts,
            'client' => $client,
            'attendees' => Db::all('SELECT name, email, phone FROM booking_attendees WHERE booking_id = ? ORDER BY id', [$id]),
            'answers' => Db::all('SELECT ba.label, ba.value, f.token AS file_token, f.original_name FROM booking_answers ba LEFT JOIN files f ON f.id = ba.file_id WHERE ba.booking_id = ? ORDER BY ba.id', [$id]),
            'history' => Db::all('SELECT action, detail, actor, created_at FROM booking_history WHERE booking_id = ? ORDER BY id DESC LIMIT 60', [$id]),
            'series' => $b['series_token'] ? Db::all('SELECT id, series_index, starts_at, status FROM bookings WHERE series_token = ? ORDER BY series_index', [$b['series_token']]) : [],
            'tz' => $tz,
            'actions' => $actions,
            'canCancel' => in_array($status, ['pending', 'confirmed'], true),
            'canMove' => in_array($status, ['pending', 'confirmed'], true),
            'publicUrl' => abs_url('/reserva/' . $b['token']),
            'wa' => $b['guest_phone'] ? A1Support::waLink((string) $b['guest_phone'], $text) : '',
            'paymentsPanel' => is_file(APP_ROOT . '/app/Views/admin/payments/_booking_panel.php'),
        ], 'layouts/admin');
    }

    public function ics(Request $req, array $p): Response
    {
        $b = A1Support::booking((int) $p['id']);
        if (!class_exists('App\\Services\\IcsService') || !class_exists('App\\Services\\BookingService')) {
            $this->flash('warn', 'La descarga del calendario aún no está disponible.');
            return $this->redirect('/admin/citas/' . (int) $b['id']);
        }
        $disp = \App\Services\BookingService::display(\App\Services\BookingService::find((int) $b['id']) ?? $b);
        $ics = \App\Services\IcsService::generate($disp);
        return Response::download($ics, 'text/calendar', 'cita-' . (int) $b['id'] . '.ics');
    }

    // ------------------------------------------------------------------ Acciones

    private function done(Request $req, array $b, string $okMsg, array $extra = []): Response
    {
        if ($req->wantsJson()) {
            $fresh = Db::one('SELECT status, starts_at, ends_at FROM bookings WHERE id = ?', [$b['id']]) ?? [];
            return $this->json(array_merge(['ok' => true, 'message' => $okMsg, 'status' => $fresh['status'] ?? null, 'status_label' => A1Support::statusLabel((string) ($fresh['status'] ?? '')), 'start' => isset($fresh['starts_at']) ? Tz::iso((string) $fresh['starts_at']) : null, 'end' => isset($fresh['ends_at']) ? Tz::iso((string) $fresh['ends_at']) : null], $extra));
        }
        $this->flash('success', $okMsg);
        $back = A1Support::safeNext($req->str('volver', 300), '/admin/citas/' . (int) $b['id']);
        return $this->redirect($back);
    }

    private function problem(Request $req, array $b, string $msg, int $status = 422, array $extra = []): Response
    {
        if ($req->wantsJson()) {
            return $this->json(array_merge(['ok' => false, 'error' => $msg], $extra), $status);
        }
        $this->flash('error', $msg);
        return $this->redirect(A1Support::safeNext($req->str('volver', 300), '/admin/citas/' . (int) $b['id']));
    }

    private function ensureCore(Request $req, array $b): ?Response
    {
        if (!class_exists('App\\Services\\BookingService')) {
            return $this->problem($req, $b, 'El motor de reservas todavía no está disponible. Inténtalo de nuevo en unos minutos.', 503);
        }
        return null;
    }

    public function status(Request $req, array $p): Response
    {
        $b = A1Support::booking((int) $p['id']);
        if ($r = $this->ensureCore($req, $b)) {
            return $r;
        }
        $to = $req->str('status', 12);
        $from = (string) $b['status'];
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return $this->problem($req, $b, 'Ese cambio de estado no es posible para una cita ' . mb_strtolower(A1Support::statusLabel($from)) . '.');
        }
        if (in_array($to, ['completed', 'no_show'], true) && $from === 'confirmed' && Tz::ts((string) $b['starts_at']) > Clock::now()) {
            return $this->problem($req, $b, 'Aún no empieza esta cita; podrás marcarla como completada o como no asistida cuando llegue su hora.');
        }
        try {
            \App\Services\BookingService::setStatus((int) $b['id'], $to, A1Support::actor());
        } catch (BookingException $e) {
            return $this->problem($req, $b, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('Falló el cambio de estado de la cita ' . $b['id'], $e);
            return $this->problem($req, $b, 'No pudimos cambiar el estado por un problema interno. Ya quedó registrado.', 500);
        }
        Auth::audit('booking_status', 'booking', (int) $b['id'], A1Support::statusLabel($from) . ' → ' . A1Support::statusLabel($to));
        $msgs = ['confirmed' => 'Cita aprobada.', 'rejected' => 'Cita rechazada.', 'completed' => 'Cita marcada como completada.', 'no_show' => 'Marcada como «no asistió».'];
        return $this->done($req, $b, $msgs[$to] ?? 'Estado actualizado.');
    }

    public function move(Request $req, array $p): Response
    {
        $b = A1Support::booking((int) $p['id']);
        if ($r = $this->ensureCore($req, $b)) {
            return $r;
        }
        if (!in_array($b['status'], ['pending', 'confirmed'], true)) {
            return $this->problem($req, $b, 'Solo se pueden reprogramar citas pendientes o confirmadas.');
        }
        $tz = Settings::tz();
        $startUtc = '';
        $local = $req->str('start_local', 16);
        $utc = $req->str('start', 30);
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $local) && Validator::date(substr($local, 0, 10))) {
            $startUtc = Tz::localToUtc(substr($local, 0, 10) . ' ' . substr($local, 11, 5) . ':00', $tz);
        } elseif (Validator::datetimeUtc($utc)) {
            $startUtc = $utc;
        } elseif ($utc !== '') {
            $startUtc = (string) Tz::parseIso($utc);
        } else {
            $d = $req->str('fecha', 10);
            $h = $req->str('hora', 5);
            if (Validator::date($d) && Validator::time($h)) {
                $startUtc = Tz::localToUtc($d . ' ' . substr($h, 0, 5) . ':00', $tz);
            }
        }
        if ($startUtc === '') {
            return $this->problem($req, $b, 'Elige la nueva fecha y hora.');
        }
        $force = $req->bool('force');
        try {
            \App\Services\BookingService::reschedule((int) $b['id'], $startUtc, A1Support::actor(), ['force' => $force]);
        } catch (BookingException $e) {
            return $this->problem($req, $b, $e->getMessage(), 422, ['code' => $e->errorCode, 'can_force' => $e->errorCode === 'slot_unavailable' && !$force]);
        } catch (\Throwable $e) {
            Logger::error('Falló la reprogramación de la cita ' . $b['id'], $e);
            return $this->problem($req, $b, 'No pudimos reprogramar por un problema interno. Ya quedó registrado.', 500);
        }
        Auth::audit('booking_move', 'booking', (int) $b['id'], 'Reprogramada a ' . Tz::format($startUtc, $tz, 'd/m/Y H:i') . ($force ? ' (forzada)' : ''));
        return $this->done($req, $b, 'Cita reprogramada para el ' . Fmt::dateTime($startUtc, $tz) . '.');
    }

    public function cancel(Request $req, array $p): Response
    {
        $b = A1Support::booking((int) $p['id']);
        if ($r = $this->ensureCore($req, $b)) {
            return $r;
        }
        if (!in_array($b['status'], ['pending', 'confirmed'], true)) {
            return $this->problem($req, $b, 'Esta cita ya no se puede cancelar.');
        }
        $reason = $req->str('reason', 500);
        try {
            \App\Services\BookingService::cancel((int) $b['id'], $reason !== '' ? $reason : 'Cancelada por el negocio', A1Support::actor());
        } catch (BookingException $e) {
            return $this->problem($req, $b, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('Falló la cancelación de la cita ' . $b['id'], $e);
            return $this->problem($req, $b, 'No pudimos cancelar por un problema interno. Ya quedó registrado.', 500);
        }
        Auth::audit('booking_cancel', 'booking', (int) $b['id'], $reason !== '' ? $reason : 'Sin motivo');
        return $this->done($req, $b, 'Cita cancelada.');
    }

    public function note(Request $req, array $p): Response
    {
        $b = A1Support::booking((int) $p['id']);
        $note = Str::clean((string) ($req->post['internal_note'] ?? $req->json()['internal_note'] ?? ''), 2000);
        Db::update('bookings', ['internal_note' => $note !== '' ? $note : null, 'updated_at' => Clock::utc()], 'id = ?', [$b['id']]);
        if (class_exists('App\\Services\\BookingService')) {
            \App\Services\BookingService::log((int) $b['id'], 'note', 'Nota interna actualizada', (string) (Auth::user()['name'] ?? 'Panel'));
        }
        \App\Core\Cache::bumpAvailability();
        Auth::audit('booking_note', 'booking', (int) $b['id'], 'Nota interna actualizada');
        return $this->done($req, $b, 'Nota guardada.');
    }
}
