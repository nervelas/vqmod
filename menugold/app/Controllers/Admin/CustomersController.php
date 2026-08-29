<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Money;
use MenuGold\Core\Session;
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
            'customers' => Customer::search($this->rid(), $q),
            'q'         => $q,
            'totals'    => DB::first(
                'SELECT COUNT(*) AS n, COALESCE(SUM(total_spent),0) AS spent, COALESCE(AVG(orders_count),0) AS avg_orders
                 FROM customers WHERE restaurant_id = :r', array('r' => $this->rid())),
        ));
    }

    public function coupons()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $code = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $this->request->str('code', '')));
            if ($code === '') {
                Session::flash('error', 'El cupón necesita un código (solo letras, números y guiones).');
                return $this->redirect('/panel/cupones');
            }
            $type = $this->request->str('type', 'percent');
            if (!in_array($type, array('percent', 'amount', 'free_delivery'), true)) { $type = 'percent'; }

            $data = array(
                'code'      => mb_substr($code, 0, 40),
                'type'      => $type,
                'value'     => Money::round($this->request->float('value')),
                'min_total' => Money::round($this->request->float('min_total')),
                'max_uses'  => max(0, min(100000, $this->request->int('max_uses', 0))),
                'starts_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->request->str('starts_at')) ? $this->request->str('starts_at') : null,
                'ends_at'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->request->str('ends_at')) ? $this->request->str('ends_at') : null,
                'is_active' => $this->request->bool('is_active') ? 1 : 0,
            );

            $id = $this->request->int('id', 0);
            $exists = DB::value('SELECT id FROM coupons WHERE restaurant_id = :r AND code = :c AND id <> :i',
                array('r' => $this->rid(), 'c' => $data['code'], 'i' => $id));
            if ($exists) {
                Session::flash('error', 'Ya existe un cupón con ese código.');
                return $this->redirect('/panel/cupones');
            }

            if ($id > 0 && $this->own('coupons', $id)) {
                DB::update('coupons', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
            } else {
                $data['restaurant_id'] = $this->rid();
                $id = DB::insert('coupons', $data);
            }
            Audit::log('coupon_saved', 'coupon', $id, array('code' => $data['code']));
            Session::flash('success', 'Cupón guardado.');
            return $this->redirect('/panel/cupones');
        }

        return $this->view('admin/coupons', array(
            'coupons' => DB::all('SELECT * FROM coupons WHERE restaurant_id = :r ORDER BY is_active DESC, id DESC', array('r' => $this->rid())),
        ));
    }

    public function deleteCoupon(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        DB::delete('coupons', 'id = :id AND restaurant_id = :r', array('id' => (int)$params['id'], 'r' => $this->rid()));
        Session::flash('success', 'Cupón eliminado.');
        return $this->redirect('/panel/cupones');
    }
}
