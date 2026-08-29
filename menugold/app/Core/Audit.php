<?php
namespace MenuGold\Core;

final class Audit
{
    public static function log($action, $entity = '', $entityId = 0, array $details = array(), $restaurantId = null)
    {
        try {
            DB::insert('audit_log', array(
                'restaurant_id' => $restaurantId !== null ? (int)$restaurantId : (Auth::check() ? Auth::restaurantId() : 0),
                'user_id'       => Auth::check() ? Auth::id() : 0,
                'action'        => substr((string)$action, 0, 60),
                'entity'        => substr((string)$entity, 0, 60),
                'entity_id'     => (int)$entityId,
                'details'       => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip'            => Security::clientIp(),
                'user_agent'    => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 200) : '',
                'created_at'    => date('Y-m-d H:i:s'),
            ));
        } catch (\Throwable $e) {
            Logger::warn('Audit: ' . $e->getMessage());
        }
    }
}
