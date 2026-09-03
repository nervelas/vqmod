<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class AttributeDef
{
    public static function all(): array
    {
        return DB::all(
            'SELECT a.*, c.name AS category_name FROM attribute_defs a
             LEFT JOIN categories c ON c.id = a.category_id
             ORDER BY a.sort, a.label'
        );
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM attribute_defs WHERE id = ? LIMIT 1', [$id]);
    }

    /** Atributos aplicables a una categoría (los globales + los de la rama). */
    public static function forCategory(?int $categoryId): array
    {
        if (!$categoryId) {
            return DB::all('SELECT * FROM attribute_defs WHERE category_id IS NULL ORDER BY sort, label');
        }
        $chain = array_map(static fn ($c) => (int) $c['id'], Category::breadcrumb($categoryId));
        if (!$chain) {
            $chain = [$categoryId];
        }
        $in = implode(',', array_fill(0, count($chain), '?'));
        return DB::all(
            "SELECT * FROM attribute_defs WHERE category_id IS NULL OR category_id IN ($in) ORDER BY sort, label",
            $chain
        );
    }

    public static function options(array $attr): array
    {
        $o = json_decode((string) ($attr['options'] ?? ''), true);
        if (is_array($o)) {
            return array_values(array_filter(array_map('strval', $o)));
        }
        $raw = trim((string) ($attr['options'] ?? ''));
        return $raw === '' ? [] : array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }

    /** Valores realmente presentes en el catálogo, para armar los filtros. */
    public static function facets(?int $categoryId = null): array
    {
        $defs = self::forCategory($categoryId);
        $out = [];
        foreach ($defs as $d) {
            if (!$d['filterable']) {
                continue;
            }
            $vals = DB::all(
                'SELECT pa.value, COUNT(*) AS n FROM product_attributes pa
                 JOIN products p ON p.id = pa.product_id AND p.active = 1
                 WHERE pa.attribute_id = ? AND pa.value <> ""
                 GROUP BY pa.value ORDER BY n DESC, pa.value LIMIT 25',
                [(int) $d['id']]
            );
            if (count($vals) > 1) {
                $out[] = ['def' => $d, 'values' => $vals];
            }
        }
        return $out;
    }
}
