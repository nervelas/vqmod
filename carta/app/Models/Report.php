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
    public static function summary($from, $to)
    {
        list($f, $t) = self::range($from, $to);
        $row = DB::first(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue,
                    COALESCE(AVG(total),0) AS ticket, COALESCE(SUM(tip),0) AS tips,
                    COALESCE(SUM(discount),0) AS discounts
             FROM mg_orders WHERE status <> 'cancelled' AND placed_at BETWEEN :f AND :t",
            array('f' => $f, 't' => $t)
        );
        $days = max(1, (int)round((strtotime($t) - strtotime($f)) / 86400));
        $prev = DB::first(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM mg_orders WHERE status <> 'cancelled' AND placed_at BETWEEN :f AND :t",
            array('f' => date('Y-m-d H:i:s', strtotime($f) - $days * 86400), 't' => $f)
        );
        $cancelled = (int)DB::value(
            "SELECT COUNT(*) FROM mg_orders WHERE status = 'cancelled' AND placed_at BETWEEN :f AND :t",
            array('f' => $f, 't' => $t), 0);

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

    public static function byDay($from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT DATE(placed_at) AS d, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM mg_orders WHERE status <> 'cancelled' AND placed_at BETWEEN :f AND :t
             GROUP BY DATE(placed_at) ORDER BY d",
            array('f' => $f, 't' => $t)
        );
    }

    public static function byHour($from, $to)
    {
        list($f, $t) = self::range($from, $to);
        $rows = DB::all(
            "SELECT HOUR(placed_at) AS h, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM mg_orders WHERE status <> 'cancelled' AND placed_at BETWEEN :f AND :t
             GROUP BY HOUR(placed_at) ORDER BY h",
            array('f' => $f, 't' => $t)
        );
        $out = array();
        for ($h = 0; $h < 24; $h++) { $out[$h] = array('h' => $h, 'orders' => 0, 'revenue' => 0.0); }
        foreach ($rows as $r) { $out[(int)$r['h']] = array('h' => (int)$r['h'], 'orders' => (int)$r['orders'], 'revenue' => (float)$r['revenue']); }
        return array_values($out);
    }

    public static function byCategory($from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT COALESCE(c.name, 'Sin categoría') AS name, SUM(oi.qty) AS qty, SUM(oi.line_total) AS revenue
             FROM mg_order_items oi
             INNER JOIN mg_orders o ON o.id = oi.order_id
             LEFT JOIN mg_products p ON p.id = oi.product_id
             LEFT JOIN mg_categories c ON c.id = p.category_id
             WHERE o.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t
             GROUP BY name ORDER BY revenue DESC",
            array('f' => $f, 't' => $t)
        );
    }

    public static function byMode($from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT mode, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
             FROM mg_orders WHERE status <> 'cancelled' AND placed_at BETWEEN :f AND :t
             GROUP BY mode ORDER BY revenue DESC",
            array('f' => $f, 't' => $t)
        );
    }

    public static function byWaiter($from, $to)
    {
        list($f, $t) = self::range($from, $to);
        return DB::all(
            "SELECT COALESCE(u.name, 'Sin asignar') AS name, COUNT(*) AS orders, COALESCE(SUM(o.total),0) AS revenue
             FROM mg_orders o LEFT JOIN mg_users u ON u.id = o.waiter_id
             WHERE o.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t
             GROUP BY name ORDER BY revenue DESC",
            array('f' => $f, 't' => $t)
        );
    }

    public static function topProducts($from, $to, $limit = 10, $asc = false)
    {
        list($f, $t) = self::range($from, $to);
        $limit = max(1, min(50, (int)$limit));
        return DB::all(
            "SELECT oi.name AS name, SUM(oi.qty) AS qty, SUM(oi.line_total) AS revenue
             FROM mg_order_items oi INNER JOIN mg_orders o ON o.id = oi.order_id
             WHERE o.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t
             GROUP BY oi.name ORDER BY qty " . ($asc ? 'ASC' : 'DESC') . " LIMIT " . $limit,
            array('f' => $f, 't' => $t)
        );
    }

    /** Tiempos medios de cocina y cierre, en minutos. */
    public static function timings($from, $to)
    {
        list($f, $t) = self::range($from, $to);
        $row = DB::first(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, placed_at, ready_at))  AS to_ready,
                    AVG(TIMESTAMPDIFF(MINUTE, placed_at, closed_at)) AS to_close
             FROM mg_orders
             WHERE status IN ('served','closed') AND placed_at BETWEEN :f AND :t",
            array('f' => $f, 't' => $t)
        );
        return array(
            'to_ready' => $row && $row['to_ready'] !== null ? round((float)$row['to_ready'], 1) : null,
            'to_close' => $row && $row['to_close'] !== null ? round((float)$row['to_close'], 1) : null,
        );
    }

    /** Cifras del día para el tablero del panel. */
    public static function today()
    {
        $f = date('Y-m-d 00:00:00');
        $t = date('Y-m-d 23:59:59');
        $row = DB::first(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS ticket
             FROM mg_orders WHERE status <> 'cancelled' AND placed_at BETWEEN :f AND :t",
            array('f' => $f, 't' => $t)
        );
        return array(
            'orders'  => (int)$row['orders'],
            'revenue' => (float)$row['revenue'],
            'ticket'  => (float)$row['ticket'],
            'active'  => (int)DB::value("SELECT COUNT(*) FROM mg_orders WHERE status IN ('new','cooking','ready')", array(), 0),
            'calls'   => (int)DB::value("SELECT COUNT(*) FROM mg_service_calls WHERE status = 'open'", array(), 0),
        );
    }
}
