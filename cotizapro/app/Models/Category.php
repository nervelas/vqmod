<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Category
{
    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM categories WHERE id = ? LIMIT 1', [$id]);
    }

    public static function bySlug(string $slug): ?array
    {
        return DB::one('SELECT * FROM categories WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS product_count
                FROM categories c' . ($onlyActive ? ' WHERE c.active = 1' : '') . '
                ORDER BY c.sort, c.name';
        return DB::all($sql);
    }

    /** Árbol padre => hijos. */
    public static function tree(bool $onlyActive = false): array
    {
        $rows = self::all($onlyActive);
        $byId = [];
        foreach ($rows as $r) {
            $r['children'] = [];
            $byId[(int) $r['id']] = $r;
        }
        $tree = [];
        foreach ($byId as $id => $r) {
            $pid = (int) ($r['parent_id'] ?? 0);
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$byId[$id];
            } else {
                $tree[] = &$byId[$id];
            }
        }
        // El conteo de cada rama incluye el de sus subcategorías.
        $roll = static function (array &$nodes) use (&$roll): int {
            $sum = 0;
            foreach ($nodes as &$n) {
                $n['own_count'] = (int) $n['product_count'];
                $n['product_count'] = $n['own_count'] + ($n['children'] ? $roll($n['children']) : 0);
                $sum += (int) $n['product_count'];
            }
            unset($n);
            return $sum;
        };
        $roll($tree);
        return $tree;
    }

    /** Ids de la categoría y todas sus descendientes. */
    public static function descendantIds(int $id): array
    {
        $all = DB::all('SELECT id, parent_id FROM categories');
        $children = [];
        foreach ($all as $r) {
            $children[(int) ($r['parent_id'] ?? 0)][] = (int) $r['id'];
        }
        $out = [$id];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($children[$cur] ?? [] as $c) {
                if (!in_array($c, $out, true)) {
                    $out[] = $c;
                    $stack[] = $c;
                }
            }
        }
        return $out;
    }

    public static function breadcrumb(int $id): array
    {
        $out = [];
        $guard = 0;
        while ($id && $guard++ < 10) {
            $c = self::find($id);
            if (!$c) {
                break;
            }
            array_unshift($out, $c);
            $id = (int) ($c['parent_id'] ?? 0);
        }
        return $out;
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM categories WHERE slug = ?' . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
            $p = $ignoreId ? [$slug, $ignoreId] : [$slug];
            if (!DB::one($sql, $p)) {
                return $slug;
            }
            $slug = slugify($base) . '-' . (++$i);
        }
    }

    /** Lista plana con sangría para <select>. */
    public static function options(?int $exclude = null): array
    {
        $tree = self::tree();
        $out = [];
        $walk = static function (array $nodes, int $depth) use (&$walk, &$out, $exclude): void {
            foreach ($nodes as $n) {
                if ($exclude && (int) $n['id'] === $exclude) {
                    continue;
                }
                $out[] = ['id' => (int) $n['id'], 'label' => str_repeat('— ', $depth) . $n['name']];
                if ($n['children']) {
                    $walk($n['children'], $depth + 1);
                }
            }
        };
        $walk($tree, 0);
        return $out;
    }
}
