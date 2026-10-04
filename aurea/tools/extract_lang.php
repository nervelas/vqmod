<?php
// Genera lang/es.php con todos los textos __('...') del sistema (clave = texto original). Uso: php tools/extract_lang.php
$root = dirname(__DIR__);
$found = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $src = file_get_contents($f->getPathname());
    if (preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m)) {
        foreach ($m[1] as $s) { $found[stripcslashes(str_replace("\\'", "'", $s))] = true; }
    }
}
ksort($found);
$out = "<?php\n// Textos de la interfaz de AUREA (español de Guatemala). Edita el valor (derecha) para cambiar cualquier texto.\n// Los marcadores %s, %d se reemplazan con datos; consérvalos.\nreturn [\n";
foreach (array_keys($found) as $k) { $out .= '    ' . var_export($k, true) . ' => ' . var_export($k, true) . ",\n"; }
file_put_contents($root . '/lang/es.php', $out . "];\n");
echo count($found) . " textos\n";
