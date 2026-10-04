<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tz;
use App\Services\BookingService;

/** GET /api/v1/bookings, /bookings/{id}; POST /bookings y /bookings/{id}/cancel. */
final class BookingsController extends ApiController
{
    private const STATUSES = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show', 'rejected'];
    /** Campos del cuerpo de POST /bookings que la API acepta (el resto se ignora: nada de force, status ni created_by). */
    private const CREATE_TEXT = ['name' => 160, 'email' => 190, 'phone' => 40, 'nit' => 30, 'notes' => 2000, 'timezone' => 64, 'coupon' => 40, 'gift_code' => 40];

    protected function doIndex(Request $req, array $p): Response
    {
        [$limit, $offset] = $this->paging($req);
        $tz = $this->timezoneParam($req);
        $where = ['1 = 1'];
        $params = [];

        $status = $this->strParam($req, 'status', 100);
        if ($status !== '') {
            $list = array_values(array_filter(array_map('trim', explode(',', $status))));
            foreach ($list as $s) {
                if (!in_array($s, self::STATUSES, true)) {
                    throw new ApiError('El estado «' . $s . '» no es válido. Opciones: ' . implode(', ', self::STATUSES) . '.', 'bad_request', 400);
                }
            }
            $where[] = 'b.status IN (' . implode(',', array_fill(0, count($list), '?')) . ')';
            $params = array_merge($params, $list);
        }
        if ($this->strParam($req, 'from', 40) !== '') {
            $where[] = 'b.starts_at >= ?';
            $params[] = $this->instant($this->strParam($req, 'from', 40), $tz, 'from');
        }
        if ($this->strParam($req, 'to', 40) !== '') {
            $to = $this->strParam($req, 'to', 40);
            $where[] = 'b.starts_at < ?';
            // «to» con solo fecha incluye todo ese día
            $params[] = strlen($to) === 10 ? gmdate('Y-m-d H:i:s', Tz::ts($this->instant($to, $tz, 'to')) + 86400) : $this->instant($to, $tz, 'to');
        }
        foreach (['host_id' => 'b.host_id', 'event_id' => 'b.event_type_id', 'client_id' => 'b.client_id'] as $name => $col) {
            $v = $this->intParam($req, $name, 0, 0, 2147483647);
            if ($v > 0) {
                $where[] = $col . ' = ?';
                $params[] = $v;
            }
        }
        $order = $this->strParam($req, 'order', 4) === 'asc' ? 'ASC' : 'DESC';
        $w = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM bookings b WHERE ' . $w, $params);
        $rows = Db::all(
            'SELECT b.*, e.name AS event_name, e.slug AS event_slug, h.name AS host_name
             FROM bookings b JOIN event_types e ON e.id = b.event_type_id JOIN hosts h ON h.id = b.host_id
             WHERE ' . $w . ' ORDER BY b.starts_at ' . $order . ', b.id ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        return $this->paged(array_map(fn (array $b): array => $this->present($b), $rows), $total, $limit, $offset);
    }

    protected function doShow(Request $req, array $p): Response
    {
        return $this->ok($this->detail($this->find((int) $p['id'])));
    }

    protected function doCreate(Request $req, array $p): Response
    {
        $body = $this->jsonBody($req);
        $eventId = $this->bodyInt($body, 'event_id');
        if ($eventId === null || $eventId < 1) {
            throw new ApiError('Indica el «event_id» del evento que quieres reservar.', 'validation', 422);
        }
        $start = $this->bodyStr($body, 'start', 40);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?(\.\d{1,6})?(Z|[+-]\d{2}:?\d{2})$/', $start)) {
            throw new ApiError('«start» debe ser una hora ISO 8601 con zona, por ejemplo 2026-10-05T14:30:00-06:00 o 2026-10-05T20:30:00Z.', 'validation', 422);
        }
        $in = ['event_id' => $eventId, 'start' => gmdate('Y-m-d H:i:s', (new \DateTimeImmutable($start))->getTimestamp()), 'created_via' => 'api'];
        foreach (self::CREATE_TEXT as $k => $max) {
            $v = $this->bodyStr($body, $k, $max);
            if ($v !== '') {
                $in[$k] = $v;
            }
        }
        foreach (['duration', 'host_id'] as $k) {
            $v = $this->bodyInt($body, $k);
            if ($v !== null) {
                $in[$k] = $v;
            }
        }
        $in['answers'] = $this->answers($body['answers'] ?? []);
        $in['guests'] = $this->guests($body['guests'] ?? []);

        $res = BookingService::create($in);
        $booking = $this->detail((array) BookingService::find((int) $res['booking']['id']));
        return $this->ok($booking, ['needs_payment' => (bool) $res['needs_payment'], 'bookings_created' => count($res['bookings'])], 201);
    }

