<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Services\AvailabilityService;
use App\Services\EventRepository;
use App\Services\WaitlistService;

/** Lista de espera: entradas, ofertas vigentes, oferta manual y cancelación. */
final class WaitlistController extends A3Controller
{
    private const STATUSES = ['waiting' => 'En espera', 'offered' => 'Con oferta', 'booked' => 'Reservó', 'expired' => 'Venció', 'cancelled' => 'Cancelada'];

    public function index(Request $req, array $p): Response
    {
        $eventId = $req->int('evento');
        $status = $req->str('estado', 10);
        $where = ['1=1'];
        $args = [];
        $scope = $this->scope();
        if ($scope !== null) {
            $where[] = '(w.host_id = ? OR w.host_id IS NULL)';
            $args[] = $scope;
        }
        if ($eventId > 0) {
            $where[] = 'w.event_type_id = ?';
            $args[] = $eventId;
        }
        if (isset(self::STATUSES[$status])) {
            $where[] = 'w.status = ?';
            $args[] = $status;
        } else {
            $status = '';
            $where[] = "(w.status IN ('waiting','offered') OR w.created_at >= ?)";
            $args[] = Clock::utc(Clock::now() - 14 * 86400);
        }
        $rows = Db::all(
            'SELECT w.*, e.name AS event_name, h.name AS host_name, oh.name AS offer_host_name
             FROM waitlist w JOIN event_types e ON e.id = w.event_type_id LEFT JOIN hosts h ON h.id = w.host_id LEFT JOIN hosts oh ON oh.id = w.offer_host_id
             WHERE ' . implode(' AND ', $where) . " ORDER BY FIELD(w.status,'offered','waiting','booked','expired','cancelled'), w.created_at, w.id LIMIT 300",
            $args
        );
        $now = Clock::utc();
        $offers = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'offered' && $r['offer_expires_at'] > $now));
        $counts = [];
        foreach (Db::all('SELECT status, COUNT(*) c FROM waitlist GROUP BY status') as $c) {
            $counts[$c['status']] = (int) $c['c'];
        }
        return $this->page('admin/waitlist/index', ['rows' => $rows, 'offers' => $offers, 'events' => $this->events(), 'eventId' => $eventId, 'status' => $status, 'statuses' => self::STATUSES, 'counts' => $counts, 'now' => $now], '/admin/espera', 'Lista de espera');
    }

    public function offerForm(Request $req, array $p): Response
    {
        $w = $this->entry((int) $p['id']);
        if ($w['status'] !== 'waiting') {
            $this->flash('warn', 'Solo se puede ofrecer un horario a quien sigue en espera.');
            return $this->redirect('/admin/espera');
        }
        return $this->page('admin/waitlist/offer', ['w' => $w, 'slots' => $this->slotsFor($w), 'minutes' => max(1, Settings::int('waitlist_offer_minutes', 15))], '/admin/espera', 'Ofrecer un horario');
    }

    public function offer(Request $req, array $p): Response
    {
        $w = $this->entry((int) $p['id']);
        $back = '/admin/espera/' . (int) $w['id'] . '/ofrecer';
        if ($w['status'] !== 'waiting') {
            return $this->fail($req, 'Esta persona ya no está en espera.', '/admin/espera');
        }
        $parts = explode('|', $req->str('slot', 40));
        $start = $parts[0] ?? '';
        $hostId = (int) ($parts[1] ?? 0);
        $chosen = null;
        foreach ($this->slotsFor($w) as $s) {
            if ($s['start'] === $start && in_array($hostId, array_map('intval', $s['host_ids']), true)) {
                $chosen = $s;
                break;
            }
        }
        if ($chosen === null) {
            return $this->fail($req, 'Ese horario ya no está disponible. Elige otro de la lista.', $back);
        }
        $event = EventRepository::find((int) $w['event_type_id']);
        $minutes = max(1, Settings::int('waitlist_offer_minutes', 15));
        $token = Str::token();
        $taken = false;
        Db::tx(function () use ($w, $start, $hostId, $token, $minutes, &$taken): void {
            Db::val('SELECT id FROM event_types WHERE id = ? FOR UPDATE', [(int) $w['event_type_id']]);
            if (Db::val("SELECT id FROM waitlist WHERE event_type_id = ? AND status = 'offered' AND offer_starts_at = ? AND offer_expires_at > ?", [(int) $w['event_type_id'], $start, Clock::utc()]) !== null) {
                $taken = true;
                return;
            }
            $n = Db::exec("UPDATE waitlist SET status = 'offered', offer_token = ?, offer_starts_at = ?, offer_host_id = ?, offer_expires_at = ? WHERE id = ? AND status = 'waiting'", [$token, $start, $hostId, Tz::fromTs(Clock::now() + $minutes * 60), (int) $w['id']]);
            $taken = $n !== 1;
        });
        if ($taken) {
            return $this->fail($req, 'Ese horario ya fue ofrecido a otra persona. Elige otro.', $back);
        }
        $this->notify($w, (string) ($event['name'] ?? 'tu cita'), $start, $token, $minutes);
        $this->audit('waitlist.offer', 'waitlist', $w['id'], $start);
        $this->flash('success', 'Oferta enviada. ' . $w['name'] . ' tiene ' . $minutes . ' minutos para reservar. Si tiene teléfono, el mensaje de WhatsApp quedó listo en "Mensajes de hoy".');
        return $this->redirect('/admin/espera');
    }

    public function cancel(Request $req, array $p): Response
    {
        $w = $this->entry((int) $p['id']);
        WaitlistService::cancel((int) $w['id']);
        $this->audit('waitlist.cancel', 'waitlist', $w['id']);
        $this->flash('success', 'La entrada se canceló.');
        return $this->redirect('/admin/espera');
    }

    private function entry(int $id): array
    {
        $w = Db::one('SELECT w.*, e.name AS event_name FROM waitlist w JOIN event_types e ON e.id = w.event_type_id WHERE w.id = ?', [$id]);
        $scope = $this->scope();
        if (!$w || ($scope !== null && $w['host_id'] !== null && (int) $w['host_id'] !== $scope)) {
            throw new HttpException(404);
        }
        return $w;
    }

    /** Próximos horarios libres compatibles con la entrada (hasta 12). */
    private function slotsFor(array $w): array
    {
        $event = EventRepository::find((int) $w['event_type_id']);
        if (!$event) {
            return [];
        }
        $from = Tz::fromTs(Clock::now());
        $to = Tz::fromTs(Clock::now() + 21 * 86400);
        $opts = ['nocache' => true];
        if ($w['host_id'] !== null) {
            $opts['host_id'] = (int) $w['host_id'];
        }
        $out = [];
        try {
            foreach (AvailabilityService::slots($event, (int) $w['duration'], $from, $to, $opts) as $s) {
                if ($w['want_date'] !== null && Tz::format($s['start'], Tz::safe((string) $w['timezone']), 'Y-m-d') !== (string) $w['want_date']) {
                    continue;
                }
                $names = [];
                foreach ($s['host_ids'] as $hid) {
                    $names[] = (string) (Db::val('SELECT name FROM hosts WHERE id = ?', [(int) $hid]) ?? '');
                }
                $s['host_names'] = implode(', ', array_filter($names));
                $out[] = $s;
                if (count($out) >= 12) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Logger::error('Lista de espera: no se pudieron calcular horarios', $e);
        }
        return $out;
    }

    private function notify(array $w, string $eventName, string $startUtc, string $token, int $minutes): void
    {
        $tz = Tz::safe((string) $w['timezone']);
        $when = Fmt::dateTime($startUtc, $tz);
        $link = abs_url('/espera/' . $token);
        $text = 'Hola ' . $w['name'] . ', se liberó un horario para «' . $eventName . '»: ' . $when . ' (' . Fmt::tzLabel($tz, $startUtc) . '). Resérvalo en los próximos ' . $minutes . ' minutos aquí: ' . $link;
        try {
            if (!empty($w['email']) && class_exists('App\\Services\\Mailer')) {
                $html = '<p>Hola ' . e($w['name']) . ',</p><p>Se liberó un horario para <strong>' . e($eventName) . '</strong>:</p><p><strong>' . e($when) . '</strong></p><p>Tienes <strong>' . $minutes . ' minutos</strong> para reservarlo.</p><p><a href="' . e($link) . '">Reservar este horario</a></p>';
                \App\Services\Mailer::queue((string) $w['email'], (string) $w['name'], 'Se liberó un horario en ' . (string) Settings::get('business_name', ''), \App\Services\Mailer::layout('Se liberó un horario', $html), $text);
            }
            if (!empty($w['phone'])) {
                Db::insert('message_queue', ['channel' => 'whatsapp', 'phone' => (string) $w['phone'], 'body' => $text, 'status' => 'pending', 'due_at' => Clock::utc(), 'created_at' => Clock::utc()]);
            }
        } catch (\Throwable $e) {
            Logger::error('Lista de espera: aviso de oferta manual', $e);
        }
    }
}
