<?php
// CLI: instala WordPress en $argv[1] con URL $argv[2] y siembra datos de prueba.
if (PHP_SAPI !== 'cli') { exit; }
$site = $argv[1]; $url = $argv[2];
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT);
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_NAME'] = '127.0.0.1'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$r = wp_install('Negocio de Prueba', 'admin_user', 'admin@servicom.test', true, '', 'Adm1n-Test-Pass!', 'es_ES');
if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\n"); exit(1); }
echo "WP instalado: admin ID {$r['user_id']}\n";
