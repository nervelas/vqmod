<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tz;
use App\Services\AvailabilityService;
use App\Services\EventRepository;

/** GET /api/v1/events, /events/{id} y /availability. */
final class EventsController extends ApiController
{
    private const MAX_RANGE_DAYS = 31;
    private const MAX_SLOTS = 2000;

    protected function doIndex(Request $req, array $p): Response
    {
        [$limit, $offset] = $this->paging($req);
        $where = ['single_use = 0'];
        $params = [];
        if ($req->get('include_inactive') !== '1') {
            $where[] = 'active = 1';
        }
        foreach (['kind' => EventRepository::KINDS, 'mode' => EventRepository::MODES] as $col => $allowed) {
            $v = $this->strParam($req, $col, 20);
            if ($v !== '') {
                if (!in_array($v, $allowed, true)) {
                    throw new ApiError('El filtro «' . $col . '» debe ser uno de: ' . implode(', ', $allowed) . '.', 'bad_request', 400);
                }
                $where[] = $col . ' = ?';
                $params[] = $v;
            }
        }
        $w = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM event_types WHERE ' . $w, $params);
        $rows = Db::all('SELECT id FROM event_types WHERE ' . $w . ' ORDER BY sort_order, id LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
        $items = [];
        foreach ($rows as $r) {
            $items[] = $this->present((array) EventRepository::find((int) $r['id']));
        }
        return $this->paged($items, $total, $limit, $offset);
    }

    protected function doShow(Request $req, array $p): Response
    {
        return $this->ok($this->present($this->find((int) $p['id'])));
    }

    protected function doAvailability(Request $req, array $p): Response
    {
        $id = $this->intParam($req, 'event_id', 0, 0, 2147483647);
        if ($id === 0) {
            throw new ApiError('Falta el parámetro «event_id».', 'bad_request', 400);
        }
        $event = $this->find($id);
        $reason = EventRepository::unavailableReason($event);
        if ($reason !== null) {
            throw new ApiError($reason, 'closed', 409);
        }
        $duration = $this->intParam($req, 'duration', (int) $event['default_duration'], 1, 1440);
        if (!in_array($duration, $event['duration_list'], true)) {
            throw new ApiError('La duración «' . $duration . '» no está disponible. Opciones: ' . implode(', ', $event['duration_list']) . ' minutos.', 'bad_request', 400);
        }
        $tz = $this->timezoneParam($req);
        $from = $this->strParam($req, 'from', 40) !== '' ? $this->instant($this->strParam($req, 'from', 40), $tz, 'from') : Clock::utc();
        $toRaw = $this->strParam($req, 'to', 40);
        $to = $toRaw !== '' ? $this->instant($toRaw, $tz, 'to') : Clock::utc(Tz::ts($from) + 7 * 86400);
        if (Tz::ts($to) <= Tz::ts($from)) {
            throw new ApiError('«to» debe ser posterior a «from».', 'bad_request', 400);
        }
        if (Tz::ts($to) - Tz::ts($from) > self::MAX_RANGE_DAYS * 86400) {
            throw new ApiError('El rango máximo es de ' . self::MAX_RANGE_DAYS . ' días por consulta.', 'bad_request', 400);
        }
        $opts = [];
        $hostId = $this->intParam($req, 'host_id', 0, 0, 2147483647);
        if ($hostId > 0) {
            $opts['host_id'] = $hostId;
        }
        $zone = new \DateTimeZone($tz);
        $slots = [];
        foreach (array_slice(AvailabilityService::slots($event, $duration, $from, $to, $opts), 0, self::MAX_SLOTS) as $s) {
            $slots[] = [
                'start' => (new \DateTimeImmutable($s['start'] . ' UTC'))->setTimezone($zone)->format('c'),
                'end' => (new \DateTimeImmutable($s['end'] . ' UTC'))->setTimezone($zone)->format('c'),
                'start_utc' => Tz::iso($s['start']),
                'end_utc' => Tz::iso($s['end']),
                'host_ids' => array_map('intval', $s['host_ids']),
                'seats_left' => $s['seats_left'] === null ? null : (int) $s['seats_left'],
            ];
        }
        return $this->ok($slots, ['event_id' => $id, 'duration' => $duration, 'timezone' => $tz, 'from' => Tz::iso($from), 'to' => Tz::iso($to), 'count' => count($slots)]);
    }

    private function find(int $id): array
    {
        $event = EventRepository::find($id);
        if ($event === null || (int) $event['single_use'] === 1) {
            throw new ApiError('No encontramos ese evento.', 'not_found', 404);
        }
        return $event;
    }

    /** Evento sin datos internos (horarios, equipos, enlaces únicos ni de videollamada). */
    private function present(array $e): array
    {
        return [
            'id' => (int) $e['id'],
            'slug' => $e['slug'],
            'name' => $e['name'],
            'description' => $e['description'],
            'kind' => $e['kind'],
            'mode' => $e['mode'],
            'location' => $e['location'],
            'color' => $e['color'],
            'active' => self::bool($e['active']),
            'visibility' => $e['visibility'],
            'durations' => $e['duration_list'],
            'default_duration' => (int) $e['default_duration'],
            'price' => (float) $e['price'],
            'currency' => 'GTQ',
            'deposit' => ['type' => $e['deposit_type'], 'value' => (float) $e['deposit_value']],
            'requires_approval' => self::bool($e['approval']),
            'capacity' => (int) $e['capacity'],
            'min_notice_minutes' => (int) $e['min_notice_minutes'],
            'max_advance_days' => (int) $e['max_advance_days'],
            'cancel_hours' => (int) $e['cancel_hours'],
            'cancel_policy' => $e['cancel_policy_text'],
            'allow_guests' => self::bool($e['allow_guests']),
            'max_guests' => (int) $e['max_guests'],
            'booking_url' => abs_url('/e/' . $e['slug']),
            'hosts' => array_map(static fn (array $h): array => ['id' => (int) $h['id'], 'name' => $h['name'], 'slug' => $h['slug'], 'title' => $h['title']], $e['hosts']),
            'questions' => array_map(static fn (array $f): array => [
                'id' => (int) $f['id'], 'name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'],
                'options' => $f['options'] === null || $f['options'] === '' ? [] : array_values(array_filter(array_map('trim', preg_split('/\R|,/', (string) $f['options']) ?: []))),
                'required' => self::bool($f['required']), 'help' => $f['help'],
                'show_if' => $f['condition_field'] === null ? null : ['question' => $f['condition_field'], 'equals' => $f['condition_value']],
            ], $e['fields']),
        ];
    }
}
