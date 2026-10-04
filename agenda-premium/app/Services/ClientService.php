<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;

/** Mini CRM: alta/unión de clientes y política por inasistencias. */
final class ClientService
{
    /** Crea o actualiza al cliente (se une por correo; si no hay correo, por teléfono). Devuelve su id. */
    public static function upsert(array $d): int
    {
        $email = strtolower(trim((string) ($d['email'] ?? '')));
        $email = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $phone = Str::phone((string) ($d['phone'] ?? ''), (string) Settings::get('phone_cc', '502')) ?? '';
        $name = Str::clean((string) ($d['name'] ?? ''), 160);
        $existing = null;
        if ($email !== '') {
            $existing = Db::one('SELECT * FROM clients WHERE email = ? AND anonymized_at IS NULL ORDER BY id LIMIT 1', [$email]);
        }
        if (!$existing && $phone !== '') {
            $existing = Db::one('SELECT * FROM clients WHERE phone = ? AND anonymized_at IS NULL ORDER BY id LIMIT 1', [$phone]);
        }
        $now = Clock::utc();
        $tz = !empty($d['timezone']) && Tz::valid((string) $d['timezone']) ? (string) $d['timezone'] : null;
        if ($existing) {
            $upd = ['updated_at' => $now];
            if ($name !== '' && $existing['name'] === '') {
                $upd['name'] = $name;
            }
            if ($email !== '' && empty($existing['email'])) {
                $upd['email'] = $email;
            }
            if ($phone !== '' && empty($existing['phone'])) {
                $upd['phone'] = $phone;
            }
            if (!empty($d['nit']) && empty($existing['nit'])) {
                $upd['nit'] = Str::clean((string) $d['nit'], 30);
            }
            if ($tz !== null) {
                $upd['timezone'] = $tz;
            }
            Db::update('clients', $upd, 'id = ?', [$existing['id']]);
            return (int) $existing['id'];
        }
        return Db::insert('clients', [
            'name' => $name !== '' ? $name : 'Sin nombre',
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'nit' => !empty($d['nit']) ? Str::clean((string) $d['nit'], 30) : null,
            'source' => !empty($d['source']) ? Str::clean((string) $d['source'], 190) : null,
            'timezone' => $tz,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Política según el historial de inasistencias de la persona (por correo o teléfono). */
    public static function policy(?string $email, ?string $phone): array
    {
        $email = strtolower(trim((string) $email));
        $phone = Str::phone((string) $phone, (string) Settings::get('phone_cc', '502')) ?? '';
        $row = null;
        if ($email !== '') {
            $row = Db::one('SELECT blocked, noshow_count FROM clients WHERE email = ? ORDER BY noshow_count DESC LIMIT 1', [$email]);
        }
        if (!$row && $phone !== '') {
            $row = Db::one('SELECT blocked, noshow_count FROM clients WHERE phone = ? ORDER BY noshow_count DESC LIMIT 1', [$phone]);
        }
        $count = $row ? (int) $row['noshow_count'] : 0;
        $blockAfter = Settings::int('noshow_block_after', 0);
        $depositAfter = Settings::int('noshow_deposit_after', 0);
        return [
            'blocked' => $row ? ((int) $row['blocked'] === 1 || ($blockAfter > 0 && $count >= $blockAfter)) : false,
            'require_deposit' => $depositAfter > 0 && $count >= $depositAfter,
            'noshow_count' => $count,
        ];
    }

    public static function adjustNoShow(?int $clientId, int $delta): void
    {
        if ($clientId) {
            Db::q('UPDATE clients SET noshow_count = GREATEST(0, CAST(noshow_count AS SIGNED) + ?), updated_at = ? WHERE id = ?', [$delta, Clock::utc(), $clientId]);
        }
    }
}
