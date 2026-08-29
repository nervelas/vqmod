<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;

class Coupon extends Model
{
    protected string $table = 'coupons';
    protected array $fillable = [
        'restaurant_id','codigo','descripcion','tipo','valor','min_compra','usos_max','usos','desde','hasta','activo',
    ];

    /**
     * Valida un cupon contra un subtotal.
     * @return array{0:?array,1:string} [cupon|null, mensajeError]
     */
    public function validar(string $codigo, float $subtotal): array
    {
        $codigo = mb_strtoupper(trim($codigo));
        if ($codigo === '') return [null, 'Escribe un código de cupón.'];
        $c = $this->first('codigo = :c', ['c' => $codigo]);
        if (!$c) return [null, 'Ese cupón no existe.'];
        if ((int)$c['activo'] !== 1) return [null, 'Ese cupón ya no está disponible.'];
        $hoy = date('Y-m-d');
        if (!empty($c['desde']) && $c['desde'] > $hoy) return [null, 'Ese cupón todavía no está vigente.'];
        if (!empty($c['hasta']) && $c['hasta'] < $hoy) return [null, 'Ese cupón ya venció.'];
        if ((int)$c['usos_max'] > 0 && (int)$c['usos'] >= (int)$c['usos_max']) {
            return [null, 'Ese cupón ya alcanzó su límite de usos.'];
        }
        if ((float)$c['min_compra'] > 0 && $subtotal < (float)$c['min_compra']) {
            return [null, 'El cupón aplica desde ' . money($c['min_compra']) . '.'];
        }
        return [$c, ''];
    }

    public static function calcular(array $cupon, float $subtotal, float $envio = 0): float
    {
        switch ((string)$cupon['tipo']) {
            case 'porcentaje':   return round(min($subtotal, $subtotal * ((float)$cupon['valor'] / 100)), 2);
            case 'monto':        return round(min($subtotal, (float)$cupon['valor']), 2);
            case 'envio_gratis': return round($envio, 2);
        }
        return 0.0;
    }

    public function registrarUso(int $id): void
    {
        DB::ejecutar('UPDATE coupons SET usos = usos + 1 WHERE id = :i AND restaurant_id = :r',
            ['i' => $id, 'r' => $this->rid()]);
    }
}
