<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Tz;
use App\Core\Validator;

/** Certificados de regalo con código legible (AB12-CD34-EF56, sin caracteres ambiguos). */
final class GiftCardService
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Normaliza lo que escribe la persona: mayúsculas, sin espacios y con guiones cada 4 caracteres. */
    public static function normalize(string $code): string
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        return $raw === '' ? '' : implode('-', str_split($raw, 4));
    }

    private static function newCode(): string
    {
        $n = strlen(self::ALPHABET);
        $s = '';
        for ($i = 0; $i < 12; $i++) {
            $s .= self::ALPHABET[random_int(0, $n - 1)];
        }
        return implode('-', str_split($s, 4));
    }

    /**
     * d: amount (obligatorio), buyer_name, recipient_name, message, expires_at (UTC o null).
     * @throws \InvalidArgumentException
     */
    public static function create(array $d): int
    {
        $amount = Validator::money($d['amount'] ?? '');
        if ($amount === null || PricingService::cents($amount) <= 0) {
            throw new \InvalidArgumentException('Escribe un monto válido para el certificado (por ejemplo 250.00).');
        }
        $exp = isset($d['expires_at']) && $d['expires_at'] !== '' ? (string) $d['expires_at'] : null;
        if ($exp !== null && (!Validator::datetimeUtc($exp) || Tz::ts($exp) <= Clock::now())) {
            throw new \InvalidArgumentException('La fecha de vencimiento del certificado debe ser futura.');
        }
        for ($try = 0; $try < 8; $try++) {
            $code = self::newCode();
            try {
                return Db::insert('gift_cards', [
                    'code' => $code,
                    'initial_amount' => $amount,
                    'balance' => $amount,
                    'buyer_name' => self::clip($d['buyer_name'] ?? null, 160),
                    'recipient_name' => self::clip($d['recipient_name'] ?? null, 160),
                    'message' => self::clip($d['message'] ?? null, 255),
                    'expires_at' => $exp,
                    'active' => 1,
                    'created_at' => Clock::utc(),
                ]);
            } catch (\PDOException $e) {
                if ((string) ($e->errorInfo[1] ?? '') !== '1062') {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('No se pudo generar un código único. Inténtalo de nuevo.');
    }

    /** Certificado por código (cualquier formato razonable) o null. */
    public static function lookup(string $code): ?array
    {
        $code = self::normalize($code);
        if (strlen($code) !== 14) {
            return null;
        }
        return Db::one('SELECT * FROM gift_cards WHERE code = ?', [$code]);
    }

    /** Estado de uso: null si se puede usar o el motivo en español. */
    public static function unusableReason(array $card): ?string
    {
        if ((int) $card['active'] !== 1) {
            return 'Este certificado de regalo está desactivado.';
        }
        if ($card['expires_at'] !== null && Tz::ts((string) $card['expires_at']) < Clock::now()) {
            return 'Este certificado de regalo ya venció.';
        }
        if (PricingService::cents($card['balance']) <= 0) {
            return 'Este certificado de regalo ya no tiene saldo.';
        }
        return null;
    }

    private static function clip($v, int $max): ?string
    {
        $s = \App\Core\Str::clean($v === null ? '' : (string) $v, $max);
        return $s === '' ? null : $s;
    }
}
