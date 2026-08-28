<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\Model;

class DeliveryZone extends Model
{
    protected string $table = 'delivery_zones';
    protected array $fillable = ['restaurant_id','nombre','costo','minimo','tiempo_min','activo','orden'];

    public function activas(): array
    {
        return $this->where('activo=1', [], 'orden ASC, id ASC');
    }
}
