<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Tz;

/** Validación de cupones de descuento. El control atómico de usos ocurre en PricingService::consume(). */
final class CouponService
{
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    public static function find(string $code): ?array
    {
        $code = self::normalize($code);
        if ($code === '' || strlen($code) > 40) {
            return null;
        }
        return Db::one('SELECT * FROM coupons WHERE code = ?', [$code]);
    }

    /**
     * @return array ['ok'=>bool,'error'=>?string,'coupon'=>?array,'discount'=>float] (discount calculado sobre $amount)
     */
    public static function validate(string $code, ?int $eventId, float $amount): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m, 'coupon' => null, 'discount' => 0.0];
        $c = self::find($code);
        if ($c === null || (int) $c['active'] !== 1) {
            return $fail('Ese cupón no existe o ya no está activo. Revisa que esté bien escrito.');
        }
        $now = Clock::now();
        if ($c['valid_from'] !== null && Tz::ts((string) $c['valid_from']) > $now) {
            return $fail('Este cupón todavía no está vigente.');
        }
        if ($c['valid_to'] !== null && Tz::ts((string) $c['valid_to']) < $now) {
            return $fail('Este cupón ya venció.');
        }
        if ($c['max_uses'] !== null && (int) $c['used'] >= (int) $c['max_uses']) {
            return $fail('Este cupón ya alcanzó su límite de usos.');
        }
        if ($c['event_type_id'] !== null && ($eventId === null || (int) $c['event_type_id'] !== $eventId)) {
            return $fail('Este cupón no aplica para este tipo de cita.');
        }
        $amountC = PricingService::cents($amount);
        $minC = PricingService::cents($c['min_amount'] ?? 0);
        if ($minC > 0 && $amountC < $minC) {
            return $fail('Este cupón aplica a partir de ' . \App\Core\Fmt::money($minC / 100) . '.');
        }
        return ['ok' => true, 'error' => null, 'coupon' => $c, 'discount' => PricingService::fromCents(self::discountCents($c, $amountC))];
    }

    /** Descuento en centavos: porcentaje redondeado al centavo (mitad hacia arriba) o monto fijo; nunca supera el precio. */
    public static function discountCents(array $coupon, int $amountC): int
    {
        $valueC = PricingService::cents($coupon['value']);
        if ($coupon['type'] === 'percent') {
            $valueC = min($valueC, 10000);
            $d = intdiv($amountC * $valueC + 5000, 10000);
        } else {
            $d = $valueC;
        }
        return max(0, min($d, $amountC));
    }
}
