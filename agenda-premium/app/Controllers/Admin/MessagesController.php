<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tz;

/** Centro "Mensajes por enviar hoy": cola de WhatsApp de un toque. */
final class MessagesController extends A3Controller
{
    public function index(Request $req, array $p): Response
    {
        $today = $this->today();
        $to = $this->dateParam($req, 'hasta', $today);
        $from = $this->dateParam($req, 'desde', '');
        $tab = $req->str('estado', 12);
        $tab = in_array($tab, ['pending', 'sent', 'dismissed'], true) ? $tab : 'pending';
        [, $toUtc] = $this->rangeUtc($to, $to);

        $where = ['m.status = ?'];
        $args = [$tab];
        if ($tab === 'pending') {
            $where[] = 'm.due_at <= ?';
            $args[] = $toUtc;
            if ($from !== '') {
                [$fromUtc] = $this->rangeUtc($from, $from);
                $where[] = 'm.due_at >= ?';
                $args[] = $fromUtc;
            }
        } else {
            $histFrom = $from !== '' ? $from : Tz::formatTs(Tz::localToTs($to . ' 12:00:00', $this->bizTz()) - 6 * 86400, $this->bizTz(), 'Y-m-d');
            [$fromUtc] = $this->rangeUtc($histFrom, $histFrom);
            $where[] = 'COALESCE(m.sent_at, m.created_at) >= ? AND COALESCE(m.sent_at, m.created_at) <= ?';
            $args[] = $fromUtc;
            $args[] = $toUtc;
        }
        $scope = $this->scope();
        if ($scope !== null) {
            $where[] = 'b.host_id = ?';
            $args[] = $scope;
        }
        $rows = Db::all(
            'SELECT m.*, b.starts_at, b.guest_name, b.guest_timezone, b.host_id, h.name AS host_name, e.name AS event_name
             FROM message_queue m
             LEFT JOIN bookings b ON b.id = m.booking_id
             LEFT JOIN hosts h ON h.id = b.host_id
             LEFT JOIN event_types e ON e.id = b.event_type_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY m.due_at ASC, m.id ASC LIMIT 300',
            $args
        );
        $tzBiz = $this->bizTz();
        $nowUtc = Clock::utc();
        foreach ($rows as &$r) {
            $r['wa_url'] = $this->waLink((string) $r['phone'], (string) $r['body']);
            $r['overdue'] = $r['due_at'] < Tz::localToUtc($today . ' 00:00:00', $tzBiz);
        }
        unset($r);

        $counts = ['pending' => 0, 'sent' => 0, 'dismissed' => 0];
        $cw = $scope !== null ? ' AND b.host_id = ?' : '';
        $ca = $scope !== null ? [$scope] : [];
        [, $todayEnd] = $this->rangeUtc($today, $today);
        foreach (Db::all('SELECT m.status, COUNT(*) c FROM message_queue m LEFT JOIN bookings b ON b.id = m.booking_id WHERE (m.status <> \'pending\' OR m.due_at <= ?)' . $cw . ' GROUP BY m.status', array_merge([$todayEnd], $ca)) as $c) {
            if (isset($counts[$c['status']])) {
                $counts[$c['status']] = (int) $c['c'];
            }
        }
        return $this->page('admin/messages/index', compact('rows', 'tab', 'from', 'to', 'today', 'counts', 'nowUtc'), '/admin/mensajes', 'Mensajes de hoy');
    }

    public function sent(Request $req, array $p): Response
    {
        return $this->mark($req, (int) $p['id'], 'sent');
    }

    public function dismiss(Request $req, array $p): Response
    {
        return $this->mark($req, (int) $p['id'], 'dismissed');
    }

    private function mark(Request $req, int $id, string $status): Response
    {
        $m = Db::one('SELECT m.*, b.host_id FROM message_queue m LEFT JOIN bookings b ON b.id = m.booking_id WHERE m.id = ?', [$id]);
        $scope = $this->scope();
        if (!$m || ($scope !== null && (int) ($m['host_id'] ?? 0) !== $scope)) {
            throw new HttpException(404);
        }
        if ($m['status'] === 'pending') {
            Db::update('message_queue', ['status' => $status, 'sent_at' => $status === 'sent' ? Clock::utc() : null], 'id = ?', [$id]);
            $this->audit('message.' . $status, 'message_queue', $id);
        }
        if ($req->wantsJson()) {
            return $this->json(['ok' => true]);
        }
        $this->flash('success', $status === 'sent' ? 'Listo, el mensaje quedó marcado como enviado.' : 'El mensaje se descartó.');
        $qs = array_filter(['hasta' => $req->str('hasta', 10), 'desde' => $req->str('desde', 10)]);
        return $this->redirect('/admin/mensajes', $qs);
    }

    private function waLink(string $phone, string $text): string
    {
        if ($this->svc('WhatsAppService')) {
            try {
                return \App\Services\WhatsAppService::link($phone, $text);
            } catch (\Throwable $e) {
                // se usa el enlace de respaldo
            }
        }
        return 'https://wa.me/' . preg_replace('/\D+/', '', $phone) . '?text=' . rawurlencode($text);
    }
}
