<?php
declare(strict_types=1);
// Configura el host simulado y los ajustes del portal de pruebas. Uso: php seed.php /tmp/s5test
$T = $argv[1] ?? '/tmp/s5test';
define('S5_ROOT', $T . '/portal');
require S5_ROOT . '/app/bootstrap.php';
use S5\Core\Settings; use S5\Services\Hosts;
Settings::set('force_scheme', 'http');
Settings::set('wa_servicom', '50255550000');
Settings::set('banco_nombre', 'Banco de Prueba'); Settings::set('banco_cuenta', '000-111222-3'); Settings::set('banco_titular', 'Servicom de Prueba'); Settings::set('banco_tipo', 'Monetaria');
Settings::set('webs_path', $T . '/webs');
$id = Hosts::save(null, 'Hosting simulado 1', 'sim', [
    'webs_path' => $T . '/webs', 'domain_root' => 'servicom.test', 'sim_dir' => $T . '/sim', 'sim_vroot' => $T . '/vroot',
    'sim_db' => ['dsn' => 'mysql:host=localhost;charset=utf8mb4', 'user' => 's5admin', 'pass' => 's5adminpass', 'host' => 'localhost'], 'sim_prefix' => 'sim_',
    'php_cli' => '/usr/bin/php', 'loopback_url' => 'http://127.0.0.1:8200',
]);
Settings::set('default_host', (string) $id);
echo "host $id\n";
