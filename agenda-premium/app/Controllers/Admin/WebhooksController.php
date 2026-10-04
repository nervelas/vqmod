<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;

/** Webhooks salientes: CRUD, secreto, evento de prueba y registro de entregas. */
final class WebhooksController extends A3Controller
{
    public const EVENTS = [
        'booking.created' => 'Cita creada',
        'booking.approved' => 'Cita aprobada',
        'booking.rescheduled' => 'Cita reprogramada',
        'booking.cancelled' => 'Cita cancelada',
        'booking.completed' => 'Cita completada',
        'booking.no_show' => 'Cliente no asistió',
    ];

    public function index(Request $req, array $p): Response
    {
        $hooks = Db::all('SELECT w.*, (SELECT COUNT(*) FROM webhook_deliveries d WHERE d.webhook_id = w.id) AS total,
            (SELECT COUNT(*) FROM webhook_deliveries d WHERE d.webhook_id = w.id AND d.status = \'failed\') AS failed
            FROM webhooks w ORDER BY w.id DESC');
        $hookId = $req->int('webhook');
        $status = $req->str('estado', 10);
        $where = ['1=1'];
        $args = [];
        if ($hookId > 0) {
            $where[] = 'd.webhook_id = ?';
            $args[] = $hookId;
        }
        if (in_array($status, ['pending', 'delivered', 'failed'], true)) {
            $where[] = 'd.status = ?';
            $args[] = $status;
        }
        $deliveries = Db::all('SELECT d.*, w.name AS hook_name FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id WHERE ' . implode(' AND ', $where) . ' ORDER BY d.id DESC LIMIT 100', $args);
        return $this->page('admin/webhooks/index', ['hooks' => $hooks, 'deliveries' => $deliveries, 'hookId' => $hookId, 'status' => $status, 'eventsList' => self::EVENTS], '/admin/webhooks', 'Webhooks');
    }

    public function form(Request $req, array $p): Response
    {
        $hook = null;
        if (isset($p['id'])) {
            $hook = Db::one('SELECT * FROM webhooks WHERE id = ?', [(int) $p['id']]);
            if (!$hook) {
                throw new HttpException(404);
            }
        }
        $selected = $hook ? ($hook['events'] === '*' ? array_keys(self::EVENTS) : array_filter(explode(',', (string) $hook['events']))) : array_keys(self::EVENTS);
        return $this->page('admin/webhooks/form', ['hook' => $hook, 'eventsList' => self::EVENTS, 'selected' => $selected], '/admin/webhooks', $hook ? 'Editar webhook' : 'Nuevo webhook');
    }

    public function save(Request $req, array $p): Response
    {
        $id = $req->int('id');
        $back = $id ? '/admin/webhooks/' . $id . '/editar' : '/admin/webhooks/nuevo';
        $name = $req->str('name', 120);
        $url = $req->str('url', 500);
        $evIn = $req->input('events', []);
        $events = is_array($evIn) ? array_values(array_intersect(array_keys(self::EVENTS), array_map('strval', $evIn))) : [];
        if ($name === '') {
            return $this->fail($req, 'Ponle un nombre al webhook para reconocerlo después.', $back);
        }
        if (!Validator::url($url)) {
            return $this->fail($req, 'La dirección debe empezar con http:// o https:// y ser válida.', $back);
        }
        if ($this->svc('SafeHttp')) {
            $err = \App\Services\SafeHttp::validateUrl($url);
            if ($err !== null) {
                return $this->fail($req, $err, $back);
            }
        }
        if (!$events) {
            return $this->fail($req, 'Elige al menos un evento para recibir.', $back);
        }
        $data = [
            'name' => $name, 'url' => $url,
            'events' => count($events) === count(self::EVENTS) ? '*' : implode(',', $events),
            'active' => $req->bool('active') ? 1 : 0,
        ];
        if ($id > 0) {
            if (!Db::val('SELECT id FROM webhooks WHERE id = ?', [$id])) {
                throw new HttpException(404);
            }
            Db::update('webhooks', $data, 'id = ?', [$id]);
        } else {
            $data['secret'] = $this->newSecret();
            $data['created_at'] = Clock::utc();
            $id = Db::insert('webhooks', $data);
        }
        $this->audit('webhook.save', 'webhook', $id, $name);
        $this->flash('success', 'El webhook quedó guardado.');
        return $this->redirect('/admin/webhooks/' . $id . '/editar');
    }

    public function secret(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM webhooks WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        Db::update('webhooks', ['secret' => $this->newSecret()], 'id = ?', [$id]);
        $this->audit('webhook.secret', 'webhook', $id);
        $this->flash('success', 'Generamos un secreto nuevo. Actualízalo en el sistema que recibe los avisos; el anterior ya no sirve.');
        return $this->redirect('/admin/webhooks/' . $id . '/editar');
    }

    public function toggle(Request $req, array $p): Response
    {
        $row = Db::one('SELECT id, active FROM webhooks WHERE id = ?', [(int) $p['id']]);
        if (!$row) {
            throw new HttpException(404);
        }
        Db::update('webhooks', ['active' => (int) $row['active'] ? 0 : 1], 'id = ?', [$row['id']]);
        $this->flash('success', (int) $row['active'] ? 'El webhook se pausó.' : 'El webhook está activo.');
        return $this->redirect('/admin/webhooks');
    }

    public function delete(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM webhooks WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        Db::delete('webhooks', 'id = ?', [$id]);
        $this->audit('webhook.delete', 'webhook', $id);
        $this->flash('success', 'El webhook se eliminó junto con su historial.');
        return $this->redirect('/admin/webhooks');
    }

    public function test(Request $req, array $p): Response
    {
        $hook = Db::one('SELECT * FROM webhooks WHERE id = ?', [(int) $p['id']]);
        if (!$hook) {
            throw new HttpException(404);
        }
        $payload = ['id' => Str::token(), 'event' => 'test.ping', 'created_at' => \App\Core\Tz::iso(Clock::utc()), 'data' => ['mensaje' => 'Evento de prueba enviado desde el panel.']];
        $did = Db::insert('webhook_deliveries', [
            'webhook_id' => $hook['id'], 'event' => 'test.ping', 'payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => Clock::utc(), 'created_at' => Clock::utc(),
        ]);
        $this->deliver();
        $row = Db::one('SELECT status, response_code FROM webhook_deliveries WHERE id = ?', [$did]);
        if ($row && $row['status'] === 'delivered') {
            $this->flash('success', 'La prueba llegó con éxito (respuesta ' . (int) $row['response_code'] . ').');
        } else {
            $this->flash('warn', 'La prueba quedó registrada, pero aún no se entregó. Revisa el detalle en el registro de entregas.');
        }
        return $this->redirect('/admin/webhooks', ['webhook' => $hook['id']]);
    }

    public function resend(Request $req, array $p): Response
    {
        $d = Db::one('SELECT * FROM webhook_deliveries WHERE id = ?', [(int) $p['id']]);
        if (!$d) {
            throw new HttpException(404);
        }
        Db::update('webhook_deliveries', ['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => Clock::utc(), 'response_code' => null, 'response_body' => null], 'id = ?', [$d['id']]);
        $this->deliver();
        $row = Db::one('SELECT status FROM webhook_deliveries WHERE id = ?', [$d['id']]);
        $this->flash($row && $row['status'] === 'delivered' ? 'success' : 'warn', $row && $row['status'] === 'delivered' ? 'La entrega se reenvió correctamente.' : 'Reenviamos el aviso, pero el destino todavía no respondió bien.');
        return $this->redirect('/admin/webhooks', ['webhook' => $d['webhook_id']]);
    }

    private function deliver(): void
    {
        if (!$this->svc('WebhookService')) {
            return;
        }
        try {
            \App\Services\WebhookService::deliverDue(20);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Entrega de webhooks', $e);
        }
    }

    private function newSecret(): string
    {
        return 'whsec_' . Str::token(20);
    }
}
