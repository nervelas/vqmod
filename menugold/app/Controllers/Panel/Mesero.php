<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Request;
use MenuGold\Core\Sse;
use MenuGold\Models\Category;
use MenuGold\Models\Coupon;
use MenuGold\Models\Customer;
use MenuGold\Models\Order;
use MenuGold\Models\Product;
use MenuGold\Models\RestaurantTable;
use MenuGold\Models\WaiterCall;
use MenuGold\Vendor\Pdf\Pdf;

/**
 * Pantalla de mesero y caja: tablero de mesas, cobros y cierres.
 */
class Mesero extends Base
{
    private function mesas(): RestaurantTable { return (new RestaurantTable())->forRestaurant($this->rid); }
    private function ordenes(): Order { return (new Order())->forRestaurant($this->rid); }

    public function index(): void
    {
        $this->exigir('mesero');
        $this->panel('panel/mesero', [
            'tablero'  => $this->mesas()->tablero(),
            'llamadas' => (new WaiterCall())->forRestaurant($this->rid)->pendientes(),
            'sinMesa'  => $this->ordenes()->where("table_id IS NULL AND estado IN ('nuevo','preparando','listo','entregado')", [], 'creado ASC', 40),
        ]);
    }

    public function datos(): void
    {
        $this->exigir('mesero');
        $this->ok($this->estado());
    }

    public function sse(): void
    {
        $this->exigir('mesero');
        $rid = $this->rid;
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        Sse::loop(function () use ($rid) {
            $mesas = (new RestaurantTable())->forRestaurant($rid)->tablero();
            $llamadas = (new WaiterCall())->forRestaurant($rid)->pendientes();
            return $this->formatear($mesas, $llamadas);
        }, 'mesas');
        exit;
    }

    private function estado(): array
    {
        return $this->formatear(
            $this->mesas()->tablero(),
            (new WaiterCall())->forRestaurant($this->rid)->pendientes()
        );
    }

    private function formatear(array $mesas, array $llamadas): array
    {
        $out = [];
        foreach ($mesas as $m) {
            $out[] = [
                'id'       => (int)$m['id'],
                'nombre'   => (string)$m['nombre'],
                'zona'     => (string)($m['zona'] ?? ''),
                'estado'   => (string)$m['estado'],
                'pedidos'  => (int)$m['pedidos_abiertos'],
                'cuenta'   => round((float)$m['cuenta'], 2),
                'llamadas' => (int)$m['llamadas'],
                'capacidad'=> (int)$m['capacidad'],
            ];
        }
        $ll = [];
        foreach ($llamadas as $l) {
            $ll[] = [
                'id'    => (int)$l['id'],
                'mesa'  => (string)$l['mesa_nombre'],
                'tipo'  => (string)$l['tipo'],
                'hace'  => (int)floor((time() - strtotime((string)$l['creado'])) / 60),
            ];
        }
        return ['mesas' => $out, 'llamadas' => $ll];
    }

    // =================================================================
    //  Detalle de una mesa
    // =================================================================
    public function mesa(array $p = []): void
    {
        $this->exigir('mesero');
        $id = (int)($p['id'] ?? 0);
        $mesa = $this->mesas()->findOrFail($id);
        $om = $this->ordenes();
        $pedidos = $om->deMesa($id);

        $totales = ['subtotal' => 0.0, 'descuento' => 0.0, 'propina' => 0.0, 'total' => 0.0];
        foreach ($pedidos as $o) {
            $totales['subtotal']  += (float)$o['subtotal'];
            $totales['descuento'] += (float)$o['descuento'];
            $totales['propina']   += (float)$o['propina'];
            $totales['total']     += (float)$o['total'];
        }

        $this->panel('panel/mesero-mesa', [
            'mesa'     => $mesa,
            'pedidos'  => $pedidos,
            'totales'  => $totales,
            'llamadas' => (new WaiterCall())->forRestaurant($this->rid)->where("table_id = :t AND estado='pendiente'", ['t' => $id], 'creado ASC'),
            'cats'     => (new Category())->forRestaurant($this->rid)->all('orden ASC'),
            'productos'=> (new Product())->forRestaurant($this->rid)->where('activo=1 AND agotado=0', [], 'orden ASC', 500),
            'propinas' => \MenuGold\Models\Restaurant::propinas($this->r),
            'metodos'  => array_filter(array_map('trim', explode(',', (string)$this->r['metodos_pago']))),
        ]);
    }

