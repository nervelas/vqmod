<?php
declare(strict_types=1);

namespace App\Core;

final class Auditoria
{
    public static function registrar(string $accion, ?string $entidad = null, ?int $entidadId = null, string $detalle = ''): void
    {
        try {
            $u = Auth::usuario();
            DB::insertar('auditoria', [
                'usuario_id' => $u['id']     ?? null,
                'usuario'    => $u['nombre'] ?? 'sistema',
                'accion'     => substr($accion, 0, 80),
                'entidad'    => $entidad !== null ? substr($entidad, 0, 60) : null,
                'entidad_id' => $entidadId,
                'detalle'    => $detalle !== '' ? substr($detalle, 0, 4000) : null,
                'ip'         => Peticion::ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Auditoría: ' . $e->getMessage());
        }
    }
}
