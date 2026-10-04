<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tz;
use App\Core\Validator;
use App\Services\BookingException;
use App\Services\PollService;

/** Encuestas de horarios: creación, resultados, cierre y finalización con cita real. */
final class PollsController extends A3Controller
{
    public function index(Request $req, array $p): Response
    {
        $scope = $this->scope();
        $where = $scope !== null ? 'WHERE p.host_id = ?' : '';
        $rows = Db::all(
            'SELECT p.*, h.name AS host_name, e.name AS event_name,
                    (SELECT COUNT(*) FROM poll_options o WHERE o.poll_id = p.id) AS option_count,
                    (SELECT COUNT(DISTINCT v.voter_email) FROM poll_votes v WHERE v.poll_id = p.id) AS voter_count
             FROM polls p JOIN hosts h ON h.id = p.host_id JOIN event_types e ON e.id = p.event_type_id ' . $where . ' ORDER BY p.id DESC LIMIT 200',
            $scope !== null ? [$scope] : []
        );
        return $this->page('admin/polls/index', ['rows' => $rows, 'now' => Clock::utc()], '/admin/encuestas', 'Encuestas de horarios');
    }

    public function form(Request $req, array $p): Response
    {
        return $this->page('admin/polls/form', ['hosts' => $this->hosts(), 'events' => $this->events(), 'tz' => $this->bizTz(), 'minDate' => $this->today() . 'T08:00'], '/admin/encuestas', 'Nueva encuesta');
    }

    public function create(Request $req, array $p): Response
    {
        $tz = $this->tzParam($req, 'timezone');
        $hostId = $req->int('host_id');
        if (!Auth::canAccessHost($hostId)) {
            throw new HttpException(403);
        }
        $raw = $req->input('opts', []);
        $options = [];
        foreach (is_array($raw) ? $raw : [] as $v) {
            $v = is_scalar($v) ? trim((string) $v) : '';
            if ($v === '') {
                continue;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v) || !Validator::date(substr($v, 0, 10))) {
                return $this->fail($req, 'Una de las fechas no tiene un formato válido.', '/admin/encuestas/nueva');
            }
            $options[] = Tz::localToUtc(str_replace('T', ' ', $v) . ':00', $tz);
        }
        $deadline = trim($req->str('deadline', 16));
        $deadlineUtc = null;
        if ($deadline !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $deadline) || !Validator::date(substr($deadline, 0, 10))) {
                return $this->fail($req, 'La fecha límite no es válida.', '/admin/encuestas/nueva');
            }
            $deadlineUtc = Tz::localToUtc(str_replace('T', ' ', $deadline) . ':00', $tz);
        }
        try {
            $id = PollService::create([
                'title' => $req->str('title', 190), 'description' => $req->str('description', 2000), 'host_id' => $hostId,
                'event_type_id' => $req->int('event_type_id'), 'duration' => $req->int('duration', 60), 'timezone' => $tz, 'deadline_at' => $deadlineUtc,
            ], $options);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($req, $e->getMessage(), '/admin/encuestas/nueva');
        }
        $this->audit('poll.create', 'poll', $id, $req->str('title', 80));
        $this->flash('success', 'Encuesta creada. Comparte el enlace para empezar a recibir votos.');
        return $this->redirect('/admin/encuestas/' . $id);
    }

    public function show(Request $req, array $p): Response
    {
        $poll = $this->load((int) $p['id']);
        $host = Db::one('SELECT name FROM hosts WHERE id = ?', [$poll['host_id']]);
        $event = Db::one('SELECT name FROM event_types WHERE id = ?', [$poll['event_type_id']]);
        $booking = $poll['final_booking_id'] ? Db::one('SELECT id, starts_at FROM bookings WHERE id = ?', [$poll['final_booking_id']]) : null;
        return $this->page('admin/polls/show', ['poll' => $poll, 'host' => $host, 'event' => $event, 'booking' => $booking, 'link' => abs_url('/encuesta/' . $poll['token'])], '/admin/encuestas', (string) $poll['title']);
    }

    public function close(Request $req, array $p): Response
    {
        $poll = $this->load((int) $p['id']);
        PollService::close((int) $poll['id']);
        $this->audit('poll.close', 'poll', $poll['id']);
        $this->flash('success', 'La encuesta se cerró: ya no recibe votos.');
        return $this->redirect('/admin/encuestas/' . (int) $poll['id']);
    }

    public function finalize(Request $req, array $p): Response
    {
        $poll = $this->load((int) $p['id']);
        $back = '/admin/encuestas/' . (int) $poll['id'];
        $user = Auth::user();
        try {
            $r = PollService::finalize((int) $poll['id'], $req->int('option_id'), ['type' => 'user', 'label' => (string) ($user['name'] ?? 'Administración'), 'user_id' => $this->userId()]);
        } catch (\InvalidArgumentException | BookingException $e) {
            return $this->fail($req, $e->getMessage(), $back);
        }
        $this->audit('poll.finalize', 'poll', $poll['id'], 'cita ' . (int) $r['booking']['id']);
        $this->flash('success', 'Horario confirmado y cita creada. Avisamos por correo a ' . (int) $r['notified'] . ' persona(s).');
        return $this->redirect($back);
    }

    public function delete(Request $req, array $p): Response
    {
        $poll = $this->load((int) $p['id']);
        Db::delete('polls', 'id = ?', [(int) $poll['id']]);
        $this->audit('poll.delete', 'poll', $poll['id']);
        $this->flash('success', 'La encuesta se eliminó.');
        return $this->redirect('/admin/encuestas');
    }

    private function load(int $id): array
    {
        $poll = PollService::getById($id, true);
        if (!$poll || !Auth::canAccessHost((int) $poll['host_id'])) {
            throw new HttpException(404);
        }
        return $poll;
    }
}
