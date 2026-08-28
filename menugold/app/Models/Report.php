<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;

/**
 * Consultas agregadas para el panel y los reportes.
 * Todas filtran por restaurant_id.
 */
class Report
{
    private int $rid;

    public function __construct(int $restaurantId)
    {
        $this->rid = $restaurantId;
    }

    private const VENDIDOS = "estado IN ('entregado','pagado')";

    /** Resumen del dashboard. */
    public function resumen(): array
    {
        $rid = $this->rid;
        $hoy = date('Y-m-d');
        $mes = date('Y-m-01');
        $ayer = date('Y-m-d', strtotime('-1 day'));

        $ventasHoy = (float)DB::value(
            "SELECT COALESCE(SUM(total),0) FROM orders WHERE restaurant_id=:r AND " . self::VENDIDOS . " AND DATE(creado)=:d",
            ['r' => $rid, 'd' => $hoy], 0);
        $ventasAyer = (float)DB::value(
            "SELECT COALESCE(SUM(total),0) FROM orders WHERE restaurant_id=:r AND " . self::VENDIDOS . " AND DATE(creado)=:d",
            ['r' => $rid, 'd' => $ayer], 0);
        $pedidosHoy = DB::int(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id=:r AND estado<>'anulado' AND DATE(creado)=:d",
            ['r' => $rid, 'd' => $hoy]);
        $ventasMes = (float)DB::value(
            "SELECT COALESCE(SUM(total),0) FROM orders WHERE restaurant_id=:r AND " . self::VENDIDOS . " AND creado>=:m",
            ['r' => $rid, 'm' => $mes], 0);
        $pedidosMes = DB::int(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id=:r AND " . self::VENDIDOS . " AND creado>=:m",
            ['r' => $rid, 'm' => $mes]);
        $abiertos = DB::int(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id=:r AND estado IN ('nuevo','preparando','listo')",
            ['r' => $rid]);
        $prepProm = (float)DB::value(
            "SELECT COALESCE(AVG(minutos_prep),0) FROM orders WHERE restaurant_id=:r AND minutos_prep IS NOT NULL AND creado>=:m",
            ['r' => $rid, 'm' => $mes], 0);

        return [
            'ventas_hoy'     => round($ventasHoy, 2),
            'ventas_ayer'    => round($ventasAyer, 2),
            'variacion'      => $ventasAyer > 0 ? round((($ventasHoy - $ventasAyer) / $ventasAyer) * 100) : ($ventasHoy > 0 ? 100 : 0),
            'pedidos_hoy'    => $pedidosHoy,
            'ventas_mes'     => round($ventasMes, 2),
            'pedidos_mes'    => $pedidosMes,
            'ticket_prom'    => $pedidosMes > 0 ? round($ventasMes / $pedidosMes, 2) : 0.0,
            'abiertos'       => $abiertos,
            'prep_promedio'  => round($prepProm, 1),
            'llamadas'       => DB::int("SELECT COUNT(*) FROM waiter_calls WHERE restaurant_id=:r AND estado='pendiente'", ['r' => $rid]),
            'agotados'       => DB::int("SELECT COUNT(*) FROM products WHERE restaurant_id=:r AND agotado=1", ['r' => $rid]),
        ];
    }

