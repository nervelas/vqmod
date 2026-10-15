<?php
declare(strict_types=1);
/* Punto de entrada 1 de 2 con PHP activo: panel de administración. */
define('GX', 1);
define('GX_ROOT', dirname(__DIR__));
require __DIR__ . '/../_core/lib/bootstrap.php';
require_once __DIR__ . '/../_core/lib/install.php';
require_once __DIR__ . '/../_core/lib/mailer.php';
require_once __DIR__ . '/../_core/lib/scanner.php';
require_once __DIR__ . '/../_core/lib/admin_ui.php';
require_once __DIR__ . '/../_core/lib/admin_pages.php';
require_once __DIR__ . '/../_core/lib/admin_app.php';
gx_admin_main();
