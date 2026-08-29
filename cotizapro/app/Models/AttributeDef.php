<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class AttributeDef
{
    public static function all(int $companyId): array
    {
        return DB::all(
            'SELECT a.*, c.name AS category_name FROM attribute_defs a
             LEFT JOIN categories c ON c.id = a.category_id AND c.company_id = a.company_id
             WHERE a.company_id = ? ORDER BY a.sort, a.label',
            [$companyId]
        );
    }

    public static function find(int $companyId, int $id): ?array
    {
        return DB::one('SELECT * FROM attribute_defs WHERE id = ? AND company_id = ? LIMIT 1', [$id, $companyId]);
    }

    /** Atributos aplicables a una categoría (los globales + los de la rama). */
    public static function forCategory(int $companyId, ?int $categoryId): array
    {
        if (!$categoryId) {
            return DB::all('SELECT * FROM attribute_defs WHERE company_id = ? AND category_id IS NULL ORDER BY sort, label', [$companyId]);
        }
        $chain = array_map(static fn ($c) => (int) $c['id'], Category::breadcrumb($companyId, $categoryId));
        if (!$chain) {
            $chain = [$categoryId];
        }
        $in = implode(',', array_fill(0, count($chain), '?'));
        return DB::all(
            "SELECT * FROM attribute_defs WHERE company_id = ? AND (category_id IS NULL OR category_id IN ($in)) ORDER BY sort, label",
            array_merge([$companyId], $chain)
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
    public static function facets(int $companyId, ?int $categoryId = null): array
    {
        $defs = self::forCategory($companyId, $categoryId);
        $out = [];
        foreach ($defs as $d) {
            if (!$d['filterable']) {
                continue;
            }
            $vals = DB::all(
                'SELECT pa.value, COUNT(*) AS n FROM product_attributes pa
                 JOIN products p ON p.id = pa.product_id AND p.company_id = pa.company_id AND p.active = 1
                 WHERE pa.company_id = ? AND pa.attribute_id = ? AND pa.value <> ""
                 GROUP BY pa.value ORDER BY n DESC, pa.value LIMIT 25',
                [$companyId, (int) $d['id']]
            );
            if (count($vals) > 1) {
                $out[] = ['def' => $d, 'values' => $vals];
            }
        }
        return $out;
    }
}
