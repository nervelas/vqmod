<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Sanitize;
use S5\Core\Settings;

final class Slugs
{
    public static function reserved(): array
    {
        $r = array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', (string) Settings::get('reservados', ''))));
        $r[] = strtolower((string) Settings::get('portal_sub', 'crear'));
        $r[] = '_base';
        return array_values(array_unique($r));
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::reserved(), true);
    }

    /** Slug único y permitido a partir del nombre del negocio. */
    public static function generate(string $name, ?int $orderId = null): string
    {
        $base = Sanitize::slug($name, 30);
        if ($base === '' || strlen($base) < 2) {
            $base = 'negocio';
        }
        $n = 0;
        do {
            $slug = $n === 0 ? $base : substr($base, 0, 30) . '-' . ($n + 1);
            $slug = trim($slug, '-');
            $taken = self::isReserved($slug) || self::taken($slug, $orderId);
            $n++;
        } while ($taken && $n < 500);
        if ($taken) {
            $slug = $base . '-' . bin2hex(random_bytes(3));
        }
        return $slug;
    }

    public static function taken(string $slug, ?int $exceptOrder = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ' . Db::t('orders') . ' WHERE slug=? AND status<>?';
        $p = [$slug, Orders::ST_ELIMINADA];
        if ($exceptOrder) {
            $sql .= ' AND id<>?';
            $p[] = $exceptOrder;
        }
        return (int) Db::val($sql, $p) > 0;
    }
}
