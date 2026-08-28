<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;

class Plan extends Model
{
    protected string $table = 'plans';
    protected bool $scoped = false;
    protected array $jsonFields = ['caracteristicas'];
    protected array $fillable = [
        'nombre','slug','descripcion','precio_mensual','precio_anual','max_productos',
        'max_mesas','max_sucursales','max_usuarios','caracteristicas','destacado','orden','activo',
    ];

    public function activos(): array
    {
        return $this->where('activo=1', [], 'orden ASC');
    }

    /** Limites del plan de un restaurante (0 = ilimitado). */
    public function limites(?int $planId): array
    {
        $base = ['max_productos' => 0, 'max_mesas' => 0, 'max_sucursales' => 1, 'max_usuarios' => 0];
        if (!$planId) return $base;
        $p = $this->find($planId);
        if (!$p) return $base;
        return [
            'max_productos'  => (int)$p['max_productos'],
            'max_mesas'      => (int)$p['max_mesas'],
            'max_sucursales' => (int)$p['max_sucursales'],
            'max_usuarios'   => (int)$p['max_usuarios'],
        ];
    }
}
