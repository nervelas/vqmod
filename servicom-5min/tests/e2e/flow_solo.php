<?php
declare(strict_types=1);
/** Modo «solo archivo»: el cliente sube ÚNICAMENTE un PDF; el sistema extrae logo, fotos, textos y colores y construye la web LUXE. */
require __DIR__ . '/lib.php';
reset_limits();
file_put_contents('/tmp/s5test/run/ai-mode', 'ok');
$pdf = dirname(__DIR__) . '/fixtures/solo_pdf/empresa.pdf';
$c = new Client();
$g = $c->req('GET', '/guia-presentacion', null, [], false);
t_ok($g['status'] === 200 && str_contains($g['body'], 'Lo esencial') && str_contains($g['body'], 'plantilla'), 'guía «qué poner en el PDF» visible');
$pl = $c->req('GET', '/guia-presentacion/plantilla', null, [], false);
$ej = $c->req('GET', '/assets/ejemplos/ejemplo-presentacion-servicios-legales.pdf', null, [], false);
t_ok($ej['status'] === 200 && str_starts_with($ej['body'], '%PDF'), 'PDF de ejemplo descargable');
t_ok($pl['status'] === 200 && str_contains($pl['headers'], 'attachment') && str_contains($pl['body'], 'NOMBRE DEL NEGOCIO'), 'plantilla descargable');
$w = $c->page('/crear'); t_ok(str_contains($w['body'], '/guia-presentacion'), 'el asistente enlaza a la guía');
$tok = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
$c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['acepto' => true]]]);
$u = $c->upload($tok, 'presentacion', $pdf, 'application/pdf'); t_ok(!empty($u['json']['ok']), 'PDF subido', json_encode($u['json']));
$c->api('POST', "/api/borrador/$tok/analizar");
$a = [];
for ($i = 0; $i < 90; $i++) { $a = $c->api('GET', "/api/borrador/$tok/analisis"); if (in_array($a['json']['estado'] ?? '', ['lista', 'error'], true)) { break; } usleep(700000); }
t_ok(($a['json']['estado'] ?? '') === 'lista', 'análisis listo', json_encode($a['json'] ?? []));
$res = $a['json']['resultado'] ?? [];
t_ok(count($res['imagenes'] ?? []) >= 4, 'imágenes extraídas del PDF: ' . count($res['imagenes'] ?? []));
t_ok(count(array_filter($res['imagenes'] ?? [], fn($x) => !empty($x['logo']))) >= 1, 'se detectó un logo candidato');
$r = $c->api('POST', "/api/borrador/$tok/auto-confirmar");
t_ok(!empty($r['json']['ok']), 'auto-confirmar', json_encode($r['json']));
$d = $c->api('GET', "/api/borrador/$tok")['json']['data'] ?? [];
t_ok(($d['negocio']['nombre'] ?? '') === 'Grupo Aurora Ingeniería', 'nombre tomado del PDF');
t_ok(!empty($d['negocio']['logo']), 'logo tomado del PDF');
t_ok(count($d['contenido']['servicios'] ?? []) === 4, 'servicios del PDF: ' . count($d['contenido']['servicios'] ?? []));
t_ok(($d['contacto']['telefono'] ?? '') !== '' && ($d['contacto']['correo'] ?? ($d['correo_contacto'] ?? '')) !== '', 'teléfono y correo del PDF');
t_ok(empty($d['contacto']['whatsapp']), 'sin WhatsApp: se deja vacío (no se inventa)');
t_ok(count($d['presentacion']['colores'] ?? []) >= 1, 'colores de marca guardados');
sleep(2);
$r = $c->api('POST', "/api/borrador/$tok/crear", ['t0' => (time() - 60) * 1000]);
t_ok(!empty($r['json']['ok']), 'construcción iniciada SOLO con el PDF', json_encode($r['json']));
$t0 = time(); $last = [];
while (time() - $t0 < 600) { $last = $c->api('GET', "/api/borrador/$tok/construccion")['json'] ?? []; if (($last['estado'] ?? '') !== 'construyendo') { break; } usleep(500000); }
t_ok(($last['estado'] ?? '') === 'lista', 'vista previa lista (estado=' . ($last['estado'] ?? '?') . ')', json_encode($last));
if (($last['estado'] ?? '') !== 'lista') { exit(1); }
$url = $last['url']; file_put_contents('/tmp/s5test/run/last_url', $url . "\n");
$uu = parse_url($url); $site = new Client('http://127.0.0.1:8200', $uu['host'] . ':8200');
$site->req('GET', '/?scpk=' . substr($uu['query'], 5)); $h = $site->req('GET', '/');
t_ok($h['status'] === 200 && str_contains($h['body'], 'Grupo Aurora'), 'portada con el nombre');
t_ok(preg_match('/--lx-primary:\s*#([0-9a-f]{6})/i', $h['body'], $m) === 1, 'color primario definido');
if ($m) { $rgb = array_map('hexdec', str_split($m[1], 2)); $hue = (function ($r, $g, $b) { $r /= 255; $g /= 255; $b /= 255; $mx = max($r, $g, $b); $mn = min($r, $g, $b); if ($mx == $mn) return -1; $d = $mx - $mn; $h = $mx == $r ? fmod(($g - $b) / $d, 6) : ($mx == $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4); return fmod($h * 60 + 360, 360); })(...$rgb);
    t_ok(($hue >= 195 && $hue <= 260) || ($hue >= 25 && $hue <= 50), 'paleta derivada del logo (azul/naranja), matiz=' . round($hue), '#' . $m[1]); }
// El logo del PDF es azul (#0b3d91) con triángulo naranja; la IA «sugiere» otros colores: deben mandar los del LOGO.
t_ok(preg_match('/--lx-accent:\s*#([0-9a-f]{6})/i', $h['body'], $ma) === 1, 'color de acento definido');
if (!empty($ma)) { [$ar, $ag, $ab] = array_map('hexdec', str_split($ma[1], 2)); t_ok($ar > $ab, 'acento cálido tomado del triángulo naranja del logo', '#' . $ma[1]); }
t_ok(substr_count($h['body'], '<img') >= 4, 'fotos del PDF en la web: ' . substr_count($h['body'], '<img') . ' imágenes');
t_ok(str_contains($h['body'], 'lx-fab'), 'botones flotantes presentes');
t_ok(!preg_match('/(Warning|Notice|Fatal error|Deprecated):/', $h['body']), 'sin mensajes PHP');
t_ok(!str_contains($h['body'], 'wa.me/?') && !str_contains($h['body'], 'wa.me/"'), 'sin enlaces de WhatsApp vacíos');
echo "  vista previa: $url\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
