<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;

class Customer extends Model
{
    protected string $table = 'customers';
    protected array $fillable = [
        'restaurant_id','nombre','telefono','email','direccion','referencia','zone_id',
        'puntos','pedidos','total_gastado','notas','ultimo_pedido',
    ];

    public function porTelefono(string $tel): ?array
    {
        $tel = self::normalizarTel($tel);
        if ($tel === '') return null;
        return $this->first('telefono = :t', ['t' => $tel]);
    }

    public static function normalizarTel(string $t): string
    {
        return preg_replace('/[^\d+]/', '', $t) ?? '';
    }

    /** Crea o actualiza el cliente a partir de los datos de un pedido. */
    public function registrar(array $datos): ?int
    {
        $tel = self::normalizarTel((string)($datos['telefono'] ?? ''));
        if ($tel === '') return null;
        $existe = $this->porTelefono($tel);
        $campos = [
            'nombre'     => mb_substr((string)($datos['nombre'] ?? 'Cliente'), 0, 120),
            'telefono'   => $tel,
            'direccion'  => mb_substr((string)($datos['direccion'] ?? ''), 0, 255),
            'referencia' => mb_substr((string)($datos['referencia'] ?? ''), 0, 255),
            'zone_id'    => !empty($datos['zone_id']) ? (int)$datos['zone_id'] : null,
        ];
        if ($existe) {
            $this->updateById((int)$existe['id'], array_filter($campos, static fn($v) => $v !== '' && $v !== null));
            return (int)$existe['id'];
        }
        return $this->create($campos);
    }

    /** Suma un pedido cerrado al historial del cliente. */
    public function acumular(int $customerId, float $total, int $puntos = 0): void
    {
        DB::exec(
            'UPDATE customers SET pedidos = pedidos + 1, total_gastado = total_gastado + :t,
                    puntos = puntos + :p, ultimo_pedido = NOW()
             WHERE id = :i AND restaurant_id = :r',
            ['t' => $total, 'p' => $puntos, 'i' => $customerId, 'r' => $this->rid()]
        );
    }

    public function historial(int $customerId, int $limite = 30): array
    {
        return DB::all(
            'SELECT * FROM orders WHERE customer_id = :c AND restaurant_id = :r ORDER BY creado DESC LIMIT ' . (int)$limite,
            ['c' => $customerId, 'r' => $this->rid()]
        );
    }

    public function buscar(string $q, int $limite = 200): array
    {
        if ($q === '') return $this->all('ultimo_pedido DESC, id DESC');
        return $this->where(
            'nombre LIKE :q OR telefono LIKE :q2 OR email LIKE :q3',
            ['q' => "%{$q}%", 'q2' => "%{$q}%", 'q3' => "%{$q}%"],
            'ultimo_pedido DESC', $limite
        );
    }
}
