<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Money;

/** Promociones con fecha y día de la semana. */
final class Promotion
{
    public static function activeNow()
    {
        $tz = Settings::get('timezone', 'America/Guatemala');
        $now = new \DateTime('now', new \DateTimeZone($tz));
        $today = $now->format('Y-m-d');
        $mask = 1 << (int)$now->format('w');
        $rows = DB::all(
            'SELECT * FROM mg_promotions
             WHERE is_active = 1
               AND (starts_at IS NULL OR starts_at <= :d1)
               AND (ends_at   IS NULL OR ends_at   >= :d2)',
            array('d1' => $today, 'd2' => $today)
        );
        $out = array();
        foreach ($rows as $r) {
            if (((int)$r['days_mask'] & $mask) !== 0) { $out[] = $r; }
        }
        return $out;
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
                $applies = (int)$p['target_id'] === (int)$product['category_id'];
            } elseif ($p['scope'] === 'product') {
                $applies = (int)$p['target_id'] === (int)$product['id'];
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
