<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;
use App\Services\BookingService;

/** Flujos y recordatorios: reglas de automatización con plantillas editables. */
final class WorkflowsController extends A3Controller
{
    public const TRIGGERS = [
        'booking.created' => 'Cuando se crea una cita',
        'booking.approved' => 'Cuando se aprueba una cita',
        'booking.rescheduled' => 'Cuando se reprograma',
        'booking.cancelled' => 'Cuando se cancela',
        'booking.completed' => 'Cuando se marca como completada',
        'booking.no_show' => 'Cuando el cliente no asiste',
        'booking.before_start' => 'Antes de que empiece la cita',
        'booking.after_end' => 'Después de que termina la cita',
    ];
    public const ACTIONS = [
        'email' => 'Enviar correo',
        'whatsapp' => 'WhatsApp de un toque',
        'whatsapp_api' => 'WhatsApp automático (API Cloud)',
        'webhook' => 'Avisar a un webhook',
        'set_status' => 'Cambiar el estado de la cita',
        'review_request' => 'Solicitar una reseña',
        'add_tag' => 'Agregar una etiqueta al cliente',
    ];
    public const RECIPIENTS = ['guest' => 'La persona invitada', 'host' => 'El anfitrión', 'admin' => 'La administración'];
    public const VARS = ['nombre' => 'Nombre del invitado', 'evento' => 'Tipo de cita', 'fecha' => 'Fecha', 'hora' => 'Hora', 'anfitrion' => 'Anfitrión', 'enlace' => 'Enlace de la cita', 'direccion' => 'Dirección', 'zona' => 'Zona horaria', 'videollamada' => 'Enlace de videollamada', 'precio' => 'Precio', 'telefono_negocio' => 'Teléfono del negocio'];
    private const STATUS_VALUES = ['confirmed' => 'Confirmada', 'completed' => 'Completada', 'no_show' => 'No asistió'];

