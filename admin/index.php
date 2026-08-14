<?php
/** Admin front controller / router. */
declare(strict_types=1);

require __DIR__ . '/includes/admin.php';

$page = admin_page();
$file = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($file)) {
    $file = __DIR__ . '/pages/dashboard.php';
}
require $file;
