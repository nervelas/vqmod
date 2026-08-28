<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;
use MenuGold\Core\Security;

/** Mesas del restaurante (la tabla SQL se llama `tables`). */
class RestaurantTable extends Model
{
    protected string $table = 'tables';
    protected array $fillable = [
        'restaurant_id','zone_id','nombre','capacidad','estado','orden','activo','abierta_desde','mesero_id',
    ];

    public function activas(): array
    {
        return $this->where('activo=1', [], 'orden ASC, id ASC');
    }

    /** Mesas con su zona y el total abierto. */
    public function tablero(): array
    {
        $rid = $this->rid();
        return DB::all(
            "SELECT t.*, z.nombre AS zona,
                    (SELECT COUNT(*) FROM orders o WHERE o.table_id = t.id AND o.estado IN ('nuevo','preparando','listo','entregado')) AS pedidos_abiertos,
                    (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.table_id = t.id AND o.estado IN ('nuevo','preparando','listo','entregado')) AS cuenta,
                    (SELECT COUNT(*) FROM waiter_calls w WHERE w.table_id = t.id AND w.estado = 'pendiente') AS llamadas
             FROM tables t
             LEFT JOIN zones z ON z.id = t.zone_id
             WHERE t.restaurant_id = :r AND t.activo = 1
             ORDER BY z.orden ASC, t.orden ASC, t.id ASC",
            ['r' => $rid]
        );
    }

    public function porNombre(string $nombre): ?array
    {
        return $this->first('nombre = :n AND activo = 1', ['n' => $nombre]);
    }

    /** Token HMAC del QR de la mesa. */
    public function token(array $mesa): string
    {
        return Security::tableToken((int)$mesa['restaurant_id'], (int)$mesa['id']);
    }

    public function verificarToken(array $mesa, string $token): bool
    {
        return Security::verifyTableToken((int)$mesa['restaurant_id'], (int)$mesa['id'], $token);
    }

    /** Crea varias mesas de golpe: "Mesa 1" .. "Mesa N". */
    public function crearLote(int $desde, int $hasta, string $prefijo = 'Mesa', ?int $zoneId = null, int $capacidad = 4): int
    {
        $n = 0;
        $orden = $this->maxOrder();
        for ($i = $desde; $i <= $hasta && $n < 200; $i++) {
            $nombre = trim($prefijo . ' ' . $i);
            if ($this->exists('nombre = :n', ['n' => $nombre])) continue;
            $this->create([
                'nombre' => $nombre, 'capacidad' => $capacidad,
                'zone_id' => $zoneId ?: null, 'orden' => ++$orden, 'activo' => 1,
            ]);
            $n++;
        }
        return $n;
    }

    public function abrir(int $id, ?int $meseroId = null): void
    {
        $this->updateById($id, [
            'estado' => 'ocupada',
            'abierta_desde' => date('Y-m-d H:i:s'),
            'mesero_id' => $meseroId,
        ]);
    }

    public function cerrar(int $id): void
    {
        $this->updateById($id, ['estado' => 'libre', 'abierta_desde' => null, 'mesero_id' => null]);
    }

    public function zonas(): array
    {
        return DB::all('SELECT * FROM zones WHERE restaurant_id = :r ORDER BY orden ASC, id ASC', ['r' => $this->rid()]);
    }
}
