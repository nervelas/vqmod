<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\DB;

/**
 * Carrito de COTIZACIÓN (no de compra) guardado en la sesión del visitante.
 */
final class Cart
{
    private static function &bag(): array
    {
        App::startSession();
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }

    public static function add(int $productId, float $qty = 1, string $note = ''): bool
    {
        $p = DB::one('SELECT id, min_qty FROM products WHERE id = ? AND active = 1 LIMIT 1', [$productId]);
        if (!$p) {
            return false;
        }
        $bag = &self::bag();
        $qty = max(0.01, min(999999, $qty));
        if (isset($bag[$productId])) {
            $bag[$productId]['qty'] = min(999999, (float) $bag[$productId]['qty'] + $qty);
            if ($note !== '') {
                $bag[$productId]['note'] = mb_substr($note, 0, 300);
            }
        } else {
            $bag[$productId] = ['qty' => max($qty, (float) $p['min_qty']), 'note' => mb_substr($note, 0, 300)];
        }
        return true;
    }

    public static function setQty(int $productId, float $qty): void
    {
        $bag = &self::bag();
        if (!isset($bag[$productId])) {
            return;
        }
        if ($qty <= 0) {
            unset($bag[$productId]);
            return;
        }
        $bag[$productId]['qty'] = min(999999, $qty);
    }

    public static function setNote(int $productId, string $note): void
    {
        $bag = &self::bag();
        if (isset($bag[$productId])) {
            $bag[$productId]['note'] = mb_substr($note, 0, 300);
        }
    }

    public static function remove(int $productId): void
    {
        $bag = &self::bag();
        unset($bag[$productId]);
    }

    public static function clear(): void
    {
        $bag = &self::bag();
        $bag = [];
    }

    public static function count(): int
    {
        return count(self::bag());
    }

    public static function raw(): array
    {
        return self::bag();
    }

    /** Líneas completas con datos frescos del catálogo. */
    public static function lines(): array
    {
        $bag = self::bag();
        if (!$bag) {
            return [];
        }
        $ids = array_map('intval', array_keys($bag));
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::all(
            "SELECT p.*, c.name AS category_name FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id IN ($in) AND p.active = 1",
            $ids
        );
        $rows = Product::attachImages($rows);
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $r['cart_qty']  = (float) ($bag[$id]['qty'] ?? 1);
            $r['cart_note'] = (string) ($bag[$id]['note'] ?? '');
            $out[] = $r;
        }
        // Limpia del carrito los productos que ya no existen o se desactivaron.
        $live = array_map(static fn ($r) => (int) $r['id'], $rows);
        foreach ($ids as $id) {
            if (!in_array($id, $live, true)) {
                self::remove($id);
            }
        }
        return $out;
    }
}
