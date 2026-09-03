<?php
namespace MenuGold\Core;

/**
 * Estado de la base de datos.
 *
 * Existe por un fallo real: si `config/config.php` está pero las tablas `mg_`
 * no (una base distinta, una instalación a medias, un restore incompleto), la
 * aplicación se creía instalada y reventaba con un 500 en cada página. Peor:
 * el instalador redirigía al panel, así que no había manera de salir.
 *
 * Con esto se distingue "no instalado" de "instalado a medias" y siempre queda
 * un camino de vuelta.
 */
final class Schema
{
    /** Las tablas que la aplicación necesita para arrancar. */
    public static function requeridas()
    {
        return array(
            'mg_settings', 'mg_hours', 'mg_users', 'mg_categories', 'mg_products',
            'mg_product_images', 'mg_variants', 'mg_modifier_groups', 'mg_modifier_options',
            'mg_product_modifier_groups', 'mg_combos', 'mg_combo_items', 'mg_promotions',
            'mg_coupons', 'mg_tables', 'mg_delivery_zones', 'mg_customers', 'mg_orders',
            'mg_order_items', 'mg_order_item_modifiers', 'mg_service_calls',
            'mg_audit_log', 'mg_rate_limits',
        );
    }

    /**
     * Tablas que faltan. Devuelve null si ni siquiera se pudo consultar
     * (sin conexión), que es un problema distinto y se informa aparte.
     *
     * @param \PDO|null $pdo conexión ya abierta; si no, se usa la de la app
     * @return array<string>|null
     */
    public static function faltantes($pdo = null)
    {
        try {
            if ($pdo === null) { $pdo = DB::pdo(); }
            $stmt = $pdo->query("SHOW TABLES LIKE 'mg\\_%'");
            $hay = array();
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $fila) {
                $hay[strtolower($fila[0])] = true;
            }
        } catch (\Throwable $e) {
            return null;
        }
        $faltan = array();
        foreach (self::requeridas() as $t) {
            if (!isset($hay[$t])) { $faltan[] = $t; }
        }
        return $faltan;
    }

    /** ¿Están todas las tablas? */
    public static function completo($pdo = null)
    {
        $f = self::faltantes($pdo);
        return is_array($f) && count($f) === 0;
    }

    /**
     * ¿La excepción viene de una tabla o columna que no existe?
     * Es la firma de una base de datos a medio instalar.
     */
    public static function esFaltaDeTabla(\Throwable $e)
    {
        if (!($e instanceof \PDOException)) { return false; }
        $codigo = (string)$e->getCode();
        // 42S02 tabla no encontrada · 42S22 columna no encontrada
        return $codigo === '42S02' || $codigo === '42S22';
    }
}
