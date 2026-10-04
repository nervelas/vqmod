<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Clock;
use App\Core\Controller;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Upload;
use App\Core\Validator;
use App\Services\BookingException;
use App\Services\BookingService;

/** Confirmación y gestión de una cita por su enlace privado (token de 128 bits). */
final class ManageController extends Controller
{
    private const MESSAGES = [
        'cancelled' => ['ok', 'Tu cita fue cancelada. Gracias por avisarnos con tiempo.'],
        'rescheduled' => ['ok', 'Listo, tu cita quedó reprogramada. Te enviamos la nueva fecha por correo.'],
        'proof' => ['ok', 'Recibimos tu comprobante. En cuanto lo verifiquemos te confirmaremos el pago.'],
        'new' => ['ok', ''],
    ];
    private const STATUS = [
        'pending' => ['Pendiente de aprobación', 'warn'],
        'confirmed' => ['Confirmada', 'ok'],
        'cancelled' => ['Cancelada', 'err'],
        'completed' => ['Completada', 'muted'],
        'no_show' => ['No asistió', 'err'],
        'rejected' => ['No aprobada', 'err'],
    ];

    public function show(Request $req, array $p): Response
    {
        $b = $this->load($req, $p);
        $m = (string) $req->get('m', '');
        $flash = isset(self::MESSAGES[$m]) && self::MESSAGES[$m][1] !== '' ? self::MESSAGES[$m] : null;
        return $this->render($req, $b, $flash ? ['type' => $flash[0], 'text' => $flash[1]] : null);
    }

