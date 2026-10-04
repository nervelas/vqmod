<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Auth;
use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Util;
use Aurea\Services\BookingService;
use Aurea\Services\NotificationService;
use Aurea\Services\ReportService;
use Aurea\Services\WaitlistService;

/** Pagos, lista de espera, reseñas, centro de mensajes y reportes. */
final class OpsController extends AdminController
{
    public function payments(): Response
    {
        $this->need('payments');
        $from = Util::isDate($this->req->str('desde')) ? $this->req->str('desde') : date('Y-m-01');
        $to = Util::isDate($this->req->str('hasta')) ? $this->req->str('hasta') : date('Y-m-d');
        $status = $this->req->str('estado');
        $where = 'p.paid_at BETWEEN ? AND ?'; $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        if (in_array($status, ['confirmed', 'pending', 'rejected'], true)) { $where .= ' AND p.status=?'; $params[] = $status; }
        $rows = Db::all("SELECT p.*, a.start_at, a.total, a.payment_status, c.name client_name, s.name service_name FROM payments p JOIN appointments a ON a.id=p.appointment_id
            JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id WHERE $where ORDER BY p.paid_at DESC LIMIT 500", $params);
        $total = 0.0; foreach ($rows as $r) { if ($r['status'] === 'confirmed') { $total += (float)$r['amount']; } }
        $pendingAppts = Db::all("SELECT a.id, a.start_at, a.total, a.deposit_required, c.name client_name FROM appointments a JOIN clients c ON c.id=a.client_id
            WHERE a.status IN ('pending','confirmed') AND a.payment_status<>'paid' AND a.total>0 AND a.start_at>=? ORDER BY a.start_at LIMIT 30", [date('Y-m-d 00:00:00')]);
        return $this->render('payments', compact('rows', 'from', 'to', 'status', 'total', 'pendingAppts') + ['methods' => AppointmentController::METHODS], 'pagos', 'Pagos');
    }

    public function waitlist(): Response
    {
        $this->need('waitlist');
        $rows = Db::all('SELECT w.*, s.name service_name, p.name prof_name FROM waitlist w JOIN services s ON s.id=w.service_id LEFT JOIN professionals p ON p.id=w.professional_id ORDER BY FIELD(w.status,\'offered\',\'waiting\',\'booked\',\'expired\'), w.id DESC LIMIT 200');
        return $this->render('waitlist', compact('rows'), 'espera', 'Lista de espera');
    }

    public function waitlistAction(): Response
    {
        $this->need('waitlist');
        $id = $this->id();
        $w = Db::one('SELECT * FROM waitlist WHERE id=?', [$id]);
        if (!$w) { $this->abort(404); }
        $a = $this->req->str('action');
        if ($a === 'delete') { Db::delete('waitlist', $id); $this->ok('Eliminado de la lista.'); }
        elseif ($a === 'booked') { Db::update('waitlist', $id, ['status' => 'booked']); $this->ok('Marcado como agendado.'); }
        elseif ($a === 'waiting') { Db::update('waitlist', $id, ['status' => 'waiting', 'offer_token' => null, 'offer_start' => null, 'offer_expires' => null]); $this->ok('Vuelve a esperar.'); }
        return $this->redirect('/admin/espera');
    }

    public function reviews(): Response
    {
        $this->need('reviews');
        $sc = $this->scopePro();
        $rows = Db::all('SELECT r.*, c.name client_name, p.name prof_name FROM reviews r JOIN clients c ON c.id=r.client_id JOIN professionals p ON p.id=r.professional_id' . ($sc ? ' WHERE r.professional_id=' . (int)$sc : '') . " ORDER BY FIELD(r.status,'pending','approved','rejected'), r.id DESC LIMIT 200");
        return $this->render('reviews', compact('rows'), 'resenas', 'Reseñas');
    }

    public function reviewAction(): Response
    {
        if (!Auth::can('reviews') || Auth::role() === 'professional') { $this->abort(403); }
        $id = $this->id();
        if (!Db::val('SELECT id FROM reviews WHERE id=?', [$id])) { $this->abort(404); }
        $a = $this->req->str('action');
        if ($a === 'approve') { Db::update('reviews', $id, ['status' => 'approved']); $this->ok('Reseña publicada.'); }
        elseif ($a === 'reject') { Db::update('reviews', $id, ['status' => 'rejected']); $this->ok('Reseña rechazada.'); }
        elseif ($a === 'delete') { Db::delete('reviews', $id); $this->ok('Reseña eliminada.'); }
        $this->audit('review_' . $a, 'review', $id);
        return $this->redirect('/admin/resenas');
    }

    public function messages(): Response
    {
        $this->need('messages');
        $sc = $this->scopePro();
        $due = NotificationService::dueWhatsApp($sc);
        $stats = ['pending_email' => (int)Db::val("SELECT COUNT(*) FROM notifications_queue WHERE channel='email' AND status='pending'"),
            'failed' => (int)Db::val("SELECT COUNT(*) FROM notifications_queue WHERE status='failed'"), 'sent_today' => (int)Db::val("SELECT COUNT(*) FROM notifications_queue WHERE status='sent' AND sent_at>=?", [date('Y-m-d 00:00:00')])];
        $failed = Auth::role() === 'admin' ? Db::all("SELECT * FROM notifications_queue WHERE status='failed' ORDER BY id DESC LIMIT 30") : [];
        $labels = ['confirmation' => 'Confirmación', 'pending' => 'Solicitud recibida', 'reminder_24h' => 'Recordatorio 24 h', 'reminder_2h' => 'Recordatorio 2 h', 'cancellation' => 'Cancelación', 'reschedule' => 'Reprogramación', 'followup' => 'Seguimiento', 'review_request' => 'Reseña', 'waitlist_offer' => 'Lista de espera'];
        foreach ($due as &$d) { $d['wa_url'] = NotificationService::waUrl($d); }
        unset($d);
        return $this->render('messages', compact('due', 'stats', 'failed', 'labels'), 'mensajes', 'Mensajes por enviar hoy');
    }

    public function messageAction(): Response
    {
        $this->need('messages');
        $id = $this->id();
        $q = Db::one('SELECT q.*, a.professional_id FROM notifications_queue q LEFT JOIN appointments a ON a.id=q.appointment_id WHERE q.id=?', [$id]);
        if (!$q) { $this->abort(404); }
        $sc = $this->scopePro();
        if ($sc !== null && (int)$q['professional_id'] !== $sc) { $this->abort(404); }
        $a = $this->req->str('action');
        if ($a === 'sent') { Db::exec("UPDATE notifications_queue SET status='sent', sent_at=?, attempts=attempts+1 WHERE id=?", [date('Y-m-d H:i:s'), $id]); }
        elseif ($a === 'skip') { Db::exec("UPDATE notifications_queue SET status='skipped' WHERE id=?", [$id]); }
        elseif ($a === 'retry' && Auth::role() === 'admin') { Db::exec("UPDATE notifications_queue SET status='pending', attempts=0, last_error='', send_after=? WHERE id=?", [date('Y-m-d H:i:s'), $id]); }
        if ($this->req->isAjax()) { return $this->json(['ok' => true]); }
        return $this->redirect('/admin/mensajes');
    }

    private function range(): array
    {
        $from = Util::isDate($this->req->str('desde')) ? $this->req->str('desde') : date('Y-m-01');
        $to = Util::isDate($this->req->str('hasta')) ? $this->req->str('hasta') : date('Y-m-t');
        if ($to < $from) { [$from, $to] = [$to, $from]; }
        return [$from, $to];
    }

    public function reports(): Response
    {
        $this->need('reports_basic');
        [$from, $to] = $this->range();
        $sc = $this->scopePro();
        return $this->render('reports', ['from' => $from, 'to' => $to, 'sum' => ReportService::summary($from, $to, $sc), 'byPro' => ReportService::byProfessional($from, $to, $sc),
            'bySvc' => ReportService::byService($from, $to, $sc), 'peak' => ReportService::peakHours($from, $to, $sc), 'nr' => ReportService::newVsReturning($from, $to, $sc)], 'reportes', 'Reportes');
    }

    public function reportCsv(): Response
    {
        $this->need('reports_basic');
        [$from, $to] = $this->range();
        $t = $this->req->str('tipo');
        $sc = $this->scopePro();
        if ($t === 'profesionales' || $t === 'servicios') {
            $rows = $t === 'profesionales' ? ReportService::byProfessional($from, $to, $sc) : ReportService::byService($from, $to, $sc);
            $csv = Util::csv([$t === 'profesionales' ? 'Profesional' : 'Servicio', 'Citas', 'Completadas', 'No asistió', 'Canceladas', 'Ingresos'], array_map(static fn($r) => [$r['name'], $r['citas'], $r['completadas'], $r['no_show'], $r['canceladas'], number_format((float)$r['ingresos'], 2, '.', '')], $rows));
        } else { $csv = ReportService::appointmentsCsv($from, $to, $sc); }
        return Response::download($csv, 'reporte-' . ($t ?: 'citas') . '-' . $from . '_' . $to . '.csv', 'text/csv; charset=utf-8');
    }
}
