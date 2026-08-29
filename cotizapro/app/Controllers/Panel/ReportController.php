<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Xlsx;
use App\Models\Quote;

final class ReportController extends Controller
{
    /** Métricas del periodo elegido; se reutilizan en pantalla, Excel y PDF. */
    private function build(string $from, string $to, ?int $userId): array
    {
        $w = 'q.is_current = 1 AND q.created_at BETWEEN ? AND ?';
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        if ($userId) {
            $w .= ' AND q.user_id = ?';
            $p[] = $userId;
        }
        $byStatus = [];
        foreach (DB::all("SELECT q.status, COUNT(*) n, COALESCE(SUM(q.total),0) monto FROM quotes q WHERE {$w} GROUP BY q.status", $p) as $r) {
            $byStatus[$r['status']] = ['n' => (int) $r['n'], 'monto' => (float) $r['monto']];
        }
        $totalN   = array_sum(array_column($byStatus, 'n'));
        $quoted   = array_sum(array_column($byStatus, 'monto'));
        $won      = $byStatus['aprobada']['monto'] ?? 0.0;
        $closed   = ($byStatus['aprobada']['n'] ?? 0) + ($byStatus['perdida']['n'] ?? 0);
        $conv     = $closed > 0 ? round(($byStatus['aprobada']['n'] ?? 0) / $closed * 100, 1) : 0.0;

        return [
            'byStatus' => $byStatus,
            'totalN'   => $totalN,
            'quoted'   => $quoted,
            'won'      => $won,
            'conv'     => $conv,
            'avgTicket' => $totalN > 0 ? $quoted / $totalN : 0.0,
            'avgResponse' => (float) DB::value("SELECT AVG(TIMESTAMPDIFF(HOUR, q.created_at, q.sent_at)) FROM quotes q WHERE {$w} AND q.sent_at IS NOT NULL", $p, 0),
            'sellers' => DB::all(
                "SELECT u.name, COUNT(q.id) n, SUM(q.status='aprobada') ganadas, COALESCE(SUM(q.total),0) cotizado, COALESCE(SUM(q.won_amount),0) ganado
                 FROM quotes q JOIN users u ON u.id = q.user_id WHERE {$w} GROUP BY u.id, u.name ORDER BY ganado DESC",
                $p
            ),
            'products' => DB::all(
                "SELECT qi.code, qi.name, COUNT(DISTINCT qi.quote_id) veces, SUM(qi.qty) unidades, COALESCE(SUM(qi.line_total),0) monto
                 FROM quote_items qi JOIN quotes q ON q.id = qi.quote_id
                 WHERE {$w} GROUP BY qi.code, qi.name ORDER BY veces DESC, monto DESC LIMIT 25",
                $p
            ),
            'lost' => DB::all(
                "SELECT COALESCE(q.lost_reason,'sin especificar') motivo, COUNT(*) n, COALESCE(SUM(q.total),0) monto
                 FROM quotes q WHERE {$w} AND q.status = 'perdida' GROUP BY motivo ORDER BY n DESC",
                $p
            ),
            'customers' => DB::all(
                "SELECT COALESCE(cu.name, q.contact_company, q.contact_name) cliente, COUNT(*) n,
                        COALESCE(SUM(q.total),0) cotizado, COALESCE(SUM(q.won_amount),0) ganado
                 FROM quotes q LEFT JOIN customers cu ON cu.id = q.customer_id
                 WHERE {$w} GROUP BY cliente ORDER BY cotizado DESC LIMIT 20",
                $p
            ),
            'monthly' => DB::all(
                "SELECT DATE_FORMAT(q.created_at,'%Y-%m') mes, COUNT(*) n, COALESCE(SUM(q.total),0) cotizado,
                        COALESCE(SUM(CASE WHEN q.status='aprobada' THEN q.won_amount ELSE 0 END),0) ganado
                 FROM quotes q WHERE {$w} GROUP BY mes ORDER BY mes",
                $p
            ),
        ];
    }

