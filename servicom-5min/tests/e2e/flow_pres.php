<?php
declare(strict_types=1);
/** Presentaciones por HTTP: válidas, inválidas, maliciosas; análisis asíncrono; prioridad del formulario; inyección de prompt. */
require __DIR__ . '/lib.php';
$F = dirname(__DIR__) . '/fixtures/presentaciones/out';
$mode = fn(string $m) => file_put_contents('/tmp/s5test/run/ai-mode', $m);
$mode('ok');

function newDraft(Client $c, string $plan = 'info'): string {
    $c->page('/crear');
    $tok = $c->api('POST', '/api/borrador', ['plan' => $plan])['json']['token'];
    $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['acepto' => true]]]);
    return $tok;
}
function upload(Client $c, string $tok, string $f): array { return $c->upload($tok, 'presentacion', $f, mime_content_type($f) ?: 'application/octet-stream'); }

echo "== Archivos rechazados (mensajes amables, sin gastar IA)\n";
$bad = ['vacio.pptx' => 'vac', 'corrupto.docx' => 'dañado|incompleto', 'protegido.pptx' => 'protegido|contraseña', 'protegido.pdf' => 'protegido|contraseña', 'macros.pptx' => 'macro', 'macros.pptm' => 'macro', 'antiguo.ppt' => 'PDF', 'antiguo.doc' => 'PDF', 'keynote.key' => 'PDF', 'diseno.canva' => 'PDF', 'script.exe' => '', 'falso.pdf' => '', 'grande.pdf' => 'grande|MB', 'bomba_ceros.docx' => '', 'bomba_entradas.pptx' => '', 'bomba_ratio.pptx' => '', 'xml_enorme.docx' => '', 'zip_anidado.docx' => '', 'traversal.docx' => ''];
$calls0 = (int) trim((string) shell_exec('wc -l < /tmp/s5test/run/ai-calls.log 2>/dev/null'));
foreach ($bad as $f => $re) {
    $c = new Client(); $tok = newDraft($c);
    $t = microtime(true); $r = upload($c, $tok, "$F/$f");
    $msg = (string) ($r['json']['error'] ?? '');
    $okMsg = empty($r['json']['ok']) && $msg !== '' && ($re === '' || preg_match("/$re/iu", $msg));
    t_ok($okMsg, "rechazado: $f", "HTTP {$r['status']} «" . mb_substr($msg, 0, 90) . '»');
    t_ok(microtime(true) - $t < 15, "rechazo rápido: $f", round(microtime(true) - $t, 1) . 's');
}
$calls1 = (int) trim((string) shell_exec('wc -l < /tmp/s5test/run/ai-calls.log 2>/dev/null'));
t_ok($calls1 === $calls0, 'archivos rechazados no llamaron a la IA');

