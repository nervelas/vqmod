<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Money;

final class Promotion
{
    public static function activeFor($restaurantId)
    {
        $today = date('Y-m-d');
        return DB::all(
            'SELECT * FROM promotions
             WHERE restaurant_id = :r AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= :d1)
               AND (ends_at   IS NULL OR ends_at   >= :d2)',
            array('r' => (int)$restaurantId, 'd1' => $today, 'd2' => $today)
        );
    }

    /** Aplica la mejor promoción vigente al precio de un producto. */
    public static function apply($price, array $product, array $promos)
    {
        $best = (float)$price;
        foreach ($promos as $p) {
            $applies = false;
            if ($p['scope'] === 'all') {
                $applies = true;
            } elseif ($p['scope'] === 'category') {
                $applies = (int)$p['scope_id'] === (int)$product['category_id'];
            } elseif ($p['scope'] === 'product') {
                $applies = (int)$p['scope_id'] === (int)$product['id'];
            }
            if (!$applies) { continue; }
            $candidate = $p['type'] === 'percent'
                ? (float)$price * (1 - ((float)$p['value'] / 100))
                : (float)$price - (float)$p['value'];
            if ($candidate < $best) { $best = $candidate; }
        }
        return Money::round(max(0, $best));
    }
}
