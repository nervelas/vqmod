<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Product
{
    public static function find(int $companyId, int $id): ?array
    {
        return DB::one('SELECT * FROM products WHERE id = ? AND company_id = ? LIMIT 1', [$id, $companyId]);
    }

    public static function bySlug(int $companyId, string $slug): ?array
    {
        return DB::one(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, b.slug AS brand_slug
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id AND c.company_id = p.company_id
             LEFT JOIN brands b ON b.id = p.brand_id AND b.company_id = p.company_id
             WHERE p.slug = ? AND p.company_id = ? LIMIT 1',
            [$slug, $companyId]
        );
    }

    public static function byCode(int $companyId, string $code): ?array
    {
        return DB::one('SELECT * FROM products WHERE code = ? AND company_id = ? LIMIT 1', [$code, $companyId]);
    }

    public static function images(int $companyId, int $productId): array
    {
        return DB::all('SELECT * FROM product_images WHERE product_id = ? AND company_id = ? ORDER BY sort, id', [$productId, $companyId]);
    }

    public static function mainImage(int $companyId, int $productId): ?array
    {
        return DB::one('SELECT * FROM product_images WHERE product_id = ? AND company_id = ? ORDER BY sort, id LIMIT 1', [$productId, $companyId]);
    }

    public static function documents(int $companyId, int $productId): array
    {
        return DB::all('SELECT * FROM product_documents WHERE product_id = ? AND company_id = ? ORDER BY id', [$productId, $companyId]);
    }

    /** Atributos técnicos con su etiqueta y unidad. */
    public static function attributes(int $companyId, int $productId): array
    {
        return DB::all(
            'SELECT pa.value, a.code, a.label, a.unit, a.type, a.sort
             FROM product_attributes pa
             JOIN attribute_defs a ON a.id = pa.attribute_id AND a.company_id = pa.company_id
             WHERE pa.product_id = ? AND pa.company_id = ?
             ORDER BY a.sort, a.label',
            [$productId, $companyId]
        );
    }

    /** Adjunta la imagen principal a un listado de productos (1 sola consulta). */
    public static function attachImages(int $companyId, array $products): array
    {
        if (!$products) {
            return $products;
        }
        $ids = array_map(static fn ($p) => (int) $p['id'], $products);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::all(
            "SELECT product_id, path, path_webp, path_thumb, alt, blur, width, height
             FROM product_images WHERE company_id = ? AND product_id IN ($in) ORDER BY sort DESC, id DESC",
            array_merge([$companyId], $ids)
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['product_id']] = $r;   // el último iterado (menor sort) queda de principal
        }
        foreach ($products as &$p) {
            $p['image'] = $map[(int) $p['id']] ?? null;
        }
        return $products;
    }

    /**
     * Búsqueda y filtrado del catálogo (público y panel).
     * @return array{0:array<int,array<string,mixed>>,1:int}
     */
    public static function search(int $companyId, array $f = []): array
    {
        $joins      = '';
        $joinParams = [];
        $where      = ['p.company_id = ?'];
        $whereParams = [$companyId];

        // Filtros por atributo técnico (JOIN por cada atributo pedido).
        $n = 0;
        foreach ((array) ($f['attr'] ?? []) as $code => $val) {
            $val  = trim((string) $val);
            $code = trim((string) $code);
            if ($val === '' || $code === '' || $n >= 6) {
                continue;
            }
            $n++;
            $joins .= " JOIN product_attributes pa{$n} ON pa{$n}.product_id = p.id AND pa{$n}.company_id = p.company_id"
                    . " JOIN attribute_defs ad{$n} ON ad{$n}.id = pa{$n}.attribute_id AND ad{$n}.company_id = p.company_id AND ad{$n}.code = ?";
            $joinParams[] = $code;
            $where[] = "pa{$n}.value = ?";
            $whereParams[] = $val;
        }

        if (!empty($f['only_active'])) {
            $where[] = 'p.active = 1';
        }
        if (isset($f['active']) && $f['active'] !== '' && $f['active'] !== null) {
            $where[] = 'p.active = ?';
            $whereParams[] = (int) $f['active'];
        }
        if (!empty($f['category_id'])) {
            $ids = Category::descendantIds($companyId, (int) $f['category_id']);
            $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            foreach ($ids as $i) {
                $whereParams[] = $i;
            }
        }
        if (!empty($f['brand_id'])) {
            $where[] = 'p.brand_id = ?';
            $whereParams[] = (int) $f['brand_id'];
        }
        if (!empty($f['featured'])) {
            $where[] = 'p.featured = 1';
        }
        if (!empty($f['ids']) && is_array($f['ids'])) {
            $ids = array_values(array_unique(array_map('intval', $f['ids'])));
            $where[] = 'p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            foreach ($ids as $i) {
                $whereParams[] = $i;
            }
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
            $where[] = '(p.code LIKE ? OR p.name LIKE ? OR p.short_desc LIKE ? OR p.description LIKE ?)';
            array_push($whereParams, $like, $like, $like, $like);
        }

        $sqlWhere = implode(' AND ', $where);
        $params   = array_merge($joinParams, $whereParams);

        $total = (int) DB::value(
            "SELECT COUNT(DISTINCT p.id) FROM products p{$joins} WHERE {$sqlWhere}",
            $params,
            0
        );

        $order = match ((string) ($f['sort'] ?? '')) {
            'nombre'    => 'p.name ASC',
            'codigo'    => 'p.code ASC',
            'nuevos'    => 'p.created_at DESC, p.id DESC',
            'cotizados' => 'p.quote_count DESC, p.name ASC',
            'precio'    => 'p.price ASC, p.name ASC',
            'precio_d'  => 'p.price DESC, p.name ASC',
            default     => 'p.featured DESC, p.quote_count DESC, p.name ASC',
        };
        $limit  = max(1, min(200, (int) ($f['limit'] ?? 24)));
        $offset = max(0, (int) ($f['offset'] ?? 0));

        $rows = DB::all(
            "SELECT DISTINCT p.id, p.company_id, p.category_id, p.brand_id, p.code, p.name, p.slug,
                    p.short_desc, p.unit, p.price, p.price_visibility, p.min_qty, p.lead_time, p.stock_note,
                    p.featured, p.active, p.quote_count, p.views, p.created_at,
                    c.name AS category_name, c.slug AS category_slug, b.name AS brand_name
             FROM products p{$joins}
             LEFT JOIN categories c ON c.id = p.category_id AND c.company_id = p.company_id
             LEFT JOIN brands b ON b.id = p.brand_id AND b.company_id = p.company_id
             WHERE {$sqlWhere}
             ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return [self::attachImages($companyId, $rows), $total];
    }

    public static function related(int $companyId, array $product, int $limit = 4): array
    {
        $rows = DB::all(
            'SELECT p.* FROM products p
             WHERE p.company_id = ? AND p.active = 1 AND p.id <> ? AND p.category_id = ?
             ORDER BY p.featured DESC, p.quote_count DESC LIMIT ' . (int) $limit,
            [$companyId, (int) $product['id'], (int) ($product['category_id'] ?? 0)]
        );
        if (count($rows) < $limit) {
            $extra = DB::all(
                'SELECT p.* FROM products p WHERE p.company_id = ? AND p.active = 1 AND p.id <> ?
                 ORDER BY p.quote_count DESC LIMIT ' . (int) $limit,
                [$companyId, (int) $product['id']]
            );
            $seen = array_column($rows, 'id');
            foreach ($extra as $e) {
                if (count($rows) >= $limit) {
                    break;
                }
                if (!in_array($e['id'], $seen, true)) {
                    $rows[] = $e;
                }
            }
        }
        return self::attachImages($companyId, $rows);
    }

    /** ¿Se muestra el precio de este producto al visitante? */
    public static function priceVisible(array $company, array $product, bool $isCustomer = false): bool
    {
        $mode = (string) ($product['price_visibility'] ?? 'heredar');
        if ($mode === 'heredar') {
            $mode = (string) ($company['price_visibility'] ?? 'oculto');
        }
        return match ($mode) {
            'publico'  => true,
            'clientes' => $isCustomer,
            default    => false,
        };
    }

    public static function uniqueSlug(int $companyId, string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM products WHERE company_id = ? AND slug = ?' . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
            $p = $ignoreId ? [$companyId, $slug, $ignoreId] : [$companyId, $slug];
            if (!DB::one($sql, $p)) {
                return $slug;
            }
            $slug = slugify($base) . '-' . (++$i);
        }
    }

    public static function uniqueCode(int $companyId, string $base, ?int $ignoreId = null): string
    {
        $code = mb_substr(trim($base), 0, 55);
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM products WHERE company_id = ? AND code = ?' . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
            $p = $ignoreId ? [$companyId, $code, $ignoreId] : [$companyId, $code];
            if (!DB::one($sql, $p)) {
                return $code;
            }
            $code = mb_substr(trim($base), 0, 50) . '-' . (++$i);
        }
    }

    /** Precio para un cliente según su lista de precios. */
    public static function priceFor(int $companyId, int $productId, ?int $priceListId): float
    {
        $base = (float) DB::value('SELECT price FROM products WHERE id = ? AND company_id = ?', [$productId, $companyId], 0);
        if (!$priceListId) {
            return $base;
        }
        $custom = DB::value('SELECT price FROM product_prices WHERE product_id = ? AND price_list_id = ? AND company_id = ?', [$productId, $priceListId, $companyId]);
        if ($custom !== null && (float) $custom > 0) {
            return (float) $custom;
        }
        $pct = (float) DB::value('SELECT discount_pct FROM price_lists WHERE id = ? AND company_id = ?', [$priceListId, $companyId], 0);
        return round($base * (1 - $pct / 100), 2);
    }
}
