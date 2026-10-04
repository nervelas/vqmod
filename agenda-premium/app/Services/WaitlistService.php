<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Lista de espera con ofertas de horario por tiempo limitado (un horario liberado se ofrece a una sola persona a la vez). */
final class WaitlistService
{
    /**
     * guest: name, email, phone, timezone. Debe haber correo o teléfono.
     * @throws \InvalidArgumentException
     */
    public static function join(array $event, int $duration, array $guest, ?string $wantDate, ?int $hostId): int
    {
        $name = Str::clean((string) ($guest['name'] ?? ''), 160);
        $email = strtolower(trim((string) ($guest['email'] ?? '')));
        $phone = Str::phone((string) ($guest['phone'] ?? ''), (string) Settings::get('phone_cc', '502'));
        if ($name === '') {
            throw new \InvalidArgumentException('Escribe tu nombre para anotarte en la lista de espera.');
        }
        if ($email !== '' && !Validator::email($email)) {
            throw new \InvalidArgumentException('El correo no parece válido. Revísalo, por favor.');
        }
        if ($email === '' && $phone === null) {
            throw new \InvalidArgumentException('Déjanos un correo o un teléfono para avisarte cuando se libere un horario.');
        }
        if ($wantDate !== null && $wantDate !== '' && !Validator::date($wantDate)) {
            throw new \InvalidArgumentException('La fecha que deseas no es válida.');
        }
        if ($duration < 5 || $duration > 1440) {
            throw new \InvalidArgumentException('La duración elegida no es válida.');
        }
        $eventId = (int) $event['id'];
        $tz = Tz::safe((string) ($guest['timezone'] ?? ''), Settings::tz());
        return (int) Db::tx(static function () use ($eventId, $hostId, $name, $email, $phone, $tz, $duration, $wantDate): int {
            Db::val('SELECT id FROM event_types WHERE id = ? FOR UPDATE', [$eventId]);
            $dup = Db::val(
                "SELECT id FROM waitlist WHERE event_type_id = ? AND status IN ('waiting','offered') AND ((? <> '' AND email = ?) OR (? IS NOT NULL AND phone = ?)) LIMIT 1",
                [$eventId, $email, $email, $phone, $phone]
            );
            if ($dup !== null) {
                throw new \InvalidArgumentException('Ya estás en la lista de espera de esta cita. Te avisaremos apenas se libere un horario.');
            }
            return Db::insert('waitlist', [
                'event_type_id' => $eventId,
                'host_id' => $hostId,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone,
                'timezone' => $tz,
                'duration' => $duration,
                'want_date' => ($wantDate !== null && $wantDate !== '') ? $wantDate : null,
                'status' => 'waiting',
                'created_at' => Clock::utc(),
            ]);
        });
    }

    /** Se liberó el horario de $booking (cancelada/reprogramada): ofrece ese horario al siguiente en la fila. */
    public static function onSlotFreed(array $booking): void
    {
        try {
            self::offerSlot((int) $booking['event_type_id'], (int) $booking['host_id'], (string) $booking['starts_at'], (int) $booking['duration']);
        } catch (\Throwable $e) {
            Logger::error('Lista de espera: no se pudo ofrecer el horario liberado', $e);
        }
    }

