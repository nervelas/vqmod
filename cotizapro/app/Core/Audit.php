<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(string $action, string $entity = '', int $entityId = 0, array $details = []): void
    {
        try {
            DB::insert('audit_log', [
                'user_id'    => Auth::id() ?: null,
                'user_name'  => Auth::user()['name'] ?? 'sistema',
                'action'     => mb_substr($action, 0, 80),
                'entity'     => mb_substr($entity, 0, 60),
                'entity_id'  => $entityId,
                'details'    => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip'         => App::ip(),
                'user_agent' => App::userAgent(),
                'created_at' => nowSql(),
            ]);
        } catch (\Throwable $e) {
            ErrorHandler::log('No se pudo escribir en la bitácora: ' . $e->getMessage());
        }
    }
}
