<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;

/**
 * Cotización y consumo de precios. Todo el cálculo usa enteros en centavos.
 *
 * Convención: `total` es lo que el invitado todavía debe pagar (precio − descuento − certificado; 0 si usa un paquete).
 * El certificado y el paquete NO generan filas en `payments`; su uso queda en `pricing_ledger`.
 */
final class PricingService
{
    public static function cents($v): int
    {
        return (int) round(((float) str_replace(',', '.', (string) $v)) * 100);
    }

    public static function fromCents(int $c): float
    {
        return round($c / 100, 2);
    }

    public static function quote(array $event, int $duration, int $seats, ?string $coupon, ?string $giftCode, ?int $clientId, ?array $policy = null): array
    {
        $seats = max(1, $seats);
        $out = [
            'ok' => true, 'error' => null, 'price' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'deposit_due' => 0.0,
            'coupon_id' => null, 'gift_card_id' => null, 'gift_applied' => 0.0, 'client_package_id' => null,
            'needs_payment' => false, 'seats' => $seats, 'coupon_code' => null,
        ];
        $priceC = self::cents($event['price'] ?? 0) * $seats;
        $out['price'] = self::fromCents($priceC);
        $coupon = $coupon !== null ? trim($coupon) : '';
        $giftCode = $giftCode !== null ? trim($giftCode) : '';

        // Se validan siempre los códigos escritos, para avisar de errores aunque otro beneficio tenga prioridad.
        $cp = null;
        if ($coupon !== '') {
            if (isset($event['allow_coupon']) && (int) $event['allow_coupon'] === 0) {
                return self::fail($out, 'Este tipo de cita no acepta cupones.');
            }
            $v = CouponService::validate($coupon, isset($event['id']) ? (int) $event['id'] : null, self::fromCents($priceC));
            if (!$v['ok']) {
                return self::fail($out, (string) $v['error']);
            }
            $cp = $v['coupon'];
        }
        $gc = null;
        if ($giftCode !== '') {
            $gc = GiftCardService::lookup($giftCode);
            if ($gc === null) {
                return self::fail($out, 'No encontramos ese certificado de regalo. Revisa el código.');
            }
            $why = GiftCardService::unusableReason($gc);
            if ($why !== null) {
                return self::fail($out, $why);
            }
        }

        // Un paquete vigente cubre la cita completa (una sesión).
        if ($clientId !== null && $priceC > 0 && $seats === 1) {
            $pk = self::findPackage($clientId, isset($event['id']) ? (int) $event['id'] : null);
            if ($pk !== null) {
                $out['client_package_id'] = (int) $pk['id'];
                return $out;
            }
        }

        $discC = 0;
        if ($cp !== null) {
            $discC = CouponService::discountCents($cp, $priceC);
            $out['coupon_id'] = (int) $cp['id'];
            $out['coupon_code'] = (string) $cp['code'];
        }
        $totalC = $priceC - $discC;
        $giftC = 0;
        if ($gc !== null) {
            $giftC = min(self::cents($gc['balance']), $totalC);
            if ($giftC > 0) {
                $out['gift_card_id'] = (int) $gc['id'];
            }
            $totalC -= $giftC;
        }

        $depC = 0;
        if ($totalC > 0) {
            $dv = self::cents($event['deposit_value'] ?? 0);
            $dt = (string) ($event['deposit_type'] ?? 'none');
            if ($dt === 'fixed') {
                $depC = $dv;
            } elseif ($dt === 'percent') {
                $depC = intdiv($totalC * min($dv, 10000) + 5000, 10000);
            }
            if (!empty($policy['require_deposit'])) {
                $pctC = max(0, min(10000, self::cents(Settings::get('noshow_deposit_percent', '50'))));
                $depC = max($depC, intdiv($totalC * $pctC + 5000, 10000));
            }
            $depC = min($depC, $totalC);
        }

        $out['discount'] = self::fromCents($discC);
        $out['gift_applied'] = self::fromCents($giftC);
        $out['total'] = self::fromCents($totalC);
        $out['deposit_due'] = self::fromCents($depC);
        $out['needs_payment'] = $depC > 0;
        return $out;
    }

    private static function fail(array $out, string $msg): array
    {
        $out['ok'] = false;
        $out['error'] = $msg;
        $out['coupon_id'] = $out['gift_card_id'] = $out['client_package_id'] = null;
        return $out;
    }

    /** Paquete pagado, con sesiones y vigente para el evento; el que vence primero. */
    private static function findPackage(int $clientId, ?int $eventId): ?array
    {
        return Db::one(
            'SELECT cp.* FROM client_packages cp JOIN packages p ON p.id = cp.package_id
             WHERE cp.client_id = ? AND cp.remaining > 0 AND cp.paid = 1 AND (cp.expires_at IS NULL OR cp.expires_at > ?)
               AND (p.event_type_id IS NULL OR p.event_type_id = ?)
             ORDER BY cp.expires_at IS NULL, cp.expires_at, cp.id LIMIT 1',
            [$clientId, Clock::utc(), $eventId]
        );
    }

