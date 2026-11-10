<?php
/**
 * Recepción del formulario de solicitud de servicio.
 * - Valida, filtra spam (honeypot, token firmado, límite por IP)
 * - Guarda el mensaje en el buzón del panel
 * - Envía el correo a la dirección configurada
 */
define('TLL_DEPTH', 0);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';
security_headers();

function wants_json(): bool {
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}
function respond(bool $ok, string $msg, int $code = 200): void {
    if (wants_json()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $msg, 'token' => form_token()], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ' . url('') . ($ok ? '?enviado=1' : '?error=1') . '#contacto', true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (isset($_GET['token'])) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(['token' => form_token()]);
        exit;
    }
    header('Location: ' . url(''), true, 302);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$C = get_content();
$ip = client_ip();

/* Anti-spam */
if (trim((string)($_POST['website'] ?? '')) !== '') { respond(true, $C['form']['success']); } // honeypot: se simula éxito
if (!form_token_check((string)($_POST['token'] ?? ''))) {
    respond(false, 'Por favor espera un momento e inténtalo de nuevo.', 400);
}
if (rl_hits('c:' . $ip, 3600) >= 6) {
    respond(false, 'Has enviado varias solicitudes seguidas. Por favor escríbenos por WhatsApp.', 429);
}

/* Limpieza y validación */
function field(string $k, int $max, bool $multi = false): string {
    $v = $_POST[$k] ?? '';
    if (is_array($v)) return '';
    $v = str_replace(["\r\n", "\r"], "\n", (string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    if (!$multi) $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}
$d = [
    'nombre'      => field('nombre', 80),
    'telefono'    => field('telefono', 25),
    'email'       => field('email', 120),
    'servicio'    => field('servicio', 80),
    'interes'     => field('interes', 80),
    'recogida'    => field('recogida', 200),
    'entrega'     => field('entrega', 200),
    'descripcion' => field('descripcion', 160),
    'tamano'      => field('tamano', 80),
    'cuando'      => field('cuando', 60),
    'fecha'       => field('fecha', 10),
    'hora'        => field('hora', 5),
    'comentarios' => field('comentarios', 600, true),
];
$errors = [];
if (mb_strlen($d['nombre']) < 2) $errors[] = 'nombre';
if (!preg_match('/^[0-9+()\-.\s]{7,25}$/', $d['telefono']) || strlen(preg_replace('/\D/', '', $d['telefono'])) < 7) $errors[] = 'teléfono';
if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'correo';
if ($d['servicio'] === '') $errors[] = 'servicio';
if ($d['recogida'] === '' || $d['entrega'] === '' || $d['descripcion'] === '') $errors[] = 'direcciones o descripción';
if (empty($_POST['acepto'])) $errors[] = 'aceptación de privacidad';
if ($d['fecha'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['fecha'])) $d['fecha'] = '';
if ($d['hora'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $d['hora'])) $d['hora'] = '';
if (preg_match_all('#https?://|www\.#i', implode(' ', $d)) > 2) $errors[] = 'enlaces no permitidos';
if ($errors) { respond(false, 'Revisa estos datos: ' . implode(', ', $errors) . '.', 422); }

rl_add('c:' . $ip, 3600);

/* Guardar en el buzón */
$msg = [
    'id'      => bin2hex(random_bytes(6)),
    'date'    => date('Y-m-d H:i:s'),
    'read'    => false,
    'ip'      => $ip,
    'mail_ok' => false,
    'mail_err'=> '',
    'data'    => $d,
];
store_update('messages', function ($list) use ($msg) {
    if (!is_array($list)) $list = [];
    array_unshift($list, $msg);
    return array_slice($list, 0, 1500);
}, []);

/* Enviar correo */
$S = get_settings();
$brand = $C['site']['brand'];
$labels = [
    'nombre' => 'Nombre', 'telefono' => 'Teléfono / WhatsApp', 'email' => 'Correo', 'servicio' => 'Tipo de servicio',
    'interes' => 'Servicio de interés (tarjeta)', 'recogida' => 'Dirección de recogida', 'entrega' => 'Dirección de entrega',
    'descripcion' => 'Qué se envía', 'tamano' => 'Volumen de entregas', 'cuando' => 'Cuándo', 'fecha' => 'Fecha', 'hora' => 'Hora', 'comentarios' => 'Comentarios',
];
$text = "NUEVA SOLICITUD DE SERVICIO - $brand\n" . str_repeat('=', 44) . "\n";
$rows = '';
foreach ($labels as $k => $l) {
    if ($d[$k] === '') continue;
    $text .= $l . ': ' . $d[$k] . "\n";
    $rows .= '<tr><td style="padding:10px 14px;background:#EEF5FF;color:#062B5F;font-weight:700;width:38%;border-bottom:1px solid #fff;vertical-align:top">' . e($l)
          . '</td><td style="padding:10px 14px;background:#F8FAFD;color:#1B2A44;border-bottom:1px solid #fff">' . nl2br(e($d[$k])) . '</td></tr>';
}
$digits = preg_replace('/\D/', '', $d['telefono']);
$wa = 'https://wa.me/' . (strlen($digits) === 8 ? '502' . $digits : $digits);
$text .= "\nFecha de recepción: " . $msg['date'] . "\nResponder por WhatsApp: $wa\n";
$html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:0 auto;background:#fff;border:1px solid #DCE6F5;border-radius:14px;overflow:hidden">'
      . '<div style="background:#062B5F;padding:22px 26px"><div style="color:#FFC914;font-size:12px;letter-spacing:2px;font-weight:700">NUEVA SOLICITUD</div>'
      . '<div style="color:#fff;font-size:22px;font-weight:800;margin-top:4px">' . e($brand) . '</div></div>'
      . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-collapse:collapse">' . $rows . '</table>'
      . '<div style="padding:20px 26px"><a href="' . e($wa) . '" style="display:inline-block;background:#25D366;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:999px;font-size:14px">Responder por WhatsApp</a>'
      . '<p style="color:#6B7A90;font-size:12px;margin:16px 0 0">Recibido el ' . e($msg['date']) . '. También está guardado en el panel de administración.</p></div></div>';
$subject = 'Nueva solicitud: ' . $d['servicio'] . ' - ' . $d['nombre'];
[$ok, $err] = send_mail($S['mail_to'], $subject, $text, $html, $d['email'], $brand);

if ($ok || $err !== '') {
    store_update('messages', function ($list) use ($msg, $ok, $err) {
        foreach ($list as &$m) { if (($m['id'] ?? '') === $msg['id']) { $m['mail_ok'] = $ok; $m['mail_err'] = $err; break; } }
        return $list;
    }, []);
}
respond(true, $C['form']['success']);
