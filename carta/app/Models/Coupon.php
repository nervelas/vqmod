<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Money;

final class Coupon
{
    public static function validate($code, $subtotal)
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') { return array('ok' => false, 'error' => 'Escribe un cupón.'); }
        $c = DB::first('SELECT * FROM mg_coupons WHERE code = :c LIMIT 1', array('c' => $code));
        if (!$c || (int)$c['is_active'] === 0) {
            return array('ok' => false, 'error' => 'Ese cupón no existe.');
        }
        $today = date('Y-m-d');
        if (!empty($c['starts_at']) && $c['starts_at'] > $today) {
            return array('ok' => false, 'error' => 'Ese cupón todavía no está vigente.');
        }
        if (!empty($c['ends_at']) && $c['ends_at'] < $today) {
            return array('ok' => false, 'error' => 'Ese cupón ya venció.');
        }
        if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) {
            return array('ok' => false, 'error' => 'Ese cupón alcanzó su límite de usos.');
        }
        if ((float)$c['min_total'] > 0 && (float)$subtotal < (float)$c['min_total']) {
            return array('ok' => false, 'error' => 'El cupón aplica desde ' . Money::format($c['min_total']) . '.');
        }
        return array('ok' => true, 'coupon' => $c);
    }

    public static function consume($code)
    {
        DB::run('UPDATE mg_coupons SET used_count = used_count + 1 WHERE code = :c', array('c' => strtoupper(trim((string)$code))));
    }
}
