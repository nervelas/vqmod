<?php
declare(strict_types=1);
/**
 * Construye el paquete base de WordPress en <webs>/_base (una sola vez; repetir para actualizar).
 *   php tools/build_base.php [--webs=/ruta] [--locale=es_ES] [--wp-dir=/ruta/wordpress] [--plugins-dir=/ruta] [--skip-download]
 * Descarga SOLO de wordpress.org: WordPress y WooCommerce (versiones gratuitas oficiales).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('S5_ROOT', dirname(__DIR__));
require S5_ROOT . '/app/bootstrap.php';
$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opt[$m[1]] = $m[2] ?? '1'; }
}
$webs = $opt['webs'] ?? (class_exists('S5\\Services\\Hosts') && \S5\Core\Config::installed() ? \S5\Services\Hosts::defaultWebsPath() : dirname(S5_ROOT) . '/webs-clientes');
echo "Paquete base en: $webs/_base\n";
$b = new \S5\Services\BaseBuilder();
try {
    $r = $b->build([
        'base' => rtrim($webs, '/') . '/_base', 'locale' => $opt['locale'] ?? 'es_ES', 'wp_dir' => $opt['wp-dir'] ?? null,
        'plugins_dir' => $opt['plugins-dir'] ?? null, 'skip_download' => isset($opt['skip-download']),
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Versiones: " . json_encode($r['versions']) . "\n";
foreach ($r['notes'] as $n) { echo "- $n\n"; }
echo "Listo.\n";
