<?php
// Prueba de iconos LUXE. Uso: php test.php   (genera también sheet.html)
if (!function_exists('esc_attr')) {
	function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
require __DIR__ . '/../../theme/servicom/inc/luxe-icons.php';

$contract = explode(' ', 'star shield check heart clock phone mail pin calendar users user handshake award crown gem sparkles lightbulb target rocket chat globe link lock key home building briefcase document pen book graduation chart trend coins wallet card receipt calculator percent tag gift bag cart truck box plane ship route map compass camera image video music play headset wrench gear hammer bolt car tools oil tire battery shirt scissors ruler hanger utensils coffee wine cake chef leaf flame droplet sun moon stethoscope pulse tooth pill syringe microscope eye brain bone baby paw scales gavel columns contract stamp fingerprint container warehouse barcode flag medal diamond thumbs trophy percent-badge support wifi cloud code printer facebook instagram tiktok youtube x linkedin whatsapp telegram');
$fail = 0;
function bad($m) { global $fail; $fail++; echo "FAIL: $m\n"; }
$keys = sc_icon_keys();
foreach (array_unique($contract) as $k) {
	if (!in_array($k, $keys, true)) bad("falta clave $k");
}
if (count($keys) !== count(array_unique($keys))) bad('claves duplicadas');
foreach ($keys as $k) {
	$svg = sc_icon($k, array('class' => 'x', 'size' => 32));
	$doc = new DOMDocument();
	if (!@$doc->loadXML($svg)) { bad("XML mal formado: $k"); continue; }
	if (stripos($svg, '<script') !== false) bad("script en $k");
	if (preg_match('/\son[a-z]+\s*=/i', $svg)) bad("on*= en $k");
	if (stripos($svg, 'href') !== false) bad("href en $k");
	if (strpos($svg, 'aria-hidden="true"') === false) bad("aria-hidden $k");
	if (sc_icon_label($k) === '') bad("label $k");
}
if (strpos(sc_icon('nope-xx'), 'sc-ic--star') === false) bad('fallback star');
if (strpos(sc_icon('<script>'), '<script') !== false) bad('clave hostil');
if (strpos(sc_icon('star', '"><script>x'), '<script') !== false) bad('clase hostil');
if (strpos(sc_icon('star', array('title' => 'A<b>')), '<b>') !== false) bad('title sin escapar');
$words = array('divorcio' => 'scales', 'consulta' => 'stethoscope', 'frenos' => 'car', 'envío' => 'truck', 'contabilidad' => 'calculator', 'impuestos' => 'calculator', 'vino' => 'wine', 'Cambio de aceite' => 'oil', 'dental' => 'tooth', 'importación' => 'container', 'ropa' => 'shirt');
foreach ($words as $w => $exp) {
	$g = sc_icon_for($w);
	if ($g !== $exp) bad("sc_icon_for($w)=$g esperado $exp");
}
$rub = array('abogado', 'clinica', 'taller', 'ropa', 'restaurante', 'transporte', 'contabilidad', 'importaciones', 'otro', '', 'zzz');
$samples = array('', 'xyzzy', 'Lorem ipsum', '¡Hola mundo!', 'qwerty 123', 'Servicio especial', 'Ñandú');
for ($i = 0; $i < 300; $i++) $samples[] = 'texto' . $i . md5((string) $i);
foreach ($rub as $r) foreach ($samples as $t) {
	$k = sc_icon_for($t, $r);
	if (!in_array($k, $keys, true)) bad("sc_icon_for inválido '$t'/$r => $k");
}
if (sc_icon_for('xyzzy', 'taller') !== sc_icon_for('xyzzy', 'taller')) bad('no determinista');
if (sc_icon_label('scales') !== 'Balanza') bad('label scales');

// Hoja de iconos
$cells = '';
foreach ($keys as $k) $cells .= '<div class="c">' . sc_icon($k, array('size' => 40)) . '<span>' . $k . '</span></div>';
$html = '<!doctype html><meta charset="utf-8"><style>body{margin:0;font:11px sans-serif}.p{padding:16px;display:grid;grid-template-columns:repeat(14,1fr);gap:10px}.d{background:#0f1115;color:#e9e4d8}.l{background:#faf8f3;color:#1c1a17}.c{display:flex;flex-direction:column;align-items:center;gap:6px}.c span{opacity:.6;font-size:9px}.sc-ic .a{stroke:#c8a45a}.l .sc-ic .a{stroke:#9a6b1f}</style><div class="p d">' . $cells . '</div><div class="p l">' . $cells . '</div>';
file_put_contents(__DIR__ . '/sheet.html', $html);
echo $fail ? "FALLOS: $fail\n" : 'OK ' . count($keys) . " iconos\n";
exit($fail ? 1 : 0);
