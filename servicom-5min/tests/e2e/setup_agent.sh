#!/usr/bin/env bash
# Segundo hosting simulado: agente en :8202 con su propia carpeta de webs y paquete base.
set -euo pipefail
PROJ="$(cd "$(dirname "$0")/../.." && pwd)"; T=/tmp/s5test
rm -rf $T/agent $T/webs2 /tmp/servicom-agent-secrets; mkdir -p $T/agent $T/webs2
(cd $T/agent && unzip -q "$PROJ/dist/servicom-5min-agente-TEST.zip")
SECRET=$(head -c 32 /dev/urandom | xxd -p -c 64)
mkdir -p $T/agent/storage
cat > $T/agent/storage/config.php <<PHP
<?php
return [
 'secret' => '$SECRET', 'allowed_ips' => [], 'webs_path' => '$T/webs2', 'domain_root' => 'servicom.test', 'portal_url' => 'http://crear.servicom.test:8201',
 'php_cli' => '/usr/bin/php', 'loopback_url' => 'http://127.0.0.1:8200', 'verify_ssl' => false,
 'sim' => ['sim_dir' => '$T/sim2', 'sim_vroot' => '$T/vroot', 'sim_prefix' => 'sm2_', 'sim_db' => ['dsn' => 'mysql:host=localhost;charset=utf8mb4', 'user' => 's5admin', 'pass' => 's5adminpass', 'host' => 'localhost']],
 'cpanel' => ['host' => 'localhost', 'port' => 2083, 'user' => 'x', 'token' => 'x'],
];
PHP
mkdir -p $T/sim2
chown -R www-data:www-data $T/agent $T/webs2 $T/sim2
runuser -u www-data -- php $T/agent/tools/build_base_agent.php --wp-dir=/opt/wp-core --skip-download
cp "$PROJ/wp-pack/tests-only/elementor-sim.php" $T/webs2/_base/wp-content/mu-plugins/; chown -R www-data:www-data $T/webs2
runuser -u www-data -- env S5_CONFIG_FILE=$T/config.php php -r "
define('S5_ROOT','$T/portal'); require '$T/portal/app/bootstrap.php';
\$id = S5\Services\Hosts::save(null,'Hosting 2 (agente)','agent',['agent_url'=>'http://127.0.0.1:8202/agent.php','agent_secret'=>'$SECRET','webs_path'=>'$T/webs2','domain_root'=>'servicom.test','verify_ssl'=>false]);
echo 'host agente '.\$id.PHP_EOL;"
