<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

/** Cabecera MIME segura: sin saltos de línea y codificada en UTF-8. */
function gx_mime_header(string $s): string
{
    $s = trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', $s) ?? '');
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

function gx_valid_email($v): bool
{
    return is_string($v) && strlen($v) <= 254 && !preg_match('/[\r\n\x00-\x1F\s,;<>]/', $v)
        && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}

function gx_mail_host(): string
{
    $h = (string)parse_url((string)gx_setting('site_url', ''), PHP_URL_HOST);
    $h = preg_replace('/[^a-z0-9.\-]/i', '', $h) ?? '';
    return $h !== '' ? $h : 'localhost';
}

/**
 * Envía un correo de texto plano. Usa SMTP si está configurado en el panel; si no, mail().
 * @return string|null null si se envió, o texto del error
 */
function gx_send_mail(string $to, string $subject, string $body, string $replyTo = ''): ?string
{
    if (!gx_valid_email($to)) {
        return 'Destinatario no válido';
    }
    $host = gx_mail_host();
    $smtp = (array)gx_setting('smtp', []);
    $fromAddr = (!empty($smtp['from']) && gx_valid_email($smtp['from'])) ? (string)$smtp['from'] : 'no-reply@' . $host;
    $fromName = (string)gx_setting('site_name', 'Sitio web');
    $hdr = [
        'From: ' . gx_mime_header($fromName) . ' <' . $fromAddr . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: GX',
    ];
    if ($replyTo !== '' && gx_valid_email($replyTo)) {
        $hdr[] = 'Reply-To: ' . $replyTo;
    }
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    if (!empty($smtp['enabled']) && !empty($smtp['host'])) {
        return gx_smtp_send($smtp, $fromAddr, $to, gx_mime_header($subject), $hdr, $body);
    }
    $ok = @mail($to, gx_mime_header($subject), str_replace("\n", "\r\n", $body), implode("\r\n", $hdr));
    return $ok ? null : 'mail() no pudo entregar el mensaje';
}

function gx_smtp_read($fp): string
{
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function gx_smtp_cmd($fp, string $cmd, string $expect): bool
{
    fwrite($fp, $cmd . "\r\n");
    return strncmp(gx_smtp_read($fp), $expect, 3) === 0;
}

function gx_smtp_send(array $c, string $from, string $to, string $subject, array $hdr, string $body): ?string
{
    $host = (string)$c['host'];
    $port = (int)($c['port'] ?? 587);
    $secure = (string)($c['secure'] ?? 'tls');
    if (!preg_match('/^[a-z0-9.\-]{1,200}$/i', $host) || $port < 1 || $port > 65535) {
        return 'Configuración SMTP no válida';
    }
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 10);
    if (!$fp) {
        return 'No se pudo conectar al servidor SMTP';
    }
    stream_set_timeout($fp, 12);
    $fail = static function (string $m) use ($fp): string {
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return $m;
    };
    if (strncmp(gx_smtp_read($fp), '220', 3) !== 0) {
        return $fail('SMTP: saludo inválido');
    }
    $ehlo = preg_replace('/[^a-z0-9.\-]/i', '', gx_mail_host()) ?: 'localhost';
    if (!gx_smtp_cmd($fp, 'EHLO ' . $ehlo, '250')) {
        return $fail('SMTP: EHLO rechazado');
    }
    if ($secure === 'tls') {
        if (!gx_smtp_cmd($fp, 'STARTTLS', '220') || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail('SMTP: no se pudo iniciar TLS');
        }
        if (!gx_smtp_cmd($fp, 'EHLO ' . $ehlo, '250')) {
            return $fail('SMTP: EHLO tras TLS rechazado');
        }
    }
    if (!empty($c['user'])) {
        if (!gx_smtp_cmd($fp, 'AUTH LOGIN', '334')
            || !gx_smtp_cmd($fp, base64_encode((string)$c['user']), '334')
            || !gx_smtp_cmd($fp, base64_encode((string)($c['pass'] ?? '')), '235')) {
            return $fail('SMTP: autenticación fallida');
        }
    }
    if (!gx_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', '250') || !gx_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', '250')) {
        return $fail('SMTP: remitente o destinatario rechazado');
    }
    if (!gx_smtp_cmd($fp, 'DATA', '354')) {
        return $fail('SMTP: DATA rechazado');
    }
    $head = implode("\r\n", $hdr) . "\r\nTo: <" . $to . ">\r\nSubject: " . $subject . "\r\nDate: " . date('r') . "\r\n";
    $payload = $head . "\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
    fwrite($fp, $payload . "\r\n.\r\n");
    $ok = strncmp(gx_smtp_read($fp), '250', 3) === 0;
    gx_smtp_cmd($fp, 'QUIT', '221');
    @fclose($fp);
    return $ok ? null : 'SMTP: el servidor no aceptó el mensaje';
}
