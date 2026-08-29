<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Models\Order;

/** Seguimiento en vivo del pedido, con el enlace secreto del comensal. */
class TrackController extends Controller
{
    public function show(array $params)
    {
        $order = Order::findByToken(isset($params['token']) ? $params['token'] : '');
        if (!$order) {
            return $this->notFound('Ese pedido no existe o el enlace ya no es válido.');
        }
        return $this->view('menu/track', array('order' => $order));
    }

    public function status(array $params)
    {
        $order = Order::findByToken(isset($params['token']) ? $params['token'] : '');
        if (!$order) { return $this->fail('Pedido no encontrado.', 404); }
        return $this->ok(array(
            'status' => $order['status'],
            'label'  => $order['status_label'],
            'total'  => (float)$order['total'],
        ));
    }
}
