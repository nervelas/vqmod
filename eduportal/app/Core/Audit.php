<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(string $accion, ?string $entidad = null, ?int $entidadId = null, string $detalle = ''): void
    {
        if (!Database::isConnected()) {
            return;
        }
        try {
            Database::run(
                'INSERT INTO bitacora (user_id, accion, entidad, entidad_id, detalle, ip, agente)
                 VALUES (:u, :a, :e, :ei, :d, :ip, :ag)',
                [
                    'u'  => Auth::id(),
                    'a'  => substr($accion, 0, 60),
                    'e'  => $entidad !== null ? substr($entidad, 0, 60) : null,
                    'ei' => $entidadId,
                    'd'  => substr($detalle, 0, 500),
                    'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                    'ag' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('Bitacora fallo', ['e' => $e->getMessage()]);
        }
    }
}
