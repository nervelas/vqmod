<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;

class Modifier extends Model
{
    protected string $table = 'modifier_groups';
    protected array $fillable = [
        'restaurant_id','nombre','nombre_en','tipo','obligatorio','min_sel','max_sel','orden','activo',
    ];

    /** Grupos con sus opciones. */
    public function conOpciones(): array
    {
        $grupos = $this->all('orden ASC, id ASC');
        if (!$grupos) return [];
        [$ph, $par] = DB::inList(array_column($grupos, 'id'), 'g');
        $ops = DB::all("SELECT * FROM modifier_options WHERE group_id IN ({$ph}) ORDER BY orden ASC, id ASC", $par);
        $map = [];
        foreach ($ops as $o) $map[(int)$o['group_id']][] = $o;
        foreach ($grupos as &$g) $g['opciones'] = $map[(int)$g['id']] ?? [];
        unset($g);
        return $grupos;
    }

    public function opciones(int $groupId): array
    {
        return DB::all(
            'SELECT o.* FROM modifier_options o
             INNER JOIN modifier_groups g ON g.id = o.group_id
             WHERE o.group_id = :g AND g.restaurant_id = :r ORDER BY o.orden ASC, o.id ASC',
            ['g' => $groupId, 'r' => $this->rid()]
        );
    }

    public function crearOpcion(int $groupId, array $data): int
    {
        $this->findOrFail($groupId);
        return DB::insert('modifier_options', [
            'group_id'      => $groupId,
            'restaurant_id' => $this->rid(),
            'nombre'        => mb_substr((string)($data['nombre'] ?? ''), 0, 120),
            'nombre_en'     => mb_substr((string)($data['nombre_en'] ?? ''), 0, 120),
            'precio_extra'  => round((float)($data['precio_extra'] ?? 0), 2),
            'orden'         => (int)($data['orden'] ?? 0),
            'activo'        => !empty($data['activo']) ? 1 : 0,
            'agotado'       => !empty($data['agotado']) ? 1 : 0,
            'predeterminado'=> !empty($data['predeterminado']) ? 1 : 0,
        ]);
    }

    public function actualizarOpcion(int $optionId, array $data): int
    {
        return DB::update('modifier_options', [
            'nombre'        => mb_substr((string)($data['nombre'] ?? ''), 0, 120),
            'nombre_en'     => mb_substr((string)($data['nombre_en'] ?? ''), 0, 120),
            'precio_extra'  => round((float)($data['precio_extra'] ?? 0), 2),
            'orden'         => (int)($data['orden'] ?? 0),
            'activo'        => !empty($data['activo']) ? 1 : 0,
            'agotado'       => !empty($data['agotado']) ? 1 : 0,
            'predeterminado'=> !empty($data['predeterminado']) ? 1 : 0,
        ], 'id = :o AND restaurant_id = :r', ['o' => $optionId, 'r' => $this->rid()]);
    }

    public function borrarOpcion(int $optionId): int
    {
        return DB::delete('modifier_options', 'id = :o AND restaurant_id = :r',
            ['o' => $optionId, 'r' => $this->rid()]);
    }

    /** Productos que usan este grupo. */
    public function usadoPor(int $groupId): int
    {
        return DB::int('SELECT COUNT(*) FROM product_modifiers WHERE group_id = :g', ['g' => $groupId]);
    }
}
