<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Brand
{
    public static function all(int $companyId, bool $onlyActive = false): array
    {
        return DB::all(
            'SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.active = 1) AS product_count
             FROM brands b WHERE b.company_id = ?' . ($onlyActive ? ' AND b.active = 1' : '') . ' ORDER BY b.sort, b.name',
            [$companyId]
        );
    }

    public static function find(int $companyId, int $id): ?array
    {
        return DB::one('SELECT * FROM brands WHERE id = ? AND company_id = ? LIMIT 1', [$id, $companyId]);
    }

    public static function findOrCreate(int $companyId, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $slug = slugify($name);
        $row = DB::one('SELECT id FROM brands WHERE company_id = ? AND slug = ? LIMIT 1', [$companyId, $slug]);
        if ($row) {
            return (int) $row['id'];
        }
        return DB::insert('brands', ['company_id' => $companyId, 'name' => mb_substr($name, 0, 120), 'slug' => $slug, 'sort' => 0, 'active' => 1]);
    }
}
