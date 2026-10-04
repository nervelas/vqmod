<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Clock;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Upload;
use App\Core\Validator;
use App\Services\AvailabilityService;
use App\Services\BookingException;
use App\Services\BookingService;
use App\Services\CaptchaService;
use App\Services\EventRepository;

/** Página de reserva y sus puntos de acceso JSON (/_/…). Sin sesión ni cookies. */
final class BookingController extends Controller
{
    private const MAX_RANGE_DAYS = 62;

    // ------------------------------------------------------------------ Página
    public function page(Request $req, array $p): Response
    {
        $event = EventRepository::findBySlug((string) $p['slug']);
        if (!$event) {
            throw new HttpException(404);
        }
        $embed = $req->get('embed') === '1';
        if (!$embed) {
            Security::allowEmbed(false);
        }
        $biz = PubSupport::biz();
        $reason = EventRepository::unavailableReason($event);
        if ($reason !== null) {
            $html = $this->view('public/event_closed', [
                'title' => 'Reserva no disponible',
                'noindex' => true,
                'embed' => $embed,
                'reason' => $reason,
                'biz' => $biz,
                'styles' => ['css/booking.css'],
                'bodyClass' => 'pub-page',
            ], 'layouts/public');
            $html->status = 200;
            return $html;
        }

        $durations = PubSupport::durations($event);
        $duration = PubSupport::defaultDuration($event, $durations);
        $want = (int) $req->get('duracion', 0);
        if ($want > 0 && in_array($want, $durations, true)) {
            $duration = $want;
        }
        $hosts = in_array((string) $event['kind'], ['individual', 'group'], true) ? PubSupport::eventHosts((int) $event['id']) : [];
        $hostPick = $this->resolveHost((string) $req->get('host', ''), $hosts);

        $fields = [];
        foreach ((array) ($event['fields'] ?? []) as $f) {
            $fields[] = [
                'id' => (int) $f['id'],
                'name' => (string) $f['name'],
                'label' => (string) $f['label'],
                'type' => (string) $f['type'],
                'options' => PubSupport::options($f['options'] ?? null),
                'help' => (string) ($f['help'] ?? ''),
                'required' => (int) ($f['required'] ?? 0) === 1,
                'cond_field' => (string) ($f['condition_field'] ?? ''),
                'cond_value' => (string) ($f['condition_value'] ?? ''),
            ];
        }

        $consent = ['text' => '', 'version' => (string) Settings::get('legal_version', '1')];
        if (class_exists('App\\Services\\LegalService')) {
            try {
                $c = \App\Services\LegalService::consentText();
                $consent['text'] = (string) ($c['label'] ?? '');
                $consent['version'] = (string) ($c['version'] ?? $consent['version']);
            } catch (\Throwable $e) {
                Logger::error('Texto de consentimiento no disponible', $e);
            }
        }
        if ($consent['text'] === '') {
            $consent['text'] = 'Autorizo que ' . $biz['name'] . ' guarde y use mis datos para gestionar mi ' . $biz['terms_label'] . ' y comunicarse conmigo sobre ella.';
        }

        $minSeconds = max(0, Settings::int('booking_min_form_seconds', 3));
        $ts = Clock::now();
        $prefill = [
            'name' => Str::clean((string) $req->get('nombre', ''), 160),
            'email' => Str::clean((string) $req->get('email', ''), 190),
            'phone' => Str::clean((string) $req->get('telefono', ''), 30),
        ];
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $k) {
            $v = PubSupport::short($req->get($k), 100);
            if ($v !== null) {
                $utm[$k] = $v;
            }
        }
        $isGroup = (string) $event['kind'] === 'group';
        $boot = [
            'base' => rtrim(url('/'), '/'),
            'embed' => $embed,
            'urls' => [
                'slots' => url('/_/slots'), 'book' => url('/_/book'), 'track' => url('/_/track'),
                'coupon' => url('/_/coupon'), 'upload' => url('/_/upload'), 'waitlist' => url('/_/waitlist'),
                'privacy' => url('/privacidad'), 'terms' => url('/terminos'), 'home' => url('/'),
            ],
            'csrf' => PubSupport::csrfSet(),
            'form_ts' => $ts . '.' . substr(Crypto::sign('fs|' . $ts), 0, 32),
            'min_seconds' => $minSeconds,
            'time_format' => (string) Settings::get('time_format', '12') === '24' ? '24' : '12',
            'phone_cc' => (string) Settings::get('phone_cc', '502'),
            'business_tz' => Settings::tz(),
            'currency' => (string) Settings::get('currency_symbol', 'Q'),
            'event' => [
                'id' => (int) $event['id'],
                'slug' => (string) $event['slug'],
                'name' => (string) $event['name'],
                'color' => PubSupport::hexColor((string) $event['color'], '#C9A050'),
                'kind' => (string) $event['kind'],
                'mode' => (string) $event['mode'],
                'mode_label' => PubSupport::modeLabel((string) $event['mode']),
                'location' => (string) ($event['location'] ?? ''),
                'durations' => $durations,
                'duration' => $duration,
                'series_sessions' => max(1, (int) $event['series_sessions']),
                'series_interval_days' => max(1, (int) $event['series_interval_days']),
                'approval' => (int) $event['approval'] === 1,
                'capacity' => (int) $event['capacity'],
                'allow_guests' => (int) $event['allow_guests'] === 1,
                'max_guests' => (int) $event['max_guests'],
                'allow_coupon' => (int) $event['allow_coupon'] === 1,
                'require_phone' => (int) $event['require_phone'] === 1,
                'redirect_url' => PubSupport::safeUrl((string) ($event['redirect_url'] ?? '')),
                'cancel_hours' => (int) $event['cancel_hours'],
                'is_group' => $isGroup,
                'max_advance_days' => (int) $event['max_advance_days'],
            ],
            'hosts' => array_map(static fn($h) => ['id' => (int) $h['id'], 'name' => (string) $h['name'], 'title' => (string) ($h['title'] ?? '')], $hosts),
            'host' => $hostPick,
            'fields' => $fields,
            'quotes' => $this->quotes($event, $durations),
            'prefill' => $prefill,
            'utm' => $utm,
            'captcha' => CaptchaService::enabled() ? ['provider' => CaptchaService::provider(), 'field' => CaptchaService::fieldName()] : null,
        ];