    public function abrir(): void
    {
        $this->exigir('mesero');
        $id = Request::int('id');
        $this->mesas()->findOrFail($id);
        $this->mesas()->abrir($id, Auth::id());
        $this->ok(['url' => url('panel/mesero/mesa/' . $id)], 'Mesa abierta');
    }

    /** Pedido tomado por el mesero desde la tablet. */
    public function pedidoManual(): void
    {
        $this->exigir('mesero');
        $tableId = Request::int('table_id');
        $mesa = $tableId > 0 ? $this->mesas()->find($tableId) : null;
        $modo = Request::enum('modo', ['mesa', 'llevar', 'delivery'], $mesa ? 'mesa' : 'llevar');

        $om = $this->ordenes();
        $calc = $om->calcularLineas(Request::arr('lineas'), $this->rid);
        if (!$calc['lineas']) {
            $this->fail($calc['errores'] ? implode(' ', $calc['errores']) : 'Agrega al menos un platillo.');
        }
        $totales = $om->calcularTotales($this->r, $calc['subtotal'], [
            'propina_pct' => Request::float('propina_pct'),
        ]);

        $orderId = $om->crear([
            'restaurant_id'    => $this->rid,
            'table_id'         => $mesa ? (int)$mesa['id'] : null,
            'mesa_nombre'      => $mesa ? (string)$mesa['nombre'] : '',
            'modo'             => $modo,
            'estado'           => 'nuevo',
            'cliente_nombre'   => Request::str('cliente_nombre', '', 80),
            'cliente_telefono' => Request::str('cliente_telefono', '', 20),
            'subtotal'         => $totales['subtotal'],
            'impuesto'         => $totales['impuesto'],
            'propina'          => $totales['propina'],
            'total'            => $totales['total'],
            'notas'            => Request::str('notas', '', 300),
            'user_id'          => Auth::id(),
            'creado_por'       => 'mesero',
            'ip'               => client_ip(),
        ], $calc['lineas']);

        if ($mesa) $this->mesas()->abrir((int)$mesa['id'], Auth::id());
        Audit::log('pedido.manual', 'orders', $orderId, null, ['total' => $totales['total']]);
        $this->ok(['id' => $orderId], 'Pedido enviado a la cocina');
    }

    public function descuento(): void
    {
        $this->exigir('mesero');
        $id = Request::int('order_id');
        $om = $this->ordenes();
        $pedido = $om->findOrFail($id);
        if (in_array($pedido['estado'], ['pagado', 'anulado'], true)) {
            $this->fail('Ese pedido ya está cerrado.');
        }

        $tipo = Request::enum('tipo', ['porcentaje', 'monto', 'cupon'], 'porcentaje');
        $subtotal = (float)$pedido['subtotal'];
        $descuento = 0.0;
        $codigo = '';

        if ($tipo === 'cupon') {
            [$c, $err] = (new Coupon())->forRestaurant($this->rid)->validar(Request::str('codigo', '', 40), $subtotal);
            if (!$c) $this->fail($err);
            $descuento = Coupon::calcular($c, $subtotal, (float)$pedido['costo_envio']);
            $codigo = (string)$c['codigo'];
            (new Coupon())->forRestaurant($this->rid)->registrarUso((int)$c['id']);
        } elseif ($tipo === 'porcentaje') {
            $pct = max(0, min(100, Request::float('valor')));
            $descuento = round($subtotal * $pct / 100, 2);
        } else {
            $descuento = round(max(0, min($subtotal, Request::float('valor'))), 2);
        }

        $totales = $om->calcularTotales($this->r, $subtotal, [
            'envio'         => (float)$pedido['costo_envio'],
            'descuento'     => $descuento,
            'propina_monto' => (float)$pedido['propina'],
        ]);
        $om->updateById($id, [
            'descuento'    => $totales['descuento'],
            'cupon_codigo' => $codigo,
            'impuesto'     => $totales['impuesto'],
            'total'        => $totales['total'],
        ]);
        Audit::log('descuento', 'orders', $id,
            ['total' => (float)$pedido['total']],
            ['descuento' => $totales['descuento'], 'total' => $totales['total'], 'cupon' => $codigo]);

        $this->ok(['total' => $totales['total'], 'descuento' => $totales['descuento']], 'Descuento aplicado');
    }