    protected function doCancel(Request $req, array $p): Response
    {
        $b = $this->find((int) $p['id']);
        $reason = '';
        if (trim((string) file_get_contents('php://input')) !== '') {
            $reason = $this->bodyStr($this->jsonBody($req), 'reason', 500);
        }
        BookingService::cancel((int) $b['id'], $reason, ['type' => 'api', 'label' => 'API: ' . (string) ($req->server['__api_key']['name'] ?? 'clave'), 'user_id' => null]);
        return $this->ok($this->detail($this->find((int) $b['id'])));
    }

    private function find(int $id): array
    {
        $b = BookingService::find($id);
        if ($b === null) {
            throw new ApiError('No encontramos esa cita.', 'not_found', 404);
        }
        return $b;
    }

    /** Respuestas {id_de_pregunta: valor} (valores de texto o lista de textos). */
    private function answers($raw): array
    {
        if (!is_array($raw)) {
            throw new ApiError('«answers» debe ser un objeto con el id de cada pregunta y su respuesta.', 'validation', 422);
        }
        $out = [];
        foreach ($raw as $id => $v) {
            if (!preg_match('/^\d{1,9}$/', (string) $id) || (!is_scalar($v) && !(is_array($v) && !array_filter($v, static fn ($x) => !is_scalar($x))))) {
                throw new ApiError('Una de las respuestas de «answers» no tiene el formato esperado.', 'validation', 422);
            }
            $out[(int) $id] = $v;
        }
        return $out;
    }

    private function guests($raw): array
    {
        if (!is_array($raw) || count($raw) > 50) {
            throw new ApiError('«guests» debe ser una lista de hasta 50 personas con «name» y «email».', 'validation', 422);
        }
        $out = [];
        foreach ($raw as $g) {
            if (!is_array($g)) {
                throw new ApiError('Cada elemento de «guests» debe ser un objeto con «name» y «email».', 'validation', 422);
            }
            $out[] = ['name' => $this->bodyStr($g, 'name', 160), 'email' => $this->bodyStr($g, 'email', 190)];
        }
        return $out;
    }

    /** Cita para listados (sin respuestas ni acompañantes). Nunca incluye token, notas internas ni datos de pago sensibles. */
    private function present(array $b): array
    {
        return [
            'id' => (int) $b['id'],
            'status' => $b['status'],
            'event' => ['id' => (int) $b['event_type_id'], 'name' => $b['event_name'] ?? null, 'slug' => $b['event_slug'] ?? null],
            'host' => ['id' => (int) $b['host_id'], 'name' => $b['host_name'] ?? null],
            'starts_at' => self::iso($b['starts_at']),
            'ends_at' => self::iso($b['ends_at']),
            'duration_minutes' => (int) $b['duration'],
            'mode' => $b['mode'],
            'location' => $b['location'],
            'video_url' => $b['video_url'],
            'seats' => (int) $b['seats'],
            'guest' => ['name' => $b['guest_name'], 'email' => $b['guest_email'], 'phone' => $b['guest_phone'], 'timezone' => $b['guest_timezone'], 'client_id' => $b['client_id'] === null ? null : (int) $b['client_id']],
            'notes' => $b['notes'],
            'payment' => [
                'currency' => 'GTQ', 'price' => (float) $b['price'], 'discount' => (float) $b['discount'], 'total' => (float) $b['total'],
                'deposit_due' => (float) $b['deposit_due'], 'paid_amount' => (float) $b['paid_amount'], 'status' => $b['payment_status'],
            ],
            'cancellation' => $b['status'] === 'cancelled' ? ['reason' => $b['cancel_reason'], 'by' => $b['cancelled_by'], 'at' => self::iso($b['cancelled_at'])] : null,
            'created_via' => $b['created_via'],
            'created_at' => self::iso($b['created_at']),
            'updated_at' => self::iso($b['updated_at']),
        ];
    }

    /** Cita con anfitriones, respuestas y acompañantes. */
    private function detail(array $b): array
    {
        $id = (int) $b['id'];
        $b['event_name'] = Db::val('SELECT name FROM event_types WHERE id = ?', [$b['event_type_id']]);
        $b['event_slug'] = Db::val('SELECT slug FROM event_types WHERE id = ?', [$b['event_type_id']]);
        $b['host_name'] = Db::val('SELECT name FROM hosts WHERE id = ?', [$b['host_id']]);
        $out = $this->present($b);
        $out['hosts'] = array_map(static fn (array $h): array => ['id' => (int) $h['id'], 'name' => $h['name']], Db::all('SELECT h.id, h.name FROM booking_hosts bh JOIN hosts h ON h.id = bh.host_id WHERE bh.booking_id = ? ORDER BY h.id', [$id]));
        $out['answers'] = array_map(static fn (array $a): array => ['question' => $a['label'], 'answer' => $a['value']], Db::all('SELECT label, value FROM booking_answers WHERE booking_id = ? AND file_id IS NULL ORDER BY id', [$id]));
        $out['attendees'] = array_map(static fn (array $a): array => ['name' => $a['name'], 'email' => $a['email']], Db::all('SELECT name, email FROM booking_attendees WHERE booking_id = ? ORDER BY id', [$id]));
        return $out;
    }
}
