<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Lang;
use MenuGold\Core\Str;

/**
 * Lectura del menú público: categorías y productos ya filtrados por
 * disponibilidad de horario y día, con promociones aplicadas.
 */
final class Menu
{
    /** Máscara de bits del día actual (1 = domingo … 64 = sábado). */
    public static function todayMask($timezone = 'America/Guatemala')
    {
        $now = new \DateTime('now', new \DateTimeZone($timezone));
        return 1 << (int)$now->format('w');
    }

    private static function availableNow(array $row, $timeNow, $mask)
    {
        if (((int)$row['days_mask'] & $mask) === 0) { return false; }
        $from = isset($row['available_from']) ? $row['available_from'] : null;
        $to   = isset($row['available_to']) ? $row['available_to'] : null;
        if (empty($from) || empty($to)) { return true; }
        return ($to > $from) ? ($timeNow >= $from && $timeNow < $to) : ($timeNow >= $from || $timeNow < $to);
    }

    /**
     * Estructura completa del menú para la vista pública.
     * @return array<int,array> categorías con clave "products"
     */
    public static function tree($includeUnavailable = false)
    {
        $tz  = Settings::get('timezone', 'America/Guatemala');
        $now = new \DateTime('now', new \DateTimeZone($tz));
        $timeNow = $now->format('H:i:s');
        $mask = 1 << (int)$now->format('w');

        $cats  = DB::all('SELECT * FROM mg_categories WHERE is_active = 1 ORDER BY sort ASC, id ASC');
        $prods = DB::all('SELECT * FROM mg_products WHERE is_active = 1 ORDER BY sort ASC, id ASC');
        $promos = Promotion::activeNow();

        $byCat = array();
        foreach ($prods as $p) {
            if (!$includeUnavailable && !self::availableNow($p, $timeNow, $mask)) { continue; }
            $p['tags_list']    = array_values(array_filter(array_map('trim', explode(',', (string)$p['tags']))));
            $p['final_price']  = Promotion::apply((float)$p['price'], $p, $promos);
            $p['has_discount'] = $p['final_price'] < (float)$p['price'] - 0.001;
            $p['label']        = Lang::field($p, 'name');
            $p['blurb']        = Str::limit(Lang::field($p, 'description'), 92);
            $byCat[(int)$p['category_id']][] = $p;
        }

        $out = array();
        foreach ($cats as $c) {
            if (!$includeUnavailable && !self::availableNow($c, $timeNow, $mask)) { continue; }
            $cid = (int)$c['id'];
            if (empty($byCat[$cid])) { continue; }
            $c['label']    = Lang::field($c, 'name');
            $c['blurb']    = Lang::field($c, 'description');
            $c['anchor']   = 'cat-' . $cid;
            $c['products'] = $byCat[$cid];
            $out[] = $c;
        }
        return $out;
    }

    /** Producto público con imágenes, variantes y grupos de modificadores. */
    public static function product($productId)
    {
        $p = DB::first('SELECT * FROM mg_products WHERE id = :id AND is_active = 1 LIMIT 1', array('id' => (int)$productId));
        if (!$p) { return null; }
        $p['tags_list'] = array_values(array_filter(array_map('trim', explode(',', (string)$p['tags']))));
        $p['label'] = Lang::field($p, 'name');
        $p['about'] = Lang::field($p, 'description');
        $p['images'] = DB::column('SELECT image FROM mg_product_images WHERE product_id = :p ORDER BY sort, id', array('p' => (int)$p['id']));
        if ($p['image'] !== '') { array_unshift($p['images'], $p['image']); }
        $p['images'] = array_values(array_unique($p['images']));
        $p['variants'] = DB::all('SELECT * FROM mg_variants WHERE product_id = :p ORDER BY sort, id', array('p' => (int)$p['id']));
        $p['groups'] = self::modifierGroups((int)$p['id']);
        $promos = Promotion::activeNow();
        $p['final_price'] = Promotion::apply((float)$p['price'], $p, $promos);
        $p['has_discount'] = $p['final_price'] < (float)$p['price'] - 0.001;
        $cat = DB::first('SELECT name, name_en FROM mg_categories WHERE id = :c', array('c' => (int)$p['category_id']));
        $p['category_label'] = $cat ? Lang::field($cat, 'name') : '';
        return $p;
    }

    public static function modifierGroups($productId)
    {
        $groups = DB::all(
            'SELECT g.* FROM mg_modifier_groups g
             INNER JOIN mg_product_modifier_groups pmg ON pmg.group_id = g.id
             WHERE pmg.product_id = :p ORDER BY pmg.sort, g.sort, g.id',
            array('p' => (int)$productId)
        );
        foreach ($groups as $i => $g) {
            $groups[$i]['label'] = Lang::field($g, 'name');
            $opts = DB::all('SELECT * FROM mg_modifier_options WHERE group_id = :g AND is_active = 1 ORDER BY sort, id', array('g' => (int)$g['id']));
            foreach ($opts as $j => $o) { $opts[$j]['label'] = $o['name']; }
            $groups[$i]['options'] = $opts;
        }
        return $groups;
    }

    /** Destacados para la portada del menú. */
    public static function featured($limit = 8)
    {
        $limit = max(1, min(24, (int)$limit));
        return DB::all(
            'SELECT p.*, c.name AS category_name FROM mg_products p
             LEFT JOIN mg_categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.is_featured = 1
             ORDER BY p.sort, p.id LIMIT ' . $limit
        );
    }

    public static function search($term, $limit = 30)
    {
        $limit = max(1, min(60, (int)$limit));
        $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), (string)$term) . '%';
        return DB::all(
            'SELECT p.*, c.name AS category_name FROM mg_products p
             LEFT JOIN mg_categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND (p.name LIKE :q OR p.name_en LIKE :q2 OR p.description LIKE :q3)
             ORDER BY p.is_featured DESC, p.sort LIMIT ' . $limit,
            array('q' => $like, 'q2' => $like, 'q3' => $like)
        );
    }
}
