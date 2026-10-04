<?php
declare(strict_types=1);

namespace Aurea\Controllers;

use Aurea\Core\Captcha;
use Aurea\Core\Controller;
use Aurea\Core\Db;
use Aurea\Core\RateLimit;
use Aurea\Core\Response;
use Aurea\Core\Settings;
use Aurea\Core\Util;
use Aurea\Services\AvailabilityService;
use Aurea\Services\BookingService;
use Aurea\Services\CouponService;
use Aurea\Services\WaitlistService;

/** Endpoints JSON de la reserva pública. Toda entrada se valida en servidor. */
final class BookingApiController extends Controller
{
    private function err(string $msg, int $status = 422, array $extra = []): Response
    {
        return $this->json(['ok' => false, 'error' => $msg] + $extra, $status);
    }

    /** Resuelve servicio + profesionales candidatos desde parámetros de consulta. */
    private function context(): ?array
    {
        $svc = Db::one('SELECT * FROM services WHERE id=? AND active=1', [$this->req->int('service')]);
        if (!$svc) { return null; }
        $pro = $this->req->str('professional', 'any');
        $loc = $this->req->int('location') ?: null;
        $profs = AvailabilityService::professionalsFor((int)$svc['id'], $loc, ($pro === 'any' || $pro === '') ? null : (int)$pro);
        $opts = [];
        // Reprogramación por token: el horario actual de la cita no debe bloquearse a sí mismo
        $tok = $this->req->str('exclude');
        if (preg_match('/^[a-f0-9]{32}$/', $tok)) {
            $id = Db::val('SELECT id FROM appointments WHERE token=?', [$tok]);
            if ($id) { $opts['exclude'] = (int)$id; }
        }
        return [$svc, $profs, $loc, $opts];
    }

    public function slots(): Response
    {
        if (!RateLimit::hit('slots', $this->req->ip(), 240, 300)) { return $this->err('Demasiadas consultas. Espera un momento.', 429); }
        $c = $this->context();
        $date = $this->req->str('date');
        if (!$c || !Util::isDate($date)) { return $this->err('Parámetros inválidos.', 400); }
        [$svc, $profs, $loc, $opts] = $c;
        return $this->json(['ok' => true, 'date' => $date, 'slots' => AvailabilityService::daySlots($svc, $profs, $date, $loc, $opts)]);
    }

    public function days(): Response
    {
        if (!RateLimit::hit('slots', $this->req->ip(), 240, 300)) { return $this->err('Demasiadas consultas. Espera un momento.', 429); }
        $c = $this->context();
        $ym = $this->req->str('month');
        if (!$c || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) { return $this->err('Parámetros inválidos.', 400); }
        [$svc, $profs, $loc, $opts] = $c;
        $out = ['ok' => true, 'month' => $ym, 'days' => (object)AvailabilityService::monthDays($svc, $profs, $ym, $loc, $opts)];
        if ($this->req->str('next') === '1') {
            $n = AvailabilityService::nextAvailable($svc, $profs, $loc, $opts);
            $out['next'] = $n ? ['start' => $n['start'], 'date' => substr($n['start'], 0, 10), 'time' => $n['time'], 'label' => Util::dateLong($n['start'])] : null;
        }
        return $this->json($out);
    }

    public function book(): Response
    {
        $ip = $this->req->ip();
        // Honeypot: un humano nunca llena este campo
        if ($this->req->str('website') !== '' || $this->req->str('company_url') !== '') {
            return $this->err('No pudimos procesar la solicitud.', 400);
        }
        if (!RateLimit::hit('book', $ip, 6, 600) || !RateLimit::hit('book_day', $ip, 40, 86400)) {
            return $this->err('Has enviado demasiadas solicitudes. Intenta de nuevo en unos minutos.', 429);
        }
        if (!Captcha::verify($this->req)) { return $this->err('No pudimos verificar que eres una persona. Intenta de nuevo.', 422, ['code' => 'captcha']); }
        $files = [];
        foreach ($_FILES as $k => $f) { if (preg_match('/^file_(\d+)$/', (string)$k, $m) && is_array($f) && !is_array($f['name'])) { $files[(int)$m[1]] = $f; } }
        $answers = is_array($this->req->post['answers'] ?? null) ? $this->req->post['answers'] : [];
        $in = [
            'service_id' => $this->req->int('service_id'),
            'professional_id' => $this->req->str('professional_id', 'any'),
            'location_id' => $this->req->int('location_id') ?: null,
            'start' => $this->req->str('start'),
            'client' => ['name' => $this->req->str('name'), 'phone' => $this->req->str('phone'), 'cc' => $this->req->str('cc', '502'), 'email' => $this->req->str('email'),
                'nit' => $this->req->str('nit'), 'consent' => $this->req->str('consent') === '1'],
            'answers' => $answers, 'files' => $files, 'coupon' => $this->req->str('coupon'),
            'client_note' => $this->req->str('client_note'), 'home_address' => $this->req->str('home_address'),
        ];
        $r = BookingService::create($in, ['staff' => false, 'source' => 'web', 'ip' => $ip]);
        if (!$r['ok']) {
            $status = ($r['code'] ?? '') === 'slot_taken' ? 409 : 422;
            return $this->json($r, $status);
        }
        return $this->json(['ok' => true, 'token' => $r['token'], 'url' => url('/cita/' . $r['token'] . '?nueva=1')]);
    }

