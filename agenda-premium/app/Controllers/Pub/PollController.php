<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Controller;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;
use App\Services\PollService;

/** Votación de horarios (sí / quizá / no). Solo se muestran totales, nunca nombres ni correos de otras personas. */
final class PollController extends Controller
{
    public function show(Request $req, array $p): Response
    {
        return $this->page(PubSupport::token($p), null, [], (string) $req->get('m', '') === 'ok');
    }

    public function vote(Request $req, array $p): Response
    {
        $token = PubSupport::token($p);
        if (trim((string) $req->input('company_site', '')) !== '') {
            throw new HttpException(404);
        }
        if (!RateLimiter::hit(PubSupport::bucket($req, 'poll'), 15, 600)) {
            return $this->page($token, 'Demasiados intentos seguidos. Espera unos minutos.', [], false, 429);
        }
        $name = Str::clean((string) $req->input('name', ''), 160);
        $email = strtolower(Str::clean((string) $req->input('email', ''), 190));
        $raw = $req->input('v', []);
        $votes = [];
        foreach (is_array($raw) ? $raw : [] as $oid => $v) {
            if (ctype_digit((string) $oid) && in_array($v, ['yes', 'maybe', 'no'], true)) {
                $votes[(int) $oid] = $v;
            }
        }
        $values = ['name' => $name, 'email' => $email, 'v' => $votes];
        if (mb_strlen($name) < 2) {
            return $this->page($token, 'Escribe tu nombre para que sepamos quién votó.', $values, false, 422);
        }
        if (!Validator::email($email)) {
            return $this->page($token, 'Revisa tu correo: parece que le falta algo.', $values, false, 422);
        }
        if (!$votes) {
            return $this->page($token, 'Elige al menos una respuesta para los horarios.', $values, false, 422);
        }
        try {
            PollService::vote($token, $name, $email, $votes);
        } catch (\InvalidArgumentException $e) {
            return $this->page($token, $e->getMessage(), $values, false, 422);
        } catch (\Throwable $e) {
            Logger::error('Falló el voto de encuesta', $e);
            return $this->page($token, 'No pudimos guardar tu voto. Inténtalo de nuevo.', $values, false, 500);
        }
        return Response::redirect(url('/encuesta/' . $token, ['m' => 'ok']));
    }

    private function page(string $token, ?string $error, array $values, bool $saved, int $status = 200): Response
    {
        $poll = PollService::get($token);
        if (!$poll) {
            throw new HttpException(404);
        }
        $tz = Tz::safe((string) $poll['timezone']);
        $options = [];
        foreach ($poll['options'] as $o) {
            $options[] = [
                'id' => (int) $o['id'],
                'date' => Fmt::dateLong((string) $o['starts_at'], $tz),
                'time' => Fmt::time((string) $o['starts_at'], $tz),
                'iso' => Tz::iso((string) $o['starts_at']),
                'yes' => (int) $o['yes'], 'maybe' => (int) $o['maybe'], 'no' => (int) $o['no'],
                'best' => (int) $poll['best_option_id'] === (int) $o['id'],
                'final' => (int) ($poll['final_option_id'] ?? 0) === (int) $o['id'],
            ];
        }
        $r = $this->view('public/poll', [
            'title' => (string) $poll['title'],
            'noindex' => true,
            'biz' => PubSupport::biz(),
            'poll' => $poll,
            'options' => $options,
            'tzLabel' => Fmt::tzLabel($tz),
            'duration' => Fmt::duration((int) $poll['duration']),
            'host' => (string) Db::val('SELECT name FROM hosts WHERE id = ?', [(int) $poll['host_id']]),
            'error' => $error, 'values' => $values, 'saved' => $saved,
            'nav' => '',
            'styles' => ['css/booking.css'],
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-poll',
        ], 'layouts/public');
        $r->status = $status;
        $r->header('Cache-Control', 'private, no-store');
        return $r;
    }
}
