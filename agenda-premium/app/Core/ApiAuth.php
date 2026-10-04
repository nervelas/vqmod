<?php
declare(strict_types=1);

namespace App\Core;

/** Claves de API: formato ap_ + 40 hex. Solo se guarda el hash SHA-256 y un prefijo visible. */
final class ApiAuth
{
    /** @return array{key:string,id:int} la clave en claro se muestra UNA sola vez */
    public static function issue(string $name, string $scope, ?int $userId): array
    {
        $plain = 'ap_' . bin2hex(random_bytes(20));
        $id = Db::insert('api_keys', [
            'name' => Str::clean($name, 120),
            'key_prefix' => substr($plain, 0, 10),
            'key_hash' => hash('sha256', $plain),
            'scope' => $scope === 'write' ? 'write' : 'read',
            'created_by' => $userId,
            'created_at' => Clock::utc(),
        ]);
        return ['key' => $plain, 'id' => $id];
    }

    public static function revoke(int $id): void
    {
        Db::update('api_keys', ['revoked_at' => Clock::utc()], 'id = ? AND revoked_at IS NULL', [$id]);
    }

    /**
     * Valida la clave y el límite de frecuencia.
     * @return array{ok:bool,status?:int,error?:string,key?:array}
     */
    public static function authenticate(Request $req, string $needScope = 'read'): array
    {
        $h = (string) $req->header('Authorization');
        $key = '';
        if (stripos($h, 'Bearer ') === 0) {
            $key = trim(substr($h, 7));
        } elseif ($req->header('X-API-Key')) {
            $key = trim((string) $req->header('X-API-Key'));
        }
        if ($key === '' || !preg_match('/^ap_[a-f0-9]{40}$/', $key)) {
            return ['ok' => false, 'status' => 401, 'error' => 'Falta una clave de API válida (Authorization: Bearer ap_...).'];
        }
        $row = Db::one('SELECT * FROM api_keys WHERE key_hash = ?', [hash('sha256', $key)]);
        if (!$row) {
            return ['ok' => false, 'status' => 401, 'error' => 'La clave de API no es válida.'];
        }
        if ($row['revoked_at'] !== null) {
            return ['ok' => false, 'status' => 401, 'error' => 'La clave de API fue revocada.'];
        }
        $limit = max(1, Settings::int('api_rate_per_minute', 60));
        if (!RateLimiter::hit('api:' . $row['id'], $limit, 60)) {
            return ['ok' => false, 'status' => 429, 'error' => 'Superaste el límite de solicitudes por minuto. Intenta de nuevo en un momento.'];
        }
        if ($needScope === 'write' && $row['scope'] !== 'write') {
            return ['ok' => false, 'status' => 403, 'error' => 'Esta clave solo tiene permiso de lectura.'];
        }
        Db::update('api_keys', ['last_used_at' => Clock::utc()], 'id = ?', [$row['id']]);
        return ['ok' => true, 'key' => $row];
    }
}
