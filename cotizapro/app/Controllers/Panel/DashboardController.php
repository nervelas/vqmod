<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Models\Notification;
use App\Models\Quote;

final class DashboardController extends Controller
{
    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel();
        $cid  = (int) $c['id'];
        $mine = Auth::ownerFilter();

        $w = 'q.company_id = ? AND q.is_current = 1';
        $p = [$cid];
        if ($mine) {
            $w .= ' AND q.user_id = ?';
            $p[] = $mine;
        }

        $monthStart = date('Y-m-01');
        $prevStart  = date('Y-m-01', strtotime('-1 month'));
        $prevEnd    = date('Y-m-t', strtotime('-1 month'));

        $quoted   = (float) DB::value("SELECT COALESCE(SUM(q.total),0) FROM quotes q WHERE {$w} AND q.created_at >= ?", array_merge($p, [$monthStart . ' 00:00:00']), 0);
        $won      = (float) DB::value("SELECT COALESCE(SUM(q.won_amount),0) FROM quotes q WHERE {$w} AND q.status='aprobada' AND q.approved_at >= ?", array_merge($p, [$monthStart . ' 00:00:00']), 0);
        $wonPrev  = (float) DB::value("SELECT COALESCE(SUM(q.won_amount),0) FROM quotes q WHERE {$w} AND q.status='aprobada' AND q.approved_at BETWEEN ? AND ?", array_merge($p, [$prevStart . ' 00:00:00', $prevEnd . ' 23:59:59']), 0);
        $closed   = (int) DB::value("SELECT COUNT(*) FROM quotes q WHERE {$w} AND q.status IN ('aprobada','perdida')", $p, 0);
        $approved = (int) DB::value("SELECT COUNT(*) FROM quotes q WHERE {$w} AND q.status = 'aprobada'", $p, 0);
        $conv     = $closed > 0 ? round($approved / $closed * 100, 1) : 0.0;

        $byStatus = [];
        foreach (DB::all("SELECT q.status, COUNT(*) n, COALESCE(SUM(q.total),0) monto FROM quotes q WHERE {$w} GROUP BY q.status", $p) as $r) {
            $byStatus[$r['status']] = ['n' => (int) $r['n'], 'monto' => (float) $r['monto']];
        }

        $avgResponse = (float) DB::value(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, q.created_at, q.sent_at)) FROM quotes q WHERE {$w} AND q.sent_at IS NOT NULL AND q.sent_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)",
            $p,
            0
        );

        // Serie de los últimos 6 meses para la gráfica.
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $from = date('Y-m-01', strtotime("-{$i} month"));
            $to   = date('Y-m-t', strtotime("-{$i} month"));
            $series[] = [
                'label'    => self::monthLabel($from),
                'cotizado' => (float) DB::value("SELECT COALESCE(SUM(q.total),0) FROM quotes q WHERE {$w} AND q.created_at BETWEEN ? AND ?", array_merge($p, [$from . ' 00:00:00', $to . ' 23:59:59']), 0),
                'ganado'   => (float) DB::value("SELECT COALESCE(SUM(q.won_amount),0) FROM quotes q WHERE {$w} AND q.status='aprobada' AND q.approved_at BETWEEN ? AND ?", array_merge($p, [$from . ' 00:00:00', $to . ' 23:59:59']), 0),
            ];
        }

        $topProducts = DB::all(
            'SELECT qi.code, qi.name, SUM(qi.qty) AS unidades, COUNT(DISTINCT qi.quote_id) AS veces
             FROM quote_items qi JOIN quotes q ON q.id = qi.quote_id AND q.company_id = qi.company_id
             WHERE qi.company_id = ? AND q.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
             GROUP BY qi.code, qi.name ORDER BY veces DESC, unidades DESC LIMIT 8',
            [$cid]
        );

        $ranking = DB::all(
            'SELECT u.id, u.name,
                    COUNT(q.id) AS total,
                    SUM(q.status = "aprobada") AS ganadas,
                    COALESCE(SUM(q.won_amount),0) AS monto
             FROM users u LEFT JOIN quotes q ON q.user_id = u.id AND q.company_id = u.company_id AND q.is_current = 1
             WHERE u.company_id = ? AND u.role IN ("admin","vendedor") AND u.status = "activo"
             GROUP BY u.id, u.name ORDER BY monto DESC, ganadas DESC',
            [$cid]
        );

        $stale = DB::all(
            "SELECT q.*, u.name AS seller_name FROM quotes q LEFT JOIN users u ON u.id = q.user_id
             WHERE {$w} AND q.status IN ('nueva','elaboracion','enviada','negociacion')
             ORDER BY COALESCE(q.last_contact_at, q.created_at) ASC LIMIT 8",
            $p
        );

        $this->view('panel/dashboard', [
            'title'   => 'Tablero de control',
            'quoted'  => $quoted,
            'won'     => $won,
            'wonPrev' => $wonPrev,
            'conv'    => $conv,
            'byStatus' => $byStatus,
            'avgResponse' => $avgResponse,
            'series'  => $series,
            'topProducts' => $topProducts,
            'ranking' => $ranking,
            'stale'   => $stale,
            'lostReasons' => DB::all("SELECT lost_reason, COUNT(*) n FROM quotes WHERE company_id = ? AND status = 'perdida' AND lost_reason IS NOT NULL GROUP BY lost_reason ORDER BY n DESC", [$cid]),
        ], 'layout/panel');
    }

    public static function monthLabel(string $date): string
    {
        $m = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return $m[(int) date('n', strtotime($date))] . ' ' . date('y', strtotime($date));
    }

    public function readNotifications(array $params = []): void
    {
        $u = Auth::require();
        Notification::markAllRead((int) $u['id']);
        $this->back('/panel');
    }
}
