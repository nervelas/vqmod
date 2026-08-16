<?php
/**
 * Public endpoint: store or remove a browser's push subscription.
 * Receives JSON: {endpoint, keys:{p256dh, auth}} plus X-CSRF-Token header.
 */
require __DIR__ . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { json_response(['ok' => false], 503); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'error' => 'method'], 405);
}
if (!Security::checkCsrf()) {
    json_response(['ok' => false, 'error' => 'csrf'], 419);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { json_response(['ok' => false, 'error' => 'json'], 400); }

$action   = $data['action'] ?? 'subscribe';
$endpoint = $data['endpoint'] ?? ($data['subscription']['endpoint'] ?? '');
$keys     = $data['keys'] ?? ($data['subscription']['keys'] ?? []);
$leagueId = isset($data['league_id']) ? (int)$data['league_id'] : null;

if (!is_string($endpoint) || !preg_match('#^https://#', $endpoint)) {
    json_response(['ok' => false, 'error' => 'endpoint'], 400);
}

try {
    Push::ensureSchema();
    if ($action === 'unsubscribe') {
        Database::q("DELETE FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
        json_response(['ok' => true, 'unsubscribed' => true]);
    }
    $p256dh = $keys['p256dh'] ?? '';
    $auth   = $keys['auth'] ?? '';
    if ($p256dh === '' || $auth === '') { json_response(['ok' => false, 'error' => 'keys'], 400); }
    Push::saveSubscription($endpoint, $p256dh, $auth, $leagueId ?: null);
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'server'], 500);
}
