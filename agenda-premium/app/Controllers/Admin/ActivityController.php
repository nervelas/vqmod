<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;

/** Centro de actividad: auditoría del panel y línea de tiempo de reservas. */
final class ActivityController extends A4Controller
{
    private const PER_PAGE = 25;

    private const HISTORY_LABELS = [
        'created' => 'Cita creada', 'confirmed' => 'Confirmada', 'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'cancelled' => 'Cancelada',
        'rescheduled' => 'Reprogramada', 'completed' => 'Completada', 'no_show' => 'No asistió', 'pending' => 'Pendiente', 'payment' => 'Pago',
        'note' => 'Nota', 'reminder' => 'Recordatorio',
    ];

    public function index(Request $req, array $p): Response
    {
        [$where, $args, $filters] = $this->filters($req);
        $page = max(1, $req->int('pagina', 1));
        $total = (int) Db::val('SELECT COUNT(*) FROM audit_log a WHERE ' . $where, $args);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $rows = Db::all('SELECT a.* FROM audit_log a WHERE ' . $where . ' ORDER BY a.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $args);

        $users = Db::all('SELECT id, name FROM users ORDER BY name');
        $actions = Db::col('SELECT DISTINCT action FROM audit_log ORDER BY action LIMIT 200');

        $timeline = [];
        try {
            $timeline = Db::all(
                'SELECT h.id, h.booking_id, h.action, h.detail, h.actor, h.created_at, b.guest_name, b.starts_at, b.status, e.name AS event_name
                 FROM booking_history h
                 JOIN bookings b ON b.id = h.booking_id
                 LEFT JOIN event_types e ON e.id = b.event_type_id
                 ORDER BY h.id DESC LIMIT 40'
            );
        } catch (\Throwable $e) {
            $timeline = [];
        }
        $res = $this->page('admin/activity/index', [
            'title' => 'Actividad',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'users' => $users,
            'actions' => $actions,
            'timeline' => $timeline,
            'historyLabels' => self::HISTORY_LABELS,
            'query' => array_filter($filters, static fn ($v): bool => $v !== ''),
        ], ['js/admin-settings.js'], '/admin/actividad');
        return $res;
    }

    public function export(Request $req, array $p): Response
    {
        [$where, $args] = $this->filters($req);
        $rows = Db::all('SELECT a.* FROM audit_log a WHERE ' . $where . ' ORDER BY a.id DESC LIMIT 10000', $args);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [self::local($r['created_at'], 'Y-m-d H:i:s'), (string) $r['user_label'], (string) $r['action'], (string) $r['entity'], (string) $r['entity_id'], (string) $r['detail'], (string) $r['ip_trunc']];
        }
        $this->audit('activity.export', count($out) . ' registros', 'audit');
        return $this->csv('actividad-' . $this->stamp() . '.csv', ['Fecha (' . $this->bizTz() . ')', 'Usuario', 'Acción', 'Entidad', 'Id', 'Detalle', 'IP truncada'], $out);
    }

    /** @return array{0:string,1:array,2:array} */
    private function filters(Request $req): array
    {
        $where = ['1=1'];
        $args = [];
        $f = ['usuario' => '', 'accion' => '', 'desde' => '', 'hasta' => ''];
        $u = $req->str('usuario', 12);
        if ($u === 'sistema') {
            $where[] = 'a.user_id IS NULL';
            $f['usuario'] = 'sistema';
        } elseif (ctype_digit($u) && $u !== '') {
            $where[] = 'a.user_id = ?';
            $args[] = (int) $u;
            $f['usuario'] = $u;
        }
        $a = $req->str('accion', 60);
        if ($a !== '') {
            $where[] = 'a.action LIKE ?';
            $args[] = addcslashes($a, '%_\\') . '%';
            $f['accion'] = $a;
        }
        $from = $this->dayStartUtc($req->str('desde', 10));
        if ($from !== null) {
            $where[] = 'a.created_at >= ?';
            $args[] = $from;
            $f['desde'] = $req->str('desde', 10);
        }
        $to = $this->dayStartUtc($req->str('hasta', 10), true);
        if ($to !== null) {
            $where[] = 'a.created_at < ?';
            $args[] = $to;
            $f['hasta'] = $req->str('hasta', 10);
        }
        return [implode(' AND ', $where), $args, $f];
    }
}