    /** Ventas por dia en un rango. */
    public function ventasPorDia(string $desde, string $hasta): array
    {
        return DB::all(
            "SELECT DATE(creado) AS dia, COALESCE(SUM(total),0) AS total, COUNT(*) AS pedidos
             FROM orders WHERE restaurant_id=:r AND " . self::VENDIDOS . "
               AND creado BETWEEN :d AND :h
             GROUP BY DATE(creado) ORDER BY dia ASC",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
    }

    /** Ventas por hora (horas pico). */
    public function ventasPorHora(string $desde, string $hasta): array
    {
        $filas = DB::pairs(
            "SELECT HOUR(creado) AS h, COUNT(*) AS n FROM orders
             WHERE restaurant_id=:r AND " . self::VENDIDOS . " AND creado BETWEEN :d AND :hh
             GROUP BY HOUR(creado)",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'hh' => $hasta . ' 23:59:59']
        );
        $out = [];
        for ($h = 0; $h < 24; $h++) $out[$h] = (int)($filas[$h] ?? 0);
        return $out;
    }

    public function topProductos(string $desde, string $hasta, int $limite = 10, string $orden = 'DESC'): array
    {
        $orden = strtoupper($orden) === 'ASC' ? 'ASC' : 'DESC';
        return DB::all(
            "SELECT oi.nombre, SUM(oi.cantidad) AS unidades, SUM(oi.subtotal) AS total
             FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
             WHERE oi.restaurant_id=:r AND o." . self::VENDIDOS . " AND o.creado BETWEEN :d AND :h
             GROUP BY oi.nombre ORDER BY unidades {$orden} LIMIT " . (int)$limite,
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
    }

    public function ventasPorCategoria(string $desde, string $hasta): array
    {
        return DB::all(
            "SELECT COALESCE(c.nombre,'Sin categoría') AS categoria, SUM(oi.subtotal) AS total, SUM(oi.cantidad) AS unidades
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             LEFT JOIN products p ON p.id = oi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE oi.restaurant_id=:r AND o." . self::VENDIDOS . " AND o.creado BETWEEN :d AND :h
             GROUP BY categoria ORDER BY total DESC",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
    }

    public function ventasPorModo(string $desde, string $hasta): array
    {
        return DB::all(
            "SELECT modo, COUNT(*) AS pedidos, COALESCE(SUM(total),0) AS total
             FROM orders WHERE restaurant_id=:r AND " . self::VENDIDOS . " AND creado BETWEEN :d AND :h
             GROUP BY modo ORDER BY total DESC",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
    }

    public function ventasPorMesero(string $desde, string $hasta): array
    {
        return DB::all(
            "SELECT COALESCE(u.nombre,'Sin asignar') AS mesero, COUNT(*) AS pedidos,
                    COALESCE(SUM(o.total),0) AS total, COALESCE(AVG(o.total),0) AS ticket
             FROM orders o LEFT JOIN users u ON u.id = o.user_id
             WHERE o.restaurant_id=:r AND o." . self::VENDIDOS . " AND o.creado BETWEEN :d AND :h
             GROUP BY mesero ORDER BY total DESC",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
    }

    public function tiempoPreparacion(string $desde, string $hasta): array
    {
        $r = DB::one(
            "SELECT COALESCE(AVG(minutos_prep),0) AS promedio, COALESCE(MIN(minutos_prep),0) AS minimo,
                    COALESCE(MAX(minutos_prep),0) AS maximo, COUNT(*) AS n
             FROM orders WHERE restaurant_id=:r AND minutos_prep IS NOT NULL AND creado BETWEEN :d AND :h",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
        return $r ?: ['promedio' => 0, 'minimo' => 0, 'maximo' => 0, 'n' => 0];
    }

    public function totalRango(string $desde, string $hasta): array
    {
        $r = DB::one(
            "SELECT COUNT(*) AS pedidos, COALESCE(SUM(total),0) AS total, COALESCE(SUM(propina),0) AS propinas,
                    COALESCE(SUM(descuento),0) AS descuentos, COALESCE(SUM(impuesto),0) AS impuestos,
                    COALESCE(SUM(costo_envio),0) AS envios, COALESCE(AVG(total),0) AS ticket
             FROM orders WHERE restaurant_id=:r AND " . self::VENDIDOS . " AND creado BETWEEN :d AND :h",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
        return $r ?: ['pedidos' => 0, 'total' => 0, 'propinas' => 0, 'descuentos' => 0, 'impuestos' => 0, 'envios' => 0, 'ticket' => 0];
    }

    public function anulados(string $desde, string $hasta): array
    {
        return DB::all(
            "SELECT o.*, u.nombre AS usuario FROM orders o LEFT JOIN users u ON u.id = o.user_id
             WHERE o.restaurant_id=:r AND o.estado='anulado' AND o.creado BETWEEN :d AND :h
             ORDER BY o.creado DESC LIMIT 200",
            ['r' => $this->rid, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
    }
}
