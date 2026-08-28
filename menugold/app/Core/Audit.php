<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Bitacora de auditoria: cambios de precio, anulaciones, cierres, accesos.
 */
final class Audit
{
    public static function log(string $accion, string $entidad = '', int $entidadId = 0, ?array $antes = null, ?array $despues = null, ?int $restaurantId = null): void
    {
        try {
            if (!DB::tableExists('audit_log')) return;
            DB::insert('audit_log', [
                'restaurant_id' => $restaurantId ?? (App::restaurantId() ?: (Auth::restaurantId() ?: null)),
                'user_id'       => Auth::id() ?: null,
                'usuario'       => Auth::nombre() ?: 'sistema',
                'accion'        => mb_substr($accion, 0, 60),
                'entidad'       => mb_substr($entidad, 0, 60),
                'entidad_id'    => $entidadId,
                'antes'         => $antes !== null ? json_encode(self::limpiar($antes), JSON_UNESCAPED_UNICODE) : null,
                'despues'       => $despues !== null ? json_encode(self::limpiar($despues), JSON_UNESCAPED_UNICODE) : null,
                'ip'            => client_ip(),
                'agente'        => Request::userAgent(),
                'creado'        => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warn('No se pudo escribir auditoria: ' . $e->getMessage());
        }
    }

    /** Registra solo si hubo cambios reales, indicando los campos modificados. */
    public static function diff(string $accion, string $entidad, int $id, array $antes, array $despues): void
    {
        $cambios = [];
        foreach ($despues as $k => $v) {
            $old = $antes[$k] ?? null;
            if ((string)$old !== (string)(is_array($v) ? json_encode($v) : $v)) {
                $cambios[$k] = ['antes' => $old, 'despues' => $v];
            }
        }
        if (!$cambios) return;
        self::log($accion, $entidad, $id, null, $cambios);
    }

    private static function limpiar(array $d): array
    {
        foreach (['password', 'password_hash', 'clave', 'smtp_pass', 'token', '_token'] as $k) {
            if (isset($d[$k])) $d[$k] = '***';
        }
        return $d;
    }
}
