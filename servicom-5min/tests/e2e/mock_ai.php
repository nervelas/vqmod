<?php
declare(strict_types=1);
/**
 * API de Claude SIMULADA (solo pruebas). php -S 127.0.0.1:8210 tests/e2e/mock_ai.php
 * Modo en /tmp/s5test/run/ai-mode: ok | error500 | invalid | credit | naive | slow
 */
$mode = trim((string) @file_get_contents('/tmp/s5test/run/ai-mode')) ?: 'ok';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
@file_put_contents('/tmp/s5test/run/ai-calls.log', date('c') . " $mode $path\n", FILE_APPEND);
header('Content-Type: application/json');
if ($path !== '/v1/messages') { http_response_code(404); echo '{}'; return; }
$body = json_decode((string) file_get_contents('php://input'), true) ?: [];
if ($mode === 'error500') { http_response_code(500); echo json_encode(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'boom']]); return; }
if ($mode === 'credit') { http_response_code(400); echo json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low to access the Anthropic API.']]); return; }
if ($mode === 'slow') { sleep(40); }
$user = ''; $hasDoc = false;
foreach (($body['messages'][0]['content'] ?? []) as $c) {
    if (($c['type'] ?? '') === 'text') { $user .= $c['text']; }
    if (($c['type'] ?? '') === 'document') { $hasDoc = true; }
}
if (is_string($body['messages'][0]['content'] ?? null)) { $user = $body['messages'][0]['content']; }
$out = '';
if (str_contains($user, '<datos_cliente>')) {
    preg_match('#<datos_cliente>\s*(.*?)\s*</datos_cliente>#s', $user, $m);
    $d = json_decode($m[1] ?? '{}', true) ?: [];
    $n = $d['negocio']['nombre'] ?? 'su negocio';
    $r = [
        'hero_titulo' => 'Bienvenido a ' . $n, 'hero_subtitulo' => 'Atención profesional y cercana para usted.', 'hero_boton' => 'Escríbanos',
        'servicios_titulo' => 'Nuestros servicios', 'servicios_intro' => 'Conozca lo que ofrecemos en ' . $n . '.',
        'nosotros_titulo' => 'Quiénes somos', 'nosotros_texto' => ($d['quienes'] ?? '') ?: ('En ' . $n . ' trabajamos para atenderle.'),
        'cta_titulo' => '¿Hablamos?', 'cta_texto' => 'Estamos listos para atenderle.', 'cta_boton' => 'Contáctenos',
        'contacto_titulo' => 'Contacto', 'contacto_intro' => 'Escríbanos o llámenos.', 'galeria_titulo' => 'Galería',
        'tienda_titulo' => 'Nuestra tienda', 'tienda_intro' => 'Explore nuestros productos.',
        'servicios' => array_map(fn($s) => ['resumen' => 'Servicio: ' . ($s['nombre'] ?? ''), 'descripcion' => ($s['descripcion'] ?? '') ?: ('Ofrecemos ' . ($s['nombre'] ?? 'este servicio') . '.')], $d['servicios'] ?? []),
    ];
    if ($mode === 'naive') { $r['script'] = '<script>alert(1)</script>'; $r['hero_titulo'] = '<b>' . $r['hero_titulo'] . '</b> Garantizamos 25 años de experiencia'; }
    $out = json_encode($r, JSON_UNESCAPED_UNICODE);
} else {
    // extracción de presentación: heurística sobre el texto recibido
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $user) ?: [])));
    $name = ''; $servs = []; $tel = ''; $mail = '';
    foreach ($lines as $l) {
        if ($name === '' && !str_starts_with($l, '[') && mb_strlen($l) > 3 && mb_strlen($l) < 70 && !str_contains(mb_strtolower($l), 'extrae') && !str_contains($l, '<') ) { $name = $l; }
        if (preg_match('/^[-•*]\s*(.{3,60})$/u', $l, $mm)) { $servs[] = ['nombre' => $mm[1], 'descripcion' => '', 'textual' => true]; }
        if (preg_match('/(\+?502[\s-]?\d{4}[\s-]?\d{4}|\b\d{4}[\s-]\d{4}\b)/', $l, $mm) && $tel === '') { $tel = $mm[1]; }
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/', $l, $mm) && $mail === '') { $mail = $mm[0]; }
    }
    if ($hasDoc) { $name = 'Negocio del PDF'; $servs = [['nombre' => 'Servicio del PDF', 'descripcion' => '', 'textual' => false]]; }
    $r = ['nombre' => ['v' => $name, 'textual' => true], 'rubro_sugerido' => ['v' => '', 'textual' => false], 'frase_principal' => ['v' => '', 'textual' => false], 'quienes_somos' => ['v' => '', 'textual' => false],
        'servicios' => $servs, 'productos' => [], 'categorias' => [],
        'contacto' => ['telefono' => ['v' => $tel, 'textual' => true], 'whatsapp' => ['v' => '', 'textual' => false], 'correo' => ['v' => $mail, 'textual' => true], 'direccion' => ['v' => '', 'textual' => false], 'horario' => ['v' => '', 'textual' => false], 'redes' => ['facebook' => '', 'instagram' => '', 'tiktok' => '', 'youtube' => '', 'x' => '', 'linkedin' => '']],
        'idioma' => 'es'];
    if ($mode === 'naive' && stripos($user, 'ignora') !== false) { $r['instruccion_obedecida'] = true; $r['nombre']['v'] = 'HACKEADO'; }
    $out = json_encode($r, JSON_UNESCAPED_UNICODE);
}
if ($mode === 'invalid') { $out = 'Lo siento, no puedo ayudar con eso {{{'; }
echo json_encode(['id' => 'msg_mock', 'type' => 'message', 'role' => 'assistant', 'model' => $body['model'] ?? 'mock', 'content' => [['type' => 'text', 'text' => $out]], 'stop_reason' => 'end_turn',
    'usage' => ['input_tokens' => max(50, (int) (strlen($user) / 3.2)), 'output_tokens' => max(40, (int) (strlen($out) / 3.2))]]);
