<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

require_once __DIR__ . '/mailer.php';

const GX_FORM_MIN_AGE = 2;        // segundos mínimos entre cargar la página y enviar
const GX_FORM_MAX_AGE = 7200;     // el token caduca a las 2 horas
const GX_FORM_PER_HOUR = 6;
const GX_FORM_PER_DAY = 20;

function gx_json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    gx_security_headers(true);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function gx_form_token_new(): string
{
    $secret = (string)(gx_config()['secret'] ?? '');
    $ts = (string)gx_now();
    $nonce = gx_rand_hex(8);
    return $ts . '.' . $nonce . '.' . hash_hmac('sha256', $ts . '.' . $nonce, $secret);
}

function gx_form_token_check(string $t): bool
{
    $secret = (string)(gx_config()['secret'] ?? '');
    if (!preg_match('/^(\d{10})\.([a-f0-9]{16})\.([a-f0-9]{64})$/', $t, $m) || $secret === '') {
        return false;
    }
    if (!hash_equals(hash_hmac('sha256', $m[1] . '.' . $m[2], $secret), $m[3])) {
        return false;
    }
    $age = gx_now() - (int)$m[1];
    if ($age < GX_FORM_MIN_AGE || $age > GX_FORM_MAX_AGE) {
        return false;
    }
    try {
        gx_db()->prepare('INSERT INTO used_tokens (nonce, ts) VALUES (:n, :t)')->execute([':n' => $m[2], ':t' => gx_now()]);
    } catch (PDOException $e) {
        return false; // token ya usado
    }
    return true;
}

function gx_form_origin_ok(): bool
{
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        if (!empty($_SERVER[$h])) {
            $u = (string)$_SERVER[$h];
            $host = (string)parse_url($u, PHP_URL_HOST);
            $port = parse_url($u, PHP_URL_PORT);
            $cmp = $host . ($port ? ':' . $port : '');
            $mine = (string)($_SERVER['HTTP_HOST'] ?? '');
            return strcasecmp($cmp, $mine) === 0 || strcasecmp($host, preg_replace('/:\d+$/', '', $mine) ?? '') === 0;
        }
    }
    return true;
}

/** Punto de entrada del formulario: GET ?t=1 emite token; POST guarda y avisa por correo. */
function gx_form_handle(): void
{
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $wantsJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

    if (gx_lockdown_active()) {
        gx_json_out(['ok' => false, 'msg' => 'El formulario está temporalmente desactivado. Escríbanos por WhatsApp o llámenos.'], 503);
    }
    if ($method === 'GET') {
        if (($_GET['t'] ?? '') === '1' && count($_GET) === 1) {
            gx_json_out(['ok' => true, 't' => gx_form_token_new()]);
        }
        gx_fail(404, 'No encontrado');
    }
    if ($method !== 'POST') {
        gx_fail(405, 'Método no permitido');
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) {
        gx_json_out(['ok' => false, 'msg' => 'Solicitud demasiado grande.'], 413);
    }
    $ctype = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($ctype, 'application/x-www-form-urlencoded') !== 0 && strpos($ctype, 'multipart/form-data') !== 0) {
        gx_json_out(['ok' => false, 'msg' => 'Solicitud no válida.'], 415);
    }
    if (!gx_form_origin_ok()) {
        gx_json_out(['ok' => false, 'msg' => 'Solicitud no permitida.'], 403);
    }

    $ip = gx_ip();
    if (gx_rate_count('form', $ip, 3600) >= GX_FORM_PER_HOUR || gx_rate_count('form', $ip, 86400) >= GX_FORM_PER_DAY) {
        gx_json_out(['ok' => false, 'msg' => 'Ha enviado demasiados mensajes. Intente más tarde o llámenos.'], 429);
    }

    // trampa para bots: campo oculto relleno => se responde "ok" sin guardar nada
    if (isset($_POST['website']) && $_POST['website'] !== '') {
        gx_rate_hit('form', $ip);
        gx_json_out(['ok' => true, 'msg' => 'Mensaje enviado.']);
    }
    $token = is_string($_POST['token'] ?? null) ? (string)$_POST['token'] : '';
    if (!gx_form_token_check($token)) {
        gx_json_out(['ok' => false, 'msg' => 'La sesión del formulario expiró. Recargue la página e intente de nuevo.'], 400);
    }

    $name = gx_line($_POST['name'] ?? '', 100);
    $phone = gx_line($_POST['phone'] ?? '', 25);
    $email = gx_line($_POST['email'] ?? '', 254);
    $service = gx_line($_POST['service'] ?? '', 120);
    $place = gx_line($_POST['place'] ?? '', 200);
    $message = gx_text($_POST['message'] ?? '', 3000);

    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors[] = 'Escriba su nombre.';
    }
    if (!preg_match('/^[0-9+()\s.\-]{6,25}$/', $phone) || strlen(preg_replace('/\D/', '', $phone) ?? '') < 6) {
        $errors[] = 'Escriba un teléfono válido.';
    }
    if ($email !== '' && !gx_valid_email($email)) {
        $errors[] = 'Correo electrónico no válido.';
    }
    if ($service === '') {
        $errors[] = 'Seleccione el tipo de servicio.';
    }
    if (mb_strlen($message) < 5) {
        $errors[] = 'Escriba su mensaje.';
    }
    if ($errors) {
        gx_json_out(['ok' => false, 'msg' => $errors[0], 'errors' => $errors], 422);
    }

    gx_rate_hit('form', $ip);
    $st = gx_db()->prepare('INSERT INTO messages (created, name, email, phone, service, place, message, ip, mail_status)
        VALUES (:c, :n, :e, :p, :s, :pl, :m, :ip, :ms)');
    $st->execute([':c' => gx_now(), ':n' => $name, ':e' => $email, ':p' => $phone, ':s' => $service, ':pl' => $place,
        ':m' => $message, ':ip' => $ip, ':ms' => 'pendiente']);
    $id = (int)gx_db()->lastInsertId();

    $to = (string)gx_setting('form_to', '');
    $status = 'sin correo';
    if ($to !== '') {
        $body = "Nuevo mensaje desde el formulario web\n\n"
            . "Nombre: $name\nTeléfono: $phone\nCorreo: " . ($email !== '' ? $email : '(no indicado)') . "\n"
            . "Tipo de servicio: $service\nUbicación / destino: " . ($place !== '' ? $place : '(no indicado)') . "\n\n"
            . "Mensaje:\n$message\n\n--\nIP: $ip · " . date('Y-m-d H:i:s');
        $err = gx_send_mail($to, 'Nuevo mensaje web: ' . $name, $body, $email);
        $status = $err === null ? 'enviado' : 'error: ' . mb_substr($err, 0, 80);
    }
    gx_db()->prepare('UPDATE messages SET mail_status = :s WHERE id = :i')->execute([':s' => $status, ':i' => $id]);

    if ($wantsJson) {
        gx_json_out(['ok' => true, 'msg' => 'Mensaje enviado correctamente.']);
    }
    header('Location: /contactenos/?enviado=1', true, 303);
    exit;
}
