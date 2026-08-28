<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Request;
use MenuGold\Models\Order;

/**
 * Historial de pedidos, detalle, cambios de estado y anulaciones.
 */
class Pedidos extends Base
{
    public function index(): void
    {
        $this->exigir('pedidos');
        $f = [
            'q'      => Request::str('q', '', 60),
            'estado' => Request::enum('estado', Order::ESTADOS, ''),
            'modo'   => Request::enum('modo', ['mesa', 'llevar', 'delivery', 'whatsapp'], ''),
            'desde'  => Request::date('desde'),
            'hasta'  => Request::date('hasta'),
        ];
        $om = (new Order())->forRestaurant($this->rid);
        $total = $om->contarHistorial($f);
        $pag = $this->paginar($total, 40);

        $this->panel('panel/pedidos', [
            'pedidos' => $om->historial($f, $pag['por'], $pag['offset']),
            'filtros' => $f,
            'pag'     => $pag,
            'resumen' => DB::one(
                "SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM orders
                 WHERE restaurant_id=:r AND estado IN ('entregado','pagado') AND DATE(creado)=CURDATE()",
                ['r' => $this->rid]
            ),
        ]);
    }

    public function ver(array $p = []): void
    {
        $this->exigir('pedidos');
        $om = (new Order())->forRestaurant($this->rid);
        $pedido = $om->conLineas((int)($p['id'] ?? 0));
        if (!$pedido) throw \MenuGold\Core\HttpException::notFound('Pedido no encontrado.');

        $this->panel('panel/pedido-detalle', [
            'pedido'  => $pedido,
            'eventos' => $om->timeline((int)$pedido['id']),
            'mesero'  => !empty($pedido['user_id'])
                ? DB::one('SELECT nombre FROM users WHERE id=:i', ['i' => (int)$pedido['user_id']]) : null,
        ]);
    }

    public function estado(): void
    {
        $this->exigir('pedidos');
        $id = Request::int('id');
        $nuevo = Request::str('estado', '', 20);
        $om = (new Order())->forRestaurant($this->rid);
        $antes = $om->findOrFail($id);
        $om->cambiarEstado($id, $nuevo, 'Cambio manual desde el panel');
        Audit::log('pedido.estado', 'orders', $id, ['estado' => $antes['estado']], ['estado' => $nuevo]);
        $this->ok(['estado' => $nuevo], 'Pedido actualizado');
    }

    public function anular(): void
    {
        $this->exigir('pedidos');
        $id = Request::int('id');
        $motivo = Request::str('motivo', '', 255);
        if (mb_strlen($motivo) < 4) $this->fail('Escribe el motivo de la anulación.');

        $om = (new Order())->forRestaurant($this->rid);
        $antes = $om->findOrFail($id);
        if ($antes['estado'] === 'pagado' && !Auth::isOwner()) {
            $this->fail('Solo el dueño o un administrador puede anular un pedido ya cobrado.');
        }
        $om->anular($id, $motivo);
        Audit::log('pedido.anular', 'orders', $id,
            ['estado' => $antes['estado'], 'total' => (float)$antes['total']],
            ['motivo' => $motivo]);

        // Libera la mesa si ya no le quedan pedidos abiertos
        if (!empty($antes['table_id'])) {
            $abiertos = DB::int(
                "SELECT COUNT(*) FROM orders WHERE table_id=:t AND estado IN ('nuevo','preparando','listo','entregado')",
                ['t' => (int)$antes['table_id']]
            );
            if ($abiertos === 0) {
                DB::update('tables', ['estado' => 'libre', 'abierta_desde' => null],
                    'id=:t AND restaurant_id=:r', ['t' => (int)$antes['table_id'], 'r' => $this->rid]);
            }
        }
        $this->ok([], 'Pedido anulado');
    }
}
