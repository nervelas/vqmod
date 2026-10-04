<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Controller;
use App\Core\Fmt;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Tz;
use App\Services\BookingException;
use App\Services\BookingService;
use App\Services\WaitlistService;

/** Oferta temporal de un horario liberado (lista de espera). */
final class WaitlistController extends Controller
{
    public function show(Request $req, array $p): Response
    {
        $token = PubSupport::token($p);
        return $this->page($token, WaitlistService::offerByToken($token), null);
    }

    public function accept(Request $req, array $p): Response
    {
        $token = PubSupport::token($p);
        if (!RateLimiter::hit(PubSupport::bucket($req, 'offer'), 12, 600)) {
            return $this->page($token, WaitlistService::offerByToken($token), 'Demasiados intentos seguidos. Espera unos minutos.', 429);
        }
        $o = WaitlistService::offerByToken($token);
        if (!$o || empty($o['event'])) {
            return $this->page($token, null, null);
        }
        if (!$req->bool('consent')) {
            return $this->page($token, $o, 'Necesitamos tu autorización para guardar tus datos y confirmar la cita.', 422);
        }
        try {
            $res = BookingService::create([
                'event_id' => (int) $o['event_type_id'],
                'duration' => (int) $o['duration'],
                'start' => (string) $o['start'],
                'host_id' => $o['host_id'] ? (int) $o['host_id'] : null,
                'timezone' => Tz::safe((string) $o['timezone'], Settings::tz()),
                'name' => (string) $o['name'],
                'email' => (string) ($o['email'] ?? ''),
                'phone' => (string) ($o['phone'] ?? ''),
                'consent' => true,
                'created_via' => 'waitlist',
                'ip' => $req->ip(),
                'actor_label' => (string) $o['name'],
            ]);
            WaitlistService::markBooked($token, (int) $res['booking']['id']);
            return Response::redirect(url('/reserva/' . $res['token'], ['m' => 'new']));
        } catch (BookingException $e) {
            return $this->page($token, $o, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            Logger::error('Falló la reserva desde lista de espera', $e);
            return $this->page($token, $o, 'No pudimos confirmar tu cita en este momento. Inténtalo de nuevo.', 500);
        }
    }

    private function page(string $token, ?array $o, ?string $error, int $status = 200): Response
    {
        $info = null;
        if ($o && !empty($o['event'])) {
            $tz = Tz::safe((string) $o['timezone'], Settings::tz());
            $info = [
                'event' => (string) $o['event']['name'],
                'date' => Fmt::dateLong((string) $o['start'], $tz),
                'time' => Fmt::time((string) $o['start'], $tz),
                'duration' => Fmt::duration((int) $o['duration']),
                'mode' => PubSupport::modeLabel((string) $o['event']['mode']),
                'name' => (string) $o['name'],
                'deadline_iso' => Tz::iso((string) $o['offer_expires_at']),
                'seconds_left' => (int) ($o['seconds_left'] ?? 0),
                'slug' => (string) $o['event']['slug'],
            ];
        }
        $r = $this->view('public/offer', [
            'title' => 'Se liberó un horario',
            'noindex' => true,
            'biz' => PubSupport::biz(),
            'token' => $token,
            'info' => $info,
            'error' => $error,
            'nav' => '',
            'scripts' => ['js/public.js'],
            'styles' => ['css/booking.css'],
            'bodyClass' => 'pub-offer',
        ], 'layouts/public');
        $r->status = $info ? $status : 410;
        $r->header('Cache-Control', 'private, no-store');
        return $r;
    }
}
