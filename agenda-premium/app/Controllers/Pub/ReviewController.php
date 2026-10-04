<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Clock;
use App\Core\Controller;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;

/** Reseña de una persona tras su cita (enlace privado de un solo uso). */
final class ReviewController extends Controller
{
    public function show(Request $req, array $p): Response
    {
        return $this->page($this->row($p), null, []);
    }

    public function submit(Request $req, array $p): Response
    {
        $row = $this->row($p);
        if ($row['status'] !== 'requested') {
            return $this->page($row, null, []);
        }
        if (!RateLimiter::hit(PubSupport::bucket($req, 'review'), 10, 600)) {
            return $this->page($row, 'Demasiados intentos seguidos. Espera unos minutos.', [], 429);
        }
        if (trim((string) $req->input('company_site', '')) !== '') {
            return $this->page($row, 'No pudimos guardar tu reseña.', [], 422);
        }
        $rating = (int) $req->input('rating', 0);
        $comment = Str::clean((string) $req->input('comment', ''), 1500);
        $values = ['rating' => $rating, 'comment' => $comment];
        if ($rating < 1 || $rating > 5) {
            return $this->page($row, 'Elige de 1 a 5 estrellas para tu calificación.', $values, 422);
        }
        // Solo una vez: la condición evita doble envío.
        $n = Db::update('reviews', ['rating' => $rating, 'comment' => $comment !== '' ? $comment : null, 'status' => 'pending', 'submitted_at' => Clock::utc()], "token = ? AND status = 'requested'", [$row['token']]);
        if ($n < 1) {
            return $this->page($row, null, []);
        }
        return Response::redirect(url('/resena/' . $row['token']));
    }

    private function row(array $p): array
    {
        $t = PubSupport::token($p);
        $row = Db::one('SELECT * FROM reviews WHERE token = ?', [$t]);
        if (!$row) {
            throw new HttpException(404);
        }
        return $row;
    }

    private function page(array $row, ?string $error, array $values, int $status = 200): Response
    {
        $when = '';
        $eventName = '';
        $hostName = '';
        if (!empty($row['booking_id'])) {
            $b = Db::one('SELECT b.starts_at, b.guest_timezone, e.name AS event_name, h.name AS host_name FROM bookings b JOIN event_types e ON e.id = b.event_type_id JOIN hosts h ON h.id = b.host_id WHERE b.id = ?', [(int) $row['booking_id']]);
            if ($b) {
                $tz = \App\Core\Tz::safe((string) $b['guest_timezone'], Settings::tz());
                $when = Fmt::dateLong((string) $b['starts_at'], $tz);
                $eventName = (string) $b['event_name'];
                $hostName = (string) $b['host_name'];
            }
        }
        $r = $this->view('public/review', [
            'title' => 'Cuéntanos cómo te fue',
            'noindex' => true,
            'biz' => PubSupport::biz(),
            'row' => $row,
            'firstName' => explode(' ', trim((string) $row['client_name']))[0] ?? '',
            'when' => $when, 'eventName' => $eventName, 'hostName' => $hostName,
            'done' => $row['status'] !== 'requested',
            'error' => $error,
            'values' => $values,
            'nav' => '',
            'scripts' => ['js/public.js'],
            'styles' => ['css/booking.css'],
            'bodyClass' => 'pub-review',
        ], 'layouts/public');
        $r->status = $status;
        $r->header('Cache-Control', 'private, no-store');
        return $r;
    }
}
