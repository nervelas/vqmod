<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\DB;

/**
 * Carrito de COTIZACIÓN (no de compra) guardado en sesión, aislado por empresa.
 */
final class Cart
{
    private static function &bag(int $companyId): array
    {
        App::startSession();
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['cart'][$companyId]) || !is_array($_SESSION['cart'][$companyId])) {
            $_SESSION['cart'][$companyId] = [];
        }
        return $_SESSION['cart'][$companyId];
    }

    public static function add(int $companyId, int $productId, float $qty = 1, string $note = ''): bool
    {
        $p = DB::one('SELECT id, min_qty FROM products WHERE id = ? AND company_id = ? AND active = 1 LIMIT 1', [$productId, $companyId]);
        if (!$p) {
            return false;
        }
        $bag = &self::bag($companyId);
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

    public static function setQty(int $companyId, int $productId, float $qty): void
    {
        $bag = &self::bag($companyId);
        if (!isset($bag[$productId])) {
            return;
        }
        if ($qty <= 0) {
            unset($bag[$productId]);
            return;
        }
        $bag[$productId]['qty'] = min(999999, $qty);
    }

    public static function setNote(int $companyId, int $productId, string $note): void
    {
        $bag = &self::bag($companyId);
        if (isset($bag[$productId])) {
            $bag[$productId]['note'] = mb_substr($note, 0, 300);
        }
    }

    public static function remove(int $companyId, int $productId): void
    {
        $bag = &self::bag($companyId);
        unset($bag[$productId]);
    }

    public static function clear(int $companyId): void
    {
        $bag = &self::bag($companyId);
        $bag = [];
    }

    public static function count(int $companyId): int
    {
        return count(self::bag($companyId));
    }

    public static function raw(int $companyId): array
    {
        return self::bag($companyId);
    }

    /** Líneas completas con datos frescos del catálogo. */
    public static function lines(int $companyId): array
    {
        $bag = self::bag($companyId);
        if (!$bag) {
            return [];
        }
        $ids = array_map('intval', array_keys($bag));
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::all(
            "SELECT p.*, c.name AS category_name FROM products p
             LEFT JOIN categories c ON c.id = p.category_id AND c.company_id = p.company_id
             WHERE p.company_id = ? AND p.id IN ($in) AND p.active = 1",
            array_merge([$companyId], $ids)
        );
        $rows = Product::attachImages($companyId, $rows);
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
                self::remove($companyId, $id);
            }
        }
        return $out;
    }
}
