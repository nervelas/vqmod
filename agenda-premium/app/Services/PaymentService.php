<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Validator;

/**
 * Registro de pagos (efectivo, transferencia, enlace externo…). Nunca se guardan datos de tarjetas.
 * Un reembolso es una fila con status 'refunded' (monto positivo) enlazada con refund_of al pago original.
 */
final class PaymentService
{
    private const METHODS = ['cash', 'transfer', 'card_onsite', 'link', 'package', 'gift_card', 'other'];

    /**
     * data: amount, method, status (pending|verified|rejected; por defecto verified), reference, proof_file_id, note.
     * @throws \InvalidArgumentException
     */
    public static function record(int $bookingId, array $data, ?int $userId): int
    {
        $amount = Validator::money($data['amount'] ?? '');
        if ($amount === null || PricingService::cents($amount) <= 0) {
            throw new \InvalidArgumentException('Escribe un monto válido, mayor que cero (por ejemplo 150.00).');
        }
        $method = (string) ($data['method'] ?? 'cash');
        if (!in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException('Elige un método de pago válido.');
        }
        $status = (string) ($data['status'] ?? 'verified');
        if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
            throw new \InvalidArgumentException('El estado del pago no es válido.');
        }
        $fileId = !empty($data['proof_file_id']) ? (int) $data['proof_file_id'] : null;
        if ($fileId !== null && Db::val('SELECT id FROM files WHERE id = ?', [$fileId]) === null) {
            throw new \InvalidArgumentException('No encontramos el comprobante adjunto.');
        }
        return (int) Db::tx(static function () use ($bookingId, $amount, $method, $status, $fileId, $data, $userId): int {
            $b = self::lockBooking($bookingId);
            if ($status === 'verified' && self::cents($b['total']) > 0) {
                $s = self::sums($bookingId);
                if ($s['verified'] - $s['refunded'] + PricingService::cents($amount) > PricingService::cents($b['total'])) {
                    throw new \InvalidArgumentException('El monto supera el saldo pendiente de la cita (' . Fmt::money(max(0, PricingService::cents($b['total']) - ($s['verified'] - $s['refunded'])) / 100) . ').');
                }
            }
            $id = Db::insert('payments', [
                'booking_id' => $bookingId,
                'client_id' => $b['client_id'] !== null ? (int) $b['client_id'] : null,
                'amount' => $amount,
                'method' => $method,
                'status' => $status,
                'reference' => self::clip($data['reference'] ?? null, 190),
                'proof_file_id' => $fileId,
                'note' => self::clip($data['note'] ?? null, 255),
                'created_by' => $userId,
                'created_at' => Clock::utc(),
            ]);
            self::history($bookingId, 'payment', 'Pago ' . Fmt::money($amount) . ' (' . $method . ', ' . $status . ')', $userId);
            self::recalc($bookingId);
            return $id;
        });
    }

    /**
     * El invitado sube su comprobante de transferencia: queda un pago 'pending' por el saldo del depósito
     * (o del total si no hay depósito). Un segundo comprobante reemplaza el archivo del pendiente.
     * @throws \InvalidArgumentException
     */
    public static function proof(int $bookingId, int $fileId): int
    {
        if (Db::val('SELECT id FROM files WHERE id = ?', [$fileId]) === null) {
            throw new \InvalidArgumentException('No pudimos encontrar el archivo del comprobante. Súbelo de nuevo.');
        }
        return (int) Db::tx(static function () use ($bookingId, $fileId): int {
            $b = self::lockBooking($bookingId);
            $s = self::sums($bookingId);
            $net = $s['verified'] - $s['refunded'];
            $dueC = self::cents($b['deposit_due']) > $net ? self::cents($b['deposit_due']) : self::cents($b['total']);
            $dueC -= $net;
            if ($dueC <= 0) {
                throw new \InvalidArgumentException('Esta cita no tiene saldo pendiente de pago.');
            }
            $pend = Db::val("SELECT id FROM payments WHERE booking_id = ? AND status = 'pending' AND method = 'transfer' AND proof_file_id IS NOT NULL ORDER BY id DESC LIMIT 1", [$bookingId]);
            if ($pend !== null) {
                Db::update('payments', ['proof_file_id' => $fileId, 'amount' => number_format($dueC / 100, 2, '.', '')], 'id = ?', [(int) $pend]);
                $id = (int) $pend;
            } else {
                $id = Db::insert('payments', [
                    'booking_id' => $bookingId,
                    'client_id' => $b['client_id'] !== null ? (int) $b['client_id'] : null,
                    'amount' => number_format($dueC / 100, 2, '.', ''),
                    'method' => 'transfer',
                    'status' => 'pending',
                    'proof_file_id' => $fileId,
                    'note' => 'Comprobante enviado por el invitado',
                    'created_at' => Clock::utc(),
                ]);
            }
            self::history($bookingId, 'payment', 'Comprobante de pago recibido', null, 'invitado');
            self::recalc($bookingId);
            return $id;
        });
    }

    /** Aprueba o rechaza un pago pendiente. @throws \InvalidArgumentException */
    public static function verify(int $paymentId, bool $ok, ?int $userId): void
    {
        Db::tx(static function () use ($paymentId, $ok, $userId): void {
            $p = Db::one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if ($p === null || $p['booking_id'] === null) {
                throw new \InvalidArgumentException('No encontramos ese pago.');
            }
            $bookingId = (int) $p['booking_id'];
            $b = self::lockBooking($bookingId);
            if ($ok && self::cents($b['total']) > 0) {
                $s = self::sums($bookingId);
                if ($s['verified'] - $s['refunded'] + self::cents($p['amount']) > self::cents($b['total'])) {
                    throw new \InvalidArgumentException('Al verificar este pago se superaría el total de la cita. Ajusta o rechaza el pago.');
                }
            }
            $n = Db::exec('UPDATE payments SET status = ? WHERE id = ? AND status = ?', [$ok ? 'verified' : 'rejected', $paymentId, 'pending']);
            if ($n !== 1) {
                throw new \InvalidArgumentException('Este pago ya fue revisado.');
            }
            self::history($bookingId, 'payment', ($ok ? 'Pago verificado ' : 'Pago rechazado ') . Fmt::money((float) $p['amount']), $userId);
            self::recalc($bookingId);
        });
    }

    /**
     * Reembolsa total o parcialmente un pago verificado. $amount null = todo lo que queda por reembolsar de ese pago.
     * @throws \InvalidArgumentException
     */
    public static function refund(int $paymentId, ?string $amount = null, ?string $note = null, ?int $userId = null): int
    {
        return (int) Db::tx(static function () use ($paymentId, $amount, $note, $userId): int {
            $p = Db::one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if ($p === null || $p['booking_id'] === null || $p['status'] !== 'verified') {
                throw new \InvalidArgumentException('Solo se pueden reembolsar pagos verificados.');
            }
            $bookingId = (int) $p['booking_id'];
            self::lockBooking($bookingId);
            $doneC = self::cents(Db::val("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE refund_of = ? AND status = 'refunded'", [$paymentId]));
            $leftC = self::cents($p['amount']) - $doneC;
            if ($leftC <= 0) {
                throw new \InvalidArgumentException('Este pago ya fue reembolsado por completo.');
            }
            if ($amount === null || trim($amount) === '') {
                $refC = $leftC;
            } else {
                $m = Validator::money($amount);
                if ($m === null || PricingService::cents($m) <= 0) {
                    throw new \InvalidArgumentException('Escribe un monto de reembolso válido.');
                }
                $refC = PricingService::cents($m);
                if ($refC > $leftC) {
                    throw new \InvalidArgumentException('El reembolso no puede superar ' . Fmt::money($leftC / 100) . ', lo que queda de ese pago.');
                }
            }
            $id = Db::insert('payments', [
                'booking_id' => $bookingId,
                'client_id' => $p['client_id'] !== null ? (int) $p['client_id'] : null,
                'amount' => number_format($refC / 100, 2, '.', ''),
                'method' => $p['method'],
                'status' => 'refunded',
                'reference' => $p['reference'],
                'note' => self::clip($note, 255),
                'refund_of' => $paymentId,
                'created_by' => $userId,
                'created_at' => Clock::utc(),
            ]);
            self::history($bookingId, 'payment', 'Reembolso ' . Fmt::money($refC / 100), $userId);
            self::recalc($bookingId);
            return $id;
        });
    }

    /**
     * Actualiza bookings.paid_amount (verificado − reembolsado) y payment_status:
     * paid (cubre el total), partial (algo verificado), pending (solo comprobantes por revisar),
     * refunded (se reembolsó todo lo cobrado) o none.
     */
    public static function recalc(int $bookingId): void
    {
        Db::tx(static function () use ($bookingId): void {
            $b = Db::one('SELECT id, total FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if ($b === null) {
                return;
            }
            $s = self::sums($bookingId);
            $net = max(0, $s['verified'] - $s['refunded']);
            $totalC = self::cents($b['total']);
            if ($s['refunded'] > 0 && $net === 0) {
                $st = 'refunded';
            } elseif ($totalC > 0 && $net >= $totalC) {
                $st = 'paid';
            } elseif ($net > 0) {
                $st = 'partial';
            } elseif ($s['pending'] > 0) {
                $st = 'pending';
            } else {
                $st = 'none';
            }
            Db::update('bookings', ['paid_amount' => number_format($net / 100, 2, '.', ''), 'payment_status' => $st, 'updated_at' => Clock::utc()], 'id = ?', [$bookingId]);
        });
    }

    /** Datos para el recibo imprimible. */
    public static function receiptData(int $bookingId): array
    {
        $b = Db::one(
            'SELECT b.*, e.name AS event_name, h.name AS host_name, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, c.nit AS client_nit
             FROM bookings b JOIN event_types e ON e.id = b.event_type_id LEFT JOIN hosts h ON h.id = b.host_id LEFT JOIN clients c ON c.id = b.client_id WHERE b.id = ?',
            [$bookingId]
        );
        if ($b === null) {
            throw new \InvalidArgumentException('No encontramos la cita.');
        }
        $tz = Settings::tz();
        $pays = Db::all("SELECT * FROM payments WHERE booking_id = ? AND status IN ('verified','refunded') ORDER BY created_at, id", [$bookingId]);
        $lines = [];
        foreach ($pays as $p) {
            $lines[] = [
                'id' => (int) $p['id'],
                'number' => 'P-' . str_pad((string) $p['id'], 6, '0', STR_PAD_LEFT),
                'date' => Fmt::dateShort((string) $p['created_at'], $tz),
                'method' => (string) $p['method'],
                'method_label' => self::methodLabel((string) $p['method']),
                'status' => (string) $p['status'],
                'reference' => $p['reference'],
                'amount' => (float) $p['amount'],
                'refund' => $p['status'] === 'refunded',
            ];
        }
        $s = self::sums($bookingId);
        $net = max(0, $s['verified'] - $s['refunded']);
        $totalC = self::cents($b['total']);
        $applied = PricingService::applied($bookingId);
        return [
            'receipt_number' => 'R-' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT),
            'issued' => Fmt::dateShort(Clock::utc(), $tz),
            'business' => [
                'name' => (string) Settings::get('business_name', ''),
                'address' => (string) Settings::get('address', ''),
                'phone' => (string) Settings::get('phone', ''),
                'email' => (string) Settings::get('email', ''),
            ],
            'client' => [
                'name' => (string) ($b['client_name'] ?? $b['guest_name']),
                'email' => $b['client_email'] ?? $b['guest_email'],
                'phone' => $b['client_phone'] ?? $b['guest_phone'],
                'nit' => $b['client_nit'] ?? null,
            ],
            'booking' => [
                'id' => (int) $b['id'],
                'event' => (string) $b['event_name'],
                'host' => $b['host_name'],
                'when' => Fmt::dateTime((string) $b['starts_at'], $tz),
                'status' => (string) $b['status'],
                'seats' => (int) $b['seats'],
            ],
            'price' => (float) $b['price'],
            'discount' => (float) $b['discount'],
            'gift_applied' => $applied['gift']['amount'] ?? 0.0,
            'total' => (float) $b['total'],
            'paid' => PricingService::fromCents($net),
            'refunded' => PricingService::fromCents($s['refunded']),
            'balance' => PricingService::fromCents(max(0, $totalC - $net)),
            'payment_status' => (string) $b['payment_status'],
            'payments' => $lines,
            'bank_info' => (string) Settings::get('bank_info', ''),
            'payment_link' => (string) Settings::get('payment_link', ''),
        ];
    }

    public static function methodLabel(string $m): string
    {
        return [
            'cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card_onsite' => 'Tarjeta en el local', 'link' => 'Enlace de pago',
            'package' => 'Paquete de sesiones', 'gift_card' => 'Certificado de regalo', 'other' => 'Otro',
        ][$m] ?? $m;
    }

    /** Sumas en centavos por estado. */
    private static function sums(int $bookingId): array
    {
        $out = ['verified' => 0, 'refunded' => 0, 'pending' => 0];
        foreach (Db::all('SELECT status, SUM(ROUND(amount * 100)) AS c FROM payments WHERE booking_id = ? GROUP BY status', [$bookingId]) as $r) {
            if (isset($out[$r['status']])) {
                $out[$r['status']] = (int) $r['c'];
            }
        }
        return $out;
    }

    private static function cents($v): int
    {
        return PricingService::cents($v);
    }

    private static function lockBooking(int $bookingId): array
    {
        $b = Db::one('SELECT id, client_id, total, deposit_due FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if ($b === null) {
            throw new \InvalidArgumentException('No encontramos la cita.');
        }
        return $b;
    }

    private static function history(int $bookingId, string $action, string $detail, ?int $userId, ?string $actor = null): void
    {
        $label = $actor ?? ($userId !== null ? 'usuario #' . $userId : 'sistema');
        Db::insert('booking_history', ['booking_id' => $bookingId, 'action' => $action, 'detail' => Str::truncate($detail, 500), 'actor' => $label, 'created_at' => Clock::utc()]);
    }

    private static function clip($v, int $max): ?string
    {
        $s = Str::clean($v === null ? '' : (string) $v, $max);
        return $s === '' ? null : $s;
    }
}
