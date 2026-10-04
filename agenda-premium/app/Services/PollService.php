<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Encuestas de horario: varias opciones, votos sí/quizá/no y, al finalizar, una cita real. */
final class PollService
{
    private const MAX_VOTERS = 200;

    /**
     * d: title, description, host_id, event_type_id, duration, timezone, deadline_at (UTC, opcional).
     * $optionsUtc: inicios en UTC "Y-m-d H:i:s" (entre 2 y 20, todos futuros).
     * @throws \InvalidArgumentException
     */
    public static function create(array $d, array $optionsUtc): int
    {
        $title = Str::clean((string) ($d['title'] ?? ''), 190);
        if ($title === '') {
            throw new \InvalidArgumentException('Ponle un título a la encuesta.');
        }
        $duration = Validator::intRange($d['duration'] ?? 60, 5, 480);
        if ($duration === null) {
            throw new \InvalidArgumentException('La duración debe estar entre 5 y 480 minutos.');
        }
        if (Db::val('SELECT id FROM hosts WHERE id = ? AND active = 1', [(int) ($d['host_id'] ?? 0)]) === null) {
            throw new \InvalidArgumentException('Elige un anfitrión válido para la encuesta.');
        }
        if (Db::val('SELECT id FROM event_types WHERE id = ?', [(int) ($d['event_type_id'] ?? 0)]) === null) {
            throw new \InvalidArgumentException('Elige un tipo de cita válido para la encuesta.');
        }
        $tz = Tz::safe((string) ($d['timezone'] ?? ''), Settings::tz());
        $deadline = isset($d['deadline_at']) && $d['deadline_at'] !== '' ? (string) $d['deadline_at'] : null;
        if ($deadline !== null && (!Validator::datetimeUtc($deadline) || Tz::ts($deadline) <= Clock::now())) {
            throw new \InvalidArgumentException('La fecha límite para votar debe ser futura.');
        }
        $opts = [];
        foreach ($optionsUtc as $o) {
            $o = (string) $o;
            if (!Validator::datetimeUtc($o)) {
                throw new \InvalidArgumentException('Una de las opciones de horario no es válida.');
            }
            if (Tz::ts($o) <= Clock::now()) {
                throw new \InvalidArgumentException('Todas las opciones de horario deben estar en el futuro.');
            }
            $opts[$o] = true;
        }
        $opts = array_keys($opts);
        sort($opts);
        if (count($opts) < 2) {
            throw new \InvalidArgumentException('Propón al menos 2 horarios distintos para que haya algo que votar.');
        }
        if (count($opts) > 20) {
            throw new \InvalidArgumentException('Puedes proponer como máximo 20 horarios.');
        }
        return (int) Db::tx(static function () use ($title, $d, $duration, $tz, $deadline, $opts): int {
            $id = Db::insert('polls', [
                'token' => Str::token(),
                'title' => $title,
                'description' => ($desc = Str::clean((string) ($d['description'] ?? ''), 2000)) !== '' ? $desc : null,
                'host_id' => (int) $d['host_id'],
                'event_type_id' => (int) $d['event_type_id'],
                'duration' => $duration,
                'timezone' => $tz,
                'status' => 'open',
                'deadline_at' => $deadline,
                'created_at' => Clock::utc(),
            ]);
            foreach ($opts as $o) {
                Db::insert('poll_options', ['poll_id' => $id, 'starts_at' => $o, 'ends_at' => Tz::fromTs(Tz::ts($o) + $duration * 60)]);
            }
            return $id;
        });
    }