    public function coupon(): Response
    {
        if (!RateLimit::hit('coupon', $this->req->ip(), 20, 600)) { return $this->err('Demasiados intentos. Espera unos minutos.', 429); }
        $svc = Db::one('SELECT * FROM services WHERE id=? AND active=1', [$this->req->int('service_id')]);
        if (!$svc) { return $this->err('Servicio inválido.', 400); }
        $price = (float)$svc['price'];
        $pid = $this->req->int('professional_id');
        if ($pid) {
            $o = Db::val('SELECT price_override FROM professional_services WHERE professional_id=? AND service_id=?', [$pid, $svc['id']]);
            if ($o !== null) { $price = (float)$o; }
        }
        [$c, $disc, $e] = CouponService::evaluate($this->req->str('code'), (int)$svc['id'], $price);
        if ($e !== '') { return $this->json(['ok' => false, 'error' => $e]); }
        $total = round($price - $disc, 2);
        return $this->json(['ok' => true, 'discount' => $disc, 'total' => $total, 'deposit' => BookingService::depositFor($svc, $total)]);
    }

    public function waitlist(): Response
    {
        if ($this->req->str('website') !== '') { return $this->err('No pudimos procesar la solicitud.', 400); }
        if (!Settings::bool('waitlist_enabled', true)) { return $this->err('La lista de espera no está disponible.', 400); }
        if (!RateLimit::hit('waitlist', $this->req->ip(), 5, 3600)) { return $this->err('Demasiadas solicitudes. Intenta más tarde.', 429); }
        if (!Captcha::verify($this->req)) { return $this->err('No pudimos verificar que eres una persona.', 422); }
        $svc = Db::one('SELECT id FROM services WHERE id=? AND active=1', [$this->req->int('service_id')]);
        $name = Util::limit($this->req->str('name'), 150);
        $ph = Util::phone($this->req->str('phone'), $this->req->str('cc', '502'));
        $email = $this->req->str('email');
        $from = $this->req->str('date_from'); $to = $this->req->str('date_to');
        $errors = [];
        if (!$svc) { $errors['service'] = 'Servicio inválido.'; }
        if (mb_strlen($name) < 2) { $errors['name'] = 'Escribe tu nombre.'; }
        if (!$ph) { $errors['phone'] = 'Teléfono inválido.'; }
        if ($email !== '' && !Util::isEmail($email)) { $errors['email'] = 'Correo inválido.'; }
        if (!Util::isDate($from) || !Util::isDate($to) || $to < $from || $to < date('Y-m-d') || strtotime($to) - strtotime($from) > 120 * 86400) { $errors['dates'] = 'Rango de fechas inválido.'; }
        if ($this->req->str('consent') !== '1') { $errors['consent'] = 'Debes aceptar el aviso de privacidad.'; }
        if ($errors) { return $this->json(['ok' => false, 'error' => 'Revisa los datos.', 'fields' => $errors], 422); }
        $pid = $this->req->int('professional_id');
        if ($pid && !Db::val('SELECT id FROM professionals WHERE id=? AND active=1', [$pid])) { $pid = 0; }
        WaitlistService::join(['name' => $name, 'cc' => $ph[0], 'phone' => $ph[1], 'email' => $email, 'service_id' => (int)$svc['id'], 'professional_id' => $pid,
            'date_from' => max($from, date('Y-m-d')), 'date_to' => $to, 'note' => $this->req->str('note')]);
        return $this->json(['ok' => true, 'message' => 'Listo. Te avisaremos por WhatsApp o correo si se libera un horario.']);
    }
}
