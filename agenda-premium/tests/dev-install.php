<?php
declare(strict_types=1);

// Instalación rápida para desarrollo y pruebas (CLI). Recrea la base de datos indicada.
// Uso: php tests/dev-install.php --name=ap_dev --config=/tmp/ap-dev.config.php [--profession=otro] [--demo=1]
if (PHP_SAPI !== 'cli') {
    exit('Solo CLI');
}
$opts = getopt('', ['name:', 'config:', 'profession::', 'demo::', 'host::', 'user::', 'pass::']);
$name = $opts['name'] ?? 'ap_dev';
$cfg = $opts['config'] ?? '/tmp/ap-dev.config.php';
putenv('AP_CONFIG=' . $cfg);
@unlink($cfg);
@unlink(dirname($cfg) . '/installed.lock');
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/app/bootstrap.php';

$db = ['host' => $opts['host'] ?? '127.0.0.1', 'port' => 3306, 'name' => $name, 'user' => $opts['user'] ?? 'ap', 'pass' => $opts['pass'] ?? 'ap_test_pw'];
$pdo = new PDO('mysql:host=' . $db['host'] . ';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS `' . $name . '`');
$pdo->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$r = \App\Services\InstallService::install([
    'db' => $db,
    'business' => ['name' => 'Consultorio Demo', 'email' => 'demo@example.test', 'phone' => '55551234', 'whatsapp' => '55551234', 'timezone' => 'America/Guatemala'],
    'admin' => ['name' => 'Ana Demo', 'email' => 'admin@demo.test', 'password' => 'Demo#Admin2026'],
    'profession' => $opts['profession'] ?? 'otro',
    'demo' => !empty($opts['demo']),
    'base_url' => '',
]);
echo "Instalado. Config: {$cfg}\nAdmin: admin@demo.test / Demo#Admin2026\nCron token: {$r['cron_token']}\n";
