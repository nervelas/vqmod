<?php
/**
 * Servidor SIMULADO para probar el portal público (vistas + API del contrato §12).
 *   php -S 127.0.0.1:8131 -t /ruta/servicom-5min tests/portal/server.php
 * Cookies de simulación: sim=ok|slow|fail (análisis), build=ok|fail, demos=0 (sin ejemplos).
 * SOLO PRUEBAS: no hay seguridad real; estado en tests/portal/state/.
 */
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('~^/assets/~', $path) && is_file($root . $path)) { return false; }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { return 'test-csrf-token'; }

$cfg = [
    'wa_servicom' => '50255550000', 'precio_info' => 1250, 'precio_tienda' => 1750, 'precio_tarjeta' => 750,
    'dominio_base' => 'servicom.gt', 'portal_sub' => 'crear', 'pres_max_mb' => 10,
    'banco' => ['banco' => 'Banco Industrial', 'cuenta' => '000-123456-7', 'titular' => 'Servicom, S. A.', 'tipo' => 'Monetaria'],
    'demos' => [
        ['nombre' => 'Bufete Montenegro', 'url' => 'https://demo1.servicom.gt', 'rubro' => 'Abogados'],
        ['nombre' => 'Clínica Sonrisa Plena', 'url' => 'https://demo2.servicom.gt', 'rubro' => 'Clínica dental'],
        ['nombre' => 'Moda Aurora', 'url' => 'https://demo3.servicom.gt', 'rubro' => 'Tienda de ropa'],
    ],
];
if (($_COOKIE['demos'] ?? '') === '0') { $cfg['demos'] = []; }
$nonce = null;

