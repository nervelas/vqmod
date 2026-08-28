<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;

class WaiterCall extends Model
{
    protected string $table = 'waiter_calls';
    protected array $fillable = [
        'restaurant_id','table_id','mesa_nombre','tipo','estado','nota','user_id','atendida_en',
    ];

    public function pendientes(): array
    {
        return $this->where("estado = 'pendiente'", [], 'creado ASC', 50);
    }

    /** Evita llamadas repetidas de la misma mesa en menos de 2 minutos. */
    public function reciente(int $tableId, string $tipo): bool
    {
        return DB::int(
            "SELECT COUNT(*) FROM waiter_calls
             WHERE restaurant_id = :r AND table_id = :t AND tipo = :ti
               AND estado = 'pendiente' AND creado > DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
            ['r' => $this->rid(), 't' => $tableId, 'ti' => $tipo]
        ) > 0;
    }

    public function atender(int $id, ?int $userId = null): void
    {
        $this->updateById($id, [
            'estado' => 'atendida',
            'atendida_en' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }
}