    public function cancel(Request $req, array $p): Response
    {
        $b = $this->load($req, $p, 'manage');
        if (!RateLimiter::hit(PubSupport::bucket($req, 'manage'), 20, 600)) {
            return $this->render($req, $b, ['type' => 'err', 'text' => 'Demasiados intentos seguidos. Espera unos minutos.'], 429);
        }
        try {
            $rules = BookingService::guestRules($b);
            if (empty($rules['cancel'])) {
                return $this->render($req, $b, ['type' => 'err', 'text' => (string) ($rules['reason'] ?: 'Esta cita ya no se puede cancelar desde aquí. Escríbenos y con gusto te ayudamos.')], 422);
            }
            $reason = Str::clean((string) $req->input('reason', ''), 500);
            BookingService::cancel((int) $b['id'], $reason, ['type' => 'guest', 'label' => (string) $b['guest_name'], 'user_id' => null]);
            return Response::redirect(url('/reserva/' . $b['token'], ['m' => 'cancelled']));
        } catch (BookingException $e) {
            return $this->render($req, $b, ['type' => 'err', 'text' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Logger::error('Falló la cancelación por el invitado', $e);
            return $this->render($req, $b, ['type' => 'err', 'text' => 'No pudimos cancelar tu cita en este momento. Inténtalo de nuevo o escríbenos.'], 500);
        }
    }

    public function reschedule(Request $req, array $p): Response
    {
        $b = $this->load($req, $p, 'manage');
        if (!RateLimiter::hit(PubSupport::bucket($req, 'manage'), 20, 600)) {
            return $this->render($req, $b, ['type' => 'err', 'text' => 'Demasiados intentos seguidos. Espera unos minutos.'], 429);
        }
        try {
            $rules = BookingService::guestRules($b);
            if (empty($rules['reschedule'])) {
                return $this->render($req, $b, ['type' => 'err', 'text' => (string) ($rules['reason'] ?: 'Esta cita ya no se puede reprogramar desde aquí. Escríbenos y con gusto te ayudamos.')], 422);
            }
            $start = (string) $req->input('start', '');
            if (!Validator::datetimeUtc($start)) {
                return $this->render($req, $b, ['type' => 'err', 'text' => 'Elige un nuevo horario antes de confirmar.'], 422);
            }
            BookingService::reschedule((int) $b['id'], $start, ['type' => 'guest', 'label' => (string) $b['guest_name'], 'user_id' => null]);
            return Response::redirect(url('/reserva/' . $b['token'], ['m' => 'rescheduled']));
        } catch (BookingException $e) {
            return $this->render($req, $b, ['type' => 'err', 'text' => $e->getMessage()], $e->errorCode === 'slot_unavailable' ? 409 : 422);
        } catch (\Throwable $e) {
            Logger::error('Falló la reprogramación por el invitado', $e);
            return $this->render($req, $b, ['type' => 'err', 'text' => 'No pudimos reprogramar tu cita en este momento. Inténtalo de nuevo.'], 500);
        }
    }

    public function proof(Request $req, array $p): Response
    {
        $b = $this->load($req, $p, 'manage');
        if (!RateLimiter::hit(PubSupport::bucket($req, 'proof'), 8, 600)) {
            return $this->render($req, $b, ['type' => 'err', 'text' => 'Has subido varios archivos seguidos. Espera unos minutos.'], 429);
        }
        if (!in_array($b['status'], ['pending', 'confirmed'], true)) {
            return $this->render($req, $b, ['type' => 'err', 'text' => 'Esta cita ya no admite comprobantes de pago.'], 422);
        }
        $f = $req->files['proof'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->render($req, $b, ['type' => 'err', 'text' => 'Elige la foto o el PDF de tu comprobante.'], 422);
        }
        try {
            $row = Upload::store($f, ['kind' => 'proof', 'owner_type' => 'booking', 'owner_id' => (int) $b['id'], 'is_public' => false, 'max_bytes' => 6 * 1024 * 1024]);
            if (!class_exists('App\\Services\\PaymentService')) {
                Logger::error('PaymentService no disponible al recibir comprobante');
                return $this->render($req, $b, ['type' => 'err', 'text' => 'No pudimos registrar tu comprobante por ahora. Escríbenos por WhatsApp y lo revisamos.'], 503);
            }
            \App\Services\PaymentService::proof((int) $b['id'], (int) $row['id']);
            return Response::redirect(url('/reserva/' . $b['token'], ['m' => 'proof']));
        } catch (\RuntimeException $e) {
            return $this->render($req, $b, ['type' => 'err', 'text' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Logger::error('Falló el comprobante de pago', $e);
            return $this->render($req, $b, ['type' => 'err', 'text' => 'No pudimos guardar tu comprobante. Inténtalo de nuevo.'], 500);
        }
    }

    public function ics(Request $req, array $p): Response
    {
        $b = $this->load($req, $p);
        if (!class_exists('App\\Services\\IcsService')) {
            throw new HttpException(404);
        }
        try {
            $ics = \App\Services\IcsService::generate(BookingService::display($b));
        } catch (\Throwable $e) {
            Logger::error('No se pudo generar el .ics', $e);
            throw new HttpException(404);
        }
        $r = Response::download($ics, 'text/calendar', 'cita.ics');
        $r->headers['Content-Disposition'] = 'attachment; filename="cita.ics"';
        return $r;
    }

    public function receipt(Request $req, array $p): Response
    {
        $b = $this->load($req, $p);
        $d = $this->display($b);
        $tz = $this->guestTz($b);
        $pays = Db::all("SELECT amount, method, reference, created_at FROM payments WHERE booking_id = ? AND status = 'verified' ORDER BY created_at", [(int) $b['id']]);
        $methods = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card_onsite' => 'Tarjeta en el lugar', 'link' => 'Enlace de pago', 'package' => 'Paquete de sesiones', 'gift_card' => 'Certificado de regalo', 'other' => 'Otro'];
        foreach ($pays as &$py) {
            $py['_method'] = $methods[$py['method']] ?? 'Otro';
            $py['_date'] = Fmt::dateShort((string) $py['created_at'], $tz);
        }
        $r = $this->view('public/receipt', [
            'title' => 'Recibo de tu cita',
            'noindex' => true,
            'biz' => PubSupport::biz(),
            'b' => $b, 'd' => $d, 'tz' => $tz, 'payments' => $pays,
            'number' => 'R-' . str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT),
            'issued' => Fmt::dateLong(Clock::utc(), $tz),
            'styles' => ['css/booking.css'],
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-receipt',
        ], 'layouts/public');
        $r->header('Cache-Control', 'private, no-store');
        return $r;
    }

    // ------------------------------------------------------------------ Internos
    private function load(Request $req, array $p, string $scope = ''): array
    {
        $token = PubSupport::token($p);
        $b = BookingService::findByToken($token);
        if (!$b) {
            // Mismo 404 que cualquier otra ruta: no se revela si el token existió.
            throw new HttpException(404);
        }
        return $b;
    }

    private function guestTz(array $b): string
    {
        return Tz::safe((string) ($b['guest_timezone'] ?? ''), Settings::tz());
    }

    private function display(array $b): array
    {
        try {
            return BookingService::display($b);
        } catch (\Throwable $e) {
            Logger::error('No se pudo armar la vista de la cita', $e);
            return ['event' => Db::one('SELECT * FROM event_types WHERE id = ?', [(int) $b['event_type_id']]) ?: [], 'host' => Db::one('SELECT id, name, title FROM hosts WHERE id = ?', [(int) $b['host_id']]) ?: [], 'attendees' => [], 'answers' => []];
        }
    }

    private function render(Request $req, array $b, ?array $flash, int $status = 200): Response
    {
        $d = $this->display($b);
        $tz = $this->guestTz($b);
        $event = (array) ($d['event'] ?? []);
        $host = (array) ($d['host'] ?? []);
        $rules = ['cancel' => false, 'reschedule' => false, 'reason' => '', 'deadline_utc' => ''];
        try {
            $rules = array_merge($rules, BookingService::guestRules($b));
        } catch (\Throwable $e) {
            Logger::error('guestRules falló', $e);
        }
        $series = [];
        if (!empty($b['series_token'])) {
            foreach (Db::all("SELECT starts_at, status, series_index FROM bookings WHERE series_token = ? AND status IN ('pending','confirmed') ORDER BY starts_at", [$b['series_token']]) as $row) {
                $series[] = ['date' => Fmt::dateLong((string) $row['starts_at'], $tz), 'time' => Fmt::time((string) $row['starts_at'], $tz)];
            }
        }
        $active = in_array($b['status'], ['pending', 'confirmed'], true);
        $future = Tz::ts((string) $b['starts_at']) > Clock::now();
        $due = 0.0;
        $payState = 'none';
        if ($active && (float) $b['total'] > 0 && in_array((string) $b['payment_status'], ['none', 'pending', 'partial'], true)) {
            $target = (float) $b['deposit_due'] > 0 ? (float) $b['deposit_due'] : (float) $b['total'];
            $due = max(0.0, round($target - (float) $b['paid_amount'], 2));
            $payState = $due > 0 ? 'due' : 'none';
            $hasProof = (int) Db::val("SELECT COUNT(*) FROM payments WHERE booking_id = ? AND status = 'pending' AND proof_file_id IS NOT NULL", [(int) $b['id']]) > 0;
            if ($hasProof) {
                $payState = 'review';
            }
        }
        $biz = PubSupport::biz();
        $when = Fmt::dateLong((string) $b['starts_at'], $tz) . ', ' . Fmt::time((string) $b['starts_at'], $tz);
        $wa = $biz['whatsapp'] !== '' ? PubSupport::waLink($biz['whatsapp'], 'Hola, tengo una consulta sobre mi ' . $biz['terms_label'] . ' "' . ($event['name'] ?? '') . '" del ' . $when . '. Soy ' . $b['guest_name'] . '.') : '';
        $google = '';
        $outlook = '';
        if ($active && class_exists('App\\Services\\IcsService')) {
            try {
                $google = (string) \App\Services\IcsService::googleLink($d);
                $outlook = (string) \App\Services\IcsService::outlookLink($d);
            } catch (\Throwable $e) {
                Logger::error('Enlaces de calendario no disponibles', $e);
            }
        }
        $resched = null;
        if (!empty($rules['reschedule']) && $event) {
            $durs = PubSupport::durations($event);
            $resched = [
                'event' => (string) ($event['slug'] ?? ''),
                'duration' => in_array((int) $b['duration'], $durs, true) ? (int) $b['duration'] : PubSupport::defaultDuration($event, $durs),
                'tz' => $tz,
                'host' => (int) $b['host_id'],
                'exclude' => (string) $b['token'],
            ];
        }
        $boot = [
            'base' => rtrim(url('/'), '/'),
            'urls' => ['slots' => url('/_/slots')],
            'time_format' => (string) Settings::get('time_format', '12') === '24' ? '24' : '12',
            'tz' => $tz,
            'business_tz' => Settings::tz(),
            'target_iso' => $active && $future ? Tz::iso((string) $b['starts_at']) : '',
            'reschedule' => $resched,
            'event_color' => PubSupport::hexColor((string) ($event['color'] ?? ''), '#C9A050'),
        ];
        $bank = trim((string) Settings::get('bank_info', ''));
        $r = $this->view('public/manage', [
            'title' => 'Tu cita',
            'noindex' => true,
            'biz' => $biz,
            'b' => $b, 'event' => $event, 'host' => $host, 'd' => $d,
            'tz' => $tz,
            'tzLabel' => Fmt::tzLabel($tz, (string) $b['starts_at']),
            'statusInfo' => self::STATUS[$b['status']] ?? [$b['status'], 'muted'],
            'when' => $when,
            'whenDate' => Fmt::dateLong((string) $b['starts_at'], $tz),
            'whenTime' => Fmt::time((string) $b['starts_at'], $tz),
            'rules' => $rules,
            'deadline' => !empty($rules['deadline_utc']) ? Fmt::dateTime((string) $rules['deadline_utc'], $tz) : '',
            'series' => $series,
            'active' => $active, 'future' => $future,
            'due' => $due, 'payState' => $payState,
            'bank' => $bank,
            'payLink' => PubSupport::safeUrl((string) Settings::get('payment_link', '')),
            'waLink' => $wa, 'google' => $google, 'outlook' => $outlook,
            'modeLabel' => PubSupport::modeLabel((string) $b['mode']),
            'videoUrl' => $b['status'] === 'confirmed' ? PubSupport::safeUrl((string) ($b['video_url'] ?? '')) : '',
            'flash' => $flash,
            'resched' => $resched,
            'boot' => $boot,
            'styles' => ['css/booking.css'],
            'scripts' => ['js/public.js', 'js/dial.js', 'js/booking.js'],
            'bodyClass' => 'pub-manage',
        ], 'layouts/public');
        $r->status = $status;
        $r->header('Cache-Control', 'private, no-store');
        $r->header('Referrer-Policy', 'no-referrer');
        return $r;
    }
}
