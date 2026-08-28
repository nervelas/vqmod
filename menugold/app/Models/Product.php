<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;

class Product extends Model
{
    protected string $table = 'products';
    protected array $jsonFields = ['imagenes','combo_items'];
    protected array $fillable = [
        'restaurant_id','category_id','nombre','nombre_en','descripcion','descripcion_en','precio','precio_promo',
        'costo','imagen','imagenes','sku','orden','activo','agotado','destacado','tiempo_prep','calorias',
        'etiquetas','alergenos','estacion','hora_inicio','hora_fin','dias','es_combo','combo_items','actualizado',
    ];

    public const ETIQUETAS = [
        'nuevo'       => ['Nuevo', 'sparkles'],
        'popular'     => ['Popular', 'fire'],
        'picante'     => ['Picante', 'fire'],
        'vegano'      => ['Vegano', 'leaf'],
        'vegetariano' => ['Vegetariano', 'leaf'],
        'sin_gluten'  => ['Sin gluten', 'wheat'],
        'recomendado' => ['Recomendado', 'crown'],
    ];

    public const ALERGENOS = ['gluten','lacteos','huevo','pescado','mariscos','frutos secos','mani','soya','apio','mostaza','sesamo','sulfitos'];

    /** Menu publico completo agrupado por categoria. */
    public function menuPublico(int $rid): array
    {
        return DB::all(
            'SELECT * FROM products
             WHERE restaurant_id = :r AND activo = 1
             ORDER BY orden ASC, id ASC',
            ['r' => $rid]
        );
    }

    public function porCategoria(int $categoryId): array
    {
        return $this->where('category_id = :c', ['c' => $categoryId], 'orden ASC, id ASC');
    }

    public function destacados(int $limite = 6): array
    {
        return $this->where('activo=1 AND destacado=1 AND agotado=0', [], 'orden ASC', $limite);
    }

    /** Busqueda para el panel. */
    public function buscar(string $q, int $categoria = 0, string $filtro = '', int $limite = 500): array
    {
        $where = '1=1';
        $p = [];
        if ($q !== '') {
            $where .= ' AND (nombre LIKE :q OR descripcion LIKE :q2 OR sku LIKE :q3)';
            $p['q'] = '%' . $q . '%'; $p['q2'] = '%' . $q . '%'; $p['q3'] = '%' . $q . '%';
        }
        if ($categoria > 0) { $where .= ' AND category_id = :c'; $p['c'] = $categoria; }
        if ($filtro === 'agotados')  $where .= ' AND agotado = 1';
        if ($filtro === 'inactivos') $where .= ' AND activo = 0';
        if ($filtro === 'destacados')$where .= ' AND destacado = 1';
        return $this->where($where, $p, 'orden ASC, id ASC', $limite);
    }

    public static function disponibleAhora(array $p): bool
    {
        if ((int)($p['activo'] ?? 1) !== 1) return false;
        $dias = trim((string)($p['dias'] ?? ''));
        if ($dias !== '' && !in_array((string)date('w'), array_map('trim', explode(',', $dias)), true)) return false;
        $ini = $p['hora_inicio'] ?? null;
        $fin = $p['hora_fin'] ?? null;
        if (!$ini && !$fin) return true;
        $ahora = date('H:i:s');
        if ($ini && $fin) {
            return $fin > $ini ? ($ahora >= $ini && $ahora <= $fin) : ($ahora >= $ini || $ahora <= $fin);
        }
        if ($ini) return $ahora >= $ini;
        return $ahora <= $fin;
    }

    /** Precio vigente considerando promocion propia. */
    public static function precioVigente(array $p): float
    {
        $promo = $p['precio_promo'] ?? null;
        if ($promo !== null && $promo !== '' && (float)$promo > 0 && (float)$promo < (float)$p['precio']) {
            return round((float)$promo, 2);
        }
        return round((float)$p['precio'], 2);
    }

    public static function tieneDescuento(array $p): bool
    {
        return self::precioVigente($p) < (float)$p['precio'];
    }

    public static function etiquetasArray(array $p): array
    {
        $t = array_filter(array_map('trim', explode(',', (string)($p['etiquetas'] ?? ''))));
        return array_values(array_intersect($t, array_keys(self::ETIQUETAS)));
    }

    public static function alergenosArray(array $p): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string)($p['alergenos'] ?? '')))));
    }

    /** Grupos de modificadores asociados, con sus opciones. */
    public function modificadores(int $productId): array
    {
        $grupos = DB::all(
            'SELECT g.* FROM modifier_groups g
             INNER JOIN product_modifiers pm ON pm.group_id = g.id
             WHERE pm.product_id = :p AND g.activo = 1
             ORDER BY pm.orden ASC, g.orden ASC',
            ['p' => $productId]
        );
        if (!$grupos) return [];
        $ids = array_column($grupos, 'id');
        [$ph, $par] = DB::inList($ids, 'g');
        $ops = DB::all(
            "SELECT * FROM modifier_options WHERE group_id IN ({$ph}) AND activo = 1 ORDER BY orden ASC, id ASC",
            $par
        );
        $porGrupo = [];
        foreach ($ops as $o) $porGrupo[(int)$o['group_id']][] = $o;
        foreach ($grupos as &$g) {
            $g['opciones'] = $porGrupo[(int)$g['id']] ?? [];
        }
        unset($g);
        return array_values(array_filter($grupos, static fn($g) => !empty($g['opciones'])));
    }

    /** Asocia grupos de modificadores a un producto. */
    public function setModificadores(int $productId, array $groupIds): void
    {
        DB::delete('product_modifiers', 'product_id = :p', ['p' => $productId]);
        $orden = 0;
        foreach ($groupIds as $g) {
            $g = (int)$g;
            if ($g <= 0) continue;
            $existe = DB::int('SELECT COUNT(*) FROM modifier_groups WHERE id=:g AND restaurant_id=:r',
                ['g' => $g, 'r' => $this->rid()]);
            if (!$existe) continue;
            DB::insert('product_modifiers', ['product_id' => $productId, 'group_id' => $g, 'orden' => $orden++]);
        }
    }

    public function duplicar(int $id): int
    {
        $p = $this->findOrFail($id);
        $nuevo = $p;
        unset($nuevo['id'], $nuevo['creado'], $nuevo['actualizado']);
        $nuevo['nombre'] = mb_substr($p['nombre'] . ' (copia)', 0, 160);
        $nuevo['sku'] = '';
        $nuevo['vendidos'] = 0;
        $nuevo['orden'] = $this->maxOrder() + 1;
        $nuevoId = $this->create($nuevo);
        foreach (DB::all('SELECT group_id, orden FROM product_modifiers WHERE product_id=:p', ['p' => $id]) as $pm) {
            DB::insert('product_modifiers', ['product_id' => $nuevoId, 'group_id' => (int)$pm['group_id'], 'orden' => (int)$pm['orden']]);
        }
        return $nuevoId;
    }
}