    /**
     * Debe llamarse dentro de la transacción de la reserva. Cada recurso se toma con un UPDATE condicional;
     * si se agotó entre cotizar y reservar lanza RuntimeException (la transacción completa se revierte).
     * @throws \RuntimeException
     */
    public static function consume(array $quote, int $bookingId): void
    {
        Db::tx(static function () use ($quote, $bookingId): void {
            $now = Clock::utc();
            if (!empty($quote['client_package_id'])) {
                $id = (int) $quote['client_package_id'];
                if (self::ledgerFree($bookingId, 'package')) {
                    $n = Db::exec(
                        'UPDATE client_packages SET remaining = remaining - 1 WHERE id = ? AND remaining > 0 AND paid = 1 AND (expires_at IS NULL OR expires_at > ?)',
                        [$id, $now]
                    );
                    if ($n !== 1) {
                        throw new \RuntimeException('Tu paquete de sesiones ya no tiene sesiones disponibles. Vuelve a revisar el precio e inténtalo de nuevo.');
                    }
                    self::ledgerAdd($bookingId, 'package', $id, 0);
                }
                return;
            }
            if (!empty($quote['coupon_id']) && self::ledgerFree($bookingId, 'coupon')) {
                $id = (int) $quote['coupon_id'];
                $n = Db::exec(
                    'UPDATE coupons SET used = used + 1 WHERE id = ? AND active = 1 AND (max_uses IS NULL OR used < max_uses) AND (valid_to IS NULL OR valid_to >= ?)',
                    [$id, $now]
                );
                if ($n !== 1) {
                    throw new \RuntimeException('Lo sentimos, el cupón se agotó justo mientras reservabas. Quita el cupón o prueba con otro.');
                }
                self::ledgerAdd($bookingId, 'coupon', $id, 0);
            }
            if (!empty($quote['gift_card_id']) && self::cents($quote['gift_applied'] ?? 0) > 0 && self::ledgerFree($bookingId, 'gift')) {
                $id = (int) $quote['gift_card_id'];
                $amount = number_format((float) $quote['gift_applied'], 2, '.', '');
                $n = Db::exec(
                    'UPDATE gift_cards SET balance = balance - ? WHERE id = ? AND active = 1 AND balance >= ? AND (expires_at IS NULL OR expires_at > ?)',
                    [$amount, $id, $amount, $now]
                );
                if ($n !== 1) {
                    throw new \RuntimeException('El saldo del certificado de regalo ya no alcanza (se usó mientras reservabas). Vuelve a revisar el precio.');
                }
                self::ledgerAdd($bookingId, 'gift', $id, self::cents($amount));
            }
        });
    }

    /** Devuelve lo consumido por la cita (idempotente: cada recurso se devuelve una sola vez). */
    public static function release(array $booking): void
    {
        $bookingId = (int) ($booking['id'] ?? 0);
        if ($bookingId <= 0) {
            return;
        }
        Db::tx(static function () use ($bookingId): void {
            $rows = Db::all("SELECT * FROM pricing_ledger WHERE booking_id = ? AND state = 'consumed'", [$bookingId]);
            foreach ($rows as $r) {
                if (Db::exec("UPDATE pricing_ledger SET state = 'released', released_at = ? WHERE id = ? AND state = 'consumed'", [Clock::utc(), (int) $r['id']]) !== 1) {
                    continue;
                }
                $ref = (int) $r['ref_id'];
                if ($r['kind'] === 'package') {
                    Db::exec('UPDATE client_packages cp JOIN packages p ON p.id = cp.package_id SET cp.remaining = LEAST(cp.remaining + 1, p.sessions) WHERE cp.id = ?', [$ref]);
                } elseif ($r['kind'] === 'coupon') {
                    Db::exec('UPDATE coupons SET used = used - 1 WHERE id = ? AND used > 0', [$ref]);
                } else {
                    Db::exec('UPDATE gift_cards SET balance = LEAST(initial_amount, balance + ?) WHERE id = ?', [number_format((float) $r['amount'], 2, '.', ''), $ref]);
                }
            }
        });
    }

    /** Lo aplicado por una cita desde la bitácora: ['coupon'=>?id,'gift'=>['id','amount'],'package'=>?id] (solo lo no devuelto). */
    public static function applied(int $bookingId): array
    {
        $out = ['coupon' => null, 'gift' => null, 'package' => null];
        foreach (Db::all("SELECT * FROM pricing_ledger WHERE booking_id = ? AND state = 'consumed'", [$bookingId]) as $r) {
            if ($r['kind'] === 'gift') {
                $out['gift'] = ['id' => (int) $r['ref_id'], 'amount' => (float) $r['amount']];
            } else {
                $out[$r['kind']] = (int) $r['ref_id'];
            }
        }
        return $out;
    }

    private static function ledgerFree(int $bookingId, string $kind): bool
    {
        return Db::val('SELECT id FROM pricing_ledger WHERE booking_id = ? AND kind = ? AND state = ?', [$bookingId, $kind, 'consumed']) === null;
    }

    private static function ledgerAdd(int $bookingId, string $kind, int $refId, int $amountC): void
    {
        // Si la cita ya devolvió este recurso antes y se vuelve a consumir, se reutiliza la fila.
        Db::exec(
            "INSERT INTO pricing_ledger (booking_id, kind, ref_id, amount, state, created_at) VALUES (?, ?, ?, ?, 'consumed', ?)
             ON DUPLICATE KEY UPDATE ref_id = VALUES(ref_id), amount = VALUES(amount), state = 'consumed', released_at = NULL, created_at = VALUES(created_at)",
            [$bookingId, $kind, $refId, number_format($amountC / 100, 2, '.', ''), Clock::utc()]
        );
    }
}
