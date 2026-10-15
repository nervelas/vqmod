<?php
require_once __DIR__ . '/core.php';
foreach (['matcher', 'notify', 'sources', 'seed', 'drafter'] as $f) require_once __DIR__ . '/' . $f . '.php';
function boot_settings(): void {
    $tz = S('timezone', 'America/Guatemala');
    @date_default_timezone_set(in_array($tz, timezone_identifiers_list(), true) ? $tz : 'America/Guatemala');
}
