<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;

/** Paquetes de sesiones prepagadas. */
final class PackageService
{
    private const METHODS = ['cash', 'transfer', 'card_onsite', 'link', 'other'];

    /**
     * Vende un paquete a un cliente. Con $paid = true registra además el pago verificado (ingreso) con $method.
     * Un paquete sin pagar no se puede usar para reservar hasta que se marque como pagado.
     * @throws \InvalidArgumentException
     */
    public static function sell(int $clientId, int $packageId, ?int $userId, bool $paid, string $method = 'cash'): int
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException('Elige un método de pago válido.');
        }
        return (int) Db::tx(static function () use ($clientId, $packageId, $userId, $paid, $method): int {
            if (Db::val('SELECT id FROM clients WHERE id = ?', [$clientId]) === null) {
                throw new \InvalidArgumentException('No encontramos al cliente.');
            }
            $pkg = Db::one('SELECT * FROM packages WHERE id = ? AND active = 1', [$packageId]);
            if ($pkg === null) {
                throw new \InvalidArgumentException('Ese paquete no está disponible.');
            }
            $now = Clock::now();
            $id = Db::insert('client_packages', [
                'client_id' => $clientId,
                'package_id' => $packageId,
                'remaining' => (int) $pkg['sessions'],
                'expires_at' => (int) $pkg['validity_days'] > 0 ? Clock::utc($now + (int) $pkg['validity_days'] * 86400) : null,
                'paid' => 0,
                'purchased_at' => Clock::utc($now),
            ]);
            if ($paid) {
                self::markPaid($id, $method, $userId);
            }
            return $id;
        });
    }

    /** Marca como pagado un paquete vendido y registra el ingreso (una sola vez). */
    public static function markPaid(int $clientPackageId, string $method, ?int $userId): void
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException('Elige un método de pago válido.');
        }
        Db::tx(static function () use ($clientPackageId, $method, $userId): void {
            if (Db::exec('UPDATE client_packages SET paid = 1 WHERE id = ? AND paid = 0', [$clientPackageId]) === 0) {
                return;
            }
            $row = Db::one('SELECT cp.client_id, p.price FROM client_packages cp JOIN packages p ON p.id = cp.package_id WHERE cp.id = ?', [$clientPackageId]);
            if ($row !== null && PricingService::cents($row['price']) > 0) {
                Db::insert('payments', [
                    'booking_id' => null,
                    'client_id' => (int) $row['client_id'],
                    'client_package_id' => $clientPackageId,
                    'amount' => number_format((float) $row['price'], 2, '.', ''),
                    'method' => $method,
                    'status' => 'verified',
                    'note' => 'Venta de paquete',
                    'created_by' => $userId,
                    'created_at' => Clock::utc(),
                ]);
            }
        });
    }

    /** Paquetes del cliente con su estado: 'usable' (pagado, con sesiones y vigente), 'expired', 'used_up' o 'unpaid'. */
    public static function balance(int $clientId): array
    {
        $rows = Db::all(
            'SELECT cp.*, p.name, p.sessions, p.event_type_id FROM client_packages cp JOIN packages p ON p.id = cp.package_id WHERE cp.client_id = ? ORDER BY cp.purchased_at DESC, cp.id DESC',
            [$clientId]
        );
        $now = Clock::utc();
        foreach ($rows as &$r) {
            $expired = $r['expires_at'] !== null && (string) $r['expires_at'] <= $now;
            $r['state'] = (int) $r['remaining'] <= 0 ? 'used_up' : ($expired ? 'expired' : ((int) $r['paid'] !== 1 ? 'unpaid' : 'usable'));
        }
        unset($r);
        return $rows;
    }
}