    public function cobrar(): void
    {
        $this->exigir('mesero');
        $ids = array_map('intval', Request::arr('order_ids'));
        if (!$ids) $ids = [Request::int('order_id')];
        $ids = array_values(array_filter($ids));
        if (!$ids) $this->fail('No hay pedidos para cobrar.');

        $om = $this->ordenes();
        $metodo = Request::str('metodo_pago', 'efectivo', 30);
        $propinaPct = max(0, min(100, Request::float('propina_pct')));
        $pagadoCon = Request::float('pagado_con');
        $totalCobrado = 0.0;
        $cambio = 0.0;

        foreach ($ids as $id) {
            $pedido = $om->findOrFail($id);
            if ($pedido['estado'] === 'anulado') continue;
            if ($pedido['estado'] === 'pagado') { $totalCobrado += (float)$pedido['total']; continue; }

            $totales = $om->calcularTotales($this->r, (float)$pedido['subtotal'], [
                'envio'       => (float)$pedido['costo_envio'],
                'descuento'   => (float)$pedido['descuento'],
                'propina_pct' => $propinaPct > 0 ? $propinaPct : 0,
                'propina_monto' => $propinaPct > 0 ? null : (float)$pedido['propina'],
            ]);
            if ($propinaPct <= 0) $totales['propina'] = (float)$pedido['propina'];

            $om->updateById($id, [
                'estado'      => 'pagado',
                'metodo_pago' => $metodo,
                'propina'     => $totales['propina'],
                'impuesto'    => $totales['impuesto'],
                'total'       => $totales['total'],
                'pagado_con'  => $pagadoCon > 0 ? $pagadoCon : null,
                'user_id'     => Auth::id(),
                'pagado_en'   => date('Y-m-d H:i:s'),
                'entregado_en'=> $pedido['entregado_en'] ?: date('Y-m-d H:i:s'),
                'actualizado' => date('Y-m-d H:i:s'),
            ]);
            DB::exec("UPDATE order_items SET estado='entregado' WHERE order_id=:o AND estado<>'anulado'", ['o' => $id]);
            $om->evento($id, 'pagado', 'Cobrado por ' . Auth::nombre());
            $totalCobrado += $totales['total'];

            if (!empty($pedido['customer_id'])) {
                (new Customer())->forRestaurant($this->rid)
                    ->acumular((int)$pedido['customer_id'], $totales['total'], (int)floor($totales['total'] / 10));
            }
            Audit::log('pedido.cobrar', 'orders', $id, null,
                ['total' => $totales['total'], 'metodo' => $metodo]);
        }

        if ($pagadoCon > 0) $cambio = round(max(0, $pagadoCon - $totalCobrado), 2);

        $tableId = Request::int('table_id');
        if ($tableId > 0 && Request::bool('cerrar_mesa', true)) {
            $abiertos = DB::int(
                "SELECT COUNT(*) FROM orders WHERE table_id=:t AND estado IN ('nuevo','preparando','listo','entregado')",
                ['t' => $tableId]
            );
            if ($abiertos === 0) {
                $this->mesas()->cerrar($tableId);
                DB::exec("UPDATE waiter_calls SET estado='atendida', atendida_en=NOW() WHERE table_id=:t AND estado='pendiente'", ['t' => $tableId]);
                Audit::log('mesa.cerrar', 'tables', $tableId);
            }
        }

        $this->ok([
            'total'  => round($totalCobrado, 2),
            'cambio' => $cambio,
            'ticket' => count($ids) === 1 ? url('panel/mesero/ticket/' . $ids[0]) : '',
        ], 'Cobro registrado: ' . money($totalCobrado, (string)$this->r['simbolo']));
    }

    public function cerrarMesa(): void
    {
        $this->exigir('mesero');
        $id = Request::int('id');
        $this->mesas()->findOrFail($id);
        $abiertos = DB::int(
            "SELECT COUNT(*) FROM orders WHERE table_id=:t AND estado IN ('nuevo','preparando','listo','entregado')",
            ['t' => $id]
        );
        if ($abiertos > 0) $this->fail('Esa mesa tiene ' . $abiertos . ' pedido(s) sin cobrar.');
        $this->mesas()->cerrar($id);
        DB::exec("UPDATE waiter_calls SET estado='atendida', atendida_en=NOW() WHERE table_id=:t AND estado='pendiente'", ['t' => $id]);
        Audit::log('mesa.cerrar', 'tables', $id);
        $this->ok([], 'Mesa liberada');
    }