    /**
     * Encuesta con opciones, totales y mejor opción. Los correos de los votantes solo se incluyen con $withEmails (panel).
     * Cada opción: id, starts_at, ends_at, yes, maybe, no, score; 'voters' = [{name,(email),votes[optionId=>vote]}].
     */
    public static function get(string $token, bool $withEmails = false): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $p = Db::one('SELECT * FROM polls WHERE token = ?', [$token]);
        return $p === null ? null : self::load($p, $withEmails);
    }

    public static function getById(int $id, bool $withEmails = true): ?array
    {
        $p = Db::one('SELECT * FROM polls WHERE id = ?', [$id]);
        return $p === null ? null : self::load($p, $withEmails);
    }

    private static function load(array $p, bool $withEmails): array
    {
        $pid = (int) $p['id'];
        $opts = Db::all('SELECT * FROM poll_options WHERE poll_id = ? ORDER BY starts_at, id', [$pid]);
        $byId = [];
        foreach ($opts as $o) {
            $byId[(int) $o['id']] = $o + ['yes' => 0, 'maybe' => 0, 'no' => 0, 'score' => 0];
        }
        $voters = [];
        foreach (Db::all('SELECT * FROM poll_votes WHERE poll_id = ? ORDER BY created_at, id', [$pid]) as $v) {
            $oid = (int) $v['option_id'];
            if (!isset($byId[$oid])) {
                continue;
            }
            $byId[$oid][$v['vote']]++;
            $key = strtolower((string) $v['voter_email']);
            $voters[$key] ??= ['name' => $v['voter_name'], 'votes' => []] + ($withEmails ? ['email' => $v['voter_email']] : []);
            $voters[$key]['votes'][$oid] = $v['vote'];
        }
        $best = null;
        foreach ($byId as $oid => &$o) {
            $o['score'] = $o['yes'] * 2 + $o['maybe'];
            if ($o['yes'] + $o['maybe'] > 0 && ($best === null || [$o['yes'], $o['maybe']] > [$byId[$best]['yes'], $byId[$best]['maybe']])) {
                $best = $oid; // empate: gana el horario más temprano (ya vienen ordenados)
            }
        }
        unset($o);
        $p['options'] = array_values($byId);
        $p['voters'] = array_values($voters);
        $p['voter_count'] = count($voters);
        $p['best_option_id'] = $best;
        $p['is_open'] = $p['status'] === 'open' && ($p['deadline_at'] === null || Tz::ts((string) $p['deadline_at']) > Clock::now());
        return $p;
    }

    /**
     * votes: [optionId => 'yes'|'maybe'|'no']. Si la persona (por correo) ya votó, se actualiza su voto.
     * @throws \InvalidArgumentException
     */
    public static function vote(string $token, string $name, string $email, array $votes): void
    {
        $name = Str::clean($name, 160);
        $email = strtolower(trim($email));
        if ($name === '') {
            throw new \InvalidArgumentException('Escribe tu nombre para votar.');
        }
        if (!Validator::email($email)) {
            throw new \InvalidArgumentException('El correo no parece válido. Revísalo, por favor.');
        }
        Db::tx(static function () use ($token, $name, $email, $votes): void {
            $p = preg_match('/^[a-f0-9]{32}$/', $token) ? Db::one('SELECT * FROM polls WHERE token = ? FOR UPDATE', [$token]) : null;
            if ($p === null) {
                throw new \InvalidArgumentException('No encontramos esta encuesta. Revisa el enlace.');
            }
            if ($p['status'] !== 'open') {
                throw new \InvalidArgumentException('Esta encuesta ya está cerrada y no recibe más votos.');
            }
            if ($p['deadline_at'] !== null && Tz::ts((string) $p['deadline_at']) <= Clock::now()) {
                throw new \InvalidArgumentException('Terminó el plazo para votar en esta encuesta.');
            }
            $valid = array_map('intval', Db::col('SELECT id FROM poll_options WHERE poll_id = ?', [(int) $p['id']]));
            $clean = [];
            foreach ($votes as $oid => $v) {
                if (!in_array((int) $oid, $valid, true) || !in_array($v, ['yes', 'maybe', 'no'], true)) {
                    throw new \InvalidArgumentException('Uno de los votos no es válido. Recarga la página e inténtalo de nuevo.');
                }
                $clean[(int) $oid] = $v;
            }
            if ($clean === []) {
                throw new \InvalidArgumentException('Elige tu respuesta (sí, quizá o no) en al menos un horario.');
            }
            $known = Db::val('SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND voter_email = ?', [(int) $p['id'], $email]);
            if ((int) $known === 0 && (int) Db::val('SELECT COUNT(DISTINCT voter_email) FROM poll_votes WHERE poll_id = ?', [(int) $p['id']]) >= self::MAX_VOTERS) {
                throw new \InvalidArgumentException('Esta encuesta alcanzó el máximo de participantes.');
            }
            foreach ($clean as $oid => $v) {
                Db::exec(
                    'INSERT INTO poll_votes (poll_id, option_id, voter_name, voter_email, vote, created_at) VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE vote = VALUES(vote), voter_name = VALUES(voter_name)',
                    [(int) $p['id'], $oid, $name, $email, $v, Clock::utc()]
                );
            }
        });
    }

    public static function close(int $pollId): void
    {
        Db::exec("UPDATE polls SET status = 'closed' WHERE id = ? AND status = 'open'", [$pollId]);
    }

    /**
     * Elige el horario final y crea UNA cita (created_via 'poll', force) con el primer votante sí/quizá como invitado principal
     * y los demás como invitados adicionales. Encola el correo de confirmación con .ics para los votantes sí/quizá.
     * @param array $actor ['type','label','user_id']
     * @return array ['booking'=>fila,'option'=>fila,'notified'=>int]
     * @throws \InvalidArgumentException|BookingException
     */
    public static function finalize(int $pollId, int $optionId, array $actor): array
    {
        $res = Db::tx(static function () use ($pollId, $optionId, $actor): array {
            $p = Db::one('SELECT * FROM polls WHERE id = ? FOR UPDATE', [$pollId]);
            if ($p === null) {
                throw new \InvalidArgumentException('No encontramos la encuesta.');
            }
            if ($p['status'] === 'finalized') {
                throw new \InvalidArgumentException('Esta encuesta ya se finalizó y tiene una cita creada.');
            }
            $opt = Db::one('SELECT * FROM poll_options WHERE id = ? AND poll_id = ?', [$optionId, $pollId]);
            if ($opt === null) {
                throw new \InvalidArgumentException('Ese horario no pertenece a la encuesta.');
            }
            $voters = Db::all(
                "SELECT voter_name, voter_email FROM poll_votes WHERE poll_id = ? AND option_id = ? AND vote IN ('yes','maybe') ORDER BY created_at, id",
                [$pollId, $optionId]
            );
            if ($voters === []) {
                throw new \InvalidArgumentException('Nadie respondió «sí» o «quizá» a ese horario. Elige otro o espera más votos.');
            }
            $main = array_shift($voters);
            $guests = [];
            foreach ($voters as $v) {
                $guests[] = ['name' => $v['voter_name'], 'email' => $v['voter_email']];
            }
            $out = BookingService::create([
                'event_id' => (int) $p['event_type_id'],
                'duration' => (int) $p['duration'],
                'start' => (string) $opt['starts_at'],
                'host_id' => (int) $p['host_id'],
                'timezone' => (string) $p['timezone'],
                'name' => $main['voter_name'],
                'email' => $main['voter_email'],
                'phone' => '',
                'notes' => 'Cita creada desde la encuesta «' . $p['title'] . '».',
                'guests' => $guests,
                'seats' => 1,
                'created_via' => 'poll',
                'force' => true,
                'consent' => true,
                'created_by' => isset($actor['user_id']) ? (int) $actor['user_id'] : null,
            ]);
            $booking = $out['booking'];
            Db::update('polls', ['status' => 'finalized', 'final_option_id' => $optionId, 'final_booking_id' => (int) $booking['id']], 'id = ?', [$pollId]);
            BookingService::log((int) $booking['id'], 'poll', 'Creada desde la encuesta «' . $p['title'] . '» por ' . (string) ($actor['label'] ?? 'sistema'), (string) ($actor['label'] ?? 'sistema'));
            return ['booking' => $booking, 'option' => $opt, 'poll' => $p, 'recipients' => array_merge([$main], $voters)];
        });
        $notified = self::notify($res['booking'], $res['poll'], $res['recipients']);
        return ['booking' => $res['booking'], 'option' => $res['option'], 'notified' => $notified];
    }

    /** Correo de confirmación con .ics a los votantes sí/quizá. Los fallos de correo no deshacen la cita. */
    private static function notify(array $booking, array $poll, array $recipients): int
    {
        $n = 0;
        try {
            $display = BookingService::display($booking);
            $ics = IcsService::generate($display);
        } catch (\Throwable $e) {
            Logger::error('Encuesta: no se pudo preparar el archivo de calendario', $e);
            $display = null;
            $ics = null;
        }
        foreach ($recipients as $r) {
            try {
                $tz = Tz::safe((string) $poll['timezone']);
                $when = Fmt::dateTime((string) $booking['starts_at'], $tz);
                $html = '<p>Hola ' . e($r['voter_name']) . ',</p><p>Quedó confirmado el horario de <strong>' . e($poll['title']) . '</strong>:</p>'
                    . '<p style="font-size:18px"><strong>' . e($when) . '</strong><br>' . e(Fmt::tzLabel($tz, (string) $booking['starts_at'])) . '</p>'
                    . '<p>Adjuntamos la invitación para que la agregues a tu calendario.</p>';
                $att = $ics !== null ? [['name' => 'cita.ics', 'mime' => 'text/calendar', 'content' => $ics]] : [];
                Mailer::queue((string) $r['voter_email'], (string) $r['voter_name'], 'Confirmado: ' . $poll['title'], Mailer::layout('Horario confirmado', $html), 'Quedó confirmado ' . $poll['title'] . ': ' . $when . '.', $att);
                $n++;
            } catch (\Throwable $e) {
                Logger::error('Encuesta: no se pudo encolar la confirmación', $e);
            }
        }
        return $n;
    }
}
