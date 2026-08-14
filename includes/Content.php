<?php
/**
 * Content model — pages, sections, menu, platforms, gallery.
 */

declare(strict_types=1);

class Content
{
    /** Fetch an active page by slug. */
    public static function page(string $slug): ?array
    {
        return Database::first(
            'SELECT * FROM pages WHERE slug = ? AND is_active = 1 LIMIT 1',
            [$slug]
        );
    }

    /** Fetch a page regardless of active state (admin use). */
    public static function pageAny(string $slug): ?array
    {
        return Database::first('SELECT * FROM pages WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function pageById(int $id): ?array
    {
        return Database::first('SELECT * FROM pages WHERE id = ? LIMIT 1', [$id]);
    }

    /** Active sections for a page, ordered. */
    public static function sections(int $pageId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM sections WHERE page_id = ?';
        if ($activeOnly) { $sql .= ' AND is_active = 1'; }
        $sql .= ' ORDER BY sort ASC, id ASC';
        return Database::all($sql, [$pageId]);
    }

    /** Index sections by block_key for easy template access. */
    public static function sectionMap(array $sections): array
    {
        $map = [];
        foreach ($sections as $s) {
            $map[$s['block_key']] = $s;
        }
        return $map;
    }

    /** Active top-level menu items. */
    public static function menu(): array
    {
        return Database::all(
            'SELECT * FROM menu_items WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort ASC, id ASC'
        );
    }

    /** Active platforms / quick access. */
    public static function platforms(): array
    {
        return Database::all(
            'SELECT * FROM platforms WHERE is_active = 1 ORDER BY sort ASC, id ASC'
        );
    }

    /** Active albums. */
    public static function albums(int $limit = 0): array
    {
        $sql = 'SELECT a.*, (SELECT COUNT(*) FROM photos ph WHERE ph.album_id = a.id) AS photo_count
                FROM albums a WHERE a.is_active = 1 ORDER BY a.sort ASC, a.event_date DESC, a.id DESC';
        if ($limit > 0) { $sql .= ' LIMIT ' . (int)$limit; }
        return Database::all($sql);
    }

    public static function album(string $slug): ?array
    {
        return Database::first('SELECT * FROM albums WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    }

    public static function photos(int $albumId): array
    {
        return Database::all('SELECT * FROM photos WHERE album_id = ? ORDER BY sort ASC, id ASC', [$albumId]);
    }

    /** Resolve a section/menu URL to a full URL (internal slug or external). */
    public static function url(string $target): string
    {
        if ($target === '' ) { return '#'; }
        if (preg_match('~^(https?://|mailto:|tel:|#)~i', $target)) { return $target; }
        return base_url($target);
    }
}