echo "== Presentación válida: análisis asíncrono → revisión → prioridad del formulario\n";
$c = new Client(); $c->page('/crear'); $tok = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
$c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['negocio' => ['nombre' => 'Mi Taller Real', 'rubro' => 'taller'], 'contacto' => ['telefono' => '5555-0000', 'whatsapp' => '50244445555']]]);
$r = upload($c, $tok, "$F/taller.pptx"); t_ok(empty($r['json']['ok']), 'sin aceptar el aviso no se sube');
$c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['acepto' => true]]]);
$r = upload($c, $tok, "$F/taller.pptx"); t_ok(!empty($r['json']['ok']), 'PPTX subido', json_encode($r['json']));
$t = microtime(true); $r = $c->api('POST', "/api/borrador/$tok/analizar");
t_ok(!empty($r['json']['ok']) && microtime(true) - $t < 5, 'analizar responde de inmediato (asíncrono)', round(microtime(true) - $t, 1) . 's ' . json_encode($r['json']));
$st = ''; for ($i = 0; $i < 60; $i++) { $a = $c->api('GET', "/api/borrador/$tok/analisis"); $st = $a['json']['estado'] ?? ''; if (in_array($st, ['lista', 'error'], true)) { break; } usleep(700000); }
t_ok($st === 'lista', 'análisis listo (estado=' . $st . ')', json_encode($a['json'] ?? []));
$res = $a['json']['resultado'] ?? [];
t_ok(!empty($res['servicios']), 'servicios extraídos: ' . count($res['servicios'] ?? []));
$antes = $c->api('GET', "/api/borrador/$tok")['json']['data'];
t_ok(($antes['negocio']['nombre'] ?? '') === 'Mi Taller Real', 'antes de confirmar el formulario no cambia');
t_ok(empty($antes['contenido']['servicios']), 'nada de la presentación pasa a los datos sin confirmar');
$imgs = array_column($res['imagenes'] ?? [], 'id');
$r = $c->api('POST', "/api/borrador/$tok/confirmar-presentacion", ['usar' => ['nombre' => true, 'contacto' => true, 'servicios' => array_keys($res['servicios'] ?? [])], 'fotos' => array_slice($imgs, 0, 2)]);
t_ok(!empty($r['json']['ok']), 'confirmación OK', json_encode($r['json'])); $d = $r['json']['data'] ?? [];
t_ok(($d['negocio']['nombre'] ?? '') === 'Mi Taller Real', 'el formulario manda sobre el nombre de la presentación');
t_ok(($d['contacto']['telefono'] ?? '') === '5555-0000', 'el formulario manda sobre el teléfono');
t_ok(count($d['contenido']['servicios'] ?? []) >= 1 && ($d['contenido']['servicios'][0]['origen'] ?? '') === 'pres', 'servicios confirmados marcados origen=pres');
t_ok(!empty($d['origen']), 'marcas «Tomado de tu presentación» presentes');

echo "== Inyección de prompt en el archivo\n";
$mode('naive');
foreach (['inyeccion.pptx', 'inyeccion.docx'] as $f) {
    $c = new Client(); $tok = newDraft($c); upload($c, $tok, "$F/$f"); $c->api('POST', "/api/borrador/$tok/analizar");
    for ($i = 0; $i < 60; $i++) { $a = $c->api('GET', "/api/borrador/$tok/analisis"); if (in_array($a['json']['estado'] ?? '', ['lista', 'error'], true)) { break; } usleep(700000); }
    $j = json_encode($a['json'] ?? []);
    t_ok(!str_contains($j, 'HACKEADO') && !str_contains($j, 'instruccion_obedecida'), "inyección ignorada: $f", substr($j, 0, 150));
}
$mode('ok');

echo "== IA caída / sin crédito / JSON inválido\n";
foreach (['error500', 'credit', 'invalid'] as $m) {
    $mode($m); $c = new Client(); $tok = newDraft($c); upload($c, $tok, "$F/abogado.pptx"); $c->api('POST', "/api/borrador/$tok/analizar");
    for ($i = 0; $i < 60; $i++) { $a = $c->api('GET', "/api/borrador/$tok/analisis"); if (in_array($a['json']['estado'] ?? '', ['lista', 'error'], true)) { break; } usleep(700000); }
    t_ok(($a['json']['estado'] ?? '') === 'error' && str_contains((string) ($a['json']['mensaje'] ?? ''), 'manualmente'), "IA $m → mensaje amable y continúa", json_encode($a['json'] ?? []));
    $g = $c->api('GET', "/api/borrador/$tok"); t_ok($g['status'] === 200, 'el borrador sigue usable');
}
$mode('ok');

echo "== Límite de 3 análisis por borrador\n";
$c = new Client(); $tok = newDraft($c); upload($c, $tok, "$F/abogado.pptx"); $last = '';
for ($k = 1; $k <= 4; $k++) {
    $r = $c->api('POST', "/api/borrador/$tok/analizar");
    for ($i = 0; $i < 40; $i++) { $a = $c->api('GET', "/api/borrador/$tok/analisis"); if (in_array($a['json']['estado'] ?? '', ['lista', 'error'], true)) { break; } usleep(600000); }
    $last = json_encode($r['json'], JSON_UNESCAPED_UNICODE);
}
t_ok(str_contains($last, '3 análisis'), 'cuarto análisis rechazado', $last);
echo "\nPresentaciones: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
