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

/** Reseñas: moderación, solicitud manual y promedio por anfitrión. */
final class ReviewsController extends A3Controller
{
    private const STATUSES = ['pending' => 'Por moderar', 'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'requested' => 'Solicitada'];

    public function index(Request $req, array $p): Response
    {
        $status = $req->str('estado', 10);
        $status = isset(self::STATUSES[$status]) ? $status : 'pending';
        $scope = $this->scope();
        $sc = $scope !== null ? ' AND r.host_id = ?' : '';
        $sa = $scope !== null ? [$scope] : [];
        $rows = Db::all('SELECT r.*, h.name AS host_name, b.starts_at, b.guest_phone, b.guest_email, e.name AS event_name
            FROM reviews r LEFT JOIN hosts h ON h.id = r.host_id LEFT JOIN bookings b ON b.id = r.booking_id LEFT JOIN event_types e ON e.id = b.event_type_id
            WHERE r.status = ?' . $sc . ' ORDER BY COALESCE(r.submitted_at, r.created_at) DESC, r.id DESC LIMIT 200', array_merge([$status], $sa));
        foreach ($rows as &$r) {
            $r['link'] = abs_url('/resena/' . $r['token']);
            $r['wa'] = $this->wa((string) ($r['guest_phone'] ?? ''), 'Hola ' . $r['client_name'] . ', gracias por tu visita a ' . (string) Settings::get('business_name', '') . '. ¿Nos cuentas cómo te fue? Déjanos tu reseña aquí: ' . $r['link']);
        }
        unset($r);
        $counts = [];
        foreach (Db::all('SELECT r.status, COUNT(*) c FROM reviews r WHERE 1=1' . $sc . ' GROUP BY r.status', $sa) as $c) {
            $counts[$c['status']] = (int) $c['c'];
        }
        $avg = Db::all("SELECT h.id, h.name, AVG(r.rating) AS avg_rating, COUNT(r.id) AS n FROM hosts h LEFT JOIN reviews r ON r.host_id = h.id AND r.status = 'approved' AND r.rating IS NOT NULL" . ($scope !== null ? ' WHERE h.id = ?' : ' WHERE h.active = 1') . ' GROUP BY h.id, h.name ORDER BY h.name', $sa);
        $eligible = Db::all("SELECT b.id, b.guest_name, b.starts_at, e.name AS event_name FROM bookings b JOIN event_types e ON e.id = b.event_type_id
            WHERE b.status = 'completed' AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.booking_id = b.id)" . ($scope !== null ? ' AND b.host_id = ?' : '') . ' ORDER BY b.starts_at DESC LIMIT 60', $sa);
        return $this->page('admin/reviews/index', ['rows' => $rows, 'status' => $status, 'statuses' => self::STATUSES, 'counts' => $counts, 'avg' => $avg, 'eligible' => $eligible], '/admin/resenas', 'Reseñas');
    }

    public function approve(Request $req, array $p): Response
    {
        return $this->moderate((int) $p['id'], 'approved');
    }

    public function reject(Request $req, array $p): Response
    {
        return $this->moderate((int) $p['id'], 'rejected');
    }

    private function moderate(int $id, string $to): Response
    {
        $r = $this->review($id);
        if (!in_array($r['status'], ['pending', 'approved', 'rejected'], true)) {
            $this->flash('warn', 'Esa reseña todavía no fue enviada por el cliente.');
            return $this->redirect('/admin/resenas');
        }
        Db::update('reviews', ['status' => $to], 'id = ?', [$id]);
        $this->audit('review.' . $to, 'review', $id);
        $this->flash('success', $to === 'approved' ? 'Reseña aprobada: ya puede mostrarse en tu página.' : 'Reseña rechazada: no se mostrará.');
        return $this->redirect('/admin/resenas', ['estado' => $r['status']]);
    }

    public function request(Request $req, array $p): Response
    {
        $bid = $req->int('booking_id');
        $b = $this->bookingOr404($bid);
        if (Db::val('SELECT id FROM reviews WHERE booking_id = ?', [$bid]) !== null) {
            return $this->fail($req, 'Ya se solicitó una reseña para esa cita.', '/admin/resenas');
        }
        $token = Str::token();
        $id = Db::insert('reviews', [
            'token' => $token, 'booking_id' => $bid, 'host_id' => (int) $b['host_id'], 'client_name' => (string) $b['guest_name'],
            'status' => 'requested', 'created_at' => Clock::utc(),
        ]);
        $link = abs_url('/resena/' . $token);
        $sent = false;
        if (!empty($b['guest_email']) && class_exists('App\\Services\\Mailer')) {
            try {
                $biz = (string) Settings::get('business_name', '');
                $html = '<p>Hola ' . e($b['guest_name']) . ',</p><p>Gracias por visitarnos. Tu opinión nos ayuda a mejorar y a que otras personas nos conozcan.</p><p><a href="' . e($link) . '">Dejar mi reseña</a></p>';
                \App\Services\Mailer::queue((string) $b['guest_email'], (string) $b['guest_name'], '¿Cómo te fue en ' . $biz . '?', \App\Services\Mailer::layout('Cuéntanos cómo te fue', $html), 'Déjanos tu reseña aquí: ' . $link);
                $sent = true;
            } catch (\Throwable $e) {
                Logger::error('Solicitud de reseña', $e);
            }
        }
        $this->audit('review.request', 'review', $id, 'cita ' . $bid);
        $this->flash('success', $sent ? 'Solicitud enviada por correo. También puedes mandarla por WhatsApp desde la pestaña "Solicitadas".' : 'Solicitud creada. Envía el enlace por WhatsApp desde la pestaña "Solicitadas".');
        return $this->redirect('/admin/resenas', ['estado' => 'requested']);
    }

    private function review(int $id): array
    {
        $r = Db::one('SELECT * FROM reviews WHERE id = ?', [$id]);
        $scope = $this->scope();
        if (!$r || ($scope !== null && (int) $r['host_id'] !== $scope)) {
            throw new HttpException(404);
        }
        return $r;
    }

    private function wa(string $phone, string $text): string
    {
        $d = Str::phone($phone) ?? '';
        if ($d === '') {
            return '';
        }
        if (class_exists('App\\Services\\WhatsAppService')) {
            try {
                return \App\Services\WhatsAppService::link($d, $text);
            } catch (\Throwable $e) {
                // se usa el enlace directo
            }
        }
        return 'https://wa.me/' . $d . '?text=' . rawurlencode($text);
    }
}
