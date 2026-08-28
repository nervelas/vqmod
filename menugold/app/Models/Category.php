<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;

class Category extends Model
{
    protected string $table = 'categories';
    protected array $fillable = [
        'restaurant_id','nombre','nombre_en','descripcion','descripcion_en','imagen','icono',
        'orden','activo','hora_inicio','hora_fin','dias','actualizado',
    ];

    /** Categorias visibles ahora mismo (respetando horario y dias). */
    public function disponibles(): array
    {
        $out = [];
        foreach ($this->where('activo=1', [], 'orden ASC, id ASC') as $c) {
            if (self::disponibleAhora($c)) $out[] = $c;
        }
        return $out;
    }

    public static function disponibleAhora(array $c): bool
    {
        $dias = trim((string)($c['dias'] ?? ''));
        if ($dias !== '' && !in_array((string)date('w'), array_map('trim', explode(',', $dias)), true)) {
            return false;
        }
        $ini = $c['hora_inicio'] ?? null;
        $fin = $c['hora_fin'] ?? null;
        if (!$ini && !$fin) return true;
        $ahora = date('H:i:s');
        if ($ini && $fin) {
            return $fin > $ini ? ($ahora >= $ini && $ahora <= $fin) : ($ahora >= $ini || $ahora <= $fin);
        }
        if ($ini) return $ahora >= $ini;
        return $ahora <= $fin;
    }

    /** Texto explicativo del horario, para mostrar en el menu. */
    public static function textoHorario(array $c): string
    {
        $ini = $c['hora_inicio'] ?? null;
        $fin = $c['hora_fin'] ?? null;
        if (!$ini && !$fin) return '';
        if ($ini && $fin) return 'De ' . substr((string)$ini, 0, 5) . ' a ' . substr((string)$fin, 0, 5);
        if ($ini) return 'Desde las ' . substr((string)$ini, 0, 5);
        return 'Hasta las ' . substr((string)$fin, 0, 5);
    }

    public function conConteo(): array
    {
        $rid = $this->rid();
        return DB::all(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS productos
             FROM categories c WHERE c.restaurant_id = :r ORDER BY c.orden ASC, c.id ASC',
            ['r' => $rid]
        );
    }
}
