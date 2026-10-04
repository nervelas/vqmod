<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;
use App\Repositories\BookingRepository;

/**
 * Reservas: creación atómica (anti doble reserva), reprogramación, cancelación y cambios de estado.
 * La disponibilidad se REVALIDA dentro de una transacción con bloqueo de filas de anfitriones y recursos.
 */
final class BookingService
{
    private const ACTIVE = ['pending', 'confirmed'];

    // ------------------------------------------------------------------ crear

    public static function create(array $in): array
    {
        $via = (string) ($in['created_via'] ?? 'public');
        if (!in_array($via, ['public', 'admin', 'api', 'waitlist', 'poll'], true)) {
            $via = 'public';
        }
        $privileged = in_array($via, ['admin', 'api', 'poll'], true);
        $force = $privileged && !empty($in['force']);

        $event = self::loadEvent((int) ($in['event_id'] ?? 0));
        if ($force) {
            $event['active'] = 1; // la administración puede reservar servicios pausados
        }
        $reason = EventRepository::unavailableReason($event);
        if ($reason !== null) {
            throw new BookingException($reason, 'closed');
        }

        $duration = (int) ($in['duration'] ?? $event['default_duration']);
        if (!in_array($duration, $event['duration_list'], true) && !$force) {
            throw new BookingException('Esa duración no está disponible para este servicio.', 'validation');
        }
        if ($duration < 5 || $duration > 720) {
            throw new BookingException('La duración no es válida.', 'validation');
        }
        $startUtc = (string) ($in['start'] ?? '');
        if (!Validator::datetimeUtc($startUtc)) {
            throw new BookingException('Elige un horario para tu cita.', 'validation');
        }
        $startTs = Tz::ts($startUtc);
        $guestTz = Tz::safe((string) ($in['timezone'] ?? ''), Settings::tz());

        // ---- datos del invitado
        $name = Str::clean((string) ($in['name'] ?? ''), 160);
        if ($name === '' || mb_strlen($name) < 2) {
            throw new BookingException('Escribe tu nombre completo.', 'validation');
        }
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $phoneRaw = trim((string) ($in['phone'] ?? ''));
        $phone = $phoneRaw !== '' ? Str::phone($phoneRaw, (string) Settings::get('phone_cc', '502')) : null;
        if ($phoneRaw !== '' && $phone === null) {
            throw new BookingException('Revisa tu número de teléfono: deben ser 8 dígitos (+502) o un número internacional completo.', 'validation');
        }
        if (!$privileged) {
            if (!Validator::email($email)) {
                throw new BookingException('Escribe un correo electrónico válido para enviarte la confirmación.', 'validation');
            }
            if ((int) $event['require_phone'] === 1 && $phone === null) {
                throw new BookingException('Escribe tu número de teléfono (8 dígitos) para poder avisarte por WhatsApp.', 'validation');
            }
            if (empty($in['consent'])) {
                throw new BookingException('Para reservar necesitamos tu consentimiento sobre el uso de tus datos.', 'validation');
            }
        } else {
            if ($email !== '' && !Validator::email($email)) {
                throw new BookingException('El correo electrónico no es válido.', 'validation');
            }
            if ($email === '' && $phone === null) {
                throw new BookingException('Indica al menos un correo o un teléfono de contacto.', 'validation');
            }
        }
        $policy = ClientService::policy($email, $phone);
        if ($policy['blocked'] && !$privileged) {
            throw new BookingException('No pudimos agendar tu cita en línea. Por favor contáctanos directamente para ayudarte.', 'blocked');
        }

        // ---- invitados adicionales y cupos
        $guests = [];
        $maxGuests = $privileged ? 50 : ((int) $event['allow_guests'] === 1 ? (int) $event['max_guests'] : 0);
        foreach ((array) ($in['guests'] ?? []) as $g) {
            $gn = Str::clean((string) ($g['name'] ?? ''), 160);
            $ge = strtolower(trim((string) ($g['email'] ?? '')));
            if ($gn === '' && $ge === '') {
                continue;
            }
            if ($gn === '' || ($ge !== '' && !Validator::email($ge))) {
                throw new BookingException('Revisa los datos de las personas que te acompañan.', 'validation');
            }
            $guests[] = ['name' => $gn, 'email' => $ge !== '' ? $ge : null, 'phone' => !empty($g['phone']) ? Str::phone((string) $g['phone']) : null];
        }
        if (count($guests) > $maxGuests) {
            throw new BookingException($maxGuests === 0 ? 'Este servicio no admite acompañantes.' : 'Solo se admiten hasta ' . $maxGuests . ' acompañantes.', 'validation');
        }
        $seats = $event['kind'] === 'group' ? 1 + count($guests) : 1;

        // ---- respuestas a preguntas personalizadas
        $answers = self::validateAnswers($event['fields'], (array) ($in['answers'] ?? []));

        $status = $event['approval'] ? 'pending' : 'confirmed';
        if ($privileged && in_array(($in['status'] ?? ''), ['pending', 'confirmed'], true)) {
            $status = (string) $in['status'];
        }
        $prefHost = !empty($in['host_id']) ? (int) $in['host_id'] : null;
        $sessions = $event['series_sessions'] > 1 ? (int) $event['series_sessions'] : 1;

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $result = Db::tx(function () use ($in, $event, $duration, $startTs, $guestTz, $name, $email, $phone, $guests, $seats, $answers, $status, $prefHost, $sessions, $force, $via, $policy): array {
                    return self::createLocked($in, $event, $duration, $startTs, $guestTz, $name, $email, $phone, $guests, $seats, $answers, $status, $prefHost, $sessions, $force, $via, $policy);
                });
                break;
            } catch (\Throwable $e) {
                if (Db::isDeadlock($e) && $attempt < 3) {
                    usleep(random_int(20000, 90000));
                    continue;
                }
                if ($e instanceof BookingException) {
                    throw $e;
                }
                Logger::error('Error al crear la cita', $e);
                throw new BookingException('No pudimos completar tu reserva en este momento. Inténtalo de nuevo en unos segundos.', 'validation');
            }
        }

        Cache::bumpAvailability();
        foreach ($result['bookings'] as $b) {
            Hooks::booking('booking.created', (int) $b['id']);
        }
        return $result;
    }

    /** Todo lo que ocurre bajo bloqueo (dentro de la transacción). */
    private static function createLocked(array $in, array $event, int $duration, int $startTs, string $guestTz, string $name, string $email, ?string $phone, array $guests, int $seats, array $answers, string $status, ?int $prefHost, int $sessions, bool $force, string $via, array $policy): array
    {
        $hostIds = array_map(static fn (array $h): int => (int) $h['id'], $event['hosts']);
        $resIds = array_map(static fn (array $r): int => (int) $r['id'], $event['resources']);
        self::lockRows($hostIds, $resIds);

        // Revalidación de TODAS las sesiones
        $placements = [];
        $firstStart = $startTs;
        $hostTz = $event['hosts'][0]['timezone'] ?? Settings::tz();
        for ($i = 0; $i < $sessions; $i++) {
            $sTs = $firstStart;
            if ($i > 0) {
                $sTs = self::addDays($firstStart, $i * (int) $event['series_interval_days'], Tz::safe((string) $hostTz));
            }
            try {
                $placements[$i] = ['start' => $sTs] + self::resolvePlacement($event, $duration, $sTs, $prefHost, $force, null, $seats);
            } catch (BookingException $e) {
                if ($i > 0) {
                    throw new BookingException('La sesión del ' . Fmt::dateLong(Tz::fromTs($sTs), Tz::safe((string) $hostTz)) . ' no tiene horario disponible. Elige otra fecha de inicio.', 'slot_unavailable');
                }
                throw $e;
            }
        }

        // Anfitrión(es) y recurso de cada sesión
        $chosenHosts = self::chooseHosts($event, $placements);
        $now = Clock::utc();
        $clientId = ClientService::upsert([
            'name' => $name, 'email' => $email, 'phone' => $phone, 'nit' => $in['nit'] ?? null, 'timezone' => $guestTz,
            'source' => !empty($in['utm_source']) ? (string) $in['utm_source'] : (!empty($in['referrer_host']) ? (string) $in['referrer_host'] : null),
        ]);

        $seriesToken = $sessions > 1 ? Str::token(16) : null;
        $bookings = [];
        $needsPayment = false;
        foreach ($placements as $i => $pl) {
            $sTs = $pl['start'];
            $eTs = $sTs + $duration * 60;
            [$bs, $be] = AvailabilityService::blockedRange($event, $sTs, $duration);

            $quote = ['price' => (float) $event['price'] * 1, 'discount' => 0.0, 'total' => (float) $event['price'], 'deposit_due' => 0.0, 'coupon_id' => null, 'gift_card_id' => null, 'client_package_id' => null, 'needs_payment' => false];
            $wantsPricing = (float) $event['price'] > 0 || (!empty($in['coupon']) && $i === 0) || (!empty($in['gift_code']) && $i === 0);
            if ($wantsPricing) {
                $quote = PricingService::quote($event, $duration, $seats, $i === 0 ? ($in['coupon'] ?? null) : null, $i === 0 ? ($in['gift_code'] ?? null) : null, $clientId, $policy);
                if (empty($quote['ok'])) {
                    throw new BookingException((string) ($quote['error'] ?? 'No pudimos calcular el precio.'), 'payment');
                }
            } elseif ((float) $event['price'] <= 0) {
                $quote['price'] = 0.0;
                $quote['total'] = 0.0;
            }
            $total = (float) $quote['total'];
            $needsPayment = $needsPayment || !empty($quote['needs_payment']) || (float) $quote['deposit_due'] > 0;

            $mode = (string) $event['mode'];
            $location = $event['location'];
            $video = null;
            if ($mode === 'video_auto') {
                $video = self::jitsiLink();
            } elseif ($mode === 'video_custom') {
                $video = $event['video_url'];
            } elseif ($mode === 'home') {
                $location = Str::clean((string) ($in['location'] ?? ($answers['_address'] ?? '')), 255) ?: $event['location'];
            }

            $bid = Db::insert('bookings', [
                'token' => Str::token(16),
                'event_type_id' => (int) $event['id'],
                'host_id' => (int) $chosenHosts[$i][0],
                'resource_id' => $pl['resource_id'],
                'client_id' => $clientId,
                'series_token' => $seriesToken,
                'series_index' => $i + 1,
                'starts_at' => Tz::fromTs($sTs),
                'ends_at' => Tz::fromTs($eTs),
                'blocked_start' => Tz::fromTs($bs),
                'blocked_end' => Tz::fromTs($be),
                'duration' => $duration,
                'seats' => $seats,
                'status' => $status,
                'guest_name' => $name,
                'guest_email' => $email !== '' ? $email : null,
                'guest_phone' => $phone,
                'guest_timezone' => $guestTz,
                'mode' => $mode,
                'location' => $location,
                'video_url' => $video,
                'price' => number_format((float) $quote['price'], 2, '.', ''),
                'discount' => number_format((float) $quote['discount'], 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
                'deposit_due' => number_format((float) $quote['deposit_due'], 2, '.', ''),
                'paid_amount' => '0.00',
                'payment_status' => $total > 0 ? 'pending' : 'none',
                'coupon_id' => $quote['coupon_id'] ?? null,
                'gift_card_id' => $quote['gift_card_id'] ?? null,
                'client_package_id' => $quote['client_package_id'] ?? null,
                'notes' => Str::clean((string) ($in['notes'] ?? ''), 2000) ?: null,
                'utm_source' => Str::clean((string) ($in['utm_source'] ?? ''), 100) ?: null,
                'utm_medium' => Str::clean((string) ($in['utm_medium'] ?? ''), 100) ?: null,
                'utm_campaign' => Str::clean((string) ($in['utm_campaign'] ?? ''), 100) ?: null,
                'referrer_host' => Str::clean((string) ($in['referrer_host'] ?? ''), 190) ?: null,
                'created_via' => $via,
                'routing_log_id' => !empty($in['routing_log_id']) ? (int) $in['routing_log_id'] : null,
                'created_by' => !empty($in['created_by']) ? (int) $in['created_by'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($chosenHosts[$i] as $hid) {
                Db::insert('booking_hosts', ['booking_id' => $bid, 'host_id' => (int) $hid]);
            }
            foreach ($guests as $g) {
                Db::insert('booking_attendees', ['booking_id' => $bid, 'name' => $g['name'], 'email' => $g['email'], 'phone' => $g['phone']]);
            }
            foreach ($answers as $a) {
                if (!is_array($a)) {
                    continue;
                }
                Db::insert('booking_answers', ['booking_id' => $bid, 'field_id' => $a['field_id'], 'label' => Str::clean($a['label'], 190), 'value' => $a['value'], 'file_id' => $a['file_id']]);
                if ($a['file_id']) {
                    Db::update('files', ['owner_type' => 'booking', 'owner_id' => $bid], 'id = ? AND owner_id IS NULL', [$a['file_id']]);
                }
            }
            if ($wantsPricing) {
                try {
                    PricingService::consume($quote, $bid);
                } catch (\RuntimeException $e) {
                    if ($e instanceof \PDOException) {
                        throw $e;
                    }
                    throw new BookingException($e->getMessage(), 'payment');
                }
            }
            if (!empty($in['consent'])) {
                LegalService::recordConsent($clientId, $bid, $email !== '' ? $email : null, (string) ($in['ip'] ?? ''));
            }
            self::log($bid, 'creada', ($via === 'public' ? 'Reserva en línea' : 'Creada desde ' . $via) . ($status === 'pending' ? ' · pendiente de aprobación' : ''), (string) ($in['actor_label'] ?? $name));
            $bookings[] = (array) BookingRepository::find($bid);
        }

        return [
            'booking' => $bookings[0],
            'bookings' => $bookings,
            'status' => $status,
            'needs_payment' => $needsPayment,
            'token' => $bookings[0]['token'],
        ];
    }

    // ------------------------------------------------------------------ reprogramar

    /** @param array $opts force (solo administración) */
    public static function reschedule(int $bookingId, string $newStartUtc, array $actor, array $opts = []): array
    {
        $b = BookingRepository::find($bookingId);
        if (!$b) {
            throw new BookingException('No encontramos esa cita.', 'validation');
        }
        if (!in_array($b['status'], self::ACTIVE, true)) {
            throw new BookingException('Esta cita ya no se puede reprogramar.', 'policy');
        }
        $guest = ($actor['type'] ?? '') === 'guest';
        $force = !$guest && !empty($opts['force']);
        if ($guest) {
            $rules = self::guestRules($b);
            if (!$rules['reschedule']) {
                throw new BookingException($rules['reason'], 'policy');
            }
        }
        if (!Validator::datetimeUtc($newStartUtc)) {
            throw new BookingException('Elige un horario válido.', 'validation');
        }
        $event = self::loadEvent((int) $b['event_type_id']);
        $newTs = Tz::ts($newStartUtc);
        $old = $b;

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $updated = Db::tx(function () use ($b, $event, $newTs, $force, $actor): array {
                    $hostIds = array_map(static fn (array $h): int => (int) $h['id'], $event['hosts']);
                    foreach (BookingRepository::hostIds((int) $b['id']) as $hid) {
                        $hostIds[] = $hid;
                    }
                    $resIds = array_map(static fn (array $r): int => (int) $r['id'], $event['resources']);
                    if ($b['resource_id']) {
                        $resIds[] = (int) $b['resource_id'];
                    }
                    self::lockRows($hostIds, $resIds);
                    $fresh = BookingRepository::find((int) $b['id']);
                    if (!$fresh || !in_array($fresh['status'], self::ACTIVE, true)) {
                        throw new BookingException('Esta cita ya no se puede reprogramar.', 'policy');
                    }
                    $current = BookingRepository::hostIds((int) $b['id']);
                    $pl = self::resolvePlacement($event, (int) $b['duration'], $newTs, $event['kind'] === 'round_robin' ? null : ($current[0] ?? null), $force, (int) $b['id'], (int) $b['seats']);
                    $hostsNew = $current;
                    if ($event['kind'] === 'round_robin' && !in_array((int) $b['host_id'], $pl['host_ids'], true)) {
                        $hostsNew = [self::pickHost($event, $pl['host_ids'])];
                    } elseif ($event['kind'] === 'collective') {
                        $hostsNew = $pl['host_ids'];
                    }
                    $eTs = $newTs + (int) $b['duration'] * 60;
                    [$bs, $be] = AvailabilityService::blockedRange($event, $newTs, (int) $b['duration']);
                    Db::update('bookings', [
                        'starts_at' => Tz::fromTs($newTs), 'ends_at' => Tz::fromTs($eTs),
                        'blocked_start' => Tz::fromTs($bs), 'blocked_end' => Tz::fromTs($be),
                        'host_id' => (int) $hostsNew[0], 'resource_id' => $pl['resource_id'], 'updated_at' => Clock::utc(),
                    ], 'id = ?', [$b['id']]);
                    if ($hostsNew !== $current) {
                        Db::delete('booking_hosts', 'booking_id = ?', [$b['id']]);
                        foreach ($hostsNew as $hid) {
                            Db::insert('booking_hosts', ['booking_id' => (int) $b['id'], 'host_id' => (int) $hid]);
                        }
                    }
                    self::log((int) $b['id'], 'reprogramada', 'De ' . $b['starts_at'] . ' UTC a ' . Tz::fromTs($newTs) . ' UTC', (string) ($actor['label'] ?? 'Sistema'));
                    return (array) BookingRepository::find((int) $b['id']);
                });
                break;
            } catch (\Throwable $e) {
                if (Db::isDeadlock($e) && $attempt < 3) {
                    usleep(random_int(20000, 90000));
                    continue;
                }
                if ($e instanceof BookingException) {
                    throw $e;
                }
                Logger::error('Error al reprogramar la cita ' . $bookingId, $e);
                throw new BookingException('No pudimos reprogramar la cita. Inténtalo de nuevo.', 'validation');
            }
        }
        Cache::bumpAvailability();
        WorkflowService::cancelPending($bookingId);
        Hooks::booking('booking.rescheduled', $bookingId);
        self::offerFreedSlot($old);
        return $updated;
    }

    // ------------------------------------------------------------------ cancelar y estados

    public static function cancel(int $bookingId, string $reason, array $actor): void
    {
        $b = BookingRepository::find($bookingId);
        if (!$b) {
            throw new BookingException('No encontramos esa cita.', 'validation');
        }
        if (!in_array($b['status'], self::ACTIVE, true)) {
            throw new BookingException('Esta cita ya no está activa.', 'policy');
        }
        $type = (string) ($actor['type'] ?? 'user');
        if ($type === 'guest') {
            $rules = self::guestRules($b);
            if (!$rules['cancel']) {
                throw new BookingException($rules['reason'], 'policy');
            }
        }
        $by = $type === 'guest' ? 'guest' : ($type === 'system' ? 'system' : 'host');
        $n = Db::update('bookings', [
            'status' => 'cancelled', 'cancel_reason' => Str::clean($reason, 500) ?: null, 'cancelled_by' => $by,
            'cancelled_at' => Clock::utc(), 'updated_at' => Clock::utc(),
        ], "id = ? AND status IN ('pending','confirmed')", [$bookingId]);
        if ($n === 0) {
            throw new BookingException('Esta cita ya no está activa.', 'policy');
        }
        self::afterRelease($b, 'cancelada', $reason, $actor);
        Hooks::booking('booking.cancelled', $bookingId);
    }

    /** @param string $status confirmed (aprobar) | rejected | completed | no_show | pending */
    public static function setStatus(int $bookingId, string $status, array $actor): void
    {
        if (!in_array($status, ['pending', 'confirmed', 'rejected', 'completed', 'no_show'], true)) {
            throw new BookingException('El estado no es válido.', 'validation');
        }
        $b = BookingRepository::find($bookingId);
        if (!$b) {
            throw new BookingException('No encontramos esa cita.', 'validation');
        }
        $from = (string) $b['status'];
        if ($from === $status) {
            return;
        }
        if (in_array($from, ['cancelled', 'rejected'], true)) {
            throw new BookingException('Una cita cancelada o rechazada ya no se puede modificar.', 'policy');
        }
        if ($status === 'rejected') {
            if ($from !== 'pending') {
                throw new BookingException('Solo se pueden rechazar citas pendientes de aprobación.', 'policy');
            }
            Db::update('bookings', ['status' => 'rejected', 'cancelled_by' => 'host', 'cancelled_at' => Clock::utc(), 'updated_at' => Clock::utc()], 'id = ?', [$bookingId]);
            self::afterRelease($b, 'rechazada', null, $actor);
            Hooks::booking('booking.cancelled', $bookingId);
            return;
        }
        Db::update('bookings', ['status' => $status, 'updated_at' => Clock::utc()], 'id = ?', [$bookingId]);
        if ($status === 'no_show') {
            ClientService::adjustNoShow($b['client_id'] ? (int) $b['client_id'] : null, 1);
        } elseif ($from === 'no_show') {
            ClientService::adjustNoShow($b['client_id'] ? (int) $b['client_id'] : null, -1);
        }
        self::log($bookingId, 'estado', $from . ' → ' . $status, (string) ($actor['label'] ?? 'Sistema'));
        Cache::bumpAvailability();
        if ($status === 'confirmed' && $from === 'pending') {
            Hooks::booking('booking.approved', $bookingId);
        } elseif ($status === 'completed') {
            Hooks::booking('booking.completed', $bookingId);
        } elseif ($status === 'no_show') {
            Hooks::booking('booking.no_show', $bookingId);
        }
    }

    private static function afterRelease(array $b, string $action, ?string $reason, array $actor): void
    {
        PricingService::release($b);
        WorkflowService::cancelPending((int) $b['id']);
        self::log((int) $b['id'], $action, $reason !== null && $reason !== '' ? Str::clean($reason, 400) : null, (string) ($actor['label'] ?? 'Sistema'));
        Cache::bumpAvailability();
        self::offerFreedSlot($b);
    }

    private static function offerFreedSlot(array $b): void
    {
        try {
            WaitlistService::onSlotFreed($b);
        } catch (\Throwable $e) {
            Logger::error('Lista de espera: falló al ofrecer el horario liberado', $e);
        }
    }

    /** Citas pendientes que nadie aprobó a tiempo (o cuya hora ya pasó) se cancelan y liberan el horario. */
    public static function expirePending(): int
    {
        $limit = Clock::utc(Clock::now() - Settings::int('pending_expire_hours', 48) * 3600);
        $rows = Db::all("SELECT id FROM bookings WHERE status = 'pending' AND (created_at < ? OR starts_at < ?) LIMIT 200", [$limit, Clock::utc()]);
        $n = 0;
        foreach ($rows as $r) {
            try {
                self::cancel((int) $r['id'], 'No fue aprobada a tiempo.', ['type' => 'system', 'label' => 'Sistema']);
                $n++;
            } catch (\Throwable $e) {
                Logger::error('No se pudo expirar la cita pendiente ' . $r['id'], $e);
            }
        }
        return $n;
    }

    /** Marca como completadas las citas confirmadas que terminaron hace más de 15 minutos. */
    public static function completeFinished(): int
    {
        $rows = Db::all("SELECT id FROM bookings WHERE status = 'confirmed' AND ends_at < ? ORDER BY ends_at LIMIT 200", [Clock::utc(Clock::now() - 900)]);
        $n = 0;
        foreach ($rows as $r) {
            try {
                self::setStatus((int) $r['id'], 'completed', ['type' => 'system', 'label' => 'Sistema']);
                $n++;
            } catch (\Throwable $e) {
                Logger::error('No se pudo completar la cita ' . $r['id'], $e);
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ consulta

    public static function find(int $id): ?array
    {
        return BookingRepository::find($id);
    }

    public static function findByToken(string $token): ?array
    {
        return BookingRepository::findByToken($token);
    }

    /** ¿Puede el invitado cancelar/reprogramar? */
    public static function guestRules(array $b): array
    {
        $event = Db::one('SELECT cancel_hours, cancel_policy_text FROM event_types WHERE id = ?', [$b['event_type_id']]);
        $hours = (int) ($event['cancel_hours'] ?? 0);
        $startTs = Tz::ts((string) $b['starts_at']);
        $deadline = $startTs - $hours * 3600;
        $active = in_array($b['status'], self::ACTIVE, true);
        $ok = $active && Clock::now() <= $deadline && $startTs > Clock::now();
        $reason = '';
        if (!$active) {
            $reason = 'Esta cita ya no está activa.';
        } elseif (!$ok) {
            $reason = $startTs <= Clock::now()
                ? 'Esta cita ya pasó.'
                : 'Ya no es posible cambiar la cita en línea: la política pide avisar con al menos ' . $hours . ' ' . ($hours === 1 ? 'hora' : 'horas') . ' de anticipación. Escríbenos por WhatsApp y con gusto te ayudamos.';
        }
        return ['cancel' => $ok, 'reschedule' => $ok, 'reason' => $reason, 'deadline_utc' => Tz::fromTs($deadline), 'policy_text' => (string) ($event['cancel_policy_text'] ?? '')];
    }

    /** Cita con todo lo necesario para mostrarla (correos, confirmación, panel). */
    public static function display(array $b): array
    {
        $id = (int) $b['id'];
        $event = Db::one('SELECT * FROM event_types WHERE id = ?', [$b['event_type_id']]) ?: [];
        $hosts = Db::all('SELECT h.* FROM booking_hosts bh JOIN hosts h ON h.id = bh.host_id WHERE bh.booking_id = ? ORDER BY h.id', [$id]);
        $host = Db::one('SELECT * FROM hosts WHERE id = ?', [$b['host_id']]) ?: [];
        $tz = (string) $b['guest_timezone'];
        $b['event'] = $event;
        $b['host'] = $host;
        $b['hosts'] = $hosts;
        $b['attendees'] = Db::all('SELECT * FROM booking_attendees WHERE booking_id = ? ORDER BY id', [$id]);
        $b['answers'] = Db::all('SELECT * FROM booking_answers WHERE booking_id = ? ORDER BY id', [$id]);
        $b['client'] = $b['client_id'] ? (Db::one('SELECT * FROM clients WHERE id = ?', [$b['client_id']]) ?: null) : null;
        $b['when_local'] = [
            'date' => Fmt::dateLong((string) $b['starts_at'], $tz),
            'time' => Fmt::time((string) $b['starts_at'], $tz),
            'end_time' => Fmt::time((string) $b['ends_at'], $tz),
            'tz' => Fmt::tzLabel($tz, (string) $b['starts_at']),
            'duration' => Fmt::duration((int) $b['duration']),
        ];
        $b['public_url'] = abs_url('/reserva/' . $b['token']);
        return $b;
    }

    public static function log(int $bookingId, string $action, ?string $detail, string $actor): void
    {
        Db::insert('booking_history', ['booking_id' => $bookingId, 'action' => substr($action, 0, 40), 'detail' => $detail !== null ? substr($detail, 0, 500) : null, 'actor' => substr($actor, 0, 120), 'created_at' => Clock::utc()]);
    }

    // ------------------------------------------------------------------ internos

    private static function loadEvent(int $id): array
    {
        $event = EventRepository::find($id);
        if (!$event) {
            throw new BookingException('No encontramos ese servicio.', 'validation');
        }
        return $event;
    }

    /** Bloquea filas de anfitriones y recursos en orden fijo (evita interbloqueos) para serializar reservas concurrentes. */
    private static function lockRows(array $hostIds, array $resourceIds): void
    {
        $hostIds = array_values(array_unique(array_map('intval', $hostIds)));
        sort($hostIds);
        if ($hostIds) {
            Db::all('SELECT id FROM hosts WHERE id IN (' . implode(',', array_fill(0, count($hostIds), '?')) . ') ORDER BY id FOR UPDATE', $hostIds);
        }
        $resourceIds = array_values(array_unique(array_map('intval', $resourceIds)));
        sort($resourceIds);
        if ($resourceIds) {
            Db::all('SELECT id FROM resources WHERE id IN (' . implode(',', array_fill(0, count($resourceIds), '?')) . ') ORDER BY id FOR UPDATE', $resourceIds);
        }
    }

    /**
     * Comprueba que el horario sigue libre y devuelve quién puede atenderlo.
     * @return array{host_ids:int[],resource_id:?int,seats_left:?int}
     */
    private static function resolvePlacement(array $event, int $duration, int $startTs, ?int $prefHost, bool $force, ?int $excludeId, int $seats): array
    {
        $capacity = max(1, (int) $event['capacity']);
        if (!$force) {
            $opts = ['nocache' => true, 'exclude_booking_id' => $excludeId];
            if ($prefHost !== null && $event['kind'] !== 'collective') {
                $opts['host_id'] = $prefHost;
            }
            foreach (AvailabilityService::slots($event, $duration, Tz::fromTs($startTs), Tz::fromTs($startTs + 1), $opts) as $s) {
                if (Tz::ts($s['start']) === $startTs) {
                    if ($event['kind'] === 'group' && (int) $s['seats_left'] < $seats) {
                        throw new BookingException((int) $s['seats_left'] <= 0 ? 'Esta sesión ya no tiene cupos.' : 'Solo quedan ' . $s['seats_left'] . ' cupos en esta sesión.', 'slot_unavailable');
                    }
                    return ['host_ids' => $s['host_ids'], 'resource_id' => $s['resource_ids'][0] ?? null, 'seats_left' => $s['seats_left']];
                }
            }
            throw new BookingException('Ese horario acaba de ocuparse. Elige otro, por favor.', 'slot_unavailable');
        }

        // Forzado por administración: nunca se permite doble agendado ni exceder cupos.
        [$bs, $be] = AvailabilityService::blockedRange($event, $startTs, $duration);
        $endTs = $startTs + $duration * 60;
        $hosts = $event['hosts'];
        if ($prefHost !== null && $event['kind'] !== 'collective') {
            $hosts = array_values(array_filter($hosts, static fn (array $h): bool => (int) $h['id'] === $prefHost));
        }
        if (in_array($event['kind'], ['individual', 'group'], true)) {
            $hosts = array_slice($hosts, 0, 1);
        }
        $free = [];
        $seatsLeft = null;
        foreach ($hosts as $h) {
            $used = 0;
            if (!self::hostConflict((int) $h['id'], $bs, $be, $event, $startTs, $endTs, $excludeId, $used)) {
                $free[] = (int) $h['id'];
                if ($event['kind'] === 'group') {
                    $seatsLeft = $capacity - $used;
                }
            }
        }
        if (!$free || ($event['kind'] === 'collective' && count($free) !== count($hosts))) {
            throw new BookingException('Ese horario ya está ocupado para el anfitrión. Elige otro.', 'slot_unavailable');
        }
        if ($event['kind'] === 'group' && $seatsLeft !== null && $seatsLeft < $seats) {
            throw new BookingException('Esta sesión no tiene cupos suficientes.', 'slot_unavailable');
        }
        $resource = null;
        if ($event['resources']) {
            foreach ($event['resources'] as $r) {
                if (AvailabilityService::resourceFree((int) $r['id'], (int) $r['capacity'], $bs, $be, $excludeId)) {
                    $resource = (int) $r['id'];
                    break;
                }
            }
            if ($resource === null) {
                throw new BookingException('La sala o el equipo ya está reservado en ese horario.', 'slot_unavailable');
            }
        }
        return ['host_ids' => $free, 'resource_id' => $resource, 'seats_left' => $seatsLeft];
    }

    private static function hostConflict(int $hostId, int $bs, int $be, array $event, int $startTs, int $endTs, ?int $excludeId, int &$seatsUsed): bool
    {
        foreach (BookingRepository::busyForHost($hostId, $bs - 86400, $be + 86400, $excludeId) as $b) {
            if ($b['s'] < $be && $b['e'] > $bs) {
                if ($event['kind'] === 'group' && $b['kind'] === 'booking' && $b['event'] === (int) $event['id'] && $b['start'] === $startTs && $b['end'] === $endTs) {
                    $seatsUsed += $b['seats'];
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Elige anfitriones por sesión. Individual/grupal: el único; colectivo: todos; round robin: el que corresponda según el modo
     * (si es serie, uno disponible para TODAS las sesiones).
     * @return array<int,int[]>
     */
    private static function chooseHosts(array $event, array $placements): array
    {
        $out = [];
        if ($event['kind'] === 'round_robin') {
            $cand = null;
            foreach ($placements as $pl) {
                $cand = $cand === null ? $pl['host_ids'] : array_values(array_intersect($cand, $pl['host_ids']));
            }
            if (!$cand) {
                throw new BookingException('No hay un profesional disponible para todas las sesiones de la serie. Elige otra fecha de inicio.', 'slot_unavailable');
            }
            $host = self::pickHost($event, $cand);
            foreach ($placements as $i => $_) {
                $out[$i] = [$host];
            }
            return $out;
        }
        foreach ($placements as $i => $pl) {
            $out[$i] = $event['kind'] === 'collective' ? array_map('intval', $pl['host_ids']) : [(int) $pl['host_ids'][0]];
        }
        return $out;
    }

    /** Round robin justo: equitativo (menos reservas recientes), ponderado (reservas/peso) o por prioridad. */
    public static function pickHost(array $event, array $candidates): int
    {
        $candidates = array_values(array_map('intval', $candidates));
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        $ph = implode(',', array_fill(0, count($candidates), '?'));
        $rows = Db::all(
            "SELECT bh.host_id, COUNT(*) AS c, MAX(b.created_at) AS last_created
             FROM booking_hosts bh JOIN bookings b ON b.id = bh.booking_id
             WHERE b.event_type_id = ? AND b.status IN ('pending','confirmed','completed','no_show') AND b.created_at >= ? AND bh.host_id IN ($ph)
             GROUP BY bh.host_id",
            array_merge([(int) $event['id'], Clock::utc(Clock::now() - 90 * 86400)], $candidates)
        );
        $count = [];
        $last = [];
        foreach ($rows as $r) {
            $count[(int) $r['host_id']] = (int) $r['c'];
            $last[(int) $r['host_id']] = (string) $r['last_created'];
        }
        $weight = [];
        $prio = [];
        foreach ($event['hosts'] as $h) {
            $weight[(int) $h['id']] = max(1, (int) $h['weight']);
            $prio[(int) $h['id']] = max(1, (int) $h['priority']);
        }
        $mode = (string) $event['rr_mode'];
        usort($candidates, static function (int $a, int $b) use ($count, $last, $weight, $prio, $mode): int {
            $ca = $count[$a] ?? 0;
            $cb = $count[$b] ?? 0;
            if ($mode === 'priority') {
                $pa = $prio[$a] ?? 1;
                $pb = $prio[$b] ?? 1;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
            } elseif ($mode === 'weighted') {
                $x = $ca * ($weight[$b] ?? 1);
                $y = $cb * ($weight[$a] ?? 1);
                if ($x !== $y) {
                    return $x <=> $y;
                }
                if (($weight[$a] ?? 1) !== ($weight[$b] ?? 1)) {
                    return ($weight[$b] ?? 1) <=> ($weight[$a] ?? 1);
                }
            }
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }
            $la = $last[$a] ?? '';
            $lb = $last[$b] ?? '';
            if ($la !== $lb) {
                return strcmp($la, $lb);
            }
            return $a <=> $b;
        });
        return $candidates[0];
    }

    /** Suma días de calendario conservando la hora local de la zona (respeta cambios de horario de verano). */
    private static function addDays(int $ts, int $days, string $tz): int
    {
        $local = Tz::formatTs($ts, $tz, 'Y-m-d H:i:s');
        $d = new \DateTimeImmutable($local, new \DateTimeZone($tz));
        return (int) $d->modify('+' . $days . ' days')->format('U');
    }

    private static function jitsiLink(): string
    {
        $domain = (string) Settings::get('video_provider_domain', 'meet.jit.si');
        if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]{0,120})[a-z0-9]$/i', $domain)) {
            $domain = 'meet.jit.si';
        }
        $biz = Str::slug((string) Settings::get('business_name', 'agenda'), 20);
        return 'https://' . $domain . '/' . $biz . '-' . Str::token(8);
    }

    /**
     * Valida respuestas contra las preguntas del evento (con lógica condicional).
     * @return array<int,array{field_id:?int,label:string,value:?string,file_id:?int}>
     */
    private static function validateAnswers(array $fields, array $raw): array
    {
        $byName = [];
        foreach ($fields as $f) {
            $byName[$f['name']] = $raw[$f['id']] ?? ($raw[$f['name']] ?? null);
        }
        $out = [];
        foreach ($fields as $f) {
            if (!empty($f['condition_field'])) {
                $dep = $byName[$f['condition_field']] ?? null;
                $depVal = is_array($dep) ? implode(',', $dep) : (string) $dep;
                if ($depVal !== (string) $f['condition_value'] && !(is_array($dep) && in_array((string) $f['condition_value'], $dep, true))) {
                    continue;
                }
            }
            $val = $raw[$f['id']] ?? ($raw[$f['name']] ?? null);
            $empty = $val === null || $val === '' || $val === [] || $val === false;
            $label = (string) $f['label'];
            $type = (string) $f['type'];
            if ($empty) {
                if ((int) $f['required'] === 1) {
                    throw new BookingException('Falta responder: ' . $label . '.', 'validation');
                }
                continue;
            }
            $fileId = null;
            if ($type === 'file') {
                $row = Db::one("SELECT id, original_name FROM files WHERE token = ? AND owner_id IS NULL AND kind = 'answer'", [(string) $val]);
                if (!$row) {
                    throw new BookingException('No pudimos adjuntar el archivo de «' . $label . '». Súbelo de nuevo.', 'validation');
                }
                $fileId = (int) $row['id'];
                $text = (string) $row['original_name'];
            } elseif ($type === 'checkbox' || $type === 'consent') {
                $text = is_array($val) ? implode(', ', array_map(static fn ($x): string => Str::clean((string) $x, 190), $val)) : (in_array($val, [1, '1', 'on', 'true', true, 'si', 'sí'], true) ? 'Sí' : Str::clean((string) $val, 500));
            } else {
                $text = Str::clean(is_array($val) ? implode(', ', $val) : (string) $val, 2000);
                if ($type === 'email' && !Validator::email($text)) {
                    throw new BookingException('Revisa el correo en «' . $label . '».', 'validation');
                }
                if ($type === 'number' && !is_numeric($text)) {
                    throw new BookingException('«' . $label . '» debe ser un número.', 'validation');
                }
                if ($type === 'date' && !Validator::date($text)) {
                    throw new BookingException('Revisa la fecha en «' . $label . '».', 'validation');
                }
                if ($type === 'phone' && Str::phone($text) === null) {
                    throw new BookingException('Revisa el teléfono en «' . $label . '».', 'validation');
                }
                if (in_array($type, ['select', 'radio'], true)) {
                    $opts = array_filter(array_map('trim', preg_split('/\R|,/', (string) $f['options']) ?: []));
                    if ($opts && !in_array($text, $opts, true)) {
                        throw new BookingException('Elige una opción válida en «' . $label . '».', 'validation');
                    }
                }
            }
            $out[] = ['field_id' => (int) $f['id'], 'label' => $label, 'value' => $text, 'file_id' => $fileId];
        }
        return $out;
    }
}
