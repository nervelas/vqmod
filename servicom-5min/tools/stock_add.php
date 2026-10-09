<?php
declare(strict_types=1);
/** php tools/stock_add.php <rubro> <hero|servicio> <archivo> "alt" "crédito" "url_fuente" "licencia" */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
[$s, $rubro, $slot, $file, $alt, $credit, $src, $lic] = array_pad($argv, 8, '');
$rubros = ['abogado', 'clinica', 'taller', 'ropa', 'restaurante', 'transporte', 'contabilidad', 'importaciones', 'otro'];
if (!in_array($rubro, $rubros, true) || !in_array($slot, ['hero', 'servicio'], true) || !is_file($file) || $alt === '' || $credit === '' || $src === '' || $lic === '') {
    fwrite(STDERR, "Uso: php tools/stock_add.php <rubro> <hero|servicio> <archivo> \"alt\" \"crédito\" \"url_fuente\" \"licencia\"\n"); exit(1);
}
$im = @imagecreatefromstring((string) file_get_contents($file));
if (!$im) { fwrite(STDERR, "Imagen no válida\n"); exit(1); }
$w = imagesx($im); $h = imagesy($im); $sc = min(1.0, 1600 / max($w, $h));
if ($sc < 1) { $n = imagecreatetruecolor((int) round($w * $sc), (int) round($h * $sc)); imagecopyresampled($n, $im, 0, 0, 0, 0, imagesx($n), imagesy($n), $w, $h); $im = $n; }
@mkdir("$root/library/$rubro", 0755, true);
$name = "$rubro/$slot-" . substr(md5_file($file), 0, 8) . '.jpg';
imagejpeg($im, "$root/library/$name", 84);
$cat = json_decode((string) file_get_contents("$root/library/catalog.json"), true);
$cat['rubros'][$rubro][$slot][] = ['file' => $name, 'alt' => $alt, 'credit' => $credit, 'source_url' => $src, 'license' => $lic];
file_put_contents("$root/library/catalog.json", json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents("$root/library/CREDITOS.md", "- `$name` — $credit — $src — $lic\n", FILE_APPEND);
echo "Agregada: $name\n";