    public function index(Request $req, array $p): Response
    {
        $rows = Db::all('SELECT w.*, e.name AS event_name,
            (SELECT COUNT(*) FROM workflow_runs r WHERE r.workflow_id = w.id AND r.status = \'done\') AS done_n,
            (SELECT COUNT(*) FROM workflow_runs r WHERE r.workflow_id = w.id AND r.status = \'failed\') AS failed_n,
            (SELECT COUNT(*) FROM workflow_runs r WHERE r.workflow_id = w.id AND r.status = \'pending\') AS pending_n
            FROM workflows w LEFT JOIN event_types e ON e.id = w.event_type_id ORDER BY w.active DESC, w.sort_order, w.id');
        return $this->page('admin/workflows/index', ['rows' => $rows, 'waOn' => Settings::bool('wa_api_enabled')], '/admin/flujos', 'Flujos y recordatorios');
    }

    public function form(Request $req, array $p): Response
    {
        $wf = null;
        if (isset($p['id'])) {
            $wf = Db::one('SELECT * FROM workflows WHERE id = ?', [(int) $p['id']]);
            if (!$wf) {
                throw new HttpException(404);
            }
        }
        $wf = $wf ?: [
            'id' => 0, 'name' => '', 'trigger_key' => 'booking.before_start', 'offset_minutes' => 1440, 'event_type_id' => null, 'action' => 'email', 'recipient' => 'guest',
            'subject' => 'Recordatorio de tu cita: {evento}', 'template' => "Hola {nombre},\n\nTe recordamos tu cita de {evento} con {anfitrion}.\n\nFecha: {fecha}\nHora: {hora} ({zona})\nLugar: {direccion}\n\nPuedes ver o cambiar tu cita aquí: {enlace}\n\nTe esperamos.", 'action_value' => '', 'active' => 1,
        ];
        [$offVal, $offUnit] = $this->splitOffset((int) $wf['offset_minutes']);
        return $this->page('admin/workflows/form', [
            'wf' => $wf, 'events' => $this->events(), 'triggers' => self::TRIGGERS, 'actions' => self::ACTIONS, 'recipients' => self::RECIPIENTS, 'vars' => self::VARS,
            'offVal' => $offVal, 'offUnit' => $offUnit, 'waOn' => Settings::bool('wa_api_enabled'), 'statusValues' => self::STATUS_VALUES,
            'recentBookings' => Db::all('SELECT b.id, b.guest_name, b.starts_at FROM bookings b ORDER BY b.id DESC LIMIT 15'),
        ], '/admin/flujos', $wf['id'] ? 'Editar flujo' : 'Nuevo flujo', ['js/admin-workflows.js']);
    }

    public function save(Request $req, array $p): Response
    {
        $id = $req->int('id');
        $back = $id ? '/admin/flujos/' . $id . '/editar' : '/admin/flujos/nuevo';
        if ($id > 0 && !Db::val('SELECT id FROM workflows WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        $name = $req->str('name', 160);
        $trigger = $req->str('trigger_key', 40);
        $action = $req->str('action', 20);
        $recipient = $req->str('recipient', 10);
        if ($name === '' || !isset(self::TRIGGERS[$trigger]) || !isset(self::ACTIONS[$action])) {
            return $this->fail($req, 'Escribe un nombre y elige cuándo y qué debe ocurrir.', $back);
        }
        if (!isset(self::RECIPIENTS[$recipient])) {
            $recipient = 'guest';
        }
        $offset = 0;
        if (in_array($trigger, ['booking.before_start', 'booking.after_end'], true)) {
            $val = Validator::intRange($req->str('offset_value', 6), 0, 100000);
            $unit = $req->str('offset_unit', 8);
            $mult = ['minutes' => 1, 'hours' => 60, 'days' => 1440][$unit] ?? null;
            if ($val === null || $mult === null || $val * $mult > 86400) {
                return $this->fail($req, 'El tiempo debe ser un número entero y no superar 60 días.', $back);
            }
            $offset = $val * $mult;
        }
        $eventId = $req->int('event_type_id');
        if ($eventId > 0 && !Db::val('SELECT id FROM event_types WHERE id = ?', [$eventId])) {
            return $this->fail($req, 'El tipo de cita elegido ya no existe.', $back);
        }
        $subject = $req->str('subject', 255);
        $template = trim((string) ($req->post['template'] ?? ''));
        $template = mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $template) ?? '', 0, 8000);
        $value = '';
        switch ($action) {
            case 'email':
                if ($subject === '' || $template === '') {
                    return $this->fail($req, 'Un correo necesita asunto y mensaje.', $back);
                }
                break;
            case 'whatsapp':
            case 'whatsapp_api':
                if ($template === '') {
                    return $this->fail($req, 'Escribe el texto del mensaje de WhatsApp.', $back);
                }
                $subject = '';
                break;
            case 'webhook':
                $value = $req->str('action_value', 500);
                if (!Validator::url($value)) {
                    return $this->fail($req, 'Escribe la dirección completa del webhook (http:// o https://).', $back);
                }
                if ($this->svc('SafeHttp') && ($err = \App\Services\SafeHttp::validateUrl($value)) !== null) {
                    return $this->fail($req, $err, $back);
                }
                $subject = '';
                $template = '';
                break;
            case 'set_status':
                $value = $req->str('action_value_status', 20);
                if (!isset(self::STATUS_VALUES[$value])) {
                    return $this->fail($req, 'Elige a qué estado debe pasar la cita.', $back);
                }
                $subject = '';
                $template = '';
                break;
            case 'add_tag':
                $value = trim(preg_replace('/[,;]+/', ' ', $req->str('action_value_tag', 40)) ?? '');
                if ($value === '') {
                    return $this->fail($req, 'Escribe la etiqueta que se agregará al cliente.', $back);
                }
                $subject = '';
                $template = '';
                break;
            default: // review_request
                $subject = $subject !== '' ? $subject : '';
                break;
        }
        $data = [
            'name' => $name, 'trigger_key' => $trigger, 'offset_minutes' => $offset, 'event_type_id' => $eventId > 0 ? $eventId : null,
            'action' => $action, 'recipient' => $recipient, 'subject' => $subject !== '' ? $subject : null, 'template' => $template !== '' ? $template : null,
            'action_value' => $value !== '' ? $value : null, 'active' => $req->bool('active') ? 1 : 0,
        ];
        if ($id > 0) {
            Db::update('workflows', $data, 'id = ?', [$id]);
        } else {
            $data['sort_order'] = (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM workflows');
            $data['created_at'] = Clock::utc();
            $id = Db::insert('workflows', $data);
        }
        $this->audit('workflow.save', 'workflow', $id, $name);
        $this->flash('success', 'El flujo quedó guardado.');
        return $this->redirect('/admin/flujos');
    }

    public function duplicate(Request $req, array $p): Response
    {
        $w = Db::one('SELECT * FROM workflows WHERE id = ?', [(int) $p['id']]);
        if (!$w) {
            throw new HttpException(404);
        }
        unset($w['id']);
        $w['name'] = Str::truncate((string) $w['name'], 150) . ' (copia)';
        $w['active'] = 0;
        $w['created_at'] = Clock::utc();
        $id = Db::insert('workflows', $w);
        $this->flash('success', 'Duplicamos el flujo. Quedó pausado para que lo revises antes de activarlo.');
        return $this->redirect('/admin/flujos/' . $id . '/editar');
    }

    public function toggle(Request $req, array $p): Response
    {
        $w = Db::one('SELECT id, active FROM workflows WHERE id = ?', [(int) $p['id']]);
        if (!$w) {
            throw new HttpException(404);
        }
        Db::update('workflows', ['active' => (int) $w['active'] ? 0 : 1], 'id = ?', [$w['id']]);
        $this->flash('success', (int) $w['active'] ? 'El flujo se pausó.' : 'El flujo está activo.');
        return $this->redirect('/admin/flujos');
    }

    public function delete(Request $req, array $p): Response
    {
        $w = Db::one('SELECT id, name FROM workflows WHERE id = ?', [(int) $p['id']]);
        if (!$w) {
            throw new HttpException(404);
        }
        Db::delete('workflows', 'id = ?', [$w['id']]);
        $this->audit('workflow.delete', 'workflow', $w['id'], (string) $w['name']);
        $this->flash('success', 'El flujo se eliminó junto con su bitácora.');
        return $this->redirect('/admin/flujos');
    }

    /** Vista previa con una cita real reciente o, si no hay, una de ejemplo. */
    public function preview(Request $req, array $p): Response
    {
        $tpl = (string) ($req->post['template'] ?? $req->json()['template'] ?? '');
        $subject = Str::clean((string) ($req->post['subject'] ?? $req->json()['subject'] ?? ''), 255);
        $bid = $req->int('booking_id');
        $display = null;
        $sample = true;
        if ($bid > 0) {
            $b = Db::one('SELECT * FROM bookings WHERE id = ?', [$bid]);
            if ($b) {
                $display = BookingService::display($b);
                $sample = false;
            }
        }
        $display = $display ?? $this->sampleDisplay();
        try {
            $body = \App\Services\WorkflowService::render(mb_substr($tpl, 0, 8000), $display);
            $subj = \App\Services\WorkflowService::render($subject, $display);
        } catch (\Throwable $e) {
            Logger::error('Vista previa de flujo', $e);
            return $this->json(['ok' => false, 'error' => 'No pudimos generar la vista previa en este momento.'], 500);
        }
        return $this->json(['ok' => true, 'subject' => $subj, 'body' => $body, 'sample' => $sample]);
    }

    public function test(Request $req, array $p): Response
    {
        $w = Db::one('SELECT * FROM workflows WHERE id = ?', [(int) $p['id']]);
        if (!$w) {
            throw new HttpException(404);
        }
        $back = $req->str('volver', 20) === 'editar' ? '/admin/flujos/' . (int) $w['id'] . '/editar' : '/admin/flujos';
        $bid = $req->int('booking_id') ?: (int) Db::val('SELECT id FROM bookings ORDER BY id DESC LIMIT 1');
        if ($bid <= 0 || !Db::val('SELECT id FROM bookings WHERE id = ?', [$bid])) {
            return $this->fail($req, 'Para probar un flujo necesitas al menos una cita. Crea una de prueba y vuelve a intentarlo.', $back);
        }
        try {
            $r = \App\Services\WorkflowService::testRun((int) $w['id'], $bid);
        } catch (\Throwable $e) {
            Logger::error('Prueba de flujo', $e);
            return $this->fail($req, 'La prueba no se pudo completar. Revisa el flujo e inténtalo de nuevo.', $back);
        }
        $ok = !empty($r['ok']);
        $this->flash($ok ? 'success' : 'warn', $ok ? (string) ($r['message'] ?? 'Prueba ejecutada con la cita #' . $bid . '.') : (string) ($r['error'] ?? 'La prueba terminó con un aviso.'));
        return $this->redirect($back);
    }

    public function runs(Request $req, array $p): Response
    {
        $status = $req->str('estado', 10);
        $wfId = $req->int('flujo');
        $where = ['1=1'];
        $args = [];
        if (in_array($status, ['pending', 'done', 'failed', 'skipped'], true)) {
            $where[] = 'r.status = ?';
            $args[] = $status;
        } else {
            $status = '';
        }
        if ($wfId > 0) {
            $where[] = 'r.workflow_id = ?';
            $args[] = $wfId;
        }
        $rows = Db::all('SELECT r.*, w.name AS wf_name, w.action AS wf_action, b.guest_name, b.starts_at FROM workflow_runs r JOIN workflows w ON w.id = r.workflow_id JOIN bookings b ON b.id = r.booking_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.id DESC LIMIT 200', $args);
        $counts = [];
        foreach (Db::all('SELECT status, COUNT(*) c FROM workflow_runs GROUP BY status') as $c) {
            $counts[$c['status']] = (int) $c['c'];
        }
        return $this->page('admin/workflows/runs', ['rows' => $rows, 'status' => $status, 'wfId' => $wfId, 'counts' => $counts, 'workflows' => Db::all('SELECT id, name FROM workflows ORDER BY name'), 'actions' => self::ACTIONS], '/admin/flujos', 'Ejecuciones de flujos');
    }

    public function retry(Request $req, array $p): Response
    {
        $run = Db::one('SELECT * FROM workflow_runs WHERE id = ?', [(int) $p['id']]);
        if (!$run) {
            throw new HttpException(404);
        }
        if (!in_array($run['status'], ['failed', 'skipped'], true)) {
            $this->flash('warn', 'Solo se pueden reintentar ejecuciones con error u omitidas.');
            return $this->redirect('/admin/flujos/ejecuciones');
        }
        Db::update('workflow_runs', ['status' => 'pending', 'attempts' => 0, 'last_error' => null, 'scheduled_at' => Clock::utc()], 'id = ?', [$run['id']]);
        $res = null;
        try {
            $res = \App\Services\WorkflowService::runDue(25);
        } catch (\Throwable $e) {
            Logger::error('Reintento de flujo', $e);
        }
        $now = Db::one('SELECT status, last_error FROM workflow_runs WHERE id = ?', [$run['id']]);
        if ($now && $now['status'] === 'done') {
            $this->flash('success', 'La ejecución se completó.');
        } else {
            $this->flash('warn', 'La ejecución quedó en cola' . ($now && $now['last_error'] ? ': ' . $now['last_error'] : '. Se intentará de nuevo en el próximo ciclo.'));
        }
        return $this->redirect('/admin/flujos/ejecuciones');
    }

    private function splitOffset(int $min): array
    {
        if ($min > 0 && $min % 1440 === 0) {
            return [intdiv($min, 1440), 'days'];
        }
        if ($min > 0 && $min % 60 === 0) {
            return [intdiv($min, 60), 'hours'];
        }
        return [$min, 'minutes'];
    }

    private function sampleDisplay(): array
    {
        $tz = $this->bizTz();
        $startTs = Tz::localToTs(Tz::formatTs(Clock::now() + 86400, $tz, 'Y-m-d') . ' 10:00:00', $tz);
        $start = Tz::fromTs($startTs);
        $end = Tz::fromTs($startTs + 45 * 60);
        $biz = (string) Settings::get('business_name', 'Tu negocio');
        return [
            'id' => 0, 'token' => str_repeat('a', 32), 'guest_name' => 'María López', 'guest_email' => 'maria@example.com', 'guest_phone' => '50255551234', 'guest_timezone' => $tz,
            'starts_at' => $start, 'ends_at' => $end, 'duration' => 45, 'mode' => 'in_person', 'location' => (string) Settings::get('address', '') ?: '12 calle 3-45, zona 10, Ciudad de Guatemala', 'video_url' => '',
            'price' => 250.0, 'discount' => 0.0, 'total' => 250.0, 'status' => 'confirmed', 'seats' => 1, 'client_id' => null, 'client' => null,
            'event' => ['id' => 0, 'name' => 'Consulta general', 'mode' => 'in_person', 'location' => '', 'price' => 250.0],
            'host' => ['id' => 0, 'name' => 'Dra. Ana Demo', 'phone' => (string) Settings::get('phone', '')], 'hosts' => [], 'attendees' => [], 'answers' => [],
            'when_local' => [
                'date' => \App\Core\Fmt::dateLong($start, $tz), 'time' => \App\Core\Fmt::time($start, $tz), 'end_time' => \App\Core\Fmt::time($end, $tz),
                'tz' => \App\Core\Fmt::tzLabel($tz, $start), 'duration' => \App\Core\Fmt::duration(45),
            ],
            'public_url' => abs_url('/reserva/' . str_repeat('a', 32)), 'business_name' => $biz,
        ];
    }
}
