<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\DB;
use MenuGold\Models\Customer;

class CustomersController extends BaseController
{
    protected $ability = 'customers';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $q = $this->request->str('q', '');
        return $this->view('admin/customers', array(
            'customers' => Customer::search($q),
            'q'         => $q,
            'totals'    => DB::first(
                'SELECT COUNT(*) AS n, COALESCE(SUM(total_spent),0) AS spent, COALESCE(AVG(orders_count),0) AS avg_orders
                 FROM mg_customers'),
        ));
    }

    public function show(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $c = Customer::find((int)$params['id']);
        if (!$c) { return $this->notFound('Ese cliente no existe.'); }
        return $this->view('admin/customer', array(
            'customer' => $c,
            'orders'   => Customer::orders((int)$c['id']),
        ));
    }
}
