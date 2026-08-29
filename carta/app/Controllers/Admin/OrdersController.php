<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Money;
use MenuGold\Core\Qr;
use MenuGold\Core\Session;
use MenuGold\Models\Order;
use MenuGold\Models\Settings;

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
            'orders'  => Order::recent(120, $filters),
            'filters' => $filters,
        ));
    }

    public function show(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $order = Order::find((int)$params['id']);
        if (!$order) { return $this->notFound('Ese pedido no existe.'); }
        return $this->view('admin/orders/show', array('order' => $order));
    }

    public function setStatus(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $status = $this->request->str('status', '');
        $note = $this->request->str('note', '');
        if ($status === 'cancelled' && $note === '') {
            return $this->request->wantsJson()
                ? $this->fail('Escribe el motivo de la anulación.')
                : $this->back('/panel/pedidos');
        }
        $ok = Order::setStatus((int)$params['id'], $status, $note);
        if ($ok) { Audit::log('order_status', 'order', (int)$params['id'], array('to' => $status)); }

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

        $order = Order::find((int)$params['id']);
        if (!$order) { return $this->fail('Pedido no encontrado.', 404); }
        if ($order['status'] === 'closed') { return $this->fail('Ese pedido ya está cobrado.'); }

        $method = $this->request->str('method', 'efectivo');
        $permitidos = Settings::list('payment_methods');
        if (!in_array($method, $permitidos, true)) { $method = $permitidos ? $permitidos[0] : 'efectivo'; }

        // Descuento manual del mesero, si lo aplicó al cobrar.
        $descPct = max(0, min(100, $this->request->float('discount_percent', 0)));
        $descuento = $descPct > 0
            ? Money::round((float)$order['subtotal'] * ($descPct / 100))
            : (float)$order['discount'];
        if ($descuento > (float)$order['subtotal']) { $descuento = (float)$order['subtotal']; }

        // La propina se recalcula en el servidor, sobre el consumo neto.
        $tipPercent = max(0, min(50, $this->request->float('tip_percent', 0)));
        $tipAmount  = max(0, $this->request->float('tip_amount', 0));
        $base = (float)$order['subtotal'] - $descuento;
        $tip = $tipPercent > 0 ? Money::round($base * ($tipPercent / 100)) : Money::round($tipAmount);
        $total = Money::round($base + (float)$order['tax'] + (float)$order['delivery_fee'] + $tip);

        DB::update('mg_orders', array(
            'discount'       => $descuento,
            'tip'            => $tip,
            'total'          => $total,
            'payment_method' => $method,
            'payment_status' => 'paid',
            'waiter_id'      => Auth::id(),
        ), 'id = :id', array('id' => (int)$order['id']));

        Order::setStatus((int)$order['id'], 'closed');
        Audit::log('order_charged', 'order', (int)$order['id'], array('method' => $method, 'total' => $total));

        if ($this->request->wantsJson()) { return $this->ok(array('total' => $total)); }
        Session::flash('success', 'Pedido ' . $order['code'] . ' cobrado: ' . Money::format($total));
        return $this->redirect('/panel/mesero');
    }

    public function ticket(array $params)
    {
        return $this->printable($params, false);
    }

    public function preBill(array $params)
    {
        return $this->printable($params, true);
    }

    /** Ticket o precuenta, en HTML listo para impresora térmica o en PDF. */
    private function printable(array $params, $isPre)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $order = Order::find((int)$params['id']);
        if (!$order) { return $this->notFound('Ese pedido no existe.'); }

        $width = $this->request->str('ancho', Settings::get('printer_width', '80')) === '58' ? 58 : 80;
        if ($this->request->str('formato', 'html') === 'pdf') {
            return $this->ticketPdf($order, $width, $isPre);
        }
        return $this->view('admin/orders/ticket', array(
            'order' => $order, 'width' => $width, 'isPre' => $isPre,
        ));
    }

    private function ticketPdf(array $order, $width, $isPre)
    {
        $cur = Settings::get('currency', 'Q');
        $lines = 26 + count($order['items']) * 3;
        $pdf = new \MenuGold\Core\Pdf($width . 'x' . max(120, $lines * 4.6));
        $pdf->setTitle('Ticket ' . $order['code']);
        $m = $width === 58 ? 4 : 6;
        $w = $width - $m * 2;
        $y = 8;

        $pdf->setFont('Helvetica', 'B', 11);
        $pdf->text($m, $y, mb_strtoupper(Settings::get('name')), 'C', $w); $y += 5;
        $pdf->setFont('Helvetica', '', 7);
        if (Settings::get('address') !== '') {
            foreach ($pdf->wrap(Settings::get('address'), $w) as $l) { $pdf->text($m, $y, $l, 'C', $w); $y += 3.2; }
        }
        if (Settings::get('phone') !== '') { $pdf->text($m, $y, 'Tel. ' . Settings::get('phone'), 'C', $w); $y += 3.2; }
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
            foreach ($pdf->wrap($it['qty'] . ' x ' . $it['name'], $w - 16) as $l) { $pdf->text($m, $y, $l); $y += 3.4; }
            $pdf->text($m, $y - 3.4, Money::format($it['line_total'], $cur), 'R', $w);
            $pdf->setFont('Helvetica', '', 7);
            foreach ((array)$it['modifiers'] as $mod) { $pdf->text($m + 2, $y, '- ' . $mod['name']); $y += 3; }
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
            $pdf->text($m, $y, Money::format($r[1], $cur), 'R', $w);
            $y += 3.6;
        }
        $y += 1;
        $pdf->setFont('Helvetica', 'B', 11);
        $pdf->text($m, $y, 'TOTAL');
        $pdf->text($m, $y, Money::format($order['total'], $cur), 'R', $w);
        $y += 7;

        $pdf->setFont('Helvetica', '', 7);
        if ($isPre) { $pdf->text($m, $y, 'Documento no fiscal', 'C', $w); $y += 3.4; }
        $pdf->text($m, $y, '¡Gracias por su visita!', 'C', $w); $y += 5;

        if (Settings::get('review_url') !== '') {
            $matrix = Qr::matrix(Settings::get('review_url'));
            $size = min(30, $w - 10);
            $pdf->qr($matrix, $m + ($w - $size) / 2, $y, $size, '#000000', 2);
            $y += $size + 3;
            $pdf->text($m, $y, 'Escanea y déjanos tu reseña', 'C', $w);
        }

        return $pdf->response('ticket-' . $order['code'] . '.pdf', true);
    }
}
