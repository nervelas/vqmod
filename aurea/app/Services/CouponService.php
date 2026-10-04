<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;

/** Cupones de descuento (porcentaje o monto) y certificados de regalo (saldo). */
final class CouponService
{
    /**
     * @return array{0:?array,1:float,2:string} [cupón, descuento, error]
     */
    public static function evaluate(string $code, int $serviceId, float $price, bool $lock = false): array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || !preg_match('/^[A-Z0-9_\-]{2,40}$/', $code)) { return [null, 0.0, 'Código inválido.']; }
        $c = Db::one('SELECT * FROM coupons WHERE code=? AND active=1' . ($lock ? ' FOR UPDATE' : ''), [$code]);
        if (!$c) { return [null, 0.0, 'El código no existe o no está activo.']; }
        $today = date('Y-m-d');
        if ($c['valid_from'] && $today < $c['valid_from']) { return [null, 0.0, 'Este código aún no está vigente.']; }
        if ($c['valid_to'] && $today > $c['valid_to']) { return [null, 0.0, 'Este código ya venció.']; }
        if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) { return [null, 0.0, 'Este código ya alcanzó su límite de usos.']; }
        if ($c['service_id'] && (int)$c['service_id'] !== $serviceId) { return [null, 0.0, 'Este código no aplica para este servicio.']; }
        if ($c['kind'] === 'gift') {
            if ((float)$c['balance'] <= 0) { return [null, 0.0, 'El certificado no tiene saldo.']; }
            $d = min((float)$c['balance'], $price);
        } elseif ($c['kind'] === 'percent') {
            $d = round($price * min(100, (float)$c['value']) / 100, 2);
        } else {
            $d = min((float)$c['value'], $price);
        }
        return [$c, round(max(0, $d), 2), ''];
    }

    public static function consume(array $c, float $discount): void
    {
        Db::exec('UPDATE coupons SET used=used+1, balance=GREATEST(0, balance-?) WHERE id=?', [$c['kind'] === 'gift' ? $discount : 0, $c['id']]);
    }

    public static function restore(int $couponId, float $discount): void
    {
        $c = Db::one('SELECT kind FROM coupons WHERE id=?', [$couponId]);
        if (!$c) { return; }
        Db::exec('UPDATE coupons SET used=GREATEST(0,used-1), balance=balance+? WHERE id=?', [$c['kind'] === 'gift' ? $discount : 0, $couponId]);
    }
}
