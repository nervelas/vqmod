<?php
declare(strict_types=1);

/** Lectura de contenido publicado para el sitio publico. */
final class Content
{
    private static array $cache = [];

    private static function cached(string $key, callable $fn): mixed
    {
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = $fn();
        }
        return self::$cache[$key];
    }

    /** @return list<array<string,mixed>> */
    public static function slides(): array
    {
        return self::cached('slides', static fn() => Database::all(
            'SELECT * FROM slides WHERE status = 1 ORDER BY sort_order ASC, id ASC'
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function services(?int $limit = null, bool $featuredOnly = false): array
    {
        $sql = 'SELECT * FROM services WHERE status = 1';
        if ($featuredOnly) {
            $sql .= ' AND featured = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return self::cached('svc' . ($limit ?? 0) . ($featuredOnly ? 'f' : ''), static fn() => Database::all($sql));
    }

    public static function service(string $slug): ?array
    {
        return Database::first('SELECT * FROM services WHERE slug = :s AND status = 1 LIMIT 1', ['s' => $slug]);
    }

    /** @return list<array<string,mixed>> */
    public static function menu(): array
    {
        return self::cached('menu', static fn() => Database::all(
            'SELECT * FROM menu_items WHERE status = 1 ORDER BY sort_order ASC, id ASC'
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function stats(): array
    {
        return self::cached('stats', static fn() => Database::all(
            'SELECT * FROM stats WHERE status = 1 ORDER BY sort_order ASC, id ASC'
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function steps(): array
    {
        return self::cached('steps', static fn() => Database::all(
            'SELECT * FROM process_steps WHERE status = 1 ORDER BY sort_order ASC, id ASC'
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function projects(?int $limit = null): array
    {
        $sql = 'SELECT * FROM projects WHERE status = 1 ORDER BY sort_order ASC, id ASC';
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return self::cached('proj' . ($limit ?? 0), static fn() => Database::all($sql));
    }

    /** @return list<array<string,mixed>> */
    public static function testimonials(?int $limit = null): array
    {
        $sql = 'SELECT * FROM testimonials WHERE status = 1 ORDER BY sort_order ASC, id ASC';
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return self::cached('test' . ($limit ?? 0), static fn() => Database::all($sql));
    }

    /** @return list<array<string,mixed>> */
    public static function faqs(?int $limit = null): array
    {
        $sql = 'SELECT * FROM faqs WHERE status = 1 ORDER BY sort_order ASC, id ASC';
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return self::cached('faq' . ($limit ?? 0), static fn() => Database::all($sql));
    }

    /** @return list<array<string,mixed>> */
    public static function plans(): array
    {
        return self::cached('plans', static fn() => Database::all(
            'SELECT * FROM plans WHERE status = 1 ORDER BY sort_order ASC, id ASC'
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function posts(?int $limit = null, int $offset = 0): array
    {
        $sql = 'SELECT * FROM posts WHERE status = 1 ORDER BY published_at DESC, id DESC';
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
        }
        return Database::all($sql);
    }

    public static function postsCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM posts WHERE status = 1', [], 0);
    }

    public static function post(string $slug): ?array
    {
        return Database::first('SELECT * FROM posts WHERE slug = :s AND status = 1 LIMIT 1', ['s' => $slug]);
    }

    public static function page(string $slug): ?array
    {
        return Database::first('SELECT * FROM pages WHERE slug = :s AND status = 1 LIMIT 1', ['s' => $slug]);
    }

    /** @return list<array<string,mixed>> */
    public static function pages(): array
    {
        return self::cached('pages', static fn() => Database::all(
            'SELECT * FROM pages WHERE status = 1 ORDER BY sort_order ASC, id ASC'
        ));
    }

    /** Bloques de contenido de una seccion identificada por clave. */
    public static function block(string $key): array
    {
        $row = Database::first('SELECT * FROM blocks WHERE `key` = :k LIMIT 1', ['k' => $key]);
        return $row ?? [];
    }

    /** @return array<string,array<string,mixed>> */
    public static function blocks(): array
    {
        return self::cached('blocks', static function () {
            $out = [];
            foreach (Database::all('SELECT * FROM blocks ORDER BY sort_order ASC, id ASC') as $b) {
                $out[(string) $b['key']] = $b;
            }
            return $out;
        });
    }

    public static function b(string $key, string $field, string $default = ''): string
    {
        $blocks = self::blocks();
        $val = isset($blocks[$key]) ? (string) ($blocks[$key][$field] ?? '') : '';
        return $val === '' ? $default : $val;
    }

    public static function blockEnabled(string $key): bool
    {
        $blocks = self::blocks();
        return !isset($blocks[$key]) || (int) ($blocks[$key]['status'] ?? 1) === 1;
    }
}
