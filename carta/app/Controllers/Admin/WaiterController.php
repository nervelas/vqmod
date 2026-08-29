<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Money;
use MenuGold\Core\Session;
use MenuGold\Core\View;
use MenuGold\Models\Order;
use MenuGold\Models\Settings;
use MenuGold\Models\TableModel;

/** Salón: estado de las mesas, llamadas y cobro. */
class WaiterController extends BaseController
{
    protected $ability = 'waiter';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return $this->view('admin/waiter', $this->boardData());
    }

    /** Fragmento del salón para refrescar sin recargar. */
    public function data()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $html = View::render('admin/partials/waiter-board', $this->boardData());
        $pulse = Order::pulse();
        return $this->ok(array('html' => $html, 'hash' => $pulse['hash']));
    }

    private function boardData()
    {
        return array(
            'tables' => TableModel::board(),
            'calls'  => DB::all("SELECT sc.*, t.name AS table_name FROM mg_service_calls sc
                                  LEFT JOIN mg_tables t ON t.id = sc.table_id
                                  WHERE sc.status = 'open' ORDER BY sc.created_at"),
            'takeaway' => DB::all("SELECT * FROM mg_orders WHERE table_id IS NULL
                                    AND status IN ('new','cooking','ready','served') ORDER BY placed_at"),
        );
    }

    public function table(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $table = TableModel::find((int)$params['id']);
        if (!$table) { return $this->notFound('Esa mesa no existe.'); }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }
            return $this->close($table);
        }

        $orders = TableModel::openOrders((int)$table['id']);
        return $this->view('admin/table-detail', array(
            'table'  => $table,
            'orders' => $orders,
            'total'  => array_sum(array_map(function ($o) { return (float)$o['total']; }, $orders)),
        ));
    }

    /** Cobra de una vez todos los pedidos abiertos de la mesa. */
    private function close(array $table)
    {
        $method = $this->request->str('method', '');
        $permitidos = Settings::list('payment_methods');
        if (!in_array($method, $permitidos, true)) { $method = $permitidos ? $permitidos[0] : 'efectivo'; }
        $tipPercent = max(0, min(50, $this->request->float('tip_percent', 0)));
        $descPct = max(0, min(100, $this->request->float('discount_percent', 0)));

        $ids = DB::column("SELECT id FROM mg_orders WHERE table_id = :t AND status IN ('new','cooking','ready','served')",
            array('t' => (int)$table['id']));
        $grand = 0.0;
        foreach ($ids as $id) {
            $o = Order::find((int)$id);
            if (!$o) { continue; }
            $desc = $descPct > 0 ? Money::round((float)$o['subtotal'] * ($descPct / 100)) : (float)$o['discount'];
            $base = (float)$o['subtotal'] - $desc;
            $tip = Money::round($base * ($tipPercent / 100));
            $total = Money::round($base + (float)$o['tax'] + (float)$o['delivery_fee'] + $tip);
            DB::update('mg_orders', array(
                'discount' => $desc, 'tip' => $tip, 'total' => $total, 'payment_method' => $method,
                'payment_status' => 'paid', 'waiter_id' => Auth::id(),
            ), 'id = :id', array('id' => (int)$id));
            Order::setStatus((int)$id, 'closed');
            $grand += $total;
        }
        DB::update('mg_tables', array('status' => 'free'), 'id = :id', array('id' => (int)$table['id']));
        DB::run("UPDATE mg_service_calls SET status='done', resolved_at=NOW() WHERE table_id = :t AND status='open'",
            array('t' => (int)$table['id']));
        Audit::log('table_closed', 'table', (int)$table['id'], array('total' => $grand, 'orders' => count($ids)));

        Session::flash('success', $table['name'] . ' cerrada. Total cobrado: ' . Money::format($grand));
        return $this->redirect('/panel/mesero');
    }

    public function resolveCall(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        DB::run("UPDATE mg_service_calls SET status='done', resolved_at=NOW() WHERE id = :id", array('id' => (int)$params['id']));
        if ($this->request->wantsJson()) { return $this->ok(); }
        return $this->back('/panel/mesero');
    }
}
