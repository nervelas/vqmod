<?php
require __DIR__ . '/../../theme/servicom/inc/luxe-art.php';
require __DIR__ . '/pal.php';
$fail = 0; $max = 0;
function ok($c, $m) { global $fail; if (!$c) { $fail++; echo "FAIL: $m\n"; } }
$pals = art_palettes();
$variants = array_keys(sc_art_variants());
ok(count(sc_art_motifs()) === 9, 'motifs');
$html = '';
$seed = 100;
foreach (sc_art_motifs() as $m) foreach ($pals as $pn => $p) foreach ($variants as $v) {
    $seed += 7;
    $svg = sc_art($seed, $m, $p, $v);
    $len = strlen($svg); $max = max($max, $len);
    ok($len < 14000, "size $m/$pn/$v = $len");
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    ok($dom->loadXML($svg), "xml $m/$pn/$v");
    libxml_clear_errors();
    ok(stripos($svg, '<script') === false && stripos($svg, 'href') === false && stripos($svg, 'xlink') === false, "forbidden $m/$pn/$v");
    ok(strpos($svg, 'preserveAspectRatio="xMidYMid slice"') !== false && strpos($svg, "sc-art--$v") !== false, "attrs");
    $html .= "<figure class=\"f f-$v\"><div class=\"b\">$svg</div><figcaption>$m · $pn · $v · " . sc_art_contrast_hint($p) . "</figcaption></figure>\n";
}
// determinismo
foreach ($pals as $p) foreach (sc_art_motifs() as $m) {
    sc_art_reset_counter(); $a = sc_art(42, $m, $p, 'cover');
    sc_art_reset_counter(); $b = sc_art(42, $m, $p, 'cover');
    sc_art_reset_counter(); $c = sc_art(43, $m, $p, 'cover');
    ok($a === $b, "determinismo $m"); ok($a !== $c, "distinto seed $m");
}
// ids unicos con 12 llamadas
$page = ''; for ($i = 0; $i < 12; $i++) $page .= sc_art(5, sc_art_motifs()[$i % 9], $pals['oro-negro'], 'card');
preg_match_all('/\bid="([^"]+)"/', $page, $mm);
ok(count($mm[1]) === count(array_unique($mm[1])), 'ids duplicados');
// todas las referencias url(#) resuelven
preg_match_all('/url\(#([^)]+)\)/', $page, $refs);
foreach (array_unique($refs[1]) as $id) ok(in_array($id, $mm[1], true), "ref rota $id");
// paleta parcial / basura
ok(strlen(sc_art(1, 'zzz', array('bg' => 'rojo'), 'nope')) > 500, 'defaults');
ok(in_array(sc_art_contrast_hint($pals['oro-negro']), array('dark','light'), true), 'hint');
ok(sc_art_contrast_hint($pals['oro-negro']) === 'dark' && sc_art_contrast_hint($pals['esmeralda-crema']) === 'light', 'hint valores');
echo "max bytes: $max\n";
$css = 'body{margin:0;background:#888;font:12px sans-serif;color:#fff;padding:12px}.g{display:flex;flex-wrap:wrap;gap:10px}figure{margin:0}.b{position:relative;overflow:hidden}figcaption{padding:2px 0}'
 . '.f-cover .b{width:480px;height:270px}.f-wide .b{width:630px;height:270px}.f-portrait .b{width:240px;height:300px}.f-square .b{width:270px;height:270px}.f-card .b{width:405px;height:270px}';
file_put_contents(__DIR__ . '/sheet.html', "<!doctype html><meta charset=utf-8><style>$css</style><div class=g>$html</div>");
echo $fail ? "FALLOS: $fail\n" : "OK\n";
exit($fail ? 1 : 0);
