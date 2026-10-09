<?php
declare(strict_types=1);
/** Construye el paquete base en el segundo hosting: php tools/build_base_agent.php [--locale=es_ES] */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('S5_ROOT', dirname(__DIR__));
require S5_ROOT . '/app/bootstrap_agent.php';
$cfgFile = null;
foreach ([dirname(S5_ROOT) . '/servicom-agent-secrets/config.php', S5_ROOT . '/storage/config.php'] as $f) { if (is_file($f)) { $cfgFile = $f; break; } }
if (!$cfgFile) { fwrite(STDERR, "Ejecute primero instalar-agente.php en el navegador.\n"); exit(1); }
$cfg = require $cfgFile;
$opt = [];
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opt[$m[1]] = $m[2] ?? '1'; } }
$b = new S5\Services\BaseBuilder();
try {
    $r = $b->build(['base' => rtrim($cfg['webs_path'], '/') . '/_base', 'locale' => $opt['locale'] ?? 'es_ES', 'wp_dir' => $opt['wp-dir'] ?? null, 'plugins_dir' => $opt['plugins-dir'] ?? null, 'skip_download' => isset($opt['skip-download'])]);
} catch (Throwable $e) { fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n"); exit(1); }
echo json_encode($r['versions']) . "\n"; foreach ($r['notes'] as $n) { echo "- $n\n"; } echo "Listo.\n";