    private function range(): array
    {
        $from = Request::str('desde') ?: date('Y-m-01', strtotime('-5 month'));
        $to   = Request::str('hasta') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-01', strtotime('-5 month'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }
        return [$from, $to];
    }

    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel();
        [$from, $to] = $this->range();
        $userId = Auth::ownerFilter() ?: Request::int('vendedor');
        $this->view('panel/reports', [
            'title'  => 'Reportes',
            'from'   => $from,
            'to'     => $to,
            'userId' => $userId,
            'r'      => $this->build($from, $to, $userId),
            'sellersList' => DB::all('SELECT id, name FROM users WHERE role IN ("admin","vendedor") ORDER BY name'),
            'statuses' => Quote::STATUSES,
        ], 'layout/panel');
    }

    public function excel(array $params = []): void
    {
        [$u, $c] = $this->panel();
        [$from, $to] = $this->range();
        $userId = Auth::ownerFilter() ?: Request::int('vendedor');
        $r = $this->build($from, $to, $userId);

        $resumen = [['Indicador', 'Valor']];
        $resumen[] = ['Periodo', $from . ' a ' . $to];
        $resumen[] = ['Cotizaciones emitidas', $r['totalN']];
        $resumen[] = ['Monto cotizado', round($r['quoted'], 2)];
        $resumen[] = ['Monto ganado', round($r['won'], 2)];
        $resumen[] = ['Tasa de conversión %', $r['conv']];
        $resumen[] = ['Ticket promedio', round($r['avgTicket'], 2)];
        $resumen[] = ['Tiempo promedio de respuesta (h)', round((float) $r['avgResponse'], 1)];
        foreach (Quote::STATUSES as $k => $meta) {
            $resumen[] = ['Estado: ' . $meta['label'], (int) ($r['byStatus'][$k]['n'] ?? 0)];
        }

        $vend = [['Vendedor', 'Cotizaciones', 'Ganadas', 'Monto cotizado', 'Monto ganado']];
        foreach ($r['sellers'] as $s) {
            $vend[] = [$s['name'], (int) $s['n'], (int) $s['ganadas'], round((float) $s['cotizado'], 2), round((float) $s['ganado'], 2)];
        }
        $prod = [['Código', 'Producto', 'Veces cotizado', 'Unidades', 'Monto']];
        foreach ($r['products'] as $pr) {
            $prod[] = [$pr['code'], $pr['name'], (int) $pr['veces'], (float) $pr['unidades'], round((float) $pr['monto'], 2)];
        }
        $cli = [['Cliente', 'Cotizaciones', 'Monto cotizado', 'Monto ganado']];
        foreach ($r['customers'] as $x) {
            $cli[] = [$x['cliente'], (int) $x['n'], round((float) $x['cotizado'], 2), round((float) $x['ganado'], 2)];
        }
        $per = [['Motivo de pérdida', 'Cantidad', 'Monto']];
        foreach ($r['lost'] as $x) {
            $per[] = [$x['motivo'], (int) $x['n'], round((float) $x['monto'], 2)];
        }

        (new Xlsx())
            ->addSheet('Resumen', $resumen, [34, 20])
            ->addSheet('Vendedores', $vend, [26, 14, 12, 18, 18])
            ->addSheet('Productos', $prod, [16, 44, 14, 12, 16])
            ->addSheet('Clientes', $cli, [38, 14, 18, 18])
            ->addSheet('Perdidas', $per, [26, 12, 16])
            ->download('reporte-' . $from . '-a-' . $to . '.xlsx');
    }

    public function pdf(array $params = []): void
    {
        [$u, $c] = $this->panel();
        [$from, $to] = $this->range();
        $userId = Auth::ownerFilter() ?: Request::int('vendedor');
        $r = $this->build($from, $to, $userId);
        $file = \App\Core\ReportPdf::render($c, $from, $to, $r);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="reporte-' . $from . '-a-' . $to . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
