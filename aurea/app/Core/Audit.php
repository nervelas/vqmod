<?php
declare(strict_types=1);

namespace Aurea\Core;

final class Audit
{
    public static function log(string $action, string $entity = '', ?int $id = null, string $detail = '', ?array $user = null): void
    {
        try {
            $u = $user ?? Auth::user();
            Db::insert('audit_log', [
                'user_id' => $u['id'] ?? null,
                'user_name' => mb_substr((string)($u['name'] ?? 'sistema'), 0, 150),
                'action' => substr($action, 0, 60),
                'entity' => substr($entity, 0, 40),
                'entity_id' => $id,
                'detail' => mb_substr($detail, 0, 500),
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::exception($e);
        }
    }
}
