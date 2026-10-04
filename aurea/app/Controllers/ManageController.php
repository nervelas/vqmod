<?php
declare(strict_types=1);

namespace Aurea\Controllers;

use Aurea\Core\Brand;
use Aurea\Core\Controller;
use Aurea\Core\Db;
use Aurea\Core\Ics;
use Aurea\Core\Logger;
use Aurea\Core\RateLimit;
use Aurea\Core\Response;
use Aurea\Core\Settings;
use Aurea\Core\Upload;
use Aurea\Core\Util;
use Aurea\Core\View;
use Aurea\Services\BookingService;
use Aurea\Services\NotificationService;

/** Páginas del cliente accesibles con enlace único (token de 128 bits). Sin inicio de sesión. */
final class ManageController extends Controller
{
    private function load(): ?array
    {
        $tok = (string)($this->req->params['token'] ?? '');
        $a = Db::one('SELECT id FROM appointments WHERE token=?', [$tok]);
        return $a ? NotificationService::appointment((int)$a['id']) : null;
    }

    private function policyOk(array $a): bool
    {
        if (!in_array($a['status'], ['pending', 'confirmed'], true)) { return false; }
        return strtotime($a['start_at']) - time() >= Settings::int('cancel_min_hours', 12) * 3600;
    }

    private function eventData(array $a): array
    {
        return ['uid' => 'appt-' . $a['id'] . '@' . preg_replace('/[^a-z0-9.\-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'aurea')), 'start' => $a['start_at'], 'end' => $a['end_at'],
            'summary' => $a['service_name'] . ' · ' . Settings::get('business_name', ''), 'status' => $a['status'],
            'description' => 'Profesional: ' . $a['prof_name'] . "\nGestiona tu cita: " . abs_url('/cita/' . $a['token']),
            'location' => NotificationService::whereText($a), 'url' => abs_url('/cita/' . $a['token'])];
    }

    public function show(): Response
    {
        $a = $this->load();
        if (!$a) { return $this->notFound(); }
        if ($a['status'] === 'rescheduled') {
            $new = Db::val('SELECT token FROM appointments WHERE rescheduled_from=? ORDER BY id DESC LIMIT 1', [$a['id']]);
            if ($new) { return $this->redirect('/cita/' . $new); }
        }
        View::$title = __('Tu %s', mb_strtolower(term('appt')));
        View::$robots = 'noindex,nofollow';
        $payments = Db::all('SELECT * FROM payments WHERE appointment_id=? ORDER BY id', [$a['id']]);
        $paid = 0.0;
        foreach ($payments as $p) { if ($p['status'] === 'confirmed') { $paid += (float)$p['amount']; } }
        $ev = $this->eventData($a);
        return $this->html('public', 'public/manage', [
            'a' => $a, 'canSelf' => $this->policyOk($a), 'new' => $this->req->str('nueva') === '1', 'paid' => $paid, 'payments' => $payments,
            'google' => Ics::googleLink($ev), 'outlook' => Ics::outlookLink($ev), 'minHours' => Settings::int('cancel_min_hours', 12),
            'wa' => Brand::waUrl(__('Hola, escribo sobre mi %s del %s a las %s.', mb_strtolower(term('appt')), fdate($a['start_at']), ftime($a['start_at']))),
            'msg' => $_GET['m'] ?? '', 'noFab' => true,
        ]);
    }

    public function ics(): Response
    {
        $a = $this->load();
        if (!$a) { return $this->notFound(); }
        return Response::download(Ics::calendar([$this->eventData($a)], (string)Settings::get('business_name', 'Cita')), 'cita.ics', 'text/calendar; charset=utf-8');
    }

    public function confirm(): Response
    {
        $a = $this->load();
        if (!$a) { return $this->notFound(); }
        if (in_array($a['status'], ['pending', 'confirmed'], true)) {
            Db::exec('UPDATE appointments SET client_confirmed_at=? WHERE id=? AND client_confirmed_at IS NULL', [date('Y-m-d H:i:s'), $a['id']]);
            BookingService::history((int)$a['id'], 'client_confirmed', 'El cliente confirmó su asistencia');
        }
        return $this->redirect('/cita/' . $a['token'] . '?m=confirmada');
    }

    public function cancel(): Response
    {
        $a = $this->load();
        if (!$a) { return $this->notFound(); }
        if (!$this->policyOk($a)) { return $this->redirect('/cita/' . $a['token'] . '?m=politica'); }
        if (!RateLimit::hit('manage', $this->req->ip(), 20, 600)) { return $this->redirect('/cita/' . $a['token'] . '?m=limite'); }
        [$ok] = BookingService::setStatus((int)$a['id'], 'cancelled', 'Cancelada por el cliente: ' . Util::limit($this->req->str('reason'), 150));
        return $this->redirect('/cita/' . $a['token'] . '?m=' . ($ok ? 'cancelada' : 'error'));
    }

    public function rescheduleForm(): Response
    {
        $a = $this->load();
        if (!$a) { return $this->notFound(); }
        if (!$this->policyOk($a)) { return $this->redirect('/cita/' . $a['token'] . '?m=politica'); }
        View::$title = __('Reprogramar %s', mb_strtolower(term('appt')));
        View::$robots = 'noindex,nofollow';
        $svc = Db::one('SELECT * FROM services WHERE id=?', [$a['service_id']]);
        return $this->html('public', 'public/reschedule', ['a' => $a, 'svc' => $svc, 'noFab' => true, 'scripts' => ['js/reschedule.js'],
            'boot' => ['base' => base_path(), 'today' => date('Y-m-d'), 'csrf' => \Aurea\Core\Csrf::token('public'), 'service' => (int)$a['service_id'], 'professional' => (int)$a['professional_id'],
                'location' => $a['location_id'] ? (int)$a['location_id'] : 0, 'token' => $a['token'], 'tzLabel' => 'hora de ' . str_replace('_', ' ', (string)(explode('/', (string)Settings::get('timezone', 'America/Guatemala'))[1] ?? '')) . ' (UTC' . date('P') . ')']]);
    }

    public function reschedule(): Response
    {
        $a = $this->load();
        if (!$a) { return $this->notFound(); }
        if (!$this->policyOk($a)) { return $this->redirect('/cita/' . $a['token'] . '?m=politica'); }
        if (!RateLimit::hit('manage', $this->req->ip(), 20, 600)) { return $this->redirect('/cita/' . $a['token'] . '?m=limite'); }
        $r = BookingService::reschedule((int)$a['id'], $this->req->str('start'), null, ['staff' => false]);
        if (!$r['ok']) {
            return $this->redirect('/cita/' . $a['token'] . '/reprogramar?e=' . rawurlencode((string)$r['error']));
        }
        return $this->redirect('/cita/' . $r['token'] . '?m=reprogramada');
    }

    public function receipt(): Response
    {
        $a = $this->load();
        if (!$a) { return $this->notFound(); }
        if (!in_array($a['status'], ['pending', 'confirmed'], true)) { return $this->redirect('/cita/' . $a['token']); }
        if (!RateLimit::hit('receipt', $this->req->ip(), 6, 3600)) { return $this->redirect('/cita/' . $a['token'] . '?m=limite'); }
        $f = $_FILES['file'] ?? null;
        $amount = (float)str_replace(',', '.', $this->req->str('amount', '0'));
        if (!is_array($f) || $amount <= 0 || $amount > 1000000) { return $this->redirect('/cita/' . $a['token'] . '?m=comprobante_datos'); }
        $pid = Db::insert('payments', ['appointment_id' => $a['id'], 'amount' => round($amount, 2), 'method' => 'transferencia', 'reference' => Util::limit($this->req->str('reference'), 120),
            'status' => 'pending', 'note' => 'Comprobante enviado por el cliente', 'paid_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]);
        try {
            $fid = Upload::privateDoc($f, 'payment', $pid);
            Db::update('payments', $pid, ['file_id' => $fid]);
        } catch (\RuntimeException $e) {
            Db::delete('payments', $pid);
            return $this->redirect('/cita/' . $a['token'] . '?m=' . rawurlencode('archivo:' . $e->getMessage()));
        }
        BookingService::history((int)$a['id'], 'receipt', 'El cliente subió un comprobante de pago');
        return $this->redirect('/cita/' . $a['token'] . '?m=comprobante');
    }

    public function reviewForm(): Response
    {
        $a = $this->load();
        if (!$a || $a['status'] !== 'completed') { return $this->notFound(); }
        View::$title = __('Tu opinión');
        View::$robots = 'noindex,nofollow';
        $done = (bool)Db::val('SELECT id FROM reviews WHERE appointment_id=?', [$a['id']]);
        return $this->html('public', 'public/review', ['a' => $a, 'done' => $done || $this->req->str('ok') === '1', 'noFab' => true]);
    }

    public function reviewSave(): Response
    {
        $a = $this->load();
        if (!$a || $a['status'] !== 'completed') { return $this->notFound(); }
        $rating = $this->req->int('rating');
        if ($rating < 1 || $rating > 5 || Db::val('SELECT id FROM reviews WHERE appointment_id=?', [$a['id']])) { return $this->redirect('/resena/' . $a['token']); }
        if (!RateLimit::hit('review', $this->req->ip(), 5, 3600)) { return $this->redirect('/resena/' . $a['token']); }
        Db::insert('reviews', ['appointment_id' => $a['id'], 'professional_id' => $a['professional_id'], 'client_id' => $a['client_id'], 'rating' => $rating,
            'comment' => Util::limit($this->req->str('comment'), 800), 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')]);
        return $this->redirect('/resena/' . $a['token'] . '?ok=1');
    }

    private function offer(): ?array
    {
        $w = Db::one('SELECT w.*, s.name service_name, p.name prof_name FROM waitlist w JOIN services s ON s.id=w.service_id LEFT JOIN professionals p ON p.id=w.offer_professional_id WHERE w.offer_token=?', [$this->req->params['token'] ?? '']);
        return $w ?: null;
    }

    public function waitOffer(): Response
    {
        $w = $this->offer();
        if (!$w) { return $this->notFound(); }
        View::$title = __('Horario disponible');
        View::$robots = 'noindex,nofollow';
        $valid = $w['status'] === 'offered' && $w['offer_expires'] >= date('Y-m-d H:i:s');
        return $this->html('public', 'public/offer', ['w' => $w, 'valid' => $valid, 'noFab' => true, 'err' => $this->req->str('e')]);
    }

    public function waitAccept(): Response
    {
        $w = $this->offer();
        if (!$w || $w['status'] !== 'offered' || $w['offer_expires'] < date('Y-m-d H:i:s')) { return $this->redirect('/espera/' . ($this->req->params['token'] ?? '')); }
        $r = BookingService::create(['service_id' => (int)$w['service_id'], 'professional_id' => (int)$w['offer_professional_id'], 'start' => $w['offer_start'],
            'client' => ['name' => $w['name'], 'phone' => $w['phone'], 'cc' => $w['phone_cc'], 'email' => $w['email'], 'consent' => true]],
            ['staff' => false, 'source' => 'web', 'ip' => $this->req->ip(), 'skip_required' => true]);
        if (!$r['ok']) { return $this->redirect('/espera/' . $w['offer_token'] . '?e=' . rawurlencode((string)$r['error'])); }
        Db::update('waitlist', (int)$w['id'], ['status' => 'booked']);
        return $this->redirect('/cita/' . $r['token'] . '?nueva=1');
    }
}
