<?php
/**
 * Arranque común del panel de administración.
 */
define('TLL_DEPTH', 1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/schema.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/_ui.php';
security_headers(true);
