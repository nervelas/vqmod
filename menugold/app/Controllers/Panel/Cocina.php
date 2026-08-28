<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Request;
use MenuGold\Core\Sse;
use MenuGold\Core\View;
use MenuGold\Models\Order;

/**
 * Pantalla de cocina (KDS): comandas en tiempo real por columnas.
 */
class Cocina extends Base
{
    public function index(): void
    {
        $this->exigir('kds');
        $estacion = Request::enum('estacion', ['cocina', 'bar', 'postres'], '');
        View::share('usuario', Auth::user());
        View::share('restaurante', $this->r);
        View::display('panel/cocina', [
            'r'        => $this->r,
            'estacion' => $estacion,
            'datos'    => $this->agrupar($estacion),
        ], 'kds');
    }

    /** Sondeo cada 5 s (respaldo de SSE). */
    public function datos(): void
    {
        $this->exigir('kds');
        $estacion = Request::enum('estacion', ['cocina', 'bar', 'postres'], '');
        $this->ok(['tablero' => $this->agrupar($estacion), 'hora' => date('H:i:s')]);
    }

    /** Flujo en vivo con Server-Sent Events. */
    public function sse(): void
    {
        $this->exigir('kds');
        $estacion = Request::enum('estacion', ['cocina', 'bar', 'postres'], '');
        $rid = $this->rid;
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

        Sse::loop(function () use ($estacion, $rid) {
            $om = (new Order())->forRestaurant($rid);
            $pedidos = $om->paraCocina($estacion);
            return $this->tablero($pedidos);
        }, 'tablero');
        exit;
    }

    public function avanzar(): void
    {
        $this->exigir('kds');
        $id = Request::int('id');
        $om = (new Order())->forRestaurant($this->rid);
        $pedido = $om->findOrFail($id);

        $destino = Request::str('estado', '', 20);
        if ($destino === '' || !in_array($destino, Order::ESTADOS, true)) {
            $destino = $om->siguienteEstado((string)$pedido['estado']);
        }
        if ($destino === $pedido['estado']) $this->fail('Ese pedido ya está en ese estado.');

        $om->cambiarEstado($id, $destino, 'Desde la pantalla de cocina');
        Audit::log('pedido.estado', 'orders', $id, ['estado' => $pedido['estado']], ['estado' => $destino]);

        $this->ok([
            'tablero' => $this->agrupar(Request::enum('estacion', ['cocina', 'bar', 'postres'], '')),
            'estado'  => $destino,
        ], 'Pedido ' . $pedido['codigo'] . ': ' . (Order::ETIQUETA_ESTADO[$destino] ?? $destino));
    }

    // -----------------------------------------------------------------
    private function agrupar(string $estacion): array
    {
        $om = (new Order())->forRestaurant($this->rid);
        return $this->tablero($om->paraCocina($estacion));
    }

    /** Estructura lista para pintar: tres columnas con sus comandas. */
    private function tablero(array $pedidos): array
    {
        $cols = ['nuevo' => [], 'preparando' => [], 'listo' => []];
        foreach ($pedidos as $o) {
            $estado = (string)$o['estado'];
            if (!isset($cols[$estado])) continue;
            $items = [];
            foreach ($o['items'] as $l) {
                $mods = [];
                foreach (jdec($l['modificadores']) as $m) $mods[] = (string)$m['opcion'];
                $items[] = [
                    'cantidad' => (int)$l['cantidad'],
                    'nombre'   => (string)$l['nombre'],
                    'mods'     => $mods,
                    'notas'    => (string)$l['notas'],
                    'estacion' => (string)$l['estacion'],
                ];
            }
            $min = (int)($o['minutos'] ?? 0);
            $cols[$estado][] = [
                'id'       => (int)$o['id'],
                'codigo'   => (string)$o['codigo'],
                'mesa'     => (string)($o['mesa'] ?? $o['mesa_nombre']) ?: Order::etiquetaModo((string)$o['modo']),
                'modo'     => (string)$o['modo'],
                'minutos'  => $min,
                'alerta'   => $min >= 25 ? 'rojo' : ($min >= 12 ? 'ambar' : 'verde'),
                'notas'    => (string)$o['notas'],
                'cliente'  => (string)$o['cliente_nombre'],
                'items'    => $items,
                'creado'   => dt((string)$o['creado'], 'H:i'),
            ];
        }
        return $cols;
    }
}