function render(string $view, array $vars = [], int $status = 200): void
{
    global $cfg, $nonce, $root;
    http_response_code($status);
    extract($vars + ['cfg' => $cfg, 'nonce' => $nonce]);
    ob_start();
    include $root . '/app/views/portal/' . $view . '.php';
    $content = ob_get_clean();
    $page = $vars['page'] ?? $view;
    $title = $vars['title'] ?? 'Tu web en 5 minutos · Servicom';
    include $root . '/app/views/layout.php';
}
function jout(array $a, int $s = 200): void { http_response_code($s); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
$sdir = __DIR__ . '/state'; if (!is_dir($sdir)) { mkdir($sdir, 0775, true); }
function st_load(string $t): ?array { global $sdir; $f = "$sdir/$t.json"; return preg_match('/^[a-f0-9]{64}$/', $t) && is_file($f) ? json_decode(file_get_contents($f), true) : null; }
function st_save(string $t, array $s): void { global $sdir; file_put_contents("$sdir/$t.json", json_encode($s, JSON_UNESCAPED_UNICODE)); }
function body(): array { $j = json_decode(file_get_contents('php://input') ?: '', true); return is_array($j) ? $j : []; }

/* ---------- páginas ---------- */
if ($path === '/') { render('home', ['title' => 'Tu web en 5 minutos · Servicom']); return; }
if ($path === '/crear') { render('wizard', ['token' => null, 'draft' => null, 'plan' => $_GET['plan'] ?? null, 'title' => 'Crea tu web · Servicom']); return; }
if (preg_match('~^/continuar/([a-f0-9]{64})$~', $path, $m)) {
    $s = st_load($m[1]); if (!$s) { render('gone', [], 410); return; }
    render('wizard', ['token' => $m[1], 'plan' => null, 'title' => 'Crea tu web · Servicom', 'draft' => [
        'data' => $s['data'], 'estado' => $s['estado'], 'paso' => $s['paso'] ?? null, 'creado_en' => $s['creado_en'],
        'analisis' => $s['analisis_resultado_visible'] ?? null, 'archivos' => $s['archivos'] ?? new stdClass(),
    ]]); return;
}
if (preg_match('~^/vista-previa/([a-f0-9]{64})$~', $path, $m)) { render('status', ['token' => $m[1], 'estado' => 'construyendo', 'title' => 'Tu vista previa']); return; }
if (preg_match('~^/pago/([a-f0-9]{64})$~', $path, $m)) { render('pay', ['token' => $m[1], 'plan' => 'tienda', 'tarjeta' => true, 'title' => 'Pago']); return; }
if ($path === '/error') { $c = (int)($_GET['code'] ?? 500); render('error', ['code' => $c], $c); return; }
if ($path === '/gone') { render('gone', [], 410); return; }

/* ---------- API simulada ---------- */
if (strpos($path, '/api/') === 0) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && ($_SERVER['HTTP_X_CSRF'] ?? '') !== csrf_token()) { jout(['ok' => false, 'error' => 'Sesión vencida. Recarga la página.'], 403); }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($path === '/api/borrador' && $method === 'POST') {
        $b = body(); $plan = in_array($b['plan'] ?? '', ['info', 'tienda'], true) ? $b['plan'] : 'info';
        $t = bin2hex(random_bytes(32));
        st_save($t, ['data' => ['plan' => $plan], 'estado' => 'borrador', 'paso' => 'pres', 'creado_en' => time(), 'archivos' => new stdClass(), 'n' => 0]);
        jout(['ok' => true, 'token' => $t]);
    }
    if (!preg_match('~^/api/borrador/([a-f0-9]{64})(?:/([a-z-]+)(?:/(.+))?)?$~', $path, $m)) { jout(['ok' => false, 'error' => 'No encontrado.'], 404); }
    $t = $m[1]; $act = $m[2] ?? ''; $arg = $m[3] ?? '';
    $s = st_load($t); if (!$s) { jout(['ok' => false, 'error' => 'Borrador no encontrado.'], 404); }
    $mode = $_COOKIE['sim'] ?? 'ok'; $bmode = $_COOKIE['build'] ?? 'ok';

    if ($act === '' && $method === 'GET') { jout(['ok' => true, 'data' => $s['data'], 'estado' => $s['estado'], 'paso' => $s['paso'] ?? null, 'analisis' => null, 'construccion' => null]); }
    if ($act === 'guardar' && $method === 'POST') {
        $b = body(); if (!isset($b['data']) || !is_array($b['data'])) { jout(['ok' => false, 'error' => 'Datos inválidos.'], 422); }
        if (($_COOKIE['savefail'] ?? '') === '1') { jout(['ok' => false, 'error' => 'Error simulado.'], 500); }
        $s['data'] = $b['data']; $s['paso'] = (string)($b['paso'] ?? ''); st_save($t, $s);
        jout(['ok' => true, 'guardado_en' => date('c')]);
    }
    if ($act === 'subir' && $method === 'POST') {
        $tipo = $_POST['tipo'] ?? ''; $f = $_FILES['archivo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) { jout(['ok' => false, 'error' => 'No recibimos el archivo.'], 400); }
        $name = (string)$f['name']; $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($tipo === 'presentacion') {
            if (in_array($ext, ['ppt', 'doc', 'key'], true)) { jout(['ok' => false, 'error' => 'Formato antiguo.', 'mensaje' => 'Guárdala como PDF y vuelve a subirla.'], 415); }
            if (!in_array($ext, ['pdf', 'pptx', 'docx'], true)) { jout(['ok' => false, 'error' => 'Formato no permitido.'], 415); }
            if ($f['size'] > 10 * 1048576) { jout(['ok' => false, 'error' => 'El archivo es muy grande.'], 413); }
        } elseif ($tipo === 'comprobante') {
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'webp'], true) || $f['size'] > 5 * 1048576) { jout(['ok' => false, 'error' => 'Comprobante no válido (JPG, PNG o PDF de hasta 5 MB).'], 415); }
        } else {
            $mime = mime_content_type($f['tmp_name']);
            if (strpos((string)$mime, 'image/') !== 0) { jout(['ok' => false, 'error' => 'Solo se permiten imágenes.'], 415); }
        }
        $s['n']++; $id = 'f' . $s['n']; $dest = "$sdir/$t-$id"; move_uploaded_file($f['tmp_name'], $dest);
        $s['archivos'] = (array)$s['archivos']; $s['archivos'][$id] = ['tipo' => $tipo, 'mime' => mime_content_type($dest) ?: 'application/octet-stream', 'nombre' => $name];
        st_save($t, $s);
        usleep((int)($_COOKIE['slowup'] ?? 0) * 1000);
        jout(['ok' => true, 'id' => $id, 'nombre' => $name, 'url_miniatura' => in_array($tipo, ['foto', 'logo'], true) ? "/api/borrador/$t/archivo/$id" : null]);
    }
    if ($act === 'archivo') {
        if ($method === 'DELETE') { unset($s['archivos'][$arg]); st_save($t, $s); jout(['ok' => true]); }
        if (preg_match('/^pimg(\d)$/', $arg, $mm)) {
            $cols = ['#c9a24b', '#2f6bff', '#0f3b2e', '#b5532f']; $c = $cols[((int)$mm[1] - 1) % 4];
            header('Content-Type: image/svg+xml'); echo "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'><rect width='120' height='120' fill='$c'/><circle cx='60' cy='55' r='24' fill='#fff' opacity='.7'/></svg>"; exit;
        }
        $a = $s['archivos'][$arg] ?? null; if (!$a) { http_response_code(404); exit; }
        header('Content-Type: ' . $a['mime']); readfile("$sdir/$t-$arg"); exit;
    }
    if ($act === 'analizar' && $method === 'POST') { $s['analisis_ini'] = time(); st_save($t, $s); jout(['ok' => true, 'estado' => 'procesando']); }
    if ($act === 'analisis' && $method === 'GET') {
        $el = time() - (int)($s['analisis_ini'] ?? time());
        if ($mode === 'fail' && $el >= 3) { jout(['ok' => true, 'estado' => 'error', 'mensaje' => 'No pudimos leer tu presentación ahora; puedes llenar los datos manualmente.']); }
        $dur = $mode === 'slow' ? 20 : 4;
        if ($el < $dur) { jout(['ok' => true, 'estado' => 'procesando']); }
        $res = [
            'nombre' => ['v' => 'Bufete Montenegro & Asociados', 'textual' => true], 'rubro_sugerido' => ['v' => 'Servicios jurídicos', 'textual' => false],
            'frase_principal' => ['v' => 'Defendemos tus derechos con experiencia', 'textual' => true], 'quienes_somos' => ['v' => "Somos un bufete con enfoque en derecho civil y mercantil.\nAcompañamos a personas y empresas en cada etapa.", 'textual' => true],
            'servicios' => [['nombre' => 'Derecho civil', 'descripcion' => 'Contratos, sucesiones y litigios civiles.', 'textual' => true], ['nombre' => 'Derecho mercantil', 'descripcion' => 'Constitución de sociedades y asesoría.', 'textual' => true], ['nombre' => 'Derecho laboral', 'descripcion' => '', 'textual' => false]],
            'productos' => [['nombre' => 'Consulta inicial', 'descripcion' => '', 'precio' => 250, 'categoria' => 'Consultas', 'textual' => true]], 'categorias' => ['Consultas'],
            'contacto' => ['telefono' => ['v' => '2222-3344', 'textual' => true], 'whatsapp' => ['v' => '5555-9999', 'textual' => true], 'correo' => ['v' => 'info@bufete.example', 'textual' => true], 'direccion' => ['v' => '6a Avenida 10-20, zona 1', 'textual' => true], 'horario' => ['v' => 'Lun a Vie 8:00 a 17:00', 'textual' => true], 'redes' => ['facebook' => 'https://facebook.com/bufete', 'instagram' => '@bufete']],
            'imagenes' => [['id' => 'pimg1', 'w' => 800, 'h' => 600], ['id' => 'pimg2', 'w' => 800, 'h' => 600], ['id' => 'pimg3', 'w' => 800, 'h' => 600], ['id' => 'pimg4', 'w' => 800, 'h' => 600]],
            'conflictos' => [['campo' => 'telefono', 'form' => '5555-1111', 'pres' => '2222-3344']], 'idioma' => 'es',
        ];
        $s['analisis_resultado_visible'] = null; st_save($t, $s);
        jout(['ok' => true, 'estado' => 'lista', 'resultado' => $res]);
    }
    if ($act === 'confirmar-presentacion' && $method === 'POST') {
        $b = body(); $u = $b['usar'] ?? []; $d = $s['data']; $o = $d['origen'] ?? [];
        $empty = fn($v) => !isset($v) || trim((string)$v) === '';
        $res = json_decode('{"servicios":[{"nombre":"Derecho civil","descripcion":"Contratos, sucesiones y litigios civiles."},{"nombre":"Derecho mercantil","descripcion":"Constitución de sociedades y asesoría."},{"nombre":"Derecho laboral","descripcion":""}]}', true);
        if (!empty($u['nombre']) && $empty($d['negocio']['nombre'] ?? '')) { $d['negocio']['nombre'] = 'Bufete Montenegro & Asociados'; $o['negocio.nombre'] = 'presentacion'; }
        if (!empty($u['rubro']) && $empty($d['negocio']['rubro'] ?? '')) { $d['negocio']['rubro'] = 'abogado'; $o['negocio.rubro'] = 'presentacion'; }
        if (!empty($u['frase']) && $empty($d['contenido']['frase'] ?? '')) { $d['contenido']['frase'] = 'Defendemos tus derechos con experiencia'; $o['contenido.frase'] = 'presentacion'; }
        if (!empty($u['quienes']) && $empty($d['contenido']['quienes'] ?? '')) { $d['contenido']['quienes'] = 'Somos un bufete con enfoque en derecho civil y mercantil.'; $o['contenido.quienes'] = 'presentacion'; }
        foreach (($u['servicios'] ?? []) as $i) { if (isset($res['servicios'][$i])) { $d['contenido']['servicios'][] = ['nombre' => $res['servicios'][$i]['nombre'], 'descripcion' => $res['servicios'][$i]['descripcion'], 'foto' => null, 'origen' => 'pres']; } }
        if (!empty($u['contacto']['direccion']) && $empty($d['contacto']['direccion'] ?? '')) { $d['contacto']['direccion'] = '6a Avenida 10-20, zona 1'; $o['contacto.direccion'] = 'presentacion'; }
        if (!empty($u['contacto']['telefono']) && $empty($d['contacto']['telefono'] ?? '')) { $d['contacto']['telefono'] = '2222-3344'; $o['contacto.telefono'] = 'presentacion'; }
        $d['origen'] = $o; $d['presentacion']['confirmada'] = true; $d['presentacion']['estado'] = 'confirmada'; $d['presentacion']['fotos_usar'] = $b['fotos'] ?? [];
        $s['data'] = $d; st_save($t, $s); jout(['ok' => true, 'data' => $d]);
    }
    if (($act === 'crear' || $act === 'regenerar') && $method === 'POST') {
        $b = body(); if (!empty($b['web_sitio'])) { jout(['ok' => false, 'error' => 'No pudimos procesar la solicitud.'], 400); }
        $s['build_ini'] = time(); $s['estado'] = 'construyendo'; st_save($t, $s); jout(['ok' => true]);
    }
    if ($act === 'construccion' && $method === 'GET') {
        $el = time() - (int)($s['build_ini'] ?? time()); $titles = ['Preparando tu información', 'Creando el sitio', 'Cargando tus fotos', 'Armando páginas y menús', 'Instalando certificado SSL', 'Revisión final'];
        $fail = $bmode === 'fail' && $el >= 4; $p = min(100, (int)round($el / 10 * 100)); $done = !$fail && $p >= 100;
        $pasos = []; foreach ($titles as $i => $ti) { $lim = ($i + 1) * 100 / count($titles); $pasos[] = ['clave' => 's' . $i, 'titulo' => $ti, 'estado' => $fail && $p >= $lim - 17 ? 'error' : ($p >= $lim ? 'ok' : ($p >= $lim - 17 ? 'en_curso' : 'pendiente'))]; }
        if ($done) { $s['estado'] = 'listo'; st_save($t, $s); }
        jout(['ok' => true, 'estado' => $fail ? 'error' : ($done ? 'listo' : 'construyendo'), 'progreso' => $fail ? min($p, 40) : $p, 'pasos' => $pasos, 'mensaje' => $fail ? 'No pudimos terminar. Intenta de nuevo.' : ($done ? 'Todo listo' : 'Estamos trabajando en ' . strtolower($titles[min(5, (int)($p / 17))])), 'url' => $done ? 'https://demo-preview.servicom.gt/?scpk=abc' : null]);
    }
    if ($act === 'pagar' && $method === 'POST') {
        $b = body(); if (trim((string)($b['nombre_nit'] ?? '')) === '' || empty($b['comprobante'])) { jout(['ok' => false, 'error' => 'Faltan datos del pago.'], 422); }
        $s['estado'] = 'pago_revisar'; st_save($t, $s); jout(['ok' => true, 'estado' => 'pago_revisar']);
    }
    jout(['ok' => false, 'error' => 'No encontrado.'], 404);
}
render('error', ['code' => 404], 404);
