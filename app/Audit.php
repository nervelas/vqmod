<?php
/**
 * Audit trail. Records who changed what, with before/after snapshots.
 */
class Audit
{
    public static function log(string $action, string $module, $recordId = null,
                               ?array $before = null, ?array $after = null): void
    {
        try {
            $u = Auth::user();
            Database::q(
                "INSERT INTO audit_log (user_id, user_name, action, module, record_id, before_json, after_json, ip)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $u['id']   ?? null,
                    $u['name'] ?? 'sistema',
                    $action,
                    $module,
                    $recordId !== null ? (string)$recordId : null,
                    $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                    $after  !== null ? json_encode($after, JSON_UNESCAPED_UNICODE)  : null,
                    client_ip(),
                ]
            );
        } catch (Throwable $e) {
            // Auditing must never break the main flow.
        }
    }
}
