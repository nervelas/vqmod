<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Brand
{
    public static function all(bool $onlyActive = false): array
    {
        return DB::all(
            'SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.active = 1) AS product_count
             FROM brands b' . ($onlyActive ? ' WHERE b.active = 1' : '') . ' ORDER BY b.sort, b.name'
        );
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM brands WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findOrCreate(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $slug = slugify($name);
        $row = DB::one('SELECT id FROM brands WHERE slug = ? LIMIT 1', [$slug]);
        if ($row) {
            return (int) $row['id'];
        }
        return DB::insert('brands', ['name' => mb_substr($name, 0, 120), 'slug' => $slug, 'sort' => 0, 'active' => 1]);
    }
}
