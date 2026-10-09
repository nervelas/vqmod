<?php
declare(strict_types=1);
/**
 * Agente de Servicom para el SEGUNDO hosting. Ejecuta localmente la creación de webs
 * (subdominio, base de datos, copia del paquete base, construcción) con el token de ESTE hosting.
 * Solo acepta mensajes firmados con HMAC (marca de tiempo + nonce) y, opcionalmente, desde IP permitidas.
 */
define('S5_ROOT', __DIR__);
require S5_ROOT . '/app/bootstrap_agent.php';

use S5\Provision\AgentServer;
use S5\Provision\CpanelHttpApi;
use S5\Provision\LocalDriver;

$cfgFile = null;
foreach ([getenv('S5_AGENT_CONFIG') ?: '', dirname(S5_ROOT) . '/servicom-agent-secrets/config.php', S5_ROOT . '/storage/config.php'] as $f) {
    if ($f !== '' && is_file($f)) { $cfgFile = $f; break; }
}
if ($cfgFile === null) {
    http_response_code(503);
    exit('Agente no configurado.');
}
$cfg = require $cfgFile;
if (!is_array($cfg) || strlen((string) ($cfg['secret'] ?? '')) < 32) {
    http_response_code(503);
    exit('Configuración inválida.');
}
$api = (!empty($cfg['sim']) && class_exists('S5\\Provision\\SimCpanelApi')) ? new S5\Provision\SimCpanelApi($cfg['sim']) : new CpanelHttpApi($cfg['cpanel']);
$driver = new LocalDriver([
    'webs_path' => $cfg['webs_path'], 'base_path' => $cfg['base_path'] ?? null, 'domain_root' => $cfg['domain_root'], 'api' => $api,
    'php_cli' => $cfg['php_cli'] ?? '', 'loopback_url' => $cfg['loopback_url'] ?? 'http://127.0.0.1',
    'fail_file' => $cfg['fail_file'] ?? '', 'verify_ssl' => $cfg['verify_ssl'] ?? true,
]);
@mkdir(S5_ROOT . '/storage', 0750, true);
(new AgentServer([
    'secret' => $cfg['secret'], 'allowed_ips' => $cfg['allowed_ips'] ?? [], 'storage' => $cfg['storage'] ?? (S5_ROOT . '/storage'), 'portal_url' => $cfg['portal_url'] ?? '',
], $driver))->handle();
