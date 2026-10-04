<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Settings;
use Aurea\Core\Upload;
use Aurea\Core\Util;
use Aurea\Core\View;
use Aurea\Services\AvailabilityService;
use Aurea\Services\BookingService;
use Aurea\Services\FormService;
use Aurea\Services\NotificationService;
use Aurea\Services\TemplateService;

final class AppointmentController extends AdminController
{
    public const METHODS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'deposito' => 'Depósito bancario', 'tarjeta' => 'Tarjeta en sitio', 'enlace' => 'Enlace de pago externo', 'otro' => 'Otro'];

    public function index(): Response
    {
        $this->need('appointments');
        $q = $this->req->str('q'); $status = $this->req->str('estado');
        $from = Util::isDate($this->req->str('desde')) ? $this->req->str('desde') : date('Y-m-d');
        $to = Util::isDate($this->req->str('hasta')) ? $this->req->str('hasta') : date('Y-m-d', strtotime('+60 days'));
        $prof = $this->req->int('prof');
        $where = ['a.start_at>=?', 'a.start_at<?']; $params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
        if ($this->scopePro()) { $where[] = 'a.professional_id=?'; $params[] = $this->scopePro(); }
        elseif ($prof) { $where[] = 'a.professional_id=?'; $params[] = $prof; }
        if (isset(BookingService::STATUSES[$status])) { $where[] = 'a.status=?'; $params[] = $status; }
        if ($q !== '') { $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like, $like); }
        $w = implode(' AND ', $where);
        $total = (int)Db::val("SELECT COUNT(*) FROM appointments a JOIN clients c ON c.id=a.client_id WHERE $w", $params);
        $pg = $this->paginate($total);
        $rows = Db::all("SELECT a.*, c.name client_name, c.phone_cc, c.phone client_phone, s.name service_name, p.name prof_name, p.color prof_color FROM appointments a
            JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id JOIN professionals p ON p.id=a.professional_id WHERE $w ORDER BY a.start_at LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
        $profs = Db::all('SELECT id,name FROM professionals ORDER BY name');
        return $this->render('appointments', compact('rows', 'q', 'status', 'from', 'to', 'prof', 'pg', 'profs'), 'citas', term('appts'));
    }

    private function formData(): array
    {
        $sc = $this->scopePro();
        return [
            'services' => Db::all('SELECT id,name,duration_min,modality FROM services WHERE active=1 ORDER BY sort,name'),
            'profs' => Db::all('SELECT id,name FROM professionals WHERE active=1' . ($sc ? ' AND id=' . (int)$sc : '') . ' ORDER BY sort,name'),
        ];
    }

    public function create(array $old = [], array $errors = []): Response
    {
        $this->need('appointments');
        $client = null;
        if ($this->req->int('cliente') && $this->clientVisible($this->req->int('cliente'))) { $client = Db::one('SELECT * FROM clients WHERE id=?', [$this->req->int('cliente')]); }
        $packages = $client ? Db::all('SELECT * FROM client_packages WHERE client_id=? AND sessions_used<sessions_total AND (expires_at IS NULL OR expires_at>=?)', [$client['id'], date('Y-m-d')]) : [];
        $old += ['date' => Util::isDate($this->req->str('fecha')) ? $this->req->str('fecha') : date('Y-m-d'), 'prof' => $this->req->int('prof'),
            'name' => $client['name'] ?? '', 'phone' => $client['phone'] ?? '', 'cc' => $client['phone_cc'] ?? '502', 'email' => $client['email'] ?? '', 'hora' => $this->req->str('hora')];
        return $this->render('appointment_new', $this->formData() + compact('old', 'errors', 'packages', 'client') + ['api' => url('/admin/api/slots')], 'citas', __('Nueva %s', mb_strtolower(term('appt'))));
    }

    public function store(): Response
    {
        $this->need('appointments');
        $sc = $this->scopePro();
        $pid = $sc ?: $this->req->int('professional_id');
        $start = $this->req->str('start_manual') !== '' ? $this->req->str('start_manual') : $this->req->str('start');
        $in = ['service_id' => $this->req->int('service_id'), 'professional_id' => $pid ?: 'any', 'start' => $start,
            'client' => ['name' => $this->req->str('name'), 'phone' => $this->req->str('phone'), 'cc' => $this->req->str('cc', '502'), 'email' => $this->req->str('email'), 'nit' => $this->req->str('nit'), 'consent' => true],
            'answers' => [], 'client_note' => $this->req->str('client_note'), 'internal_note' => $this->req->str('internal_note'), 'home_address' => $this->req->str('home_address'), 'coupon' => $this->req->str('coupon')];
        $opts = ['staff' => true, 'user_id' => $this->user['id'], 'source' => in_array($this->req->str('source'), ['phone', 'whatsapp', 'walkin', 'manual'], true) ? $this->req->str('source') : 'manual',
            'status' => in_array($this->req->str('status'), ['pending', 'confirmed'], true) ? $this->req->str('status') : 'confirmed', 'ignore_schedule' => $this->req->str('ignore_schedule') === '1',
            'allow_past' => true, 'ip' => $this->req->ip()];
        if ($this->req->int('client_package_id')) { $opts['client_package_id'] = $this->req->int('client_package_id'); }
        // Para el equipo, los campos del formulario del servicio no son obligatorios
        $r = BookingService::create($in, $opts);
        if (!$r['ok']) {
            $_SESSION['flash'][] = ['err', $r['error'] ?? 'No se pudo crear.'];
            $old = ['date' => substr($start, 0, 10), 'prof' => $pid, 'service_id' => $in['service_id'], 'name' => $in['client']['name'], 'phone' => $this->req->str('phone'), 'cc' => $in['client']['cc'], 'email' => $in['client']['email'], 'nit' => $in['client']['nit'], 'client_note' => $in['client_note'], 'internal_note' => $in['internal_note'], 'source' => $opts['source']];
            return $this->create($old, $r['fields'] ?? []);
        }
        $this->audit('appointment_created', 'appointment', $r['id']);
        $this->ok('Cita creada.');
        return $this->redirect('/admin/citas/' . $r['id']);
    }

    public function show(): Response
    {
        $this->need('appointments');
        $a = $this->appointment($this->id());
        $answers = Db::all('SELECT * FROM appointment_answers WHERE appointment_id=? ORDER BY id', [$a['id']]);
        $files = Db::all("SELECT * FROM files WHERE owner_type='appointment' AND owner_id=? ORDER BY id", [$a['id']]);
        $payments = Db::all('SELECT p.*, f.original_name, u.name user_name FROM payments p LEFT JOIN files f ON f.id=p.file_id LEFT JOIN users u ON u.id=p.user_id WHERE p.appointment_id=? ORDER BY p.id', [$a['id']]);
        $history = Db::all('SELECT * FROM appointment_history WHERE appointment_id=? ORDER BY id DESC', [$a['id']]);
        $queue = Db::all('SELECT id,channel,type,status,send_after,last_error FROM notifications_queue WHERE appointment_id=? ORDER BY id DESC LIMIT 12', [$a['id']]);
        $paid = 0.0; foreach ($payments as $p) { if ($p['status'] === 'confirmed') { $paid += (float)$p['amount']; } }
        $full = NotificationService::appointment((int)$a['id']);
        $tpl = TemplateService::find($a['status'] === 'pending' ? 'pending' : 'confirmation');
        $wa = $tpl ? 'https://wa.me/' . preg_replace('/\D+/', '', $a['phone_cc'] . $a['client_phone']) . '?text=' . rawurlencode(TemplateService::fill((string)$tpl['wa_body'], NotificationService::vars($full))) : '';
        $svc = Db::one('SELECT * FROM services WHERE id=?', [$a['service_id']]);
        $child = Db::val('SELECT id FROM appointments WHERE rescheduled_from=? ORDER BY id DESC LIMIT 1', [$a['id']]);
        return $this->render('appointment', compact('a', 'answers', 'files', 'payments', 'history', 'queue', 'paid', 'wa', 'svc', 'child') + ['methods' => self::METHODS, 'api' => url('/admin/api/slots'), 'statuses' => BookingService::STATUSES],
            'citas', __('%s #%d', term('appt'), $a['id']));
    }

    public function status(): Response
    {
        $this->need('appointments');
        $a = $this->appointment($this->id());
        $to = $this->req->str('status');
        [$ok, $err] = BookingService::setStatus((int)$a['id'], $to, $this->req->str('reason'), (int)$this->user['id']);
        if ($ok) { $this->audit('appointment_status', 'appointment', (int)$a['id'], $a['status'] . ' → ' . $to); $this->ok('Estado actualizado.'); } else { $this->fail($err); }
        $back = $this->req->str('back');
        return $this->redirect($back === 'inicio' ? '/admin' : '/admin/citas/' . $a['id']);
    }

    public function reschedule(): Response
    {
        $this->need('appointments');
        $a = $this->appointment($this->id());
        $start = $this->req->str('start_manual') !== '' ? $this->req->str('start_manual') : $this->req->str('start');
        $pid = $this->scopePro() ? (int)$a['professional_id'] : ($this->req->int('professional_id') ?: null);
        $r = BookingService::reschedule((int)$a['id'], $start, $pid, ['staff' => true, 'user_id' => $this->user['id'], 'ignore_schedule' => $this->req->str('ignore_schedule') === '1']);
        if (!$r['ok']) { $this->fail($r['error']); return $this->redirect('/admin/citas/' . $a['id']); }
        $this->audit('appointment_rescheduled', 'appointment', (int)$a['id'], '→ #' . $r['id']);
        $this->ok('Cita reprogramada.');
        return $this->redirect('/admin/citas/' . $r['id']);
    }

    public function notes(): Response
    {
        $this->need('appointments');
        $a = $this->appointment($this->id());
        Db::update('appointments', (int)$a['id'], ['internal_note' => Util::limit($this->req->str('internal_note'), 2000), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->ok('Nota guardada.');
        return $this->redirect('/admin/citas/' . $a['id']);
    }

    public function addPayment(): Response
    {
        $this->need('appointments');
        if (!\Aurea\Core\Auth::can('payments') && \Aurea\Core\Auth::role() !== 'professional') { $this->abort(403); }
        $a = $this->appointment($this->id());
        $amount = round((float)str_replace(',', '.', $this->req->str('amount')), 2);
        $method = array_key_exists($this->req->str('method'), self::METHODS) ? $this->req->str('method') : 'efectivo';
        if ($amount <= 0 || $amount > 1000000) { $this->fail('Ingresa un monto válido.'); return $this->redirect('/admin/citas/' . $a['id']); }
        $pid = Db::insert('payments', ['appointment_id' => $a['id'], 'amount' => $amount, 'method' => $method, 'reference' => Util::limit($this->req->str('reference'), 190), 'status' => 'confirmed',
            'note' => Util::limit($this->req->str('note'), 250), 'paid_at' => date('Y-m-d H:i:s'), 'user_id' => $this->user['id'], 'created_at' => date('Y-m-d H:i:s')]);
        if (!empty($_FILES['file']['name'])) {
            try { Db::update('payments', $pid, ['file_id' => Upload::privateDoc($_FILES['file'], 'payment', $pid, (int)$this->user['id'])]); }
            catch (\RuntimeException $e) { $this->fail('El pago se registró, pero el comprobante no se pudo guardar: ' . $e->getMessage()); }
        }
        BookingService::refreshPayment((int)$a['id']);
        BookingService::history((int)$a['id'], 'payment', 'Pago registrado: ' . money($amount) . ' (' . self::METHODS[$method] . ')', (int)$this->user['id']);
        $this->audit('payment_added', 'appointment', (int)$a['id'], money($amount));
        $this->ok('Pago registrado.');
        return $this->redirect('/admin/citas/' . $a['id']);
    }

    public function paymentStatus(): Response
    {
        $this->need('appointments');
        $p = Db::one('SELECT * FROM payments WHERE id=?', [$this->id()]);
        if (!$p) { $this->abort(404); }
        $a = $this->appointment((int)$p['appointment_id']);
        $to = $this->req->str('status') === 'confirmed' ? 'confirmed' : 'rejected';
        Db::update('payments', (int)$p['id'], ['status' => $to, 'user_id' => $this->user['id']]);
        BookingService::refreshPayment((int)$a['id']);
        BookingService::history((int)$a['id'], 'payment', ($to === 'confirmed' ? 'Pago confirmado' : 'Pago rechazado') . ': ' . money($p['amount']), (int)$this->user['id']);
        $this->audit('payment_' . $to, 'payment', (int)$p['id']);
        $this->ok($to === 'confirmed' ? 'Pago confirmado.' : 'Pago rechazado.');
        return $this->redirect($this->req->str('back') === 'pagos' ? '/admin/pagos' : '/admin/citas/' . $a['id']);
    }

    public function receipt(): Response
    {
        $this->need('appointments');
        $a = $this->appointment($this->id());
        $payments = Db::all("SELECT * FROM payments WHERE appointment_id=? AND status='confirmed' ORDER BY id", [$a['id']]);
        View::$title = 'Recibo';
        return Response::html(View::page('print', 'admin/receipt', ['a' => $a, 'payments' => $payments, 'methods' => self::METHODS]));
    }

    public function block(): Response
    {
        $this->need('schedule_own');
        $sc = $this->scopePro();
        $pid = $sc ?: ($this->req->int('professional_id') ?: null);
        $s = str_replace('T', ' ', $this->req->str('start_at')); $e = str_replace('T', ' ', $this->req->str('end_at'));
        $ok = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $e) && $s < $e;
        if (!$ok) { $this->fail('Indica inicio y fin válidos (el fin debe ser posterior).'); return $this->redirect('/admin/agenda'); }
        if ($pid && !Db::val('SELECT id FROM professionals WHERE id=?', [$pid])) { $this->abort(404); }
        Db::insert('time_off', ['professional_id' => $pid, 'start_at' => $s . ':00', 'end_at' => $e . ':00', 'reason' => Util::limit($this->req->str('reason') ?: 'Bloqueo', 190), 'created_at' => date('Y-m-d H:i:s')]);
        $this->audit('time_blocked', 'time_off', null, $s . ' → ' . $e);
        $this->ok('Horario bloqueado.');
        return $this->redirect('/admin/agenda?fecha=' . substr($s, 0, 10));
    }
}