    /** Oferta vigente: la fila de espera + 'event', 'start', 'host_id', 'seconds_left'. Null si no existe o venció. */
    public static function offerByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $w = Db::one("SELECT * FROM waitlist WHERE offer_token = ? AND status = 'offered' AND offer_expires_at > ?", [$token, Clock::utc()]);
        if ($w === null) {
            return null;
        }
        $w['event'] = EventRepository::find((int) $w['event_type_id']);
        $w['start'] = $w['offer_starts_at'];
        $w['host_id'] = $w['offer_host_id'];
        $w['seconds_left'] = max(0, Tz::ts((string) $w['offer_expires_at']) - Clock::now());
        return $w;
    }

    /** La persona reservó con su oferta. */
    public static function markBooked(string $token, int $bookingId): void
    {
        Db::exec("UPDATE waitlist SET status = 'booked', booking_id = ? WHERE offer_token = ? AND status = 'offered'", [$bookingId, $token]);
    }

    /** Rechazo o cancelación (desde el panel o desde el enlace de la oferta); si tenía una oferta, el horario pasa al siguiente. */
    public static function cancel(int $id): void
    {
        $w = Db::one('SELECT * FROM waitlist WHERE id = ?', [$id]);
        if ($w === null || Db::exec("UPDATE waitlist SET status = 'cancelled' WHERE id = ? AND status IN ('waiting','offered')", [$id]) !== 1) {
            return;
        }
        if ($w['status'] === 'offered') {
            self::passOn($w);
        }
    }

    public static function cancelByToken(string $token): bool
    {
        $id = preg_match('/^[a-f0-9]{32}$/', $token) ? Db::val("SELECT id FROM waitlist WHERE offer_token = ? AND status = 'offered'", [$token]) : null;
        if ($id === null) {
            return false;
        }
        self::cancel((int) $id);
        return true;
    }

    /** Para el cron: vence ofertas y deseos de fecha pasada, y ofrece al siguiente. @return array ['expired'=>n,'offered'=>n] */
    public static function tick(): array
    {
        $now = Clock::utc();
        $expired = 0;
        $offered = 0;
        $rows = Db::all("SELECT * FROM waitlist WHERE status = 'offered' AND offer_expires_at <= ? ORDER BY offer_expires_at, id LIMIT 100", [$now]);
        foreach ($rows as $w) {
            if (Db::exec("UPDATE waitlist SET status = 'expired' WHERE id = ? AND status = 'offered'", [(int) $w['id']]) !== 1) {
                continue;
            }
            $expired++;
            try {
                $offered += self::passOn($w) ? 1 : 0;
            } catch (\Throwable $e) {
                Logger::error('Lista de espera: falló al pasar la oferta al siguiente', $e);
            }
        }
        $today = Tz::formatTs(Clock::now(), Settings::tz(), 'Y-m-d');
        $expired += Db::exec("UPDATE waitlist SET status = 'expired' WHERE status = 'waiting' AND want_date IS NOT NULL AND want_date < ?", [$today]);
        return ['expired' => $expired, 'offered' => $offered];
    }

    /** Pasa el horario de una oferta que terminó al siguiente (si aún es futuro). */
    private static function passOn(array $w): bool
    {
        if ($w['offer_starts_at'] === null || Tz::ts((string) $w['offer_starts_at']) <= Clock::now()) {
            return false;
        }
        return self::offerSlot((int) $w['event_type_id'], (int) $w['offer_host_id'], (string) $w['offer_starts_at'], (int) $w['duration']);
    }

    /** Ofrece un horario concreto al primer 'waiting' compatible, de forma atómica. Devuelve true si hubo oferta. */
    private static function offerSlot(int $eventId, int $hostId, string $startUtc, int $duration): bool
    {
        $event = EventRepository::find($eventId);
        if ($event === null || Tz::ts($startUtc) <= Clock::now()) {
            return false;
        }
        $minutes = max(1, Settings::int('waitlist_offer_minutes', 15));
        $offer = null;
        Db::tx(static function () use ($event, $eventId, $hostId, $startUtc, $duration, $minutes, &$offer): void {
            // Serializa las ofertas del mismo evento: dos procesos no pueden tomar el mismo horario.
            Db::val('SELECT id FROM event_types WHERE id = ? FOR UPDATE', [$eventId]);
            $taken = Db::val(
                "SELECT id FROM waitlist WHERE event_type_id = ? AND status = 'offered' AND offer_starts_at = ? AND offer_expires_at > ? LIMIT 1",
                [$eventId, $startUtc, Clock::utc()]
            );
            if ($taken !== null) {
                return;
            }
            $end = Tz::fromTs(Tz::ts($startUtc) + $duration * 60);
            $free = false;
            $slotHost = $hostId;
            foreach (AvailabilityService::slots($event, $duration, $startUtc, $end, ['host_id' => $hostId, 'nocache' => true]) as $s) {
                if ($s['start'] === $startUtc) {
                    $free = true;
                    $slotHost = in_array($hostId, array_map('intval', $s['host_ids'] ?? []), true) ? $hostId : (int) ($s['host_ids'][0] ?? $hostId);
                    break;
                }
            }
            if (!$free) {
                return;
            }
            $cands = Db::all(
                "SELECT * FROM waitlist WHERE event_type_id = ? AND status = 'waiting' AND duration = ? AND (host_id IS NULL OR host_id = ?) ORDER BY created_at, id LIMIT 50",
                [$eventId, $duration, $slotHost]
            );
            foreach ($cands as $w) {
                if ($w['want_date'] !== null && Tz::format($startUtc, Tz::safe((string) $w['timezone']), 'Y-m-d') !== (string) $w['want_date']) {
                    continue;
                }
                $token = Str::token();
                $exp = Tz::fromTs(Clock::now() + $minutes * 60);
                $n = Db::exec(
                    "UPDATE waitlist SET status = 'offered', offer_token = ?, offer_starts_at = ?, offer_host_id = ?, offer_expires_at = ? WHERE id = ? AND status = 'waiting'",
                    [$token, $startUtc, $slotHost, $exp, (int) $w['id']]
                );
                if ($n === 1) {
                    $offer = ['w' => $w, 'token' => $token, 'minutes' => $minutes];
                    return;
                }
            }
        });
        if ($offer === null) {
            return false;
        }
        self::notify($offer['w'], $event, $startUtc, $offer['token'], $offer['minutes']);
        return true;
    }

    /** Correo y mensaje de WhatsApp con el enlace de la oferta. Un fallo de envío no deshace la oferta. */
    private static function notify(array $w, array $event, string $startUtc, string $token, int $minutes): void
    {
        $tz = Tz::safe((string) $w['timezone']);
        $when = Fmt::dateTime($startUtc, $tz);
        $link = abs_url('/espera/' . $token);
        $biz = (string) Settings::get('business_name', '');
        $text = 'Hola ' . $w['name'] . ', se liberó un horario para «' . $event['name'] . '»: ' . $when . ' (' . Fmt::tzLabel($tz, $startUtc) . '). '
            . 'Resérvalo en los próximos ' . $minutes . ' minutos aquí: ' . $link;
        if (!empty($w['email'])) {
            try {
                $html = '<p>Hola ' . e($w['name']) . ',</p>'
                    . '<p>Se liberó un horario para <strong>' . e($event['name']) . '</strong>:</p>'
                    . '<p style="font-size:18px"><strong>' . e($when) . '</strong><br>' . e(Fmt::tzLabel($tz, $startUtc)) . '</p>'
                    . '<p>Tienes <strong>' . $minutes . ' minutos</strong> para reservarlo antes de que se ofrezca a la siguiente persona en la lista.</p>'
                    . '<p><a href="' . e($link) . '">Reservar este horario</a></p>'
                    . '<p>Si ya no lo necesitas, no tienes que hacer nada.</p>';
                Mailer::queue((string) $w['email'], (string) $w['name'], 'Se liberó un horario en ' . $biz, Mailer::layout('Se liberó un horario', $html), $text);
            } catch (\Throwable $e) {
                Logger::error('Lista de espera: no se pudo encolar el correo de la oferta', $e);
            }
        }
        if (!empty($w['phone'])) {
            try {
                Db::insert('message_queue', [
                    'client_id' => null,
                    'channel' => 'whatsapp',
                    'phone' => (string) $w['phone'],
                    'body' => $text,
                    'status' => 'pending',
                    'due_at' => Clock::utc(),
                    'created_at' => Clock::utc(),
                ]);
            } catch (\Throwable $e) {
                Logger::error('Lista de espera: no se pudo encolar el mensaje de WhatsApp', $e);
            }
        }
    }
}
