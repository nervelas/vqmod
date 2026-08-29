<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\View;
use MenuGold\Models\Order;

/** Pantalla de cocina: tres columnas que se refrescan solas. */
class KitchenController extends BaseController
{
    protected $ability = 'kds';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return $this->view('admin/kitchen', array(
            'board' => Order::kitchenBoard(),
            'pulse' => Order::pulse(),
        ));
    }

    /** Fragmento HTML de las columnas, para refrescar sin recargar la página. */
    public function data()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $html = View::render('admin/partials/kds-columns', array('board' => Order::kitchenBoard()));
        $pulse = Order::pulse();
        return $this->ok(array('html' => $html, 'hash' => $pulse['hash']));
    }
}
