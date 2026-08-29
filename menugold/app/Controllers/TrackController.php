<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\Lang;
use MenuGold\Core\Money;
use MenuGold\Models\Order;
use MenuGold\Models\Restaurant;

/** Seguimiento en vivo del pedido, con el enlace secreto del comensal. */
class TrackController extends Controller
{
    public function show(array $params)
    {
        $order = Order::findByToken(isset($params['token']) ? $params['token'] : '');
        if (!$order) {
            return $this->notFound('Ese pedido no existe o el enlace ya no es válido.');
        }
        $r = Restaurant::find((int)$order['restaurant_id']);
        Money::setCurrency($r['currency']);
        Lang::setLocale($order['lang']);
        date_default_timezone_set($r['timezone']);

        return $this->view('menu/track', array(
            'order'      => $order,
            'restaurant' => $r,
            'settings'   => Restaurant::settings((int)$r['id']),
        ));
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
