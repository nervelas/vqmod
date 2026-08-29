<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Request;
use App\Models\Quote;

final class BoardController extends Controller
{
    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel();
        $cid = (int) $c['id'];
        $mine = Auth::ownerFilter();
        $userFilter = Request::int('vendedor');
        if ($mine) {
            $userFilter = $mine;
        }

        $columns = [];
        foreach (array_keys(Quote::STATUSES) as $st) {
            [$rows, $total] = Quote::search($cid, [
                'status'  => [$st],
                'user_id' => $userFilter,
                'q'       => Request::str('q'),
                'sort'    => 'seguimiento',
                'limit'   => 60,
            ]);
            $columns[$st] = [
                'rows'  => $rows,
                'total' => $total,
                'monto' => array_sum(array_map(static fn ($r) => (float) $r['total'], $rows)),
            ];
        }

        $this->view('panel/board', [
            'title'   => 'Tablero de cotizaciones',
            'columns' => $columns,
            'sellers' => DB::all('SELECT id, name FROM users WHERE company_id = ? AND role IN ("admin","vendedor") AND status = "activo" ORDER BY name', [$cid]),
            'userFilter' => $userFilter,
        ], 'layout/panel');
    }

    /** Mueve una tarjeta entre columnas (AJAX del Kanban). */
    public function move(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        Csrf::verify();
        $cid = (int) $c['id'];
        $id  = Request::int('id');
        $to  = Request::str('status');

        $q = Quote::find($cid, $id);
        if (!$q) {
            jsonOut(['ok' => false, 'error' => 'Cotización no encontrada'], 404);
        }
        if (Auth::isSeller() && (int) $q['user_id'] !== (int) $u['id']) {
            jsonOut(['ok' => false, 'error' => 'Esta cotización pertenece a otro vendedor.'], 403);
        }
        if (!isset(Quote::STATUSES[$to])) {
            jsonOut(['ok' => false, 'error' => 'Estado no válido'], 400);
        }
        if ($to === 'perdida' && Request::str('lost_reason') === '') {
            jsonOut(['ok' => false, 'needReason' => true, 'reasons' => \App\Models\Company::LOST_REASONS]);
        }
        Quote::setStatus($cid, $id, $to, [
            'lost_reason' => Request::str('lost_reason'),
            'lost_detail' => Request::str('lost_detail'),
        ]);
        $fresh = Quote::find($cid, $id);
        jsonOut([
            'ok'    => true,
            'light' => Quote::trafficLight($fresh),
            'totals' => $this->columnTotals($cid),
        ]);
    }

    private function columnTotals(int $cid): array
    {
        $out = [];
        $mine = Auth::ownerFilter();
        foreach (DB::all(
            'SELECT status, COUNT(*) n, COALESCE(SUM(total),0) monto FROM quotes
             WHERE company_id = ? AND is_current = 1' . ($mine ? ' AND user_id = ' . (int) $mine : '') . ' GROUP BY status',
            [$cid]
        ) as $r) {
            $out[$r['status']] = ['n' => (int) $r['n'], 'monto' => (float) $r['monto']];
        }
        return $out;
    }
}
