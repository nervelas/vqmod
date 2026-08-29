<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Money;
use MenuGold\Core\Session;
use MenuGold\Core\View;
use MenuGold\Models\Order;
use MenuGold\Models\TableModel;

class OrdersController extends BaseController
{
    protected $ability = 'orders';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $filters = array(
            'status' => $this->request->str('estado', ''),
            'mode'   => $this->request->str('modo', ''),
            'from'   => $this->request->str('desde', ''),
            'to'     => $this->request->str('hasta', ''),
            'q'      => $this->request->str('q', ''),
        );
        return $this->view('admin/orders/index', array(
            'orders'  => Order::recent($this->rid(), 120, $filters),
            'filters' => $filters,
        ));
    }

    public function show(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $order = Order::find($this->rid(), (int)$params['id']);
        if (!$order) { return $this->notFound('Ese pedido no existe.'); }

        return $this->view('admin/orders/show', array(
            'order'  => $order,
            'events' => DB::all('SELECT e.*, u.name AS user_name FROM order_events e
                                  LEFT JOIN users u ON u.id = e.user_id
                                  WHERE e.order_id = :o ORDER BY e.id', array('o' => (int)$order['id'])),
        ));
    }

    public function setStatus(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $status = $this->request->str('status', '');
        $ok = Order::setStatus($this->rid(), (int)$params['id'], $status, Auth::id(), $this->request->str('note', ''));
        if ($ok) {
            Audit::log('order_status', 'order', (int)$params['id'], array('to' => $status));
        }
        if ($this->request->wantsJson()) {
            return $ok ? $this->ok(array('status' => $status)) : $this->fail('No se pudo cambiar el estado.');
        }
        Session::flash($ok ? 'success' : 'error', $ok ? 'Pedido actualizado.' : 'No se pudo cambiar el estado.');
        return $this->back('/panel/pedidos');
    }

    /** Cobro: método de pago, propina y cierre. */
    public function charge(array $params)
    {
        $stop = $this->guard('waiter');
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $order = Order::find($this->rid(), (int)$params['id']);
        if (!$order) { return $this->fail('Pedido no encontrado.', 404); }
        if ($order['status'] === 'paid') { return $this->fail('Ese pedido ya está cobrado.'); }

        $method = $this->request->str('method', 'cash');
        if (!in_array($method, array('cash', 'card', 'transfer', 'link'), true)) { $method = 'cash'; }

        // La propina se recalcula en el servidor sobre el consumo neto.
        $tipPercent = max(0, min(50, $this->request->float('tip_percent', 0)));
        $tipAmount  = max(0, $this->request->float('tip_amount', 0));
        $base = (float)$order['subtotal'] - (float)$order['discount'];
        $tip = $tipPercent > 0 ? Money::round($base * ($tipPercent / 100)) : Money::round($tipAmount);
        $total = Money::round($base + (float)$order['tax'] + (float)$order['delivery_fee'] + $tip);

        DB::update('orders', array(
            'tip'            => $tip,
            'total'          => $total,
            'payment_method' => $method,
            'payment_status' => 'paid',
            'waiter_id'      => Auth::id(),
        ), 'id = :id AND restaurant_id = :r', array('id' => (int)$order['id'], 'r' => $this->rid()));

        Order::setStatus($this->rid(), (int)$order['id'], 'paid', Auth::id(), 'Cobrado en ' . $method);
        Audit::log('order_charged', 'order', (int)$order['id'], array('method' => $method, 'total' => $total));

        if ($this->request->wantsJson()) {
            return $this->ok(array('total' => $total));
        }
        Session::flash('success', 'Pedido ' . $order['code'] . ' cobrado: ' . Money::format($total));
        return $this->redirect('/panel/mesero');
    }

    /** Descuento manual sobre un pedido abierto. */
    public function discount(array $params)
    {
        $stop = $this->guard('waiter');
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $order = Order::find($this->rid(), (int)$params['id']);
        if (!$order) { return $this->fail('Pedido no encontrado.', 404); }
        if ($order['status'] === 'paid') { return $this->fail('El pedido ya está cobrado.'); }

        $percent = max(0, min(100, $this->request->float('percent', 0)));
        $amount  = max(0, $this->request->float('amount', 0));
        $discount = $percent > 0 ? Money::round((float)$order['subtotal'] * ($percent / 100)) : Money::round($amount);
        if ($discount > (float)$order['subtotal']) { $discount = (float)$order['subtotal']; }

        $total = Money::round((float)$order['subtotal'] - $discount + (float)$order['tax'] + (float)$order['delivery_fee'] + (float)$order['tip']);
        DB::update('orders', array('discount' => $discount, 'total' => $total),
            'id = :id AND restaurant_id = :r', array('id' => (int)$order['id'], 'r' => $this->rid()));
        Audit::log('order_discount', 'order', (int)$order['id'], array('discount' => $discount, 'reason' => $this->request->str('reason', '')));

        Session::flash('success', 'Descuento aplicado: ' . Money::format($discount));
        return $this->back('/panel/pedidos/' . (int)$order['id']);
    }

    public function cancel(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $reason = $this->request->str('reason', '');
        if ($reason === '') {
            Session::flash('error', 'Escribe el motivo de la anulación.');
            return $this->back('/panel/pedidos/' . (int)$params['id']);
        }
        Order::setStatus($this->rid(), (int)$params['id'], 'cancelled', Auth::id(), $reason);
        Audit::log('order_cancelled', 'order', (int)$params['id'], array('reason' => $reason));
        Session::flash('success', 'Pedido anulado.');
        return $this->redirect('/panel/pedidos');
    }

    /** Ticket para impresora térmica (80 o 58 mm) o precuenta. */
    public function ticket(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $order = Order::find($this->rid(), (int)$params['id']);
        if (!$order) { return $this->notFound('Ese pedido no existe.'); }

        $width = $this->request->str('ancho', '80') === '58' ? 58 : 80;
        $isPre = $this->request->bool('precuenta', false);

        if ($this->request->str('formato', 'html') === 'pdf') {
            return $this->ticketPdf($order, $width, $isPre);
        }
        return $this->view('admin/orders/ticket', array(
            'order'   => $order,
            'width'   => $width,
            'isPre'   => $isPre,
            'settings'=> \MenuGold\Models\Restaurant::settings($this->rid()),
        ));
    }

    private function ticketPdf(array $order, $width, $isPre)
    {
        $lines = 26 + count($order['items']) * 3;
        $pdf = new \MenuGold\Core\Pdf($width . 'x' . max(120, $lines * 4.6));
        $pdf->setTitle('Ticket ' . $order['code']);
        $m = $width === 58 ? 4 : 6;
        $w = $width - $m * 2;
        $y = 8;

        $pdf->setFont('Helvetica', 'B', 11);
        $pdf->text($m, $y, mb_strtoupper($this->restaurant['name']), 'C', $w); $y += 5;
        $pdf->setFont('Helvetica', '', 7);
        if ($this->restaurant['address'] !== '') {
            foreach ($pdf->wrap($this->restaurant['address'], $w) as $l) { $pdf->text($m, $y, $l, 'C', $w); $y += 3.2; }
        }
        if ($this->restaurant['phone'] !== '') { $pdf->text($m, $y, 'Tel. ' . $this->restaurant['phone'], 'C', $w); $y += 3.2; }
        $y += 2;
        $pdf->setDrawColor('#000000'); $pdf->setLineWidth(0.2);
        $pdf->line($m, $y, $m + $w, $y); $y += 4;

        $pdf->setFont('Helvetica', 'B', 9);
        $pdf->text($m, $y, ($isPre ? 'PRECUENTA ' : 'PEDIDO ') . $order['code'], 'C', $w); $y += 4.5;
        $pdf->setFont('Helvetica', '', 7);
        $pdf->text($m, $y, mg_date($order['placed_at']) . ($order['table'] ? '  ·  ' . $order['table']['name'] : ''), 'C', $w); $y += 3.4;
        $pdf->text($m, $y, Order::modeLabel($order['mode']), 'C', $w); $y += 4;
        $pdf->line($m, $y, $m + $w, $y); $y += 4;

        foreach ($order['items'] as $it) {
            $pdf->setFont('Helvetica', 'B', 8);
            $label = $it['qty'] . ' x ' . $it['name_snapshot'];
            foreach ($pdf->wrap($label, $w - 16) as $l) { $pdf->text($m, $y, $l); $y += 3.4; }
            $pdf->text($m, $y - 3.4, Money::format($it['line_total'], $this->restaurant['currency']), 'R', $w);
            $pdf->setFont('Helvetica', '', 7);
            foreach ((array)$it['modifiers'] as $mod) {
                $pdf->text($m + 2, $y, '- ' . $mod['name']); $y += 3;
            }
            if ($it['notes'] !== '') {
                foreach ($pdf->wrap('Nota: ' . $it['notes'], $w - 4) as $l) { $pdf->text($m + 2, $y, $l); $y += 3; }
            }
            $y += 1;
        }

        $y += 1;
        $pdf->line($m, $y, $m + $w, $y); $y += 4;
        $pdf->setFont('Helvetica', '', 8);
        $rows = array(array('Subtotal', $order['subtotal']));
        if ((float)$order['discount'] > 0)     { $rows[] = array('Descuento', -(float)$order['discount']); }
        if ((float)$order['delivery_fee'] > 0) { $rows[] = array('Envío', $order['delivery_fee']); }
        if ((float)$order['tax'] > 0)          { $rows[] = array('Impuesto', $order['tax']); }
        if ((float)$order['tip'] > 0)          { $rows[] = array('Propina', $order['tip']); }
        foreach ($rows as $r) {
            $pdf->text($m, $y, $r[0]);
            $pdf->text($m, $y, Money::format($r[1], $this->restaurant['currency']), 'R', $w);
            $y += 3.6;
        }
        $y += 1;
        $pdf->setFont('Helvetica', 'B', 11);
        $pdf->text($m, $y, 'TOTAL');
        $pdf->text($m, $y, Money::format($order['total'], $this->restaurant['currency']), 'R', $w);
        $y += 7;

        $pdf->setFont('Helvetica', '', 7);
        if ($isPre) { $pdf->text($m, $y, 'Documento no fiscal', 'C', $w); $y += 3.4; }
        $pdf->text($m, $y, '¡Gracias por su visita!', 'C', $w); $y += 5;

        if ($this->restaurant['review_url'] !== '') {
            $matrix = \MenuGold\Core\Qr::matrix($this->restaurant['review_url']);
            $size = min(30, $w - 10);
            $pdf->qr($matrix, $m + ($w - $size) / 2, $y, $size, '#000000', 2);
            $y += $size + 3;
            $pdf->text($m, $y, 'Escanea y déjanos tu reseña', 'C', $w);
        }

        return $pdf->response('ticket-' . $order['code'] . '.pdf', true);
    }

    /* ---------------- Pantalla de cocina ---------------- */

    public function kitchen()
    {
        $stop = $this->guard('kds');
        if ($stop) { return $stop; }
        $board = Order::kitchenBoard($this->rid());
        return $this->view('admin/kitchen', array(
            'board' => $board,
            'pulse' => Order::pulse($this->rid()),
        ));
    }

    /** Fragmento HTML de las tres columnas, para refrescar sin recargar. */
    public function kitchenData()
    {
        $stop = $this->guard('kds');
        if ($stop) { return $stop; }
        $board = Order::kitchenBoard($this->rid());
        $html = View::render('admin/partials/kds-columns', array('board' => $board, 'restaurant' => $this->restaurant));
        $pulse = Order::pulse($this->rid());
        return $this->ok(array('html' => $html, 'hash' => $pulse['hash']));
    }

    /* ---------------- Salón del mesero ---------------- */

    public function waiter()
    {
        $stop = $this->guard('waiter');
        if ($stop) { return $stop; }
        return $this->view('admin/waiter', array(
            'tables' => TableModel::board($this->rid()),
            'calls'  => DB::all("SELECT sc.*, t.name AS table_name FROM service_calls sc
                                  LEFT JOIN tables t ON t.id = sc.table_id
                                  WHERE sc.restaurant_id = :r AND sc.status = 'open' ORDER BY sc.created_at",
                                array('r' => $this->rid())),
            'takeaway' => DB::all("SELECT * FROM orders WHERE restaurant_id = :r AND table_id IS NULL
                                    AND status IN ('new','preparing','ready','delivered') ORDER BY placed_at",
                                array('r' => $this->rid())),
        ));
    }

    public function tableDetail(array $params)
    {
        $stop = $this->guard('waiter');
        if ($stop) { return $stop; }
        $table = TableModel::find($this->rid(), (int)$params['id']);
        if (!$table) { return $this->notFound('Esa mesa no existe.'); }

        $rows = DB::all(
            "SELECT * FROM orders WHERE restaurant_id = :r AND table_id = :t
             AND status IN ('new','preparing','ready','delivered') ORDER BY placed_at",
            array('r' => $this->rid(), 't' => (int)$table['id'])
        );
        $orders = array();
        foreach ($rows as $o) { $orders[] = Order::hydrate($o); }

        return $this->view('admin/table-detail', array(
            'table'  => $table,
            'orders' => $orders,
            'total'  => array_sum(array_map(function ($o) { return (float)$o['total']; }, $orders)),
        ));
    }

    /** Cobra de una vez todos los pedidos abiertos de la mesa. */
    public function closeTable(array $params)
    {
        $stop = $this->guard('waiter');
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $table = TableModel::find($this->rid(), (int)$params['id']);
        if (!$table) { return $this->notFound('Esa mesa no existe.'); }

        $method = $this->request->str('method', 'cash');
        if (!in_array($method, array('cash', 'card', 'transfer', 'link'), true)) { $method = 'cash'; }
        $tipPercent = max(0, min(50, $this->request->float('tip_percent', 0)));

        $ids = DB::column(
            "SELECT id FROM orders WHERE restaurant_id = :r AND table_id = :t AND status IN ('new','preparing','ready','delivered')",
            array('r' => $this->rid(), 't' => (int)$table['id'])
        );
        $grand = 0.0;
        foreach ($ids as $id) {
            $o = Order::find($this->rid(), (int)$id);
            if (!$o) { continue; }
            $base = (float)$o['subtotal'] - (float)$o['discount'];
            $tip = Money::round($base * ($tipPercent / 100));
            $total = Money::round($base + (float)$o['tax'] + (float)$o['delivery_fee'] + $tip);
            DB::update('orders', array(
                'tip' => $tip, 'total' => $total, 'payment_method' => $method,
                'payment_status' => 'paid', 'waiter_id' => Auth::id(),
            ), 'id = :id AND restaurant_id = :r', array('id' => (int)$id, 'r' => $this->rid()));
            Order::setStatus($this->rid(), (int)$id, 'paid', Auth::id(), 'Cierre de mesa');
            $grand += $total;
        }
        DB::update('tables', array('status' => 'free'), 'id = :id', array('id' => (int)$table['id']));
        DB::run("UPDATE service_calls SET status='done', resolved_at=NOW() WHERE table_id = :t AND status='open'",
            array('t' => (int)$table['id']));
        Audit::log('table_closed', 'table', (int)$table['id'], array('total' => $grand, 'orders' => count($ids)));

        Session::flash('success', $table['name'] . ' cerrada. Total cobrado: ' . Money::format($grand));
        return $this->redirect('/panel/mesero');
    }

    public function resolveCall(array $params)
    {
        $stop = $this->guard('waiter');
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        DB::run("UPDATE service_calls SET status='done', resolved_at=NOW() WHERE id = :id AND restaurant_id = :r",
            array('id' => (int)$params['id'], 'r' => $this->rid()));
        if ($this->request->wantsJson()) { return $this->ok(); }
        return $this->back('/panel/mesero');
    }
}
