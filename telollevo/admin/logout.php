<?php
require __DIR__ . '/_boot.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && auth_check() && csrf_valid()) {
    auth_logout();
}
header('Location: ' . url('admin/login.php'), true, 303);
exit;
