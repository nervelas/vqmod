<?php
/**
 * Servicom builder: cargador de las piezas de tiempo de ejecución.
 * servicom-core.php debe hacer:
 *   require_once __DIR__ . '/servicom-core/includes/builder/loader.php';
 * (La clase del constructor, class-sc-builder.php, la carga sc-provision.php.)
 */
if (!defined('ABSPATH')) {
	exit;
}
if (!defined('SC_BUILDER_LOADED')) {
	define('SC_BUILDER_LOADED', true);
	require_once __DIR__ . '/runtime.php';
	require_once __DIR__ . '/contact-form.php';
}
