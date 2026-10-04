<?php
// Define versiones mínimas de servicios que aún no existan, para probar el núcleo de forma aislada.
foreach (['WorkflowService' => 'public static function fire(string $t, int $id): void {} public static function cancelPending(int $id): void {}',
          'WebhookService' => 'public static function dispatch(string $e, array $p): void {} public static function bookingPayload(int $id): array { return []; }',
          'LegalService' => 'public static function recordConsent(?int $c, ?int $b, ?string $e, string $ip): void {} public static function save(array $a): void {} public static function defaults(): array { return []; }',
          ] as $cls => $body) {
    if (!class_exists('App\\Services\\' . $cls)) {
        eval('namespace App\\Services; final class ' . $cls . ' { ' . $body . ' }');
    }
}
