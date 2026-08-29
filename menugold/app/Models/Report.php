<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;

/** Agregaciones para el módulo de reportes. */
final class Report
{
    private static function range($from, $to)
    {
        $from = $from !== '' ? $from : date('Y-m-01');
        $to   = $to   !== '' ? $to   : date('Y-m-d');
        return array($from . ' 00:00:00', $to . ' 23:59:59');
    }

    /** Cifras principales del periodo, comparadas con el periodo anterior. */
    public static function summary($restaurantId, $from, $to)
    {
        list($f, $t) = self::range($from, $to);
        $row = DB::first(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue,
                    COALESCE(AVG(total),0) AS ticket, COALESCE(SUM(tip),0) AS tips,
                    COALESCE(SUM(discount),0) AS discounts
             FROM orders WHERE restaurant_id = :r AND status <> 'cancelled' AND placed_at BETWEEN :f AND :t",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
        $days = max(1, (int)round((strtotime($t) - strtotime($f)) / 86400));
        $pf = date('Y-m-d H:i:s', strtotime($f) - $days * 86400);
        $pt = $f;
        $prev = DB::first(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM orders WHERE restaurant_id = :r AND status <> 'cancelled' AND placed_at BETWEEN :f AND :t",
            array('r' => (int)$restaurantId, 'f' => $pf, 't' => $pt)
        );
        $cancelled = (int)DB::value(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id = :r AND status = 'cancelled' AND placed_at BETWEEN :f AND :t",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t), 0);

        $growth = ((float)$prev['revenue'] > 0)
            ? round((((float)$row['revenue'] - (float)$prev['revenue']) / (float)$prev['revenue']) * 100, 1)
            : null;

        return array(
            'orders'    => (int)$row['orders'],
            'revenue'   => (float)$row['revenue'],
            'ticket'    => (float)$row['ticket'],
            'tips'      => (float)$row['tips'],
            'discounts' => (float)$row['discounts'],
            'cancelled' => $cancelled,
            'growth'    => $growth,
        );
    }

    public static function byDay($restaurantId, $from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT DATE(placed_at) AS d, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM orders WHERE restaurant_id = :r AND status <> 'cancelled' AND placed_at BETWEEN :f AND :t
             GROUP BY DATE(placed_at) ORDER BY d",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
    }

    public static function byHour($restaurantId, $from, $to)
    {
        list($f, $t) = self::range($from, $to);
        $rows = DB::all(
            "SELECT HOUR(placed_at) AS h, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM orders WHERE restaurant_id = :r AND status <> 'cancelled' AND placed_at BETWEEN :f AND :t
             GROUP BY HOUR(placed_at) ORDER BY h",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
        $out = array();
        for ($h = 0; $h < 24; $h++) { $out[$h] = array('h' => $h, 'orders' => 0, 'revenue' => 0.0); }
        foreach ($rows as $r) { $out[(int)$r['h']] = array('h' => (int)$r['h'], 'orders' => (int)$r['orders'], 'revenue' => (float)$r['revenue']); }
        return array_values($out);
    }

    public static function byCategory($restaurantId, $from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT COALESCE(c.name, 'Sin categoría') AS name, SUM(oi.qty) AS qty, SUM(oi.line_total) AS revenue
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             LEFT JOIN products p ON p.id = oi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE o.restaurant_id = :r AND o.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t
             GROUP BY name ORDER BY revenue DESC",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
    }

    public static function byMode($restaurantId, $from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT mode, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM orders WHERE restaurant_id = :r AND status <> 'cancelled' AND placed_at BETWEEN :f AND :t
             GROUP BY mode ORDER BY revenue DESC",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
    }

    public static function byWaiter($restaurantId, $from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT COALESCE(u.name, 'Sin asignar') AS name, COUNT(*) AS orders, COALESCE(SUM(o.total),0) AS revenue
             FROM orders o LEFT JOIN users u ON u.id = o.waiter_id
             WHERE o.restaurant_id = :r AND o.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t
             GROUP BY name ORDER BY revenue DESC",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
    }

    public static function topProducts($restaurantId, $from, $to, $limit = 10, $asc = false)
    {
        list($f, $t) = self::range($from, $to);
        $limit = max(1, min(50, (int)$limit));
        return DB::all(
            "SELECT oi.name_snapshot AS name, SUM(oi.qty) AS qty, SUM(oi.line_total) AS revenue
             FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.restaurant_id = :r AND o.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t
             GROUP BY oi.name_snapshot ORDER BY qty " . ($asc ? 'ASC' : 'DESC') . " LIMIT " . $limit,
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
    }

    /** Tiempos medios de preparación y entrega, en minutos. */
    public static function timings($restaurantId, $from, $to)
    {
        list($f, $t) = self::range($from, $to);
        $row = DB::first(
            "SELECT
               AVG(TIMESTAMPDIFF(MINUTE, placed_at, accepted_at))  AS to_accept,
               AVG(TIMESTAMPDIFF(MINUTE, accepted_at, ready_at))   AS to_ready,
               AVG(TIMESTAMPDIFF(MINUTE, placed_at, delivered_at)) AS to_deliver
             FROM orders
             WHERE restaurant_id = :r AND status IN ('delivered','paid') AND placed_at BETWEEN :f AND :t",
            array('r' => (int)$restaurantId, 'f' => $f, 't' => $t)
        );
        return array(
            'to_accept'  => $row && $row['to_accept']  !== null ? round((float)$row['to_accept'], 1)  : null,
            'to_ready'   => $row && $row['to_ready']   !== null ? round((float)$row['to_ready'], 1)   : null,
            'to_deliver' => $row && $row['to_deliver'] !== null ? round((float)$row['to_deliver'], 1) : null,
        );
    }

    /** Panorama de la plataforma para el superadministrador. */
    public static function platform()
    {
        return array(
            'restaurants' => (int)DB::value('SELECT COUNT(*) FROM restaurants', array(), 0),
            'active'      => (int)DB::value("SELECT COUNT(*) FROM restaurants WHERE status = 'active'", array(), 0),
            'suspended'   => (int)DB::value("SELECT COUNT(*) FROM restaurants WHERE status = 'suspended'", array(), 0),
            'orders_month'=> (int)DB::value('SELECT COUNT(*) FROM orders WHERE placed_at >= :d', array('d' => date('Y-m-01')), 0),
            'revenue_month'=> (float)DB::value("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled' AND placed_at >= :d", array('d' => date('Y-m-01')), 0),
            'products'    => (int)DB::value('SELECT COUNT(*) FROM products', array(), 0),
            'expiring'    => DB::all("SELECT id, name, slug, plan_expires_at FROM restaurants
                                      WHERE plan_expires_at IS NOT NULL AND plan_expires_at <= :d
                                      ORDER BY plan_expires_at LIMIT 10", array('d' => date('Y-m-d', strtotime('+15 days')))),
        );
    }
}
