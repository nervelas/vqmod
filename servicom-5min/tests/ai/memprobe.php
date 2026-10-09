<?php
declare(strict_types=1);
/**
 * Sonda en proceso aparte: php memprobe.php <archivo> <nombre> [maxBytes] [workdir]
 * Imprime JSON con inspect(), extract() (si ok) y el pico de memoria real.
 */
require __DIR__ . '/bootstrap.php';
use S5\Services\PresentationParser;

$f = $argv[1];
$nombre = $argv[2] ?? basename($f);
$max = (int)($argv[3] ?? 10485760);
$wd = $argv[4] ?? sys_get_temp_dir() . '/memprobe_' . getmypid();
$t0 = microtime(true);
$r = ['inspect' => PresentationParser::inspect($f, $nombre, $max)];
if ($r['inspect']['ok']) {
    try {
        $x = PresentationParser::extract($f, $r['inspect']['tipo'], $wd);
        $r['extract'] = ['chars' => strlen($x['texto']), 'imagenes' => count($x['imagenes']), 'paginas' => $x['paginas'], 'texto' => mb_substr($x['texto'], 0, 400)];
    } catch (\Throwable $e) {
        $r['extract'] = ['error' => $e->getMessage(), 'codigo' => $e->codigo ?? ''];
    }
}
$r['peak_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);
$r['rss_mb'] = 0;
if (is_readable('/proc/self/status') && preg_match('/VmHWM:\s+(\d+)\s+kB/', (string)file_get_contents('/proc/self/status'), $m)) {
    $r['rss_mb'] = round($m[1] / 1024, 1);
}
$r['seg'] = round(microtime(true) - $t0, 2);
echo json_encode($r, JSON_UNESCAPED_UNICODE), "\n";
