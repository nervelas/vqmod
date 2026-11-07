<?php
require __DIR__ . '/_boot.php';
if (!auth_check()) { http_response_code(401); exit; }
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, max-age=3600');
readfile(dirname(__DIR__) . '/includes/icon-index.json');