    public function atenderLlamada(): void
    {
        $this->exigir('mesero');
        $wc = (new WaiterCall())->forRestaurant($this->rid);
        $id = Request::int('id');
        $llamada = $wc->findOrFail($id);
        $wc->atender($id, Auth::id());

        if (!empty($llamada['table_id'])) {
            $pend = DB::int("SELECT COUNT(*) FROM waiter_calls WHERE table_id=:t AND estado='pendiente'",
                ['t' => (int)$llamada['table_id']]);
            if ($pend === 0) {
                $mesa = $this->mesas()->find((int)$llamada['table_id']);
                if ($mesa && in_array($mesa['estado'], ['llamada', 'cuenta'], true)) {
                    $abiertos = DB::int(
                        "SELECT COUNT(*) FROM orders WHERE table_id=:t AND estado IN ('nuevo','preparando','listo','entregado')",
                        ['t' => (int)$mesa['id']]
                    );
                    $this->mesas()->updateById((int)$mesa['id'], ['estado' => $abiertos > 0 ? 'ocupada' : 'libre']);
                }
            }
        }
        $this->ok($this->estado(), 'Llamada atendida');
    }

    // =================================================================
    //  Tickets
    // =================================================================
    public function ticket(array $p = []): void
    {
        $this->exigir('mesero');
        $this->imprimible((int)($p['id'] ?? 0), false);
    }

    public function precuenta(array $p = []): void
    {
        $this->exigir('mesero');
        $this->imprimible((int)($p['id'] ?? 0), true);
    }