        $color = PubSupport::hexColor((string) $req->get('color', ''));
        return $this->view('public/event', [
            'title' => (string) $event['name'],
            'description' => Str::truncate(trim(strip_tags((string) ($event['description'] ?? ''))), 160) ?: ('Reserva en línea con ' . $biz['name']),
            'embed' => $embed,
            'biz' => $biz,
            'event' => $event,
            'hosts' => $hosts,
            'durations' => $durations,
            'duration' => $duration,
            'hostPick' => $hostPick,
            'fields' => $fields,
            'consent' => $consent,
            'boot' => $boot,
            'tzGroups' => PubSupport::tzGroups(),
            'captchaScript' => CaptchaService::scriptUrl(),
            'captchaKey' => CaptchaService::siteKey(),
            'captchaProvider' => CaptchaService::provider(),
            'colorOverride' => $color,
            'styles' => ['css/booking.css'],
            'scripts' => ['js/public.js', 'js/dial.js', 'js/booking.js'],
            'bodyClass' => 'pub-booking' . ($embed ? ' is-embed' : ''),
        ], 'layouts/public');
    }

    // ------------------------------------------------------------------ Horarios
    public function slots(Request $req, array $p): Response
    {
        if (!RateLimiter::hit(PubSupport::bucket($req, 'slots'), 180, 60)) {
            return $this->json(['ok' => false, 'error' => 'Estás consultando horarios muy rápido. Espera unos segundos e inténtalo de nuevo.'], 429);
        }
        try {
            $event = $this->eventFromRef((string) $req->get('event', ''));
            if (!$event || EventRepository::unavailableReason($event) !== null) {
                return $this->json(['ok' => false, 'error' => 'Este evento no está disponible para reservar.'], 404);
            }
            $durations = PubSupport::durations($event);
            $duration = (int) $req->get('duration', 0);
            if (!in_array($duration, $durations, true)) {
                $duration = PubSupport::defaultDuration($event, $durations);
            }
            $tz = Tz::safe((string) $req->get('tz', ''), Settings::tz());
            $today = Tz::formatTs(Clock::now(), $tz, 'Y-m-d');
            $from = (string) $req->get('from', '');
            $to = (string) $req->get('to', '');
            if (!Validator::date($from)) {
                $from = $today;
            }
            if ($from < $today) {
                $from = $today;
            }
            if (!Validator::date($to) || $to < $from) {
                $to = gmdate('Y-m-d', strtotime($from . ' UTC +30 days'));
            }
            $maxTo = gmdate('Y-m-d', strtotime($from . ' UTC +' . (self::MAX_RANGE_DAYS - 1) . ' days'));
            if ($to > $maxTo) {
                $to = $maxTo;
            }

            $opts = [];
            $hostId = $this->hostIdFor($event, (string) $req->get('host', ''));
            if ($hostId !== null) {
                $opts['host_id'] = $hostId;
            }
            $exclude = (string) $req->get('exclude', '');
            if (preg_match('/^[a-f0-9]{32}$/', $exclude)) {
                $b = Db::one('SELECT id, event_type_id FROM bookings WHERE token = ?', [$exclude]);
                if ($b && (int) $b['event_type_id'] === (int) $event['id']) {
                    $opts['exclude_booking_id'] = (int) $b['id'];
                }
            }

            $byDay = AvailabilityService::slotsByDay($event, $duration, $from, $to, $tz, $opts);
            $days = [];
            $count = 0;
            foreach ($byDay as $date => $list) {
                $row = [];
                foreach ($list as $s) {
                    $row[] = [
                        's' => (string) $s['start'],
                        't' => Tz::format((string) $s['start'], $tz, 'H:i'),
                        'n' => isset($s['seats_left']) && $s['seats_left'] !== null ? (int) $s['seats_left'] : null,
                    ];
                }
                if ($row) {
                    $days[(string) $date] = $row;
                    $count += count($row);
                }
            }
            $out = [
                'ok' => true,
                'event' => (int) $event['id'],
                'duration' => $duration,
                'from' => $from,
                'to' => $to,
                'tz' => $tz,
                'count' => $count,
                'days' => (object) $days,
                'csrf' => PubSupport::csrfSet(),
            ];
            if ($req->get('next') === '1') {
                $nx = AvailabilityService::next($event, $duration, $opts);
                $out['next'] = $nx ? [
                    'date' => Tz::format((string) $nx['start'], $tz, 'Y-m-d'),
                    't' => Tz::format((string) $nx['start'], $tz, 'H:i'),
                    's' => (string) $nx['start'],
                ] : null;
            }
            return $this->json($out);
        } catch (\Throwable $e) {
            Logger::error('No se pudieron calcular horarios públicos', $e);
            return $this->json(['ok' => false, 'error' => 'No pudimos consultar los horarios en este momento. Inténtalo de nuevo en unos segundos.'], 500);
        }
    }

    // ------------------------------------------------------------------ Reservar
    public function book(Request $req, array $p): Response
    {
        $generic = 'No pudimos completar tu reserva. Revisa tus datos e inténtalo de nuevo.';
        try {
            // Trampa para bots: campo oculto que una persona nunca llena.
            if (trim((string) $req->input('company_site', '')) !== '') {
                return $this->json(['ok' => false, 'error' => $generic], 422);
            }
            $ft = explode('.', (string) $req->input('form_ts', ''));
            $min = max(0, Settings::int('booking_min_form_seconds', 3));
            if (count($ft) !== 2 || !ctype_digit($ft[0]) || !hash_equals(substr(Crypto::sign('fs|' . $ft[0]), 0, 32), $ft[1])) {
                return $this->json(['ok' => false, 'error' => 'La página lleva abierta demasiado tiempo. Recárgala para continuar.', 'reload' => true], 419);
            }
            if (Clock::now() - (int) $ft[0] < $min) {
                return $this->json(['ok' => false, 'error' => 'Vas muy rápido. Espera un par de segundos y vuelve a confirmar.'], 429);
            }
            $limit = max(1, Settings::int('booking_rate_limit', 10));
            if (!RateLimiter::hit(PubSupport::bucket($req, 'book'), $limit, 600)) {
                return $this->json(['ok' => false, 'error' => 'Hemos recibido muchos intentos desde tu conexión. Espera unos minutos e inténtalo de nuevo.', 'code' => 'rate_limit'], 429);
            }
            $cap = CaptchaService::verify((string) $req->input(CaptchaService::fieldName(), ''), $req->ip());
            if (!$cap['ok']) {
                return $this->json(['ok' => false, 'error' => $cap['error'], 'code' => 'captcha'], 422);
            }

            $event = $this->eventFromRef((string) $req->input('event', ''));
            if (!$event) {
                return $this->json(['ok' => false, 'error' => 'No encontramos este evento.'], 404);
            }
            if (EventRepository::unavailableReason($event) !== null) {
                return $this->json(['ok' => false, 'error' => 'Este evento ya no está recibiendo reservas.', 'code' => 'closed'], 409);
            }

            [$in, $errors] = $this->validateBooking($req, $event);
            if ($errors) {
                return $this->json(['ok' => false, 'error' => 'Revisa los campos marcados.', 'errors' => $errors], 422);
            }

            $res = BookingService::create($in);
            $b = $res['booking'];
            PubSupport::track('booked', $req, [
                'event_type_id' => (int) $event['id'], 'host_id' => (int) $b['host_id'],
                'visit_id' => $req->input('visit_id', ''),
                'utm_source' => $in['utm_source'] ?? null, 'utm_medium' => $in['utm_medium'] ?? null,
                'utm_campaign' => $in['utm_campaign'] ?? null, 'referrer_host' => $in['referrer_host'] ?? null,
            ]);
            return $this->json($this->successPayload($event, $res, (string) $in['timezone']));
        } catch (BookingException $e) {
            $status = match ($e->errorCode) {
                'slot_unavailable' => 409,
                'rate_limit' => 429,
                'closed' => 409,
                default => 422,
            };
            return $this->json(['ok' => false, 'error' => $e->getMessage(), 'code' => $e->errorCode], $status);
        } catch (\Throwable $e) {
            Logger::error('Falló la reserva pública', $e);
            return $this->json(['ok' => false, 'error' => $generic], 500);
        }
    }

    // ------------------------------------------------------------------ Embudo
    public function track(Request $req, array $p): Response
    {
        if (!RateLimiter::hit(PubSupport::bucket($req, 'track'), 120, 600)) {
            return $this->json(['ok' => true]);
        }
        $step = (string) $req->input('step', '');
        if (in_array($step, ['view', 'slot'], true)) {
            $event = $this->eventFromRef((string) $req->input('event', ''));
            if ($event) {
                PubSupport::track($step, $req, [
                    'event_type_id' => (int) $event['id'],
                    'host_id' => $this->hostIdFor($event, (string) $req->input('host', '')),
                    'visit_id' => $req->input('visit_id', ''),
                    'utm_source' => $req->input('utm_source'), 'utm_medium' => $req->input('utm_medium'),
                    'utm_campaign' => $req->input('utm_campaign'), 'referrer_host' => $req->input('referrer_host'),
                ]);
            }
        }
        return $this->json(['ok' => true]);
    }

    // ------------------------------------------------------------------ Cupón
    public function coupon(Request $req, array $p): Response
    {
        if (!RateLimiter::hit(PubSupport::bucket($req, 'coupon'), 30, 600)) {
            return $this->json(['ok' => false, 'error' => 'Demasiados intentos con cupones. Espera unos minutos.'], 429);
        }
        try {
            $event = $this->eventFromRef((string) $req->input('event', ''));
            if (!$event || EventRepository::unavailableReason($event) !== null) {
                return $this->json(['ok' => false, 'error' => 'Este evento no está disponible.'], 404);
            }
            $durations = PubSupport::durations($event);
            $duration = (int) $req->input('duration', 0);
            if (!in_array($duration, $durations, true)) {
                $duration = PubSupport::defaultDuration($event, $durations);
            }
            $seats = max(1, min(20, (int) $req->input('seats', 1)));
            $code = strtoupper(Str::clean((string) $req->input('coupon', ''), 40));
            if ($code !== '' && (int) $event['allow_coupon'] !== 1) {
                return $this->json(['ok' => false, 'error' => 'Este evento no admite cupones.'], 422);
            }
            $q = $this->quote($event, $duration, $seats, $code !== '' ? $code : null);
            if (empty($q['ok'])) {
                return $this->json(['ok' => false, 'error' => (string) ($q['error'] ?? 'Ese cupón no es válido.')], 422);
            }
            return $this->json(['ok' => true, 'quote' => $this->quoteOut($q), 'coupon' => $code]);
        } catch (\Throwable $e) {
            Logger::error('Falló la validación de cupón', $e);
            return $this->json(['ok' => false, 'error' => 'No pudimos validar el cupón ahora. Inténtalo de nuevo.'], 500);
        }
    }

    // ------------------------------------------------------------------ Archivos de preguntas
    public function upload(Request $req, array $p): Response
    {
        if (!RateLimiter::hit(PubSupport::bucket($req, 'upload'), 12, 600)) {
            return $this->json(['ok' => false, 'error' => 'Has subido varios archivos seguidos. Espera unos minutos.'], 429);
        }
        $f = $req->files['file'] ?? null;
        if (!is_array($f)) {
            return $this->json(['ok' => false, 'error' => 'Elige un archivo para adjuntar.'], 422);
        }
        try {
            $row = Upload::store($f, ['kind' => 'answer', 'is_public' => false, 'max_bytes' => 5 * 1024 * 1024]);
            return $this->json(['ok' => true, 'file_token' => (string) $row['token'], 'name' => (string) $row['original_name'], 'size' => (int) $row['size']]);
        } catch (\RuntimeException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Logger::error('Falló la subida pública', $e);
            return $this->json(['ok' => false, 'error' => 'No pudimos guardar el archivo. Inténtalo de nuevo.'], 500);
        }
    }

    // ------------------------------------------------------------------ Lista de espera
    public function waitlist(Request $req, array $p): Response
    {
        if (!RateLimiter::hit(PubSupport::bucket($req, 'wait'), 6, 600)) {
            return $this->json(['ok' => false, 'error' => 'Has enviado varias solicitudes seguidas. Espera unos minutos.'], 429);
        }
        if (trim((string) $req->input('company_site', '')) !== '') {
            return $this->json(['ok' => false, 'error' => 'No pudimos registrar tu solicitud.'], 422);
        }
        try {
            $event = $this->eventFromRef((string) $req->input('event', ''));
            if (!$event || EventRepository::unavailableReason($event) !== null) {
                return $this->json(['ok' => false, 'error' => 'Este evento no está disponible.'], 404);
            }
            if (!class_exists('App\\Services\\WaitlistService')) {
                return $this->json(['ok' => false, 'error' => 'La lista de espera no está disponible por ahora.'], 503);
            }
            $cap = CaptchaService::verify((string) $req->input(CaptchaService::fieldName(), ''), $req->ip());
            if (!$cap['ok']) {
                return $this->json(['ok' => false, 'error' => $cap['error']], 422);
            }
            $name = Str::clean((string) $req->input('name', ''), 160);
            $email = strtolower(Str::clean((string) $req->input('email', ''), 190));
            $phoneRaw = Str::clean((string) $req->input('phone', ''), 30);
            $phone = $phoneRaw !== '' ? Str::phone($phoneRaw, (string) Settings::get('phone_cc', '502')) : null;
            $errors = [];
            if (mb_strlen($name) < 2) {
                $errors['name'] = 'Escribe tu nombre para poder avisarte.';
            }
            if ($email === '' && $phone === null) {
                $errors['email'] = 'Déjanos un correo o un teléfono para avisarte.';
            }
            if ($email !== '' && !Validator::email($email)) {
                $errors['email'] = 'Revisa tu correo: parece que le falta algo.';
            }
            if ($phoneRaw !== '' && $phone === null) {
                $errors['phone'] = 'Revisa tu teléfono: en Guatemala son 8 dígitos.';
            }
            if (!$req->bool('consent')) {
                $errors['consent'] = 'Necesitamos tu autorización para avisarte cuando haya un horario.';
            }
            if ($errors) {
                return $this->json(['ok' => false, 'error' => 'Revisa los campos marcados.', 'errors' => $errors], 422);
            }
            $durations = PubSupport::durations($event);
            $duration = (int) $req->input('duration', 0);
            if (!in_array($duration, $durations, true)) {
                $duration = PubSupport::defaultDuration($event, $durations);
            }
            $want = (string) $req->input('want_date', '');
            $want = Validator::date($want) && $want >= gmdate('Y-m-d', Clock::now() - 86400) ? $want : null;
            $guest = [
                'name' => $name, 'email' => $email !== '' ? $email : null, 'phone' => $phone,
                'timezone' => Tz::safe((string) $req->input('tz', ''), Settings::tz()),
            ];
            \App\Services\WaitlistService::join($event, $duration, $guest, $want, $this->hostIdFor($event, (string) $req->input('host', '')));
            return $this->json(['ok' => true, 'message' => 'Quedaste en la lista de espera. Te avisaremos apenas se libere un horario; tendrás unos minutos para confirmarlo.']);
        } catch (\InvalidArgumentException | BookingException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Logger::error('Falló la lista de espera pública', $e);
            return $this->json(['ok' => false, 'error' => 'No pudimos anotarte en la lista de espera. Inténtalo de nuevo.'], 500);
        }
    }

    // ================================================================== Internos
    private function eventFromRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (ctype_digit($ref)) {
            return EventRepository::find((int) $ref);
        }
        return preg_match('/^[a-z0-9\-]{1,80}$/', $ref) ? EventRepository::findBySlug($ref) : null;
    }

    /** id de un anfitrión del evento a partir de id o slug; null si no pertenece al evento. */
    private function hostIdFor(array $event, string $ref): ?int
    {
        $ref = trim($ref);
        if ($ref === '' || $ref === '0') {
            return null;
        }
        $hosts = PubSupport::eventHosts((int) $event['id']);
        $r = $this->resolveHost($ref, $hosts);
        return $r > 0 ? $r : null;
    }

    private function resolveHost(string $ref, array $hosts): int
    {
        foreach ($hosts as $h) {
            if ($ref !== '' && ((string) $h['id'] === $ref || (string) $h['slug'] === $ref)) {
                return (int) $h['id'];
            }
        }
        return 0;
    }

    private function quote(array $event, int $duration, int $seats, ?string $coupon): array
    {
        if (class_exists('App\\Services\\PricingService')) {
            return \App\Services\PricingService::quote($event, $duration, $seats, $coupon, null, null);
        }
        $price = round((float) $event['price'] * $seats, 2);
        $dep = 0.0;
        if ($event['deposit_type'] === 'fixed') {
            $dep = min($price, (float) $event['deposit_value']);
        } elseif ($event['deposit_type'] === 'percent') {
            $dep = round($price * (float) $event['deposit_value'] / 100, 2);
        }
        return ['ok' => true, 'price' => $price, 'discount' => 0, 'total' => $price, 'deposit_due' => $dep, 'needs_payment' => $dep > 0];
    }

    private function quoteOut(array $q): array
    {
        return [
            'price' => (float) ($q['price'] ?? 0), 'discount' => (float) ($q['discount'] ?? 0), 'total' => (float) ($q['total'] ?? 0),
            'deposit_due' => (float) ($q['deposit_due'] ?? 0), 'needs_payment' => !empty($q['needs_payment']),
            'price_text' => Fmt::money((float) ($q['price'] ?? 0)), 'discount_text' => Fmt::money((float) ($q['discount'] ?? 0)),
            'total_text' => Fmt::money((float) ($q['total'] ?? 0)), 'deposit_text' => Fmt::money((float) ($q['deposit_due'] ?? 0)),
        ];
    }

    private function quotes(array $event, array $durations): array
    {
        $out = [];
        foreach ($durations as $d) {
            try {
                $q = $this->quote($event, $d, 1, null);
                $out[(string) $d] = !empty($q['ok']) ? $this->quoteOut($q) : null;
            } catch (\Throwable $e) {
                $out[(string) $d] = null;
            }
        }
        return $out;
    }

    /** @return array{0:array,1:array} entrada para BookingService::create y errores por campo */
    private function validateBooking(Request $req, array $event): array
    {
        $errors = [];
        $cc = (string) Settings::get('phone_cc', '502');
        $durations = PubSupport::durations($event);
        $duration = (int) $req->input('duration', 0);
        if (!in_array($duration, $durations, true)) {
            $errors['_'] = 'Elige una duración válida.';
        }
        $start = (string) $req->input('start', '');
        if (!Validator::datetimeUtc($start)) {
            $errors['_'] = 'Elige un horario para tu cita.';
        }
        $tz = Tz::safe((string) $req->input('tz', ''), Settings::tz());
        $name = Str::clean((string) $req->input('name', ''), 160);
        if (mb_strlen($name) < 2 || !preg_match('/\p{L}/u', $name)) {
            $errors['name'] = 'Escribe tu nombre completo.';
        }
        $email = strtolower(Str::clean((string) $req->input('email', ''), 190));
        if (!Validator::email($email)) {
            $errors['email'] = 'Revisa tu correo: parece que le falta algo (por ejemplo, nombre@correo.com).';
        }
        $phoneRaw = Str::clean((string) $req->input('phone', ''), 30);
        $phone = null;
        if ($phoneRaw !== '') {
            $phone = Str::phone($phoneRaw, $cc);
            if ($phone === null) {
                $errors['phone'] = 'Revisa tu teléfono: en Guatemala son 8 dígitos (por ejemplo, 5555 1234) o escribe tu código de país con +.';
            }
        } elseif ((int) $event['require_phone'] === 1) {
            $errors['phone'] = 'Déjanos un teléfono para poder contactarte si hay algún cambio.';
        }
        if (!$req->bool('consent')) {
            $errors['consent'] = 'Necesitamos tu autorización para guardar tus datos y confirmar la cita.';
        }
        $notes = Str::clean((string) $req->input('notes', ''), 1500);

        // Invitados adicionales
        $guests = [];
        if ((int) $event['allow_guests'] === 1) {
            $raw = $req->input('guests', []);
            if (is_array($raw)) {
                foreach (array_slice($raw, 0, max(0, (int) $event['max_guests'])) as $i => $g) {
                    if (!is_array($g)) {
                        continue;
                    }
                    $gn = Str::clean((string) ($g['name'] ?? ''), 160);
                    $ge = strtolower(Str::clean((string) ($g['email'] ?? ''), 190));
                    if ($gn === '' && $ge === '') {
                        continue;
                    }
                    if ($gn === '') {
                        $errors['guest_' . $i] = 'Escribe el nombre de tu invitado.';
                    }
                    if ($ge !== '' && !Validator::email($ge)) {
                        $errors['guest_' . $i] = 'Revisa el correo de tu invitado.';
                    }
                    $guests[] = ['name' => $gn, 'email' => $ge !== '' ? $ge : null];
                }
            }
        }

        $seats = 1;
        if ((string) $event['kind'] === 'group') {
            $seats = max(1, min(20, (int) $req->input('seats', 1)));
        }

        [$answers, $ansErr] = $this->validateAnswers((array) ($event['fields'] ?? []), $req->input('answers', []));
        foreach ($ansErr as $k => $v) {
            $errors[$k] = $v;
        }

        $coupon = null;
        $code = strtoupper(Str::clean((string) $req->input('coupon', ''), 40));
        if ($code !== '' && (int) $event['allow_coupon'] === 1) {
            $coupon = $code;
        }

        $ref = (string) $req->input('referrer_host', '');
        $in = [
            'event_id' => (int) $event['id'],
            'duration' => $duration,
            'start' => $start,
            'host_id' => $this->hostIdFor($event, (string) $req->input('host', '')),
            'timezone' => $tz,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'notes' => $notes,
            'answers' => $answers,
            'guests' => $guests,
            'seats' => $seats,
            'coupon' => $coupon,
            'utm_source' => PubSupport::short($req->input('utm_source'), 100),
            'utm_medium' => PubSupport::short($req->input('utm_medium'), 100),
            'utm_campaign' => PubSupport::short($req->input('utm_campaign'), 100),
            'referrer_host' => preg_match('/^[A-Za-z0-9.\-]{1,190}$/', $ref) ? $ref : null,
            'consent' => true,
            'created_via' => 'public',
            'ip' => $req->ip(),
            'actor_label' => $name,
        ];
        if ((string) $event['mode'] === 'home') {
            $loc = Str::clean((string) $req->input('location', ''), 255);
            if (mb_strlen($loc) < 5) {
                $errors['location'] = 'Escribe la dirección donde te atenderemos (zona, calle y referencias).';
            }
            $in['location'] = $loc;
        }
        return [$in, $errors];
    }

    /** Valida respuestas de preguntas personalizadas respetando la lógica condicional. @return array{0:array,1:array} */
    private function validateAnswers(array $fields, $rawAnswers): array
    {
        $raw = is_array($rawAnswers) ? $rawAnswers : [];
        $byName = [];
        foreach ($fields as $f) {
            $byName[(string) $f['name']] = $f;
        }
        $visible = [];
        $isVisible = function (array $f) use (&$isVisible, &$visible, $byName, $raw): bool {
            $id = (int) $f['id'];
            if (isset($visible[$id])) {
                return $visible[$id];
            }
            $visible[$id] = true;
            $cf = (string) ($f['condition_field'] ?? '');
            if ($cf !== '' && isset($byName[$cf])) {
                $other = $byName[$cf];
                if (!$isVisible($other)) {
                    return $visible[$id] = false;
                }
                $val = $raw[(int) $other['id']] ?? ($raw[(string) $other['id']] ?? '');
                $cv = (string) ($f['condition_value'] ?? '');
                $match = is_array($val) ? in_array($cv, array_map('strval', $val), true) : ((string) $val === $cv || ($val === true && in_array(strtolower($cv), ['1', 'si', 'sí', 'true'], true)));
                return $visible[$id] = $match;
            }
            return true;
        };

        $clean = [];
        $errors = [];
        foreach ($fields as $f) {
            $id = (int) $f['id'];
            if (!$isVisible($f)) {
                continue;
            }
            $key = 'f_' . $id;
            $label = (string) $f['label'];
            $required = (int) ($f['required'] ?? 0) === 1;
            $type = (string) $f['type'];
            $opts = PubSupport::options($f['options'] ?? null);
            $v = $raw[$id] ?? ($raw[(string) $id] ?? null);
            $empty = $v === null || $v === '' || $v === false || $v === [];
            if ($type === 'consent' || ($type === 'checkbox' && !$opts)) {
                $on = $v === true || $v === 1 || $v === '1' || $v === 'on' || $v === 'Sí';
                if (!$on) {
                    if ($required) {
                        $errors[$key] = $type === 'consent' ? 'Para continuar necesitamos que aceptes: ' . $label : 'Marca esta casilla para continuar.';
                    }
                    continue;
                }
                $clean[$id] = 'Sí';
                continue;
            }
            if ($empty) {
                if ($required) {
                    $errors[$key] = 'Esta respuesta es obligatoria.';
                }
                continue;
            }
            switch ($type) {
                case 'text':
                    $clean[$id] = Str::clean(is_scalar($v) ? (string) $v : '', 500);
                    break;
                case 'textarea':
                    $clean[$id] = Str::clean(is_scalar($v) ? (string) $v : '', 3000);
                    break;
                case 'select':
                case 'radio':
                    if (!is_scalar($v) || ($opts && !in_array((string) $v, $opts, true))) {
                        $errors[$key] = 'Elige una de las opciones de la lista.';
                    } else {
                        $clean[$id] = (string) $v;
                    }
                    break;
                case 'checkbox':
                    $vals = is_array($v) ? array_map('strval', $v) : [(string) $v];
                    $bad = array_diff($vals, $opts);
                    if ($bad) {
                        $errors[$key] = 'Alguna de las opciones elegidas no es válida.';
                    } else {
                        $clean[$id] = implode(', ', $vals);
                    }
                    break;
                case 'number':
                    if (!is_scalar($v) || !is_numeric(str_replace(',', '.', (string) $v))) {
                        $errors[$key] = 'Escribe solo números.';
                    } else {
                        $clean[$id] = Str::clean((string) $v, 30);
                    }
                    break;
                case 'email':
                    if (!is_scalar($v) || !Validator::email(strtolower(trim((string) $v)))) {
                        $errors[$key] = 'Revisa el correo electrónico.';
                    } else {
                        $clean[$id] = strtolower(trim((string) $v));
                    }
                    break;
                case 'phone':
                    $ph = is_scalar($v) ? Str::phone((string) $v, (string) Settings::get('phone_cc', '502')) : null;
                    if ($ph === null) {
                        $errors[$key] = 'Revisa el teléfono: en Guatemala son 8 dígitos.';
                    } else {
                        $clean[$id] = $ph;
                    }
                    break;
                case 'date':
                    if (!is_scalar($v) || !Validator::date((string) $v)) {
                        $errors[$key] = 'Elige una fecha válida.';
                    } else {
                        $clean[$id] = (string) $v;
                    }
                    break;
                case 'file':
                    $tok = is_scalar($v) ? (string) $v : '';
                    $ok = preg_match('/^[a-f0-9]{32}$/', $tok) && Db::val("SELECT COUNT(*) FROM files WHERE token = ? AND kind = 'answer' AND owner_type IS NULL", [$tok]);
                    if (!$ok) {
                        $errors[$key] = 'No pudimos recibir el archivo. Vuelve a adjuntarlo.';
                    } else {
                        $clean[$id] = $tok;
                    }
                    break;
                default:
                    $clean[$id] = Str::clean(is_scalar($v) ? (string) $v : '', 500);
            }
        }
        return [$clean, $errors];
    }

    /** Respuesta de éxito: solo datos de la persona que reservó. */
    private function successPayload(array $event, array $res, string $tz): array
    {
        $b = $res['booking'];
        $all = $res['bookings'] ?? [$b];
        $status = (string) ($res['status'] ?? $b['status']);
        $confirmed = $status === 'confirmed';
        $sessions = [];
        foreach ($all as $row) {
            $sessions[] = [
                'date' => Fmt::dateLong((string) $row['starts_at'], $tz),
                'time' => Fmt::time((string) $row['starts_at'], $tz),
                'time24' => Tz::format((string) $row['starts_at'], $tz, 'H:i'),
                'iso' => Tz::iso((string) $row['starts_at']),
            ];
        }
        $token = (string) $res['token'];
        $host = Db::val('SELECT name FROM hosts WHERE id = ?', [(int) $b['host_id']]);
        $out = [
            'ok' => true,
            'token' => $token,
            'status' => $status,
            'needs_payment' => !empty($res['needs_payment']),
            'manage_url' => url('/reserva/' . $token),
            'summary' => [
                'event' => (string) $event['name'],
                'date' => $sessions[0]['date'],
                'time' => $sessions[0]['time'],
                'time24' => $sessions[0]['time24'],
                'iso' => $sessions[0]['iso'],
                'duration' => Fmt::duration((int) $b['duration']),
                'tz' => Fmt::tzLabel($tz, (string) $b['starts_at']),
                'host' => (string) ($host ?? ''),
                'mode' => PubSupport::modeLabel((string) $b['mode']),
                'location' => (string) ($b['location'] ?? ''),
                'video_url' => $confirmed ? PubSupport::safeUrl((string) ($b['video_url'] ?? '')) : '',
                'sessions' => count($sessions) > 1 ? $sessions : [],
                'total' => Fmt::money((float) $b['total']),
                'deposit' => Fmt::money((float) $b['deposit_due']),
            ],
            'confirm_html' => trim((string) ($event['confirm_message'] ?? '')) !== '' ? Str::richText((string) $event['confirm_message']) : '',
            'redirect_url' => PubSupport::safeUrl((string) ($event['redirect_url'] ?? '')),
            'ics_url' => url('/reserva/' . $token . '/evento.ics'),
            'google_url' => '',
            'outlook_url' => '',
            'wa_url' => '',
        ];
        try {
            $disp = BookingService::display($b);
            if (class_exists('App\\Services\\IcsService')) {
                $out['google_url'] = (string) \App\Services\IcsService::googleLink($disp);
                $out['outlook_url'] = (string) \App\Services\IcsService::outlookLink($disp);
            }
        } catch (\Throwable $e) {
            Logger::error('Enlaces de calendario no disponibles', $e);
        }
        $wa = (string) Settings::get('whatsapp', '');
        if ($wa !== '') {
            $out['wa_url'] = PubSupport::waLink($wa, 'Hola, acabo de reservar "' . $event['name'] . '" para el ' . $sessions[0]['date'] . ' a las ' . $sessions[0]['time'] . '. Mi nombre es ' . (string) $b['guest_name'] . '.');
        }
        return $out;
    }
}
