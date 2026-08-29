<?php
namespace MenuGold\Core;

/** Bitácora: quién hizo qué y desde dónde. Nunca interrumpe la operación. */
final class Audit
{
    public static function log($action, $entity = '', $entityId = 0, array $meta = array())
    {
        try {
            DB::insert('mg_audit_log', array(
                'user_id'    => Auth::check() ? Auth::id() : null,
                'action'     => substr((string)$action, 0, 80),
                'entity'     => substr((string)$entity, 0, 40),
                'entity_id'  => (int)$entityId,
                'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip'         => Security::clientIp(),
                'created_at' => date('Y-m-d H:i:s'),
            ));
        } catch (\Throwable $e) {
            Logger::warn('Audit: ' . $e->getMessage());
        }
    }
}