    /** Ticket de 80 mm (o 58 mm) generado como PDF. */
    private function imprimible(int $id, bool $precuenta): void
    {
        $om = $this->ordenes();
        $pedido = $om->conLineas($id);
        if (!$pedido) throw \MenuGold\Core\HttpException::notFound('Pedido no encontrado.');

        $ancho = Request::str('mm', '80', 3) === '58' ? 'TICKET58' : 'TICKET80';
        $s = (string)$this->r['simbolo'];

        // Alto dinámico según el número de líneas
        $alto = 210 + count($pedido['items']) * 26 + (strlen((string)$pedido['notas']) > 0 ? 26 : 0);
        $pdf = new Pdf($ancho, 'P', 8);
        $pdf->setTamano($ancho, 'P', $alto);
        $pdf->meta('titulo', ($precuenta ? 'Precuenta' : 'Ticket') . ' ' . $pedido['codigo']);
        $pdf->addPage();

        $w = $pdf->anchoUtil();
        $x = $pdf->margen();
        $y = 12.0;

        $pdf->setColorTexto('#000000');
        $pdf->setFuente('helvetica-b', 11);
        $y += $pdf->cell($x, $y, $w, mb_strtoupper((string)$this->r['nombre']), 'C', 15);
        $pdf->setFuente('helvetica', 7);
        if (!empty($this->r['direccion'])) $y += $pdf->multiCell($x, $y, $w, (string)$this->r['direccion'], 1.25, 'C');
        if (!empty($this->r['telefono'])) $y += $pdf->cell($x, $y, $w, (string)$this->r['telefono'], 'C', 10);

        $y += 4;
        $pdf->setTrazo('#000000', 0.5);
        $pdf->dashed($x, $y, $x + $w, $y, 2, 2);
        $y += 7;

        $pdf->setFuente('helvetica-b', 9);
        $y += $pdf->cell($x, $y, $w, $precuenta ? 'PRECUENTA' : 'TICKET DE VENTA', 'C', 12);
        $pdf->setFuente('helvetica', 7.5);
        $y += $pdf->cell($x, $y, $w, 'No. ' . $pedido['codigo'] . '   ' . dt((string)$pedido['creado']), 'C', 10);
        if (!empty($pedido['mesa_nombre'])) {
            $y += $pdf->cell($x, $y, $w, $pedido['mesa_nombre'] . ' · ' . Order::etiquetaModo((string)$pedido['modo']), 'C', 10);
        }
        if (!empty($pedido['cliente_nombre'])) {
            $y += $pdf->cell($x, $y, $w, $pedido['cliente_nombre'], 'C', 10);
        }

        $y += 3;
        $pdf->dashed($x, $y, $x + $w, $y, 2, 2);
        $y += 6;

        $pdf->setFuente('helvetica', 7.5);
        foreach ($pedido['items'] as $l) {
            $linea = (int)$l['cantidad'] . ' x ' . (string)$l['nombre'];
            $importe = $s . number_format((float)$l['subtotal'], 2);
            $anchoImp = $pdf->anchoTexto($importe) + 3;
            $lineas = $pdf->ajustar($linea, $w - $anchoImp);
            $pdf->cell($x, $y, $w, $importe, 'R', 10);
            foreach ($lineas as $i => $t) {
                $y += $pdf->cell($x, $y, $w - $anchoImp, $t, 'L', 10);
            }
            foreach (jdec($l['modificadores']) as $m) {
                $pdf->setFuente('helvetica', 6.5);
                $y += $pdf->cell($x + 6, $y, $w - 6, '· ' . (string)$m['opcion'], 'L', 8.5);
                $pdf->setFuente('helvetica', 7.5);
            }
            if (!empty($l['notas'])) {
                $pdf->setFuente('helvetica-i', 6.5);
                $y += $pdf->multiCell($x + 6, $y, $w - 6, '* ' . (string)$l['notas'], 1.2);
                $pdf->setFuente('helvetica', 7.5);
            }
        }

        $y += 3;
        $pdf->dashed($x, $y, $x + $w, $y, 2, 2);
        $y += 6;

        $fila = function (string $et, float $v, bool $fuerte = false) use ($pdf, $x, $w, $s, &$y): void {
            $pdf->setFuente($fuerte ? 'helvetica-b' : 'helvetica', $fuerte ? 10 : 7.5);
            $pdf->cell($x, $y, $w, $s . number_format($v, 2), 'R', $fuerte ? 14 : 11);
            $y += $pdf->cell($x, $y, $w, $et, 'L', $fuerte ? 14 : 11);
        };
        $fila('Subtotal', (float)$pedido['subtotal']);
        if ((float)$pedido['descuento'] > 0) $fila('Descuento', -(float)$pedido['descuento']);
        if ((float)$pedido['costo_envio'] > 0) $fila('Envío', (float)$pedido['costo_envio']);
        if ((float)$pedido['impuesto'] > 0) {
            $pdf->setFuente('helvetica', 6.5);
            $y += $pdf->cell($x, $y, $w, 'IVA incluido: ' . $s . number_format((float)$pedido['impuesto'], 2), 'L', 9);
        }
        if ((float)$pedido['propina'] > 0) $fila('Propina', (float)$pedido['propina']);
        $y += 2;
        $fila('TOTAL', (float)$pedido['total'], true);

        if (!$precuenta && !empty($pedido['metodo_pago'])) {
            $pdf->setFuente('helvetica', 7.5);
            $y += $pdf->cell($x, $y, $w, 'Pago: ' . ucfirst((string)$pedido['metodo_pago']), 'L', 11);
            if (!empty($pedido['pagado_con'])) {
                $y += $pdf->cell($x, $y, $w, 'Recibido: ' . $s . number_format((float)$pedido['pagado_con'], 2), 'L', 10);
                $y += $pdf->cell($x, $y, $w, 'Cambio: ' . $s . number_format(max(0, (float)$pedido['pagado_con'] - (float)$pedido['total']), 2), 'L', 10);
            }
        }
        if (!empty($pedido['notas'])) {
            $y += 3;
            $pdf->setFuente('helvetica-i', 6.5);
            $y += $pdf->multiCell($x, $y, $w, 'Notas: ' . (string)$pedido['notas'], 1.25);
        }

        $y += 6;
        $pdf->dashed($x, $y, $x + $w, $y, 2, 2);
        $y += 8;
        $pdf->setFuente('helvetica', 7);
        $y += $pdf->cell($x, $y, $w, $precuenta ? 'Esta precuenta no es un comprobante fiscal.' : '¡Gracias por su visita!', 'C', 10);
        if (!empty($this->r['google_reviews']) && !$precuenta) {
            $y += $pdf->cell($x, $y, $w, 'Cuéntanos cómo estuvo todo:', 'C', 10);
            [$mod, $rects] = \MenuGold\Vendor\QrCode\QrCode::rects((string)$this->r['google_reviews'], 'M');
            $lado = min(70.0, $w * 0.6);
            $pdf->qr($rects, $mod, $x + ($w - $lado) / 2, $y + 3, $lado, '#000000');
            $y += $lado + 8;
        }
        $pdf->setFuente('helvetica', 6);
        $pdf->cell($x, $y, $w, 'MenuGold', 'C', 9);

        $this->inline($pdf->output(), ($precuenta ? 'precuenta-' : 'ticket-') . $pedido['codigo'] . '.pdf', 'application/pdf');
    }
}
