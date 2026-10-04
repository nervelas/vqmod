<?php
declare(strict_types=1);

// Ejecuta todas las pruebas: php tests/run.php [filtro]
$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/cases/*_test.php') ?: [];
sort($files);
$fail = 0;
$total = 0;
foreach ($files as $f) {
    if ($filter !== '' && strpos(basename($f), $filter) === false) {
        continue;
    }
    $total++;
    echo str_repeat('=', 70) . "\n" . basename($f) . "\n";
    $out = [];
    exec('php ' . escapeshellarg($f) . ' 2>&1', $out, $code);
    echo implode("\n", $out) . "\n";
    if ($code !== 0) {
        $fail++;
        echo ">>> FALLÓ (código {$code})\n";
    }
}
echo str_repeat('=', 70) . "\nArchivos: {$total}, con fallos: {$fail}\n";
exit($fail > 0 ? 1 : 0);
