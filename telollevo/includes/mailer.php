<?php
/**
 * Envío de correo: usa mail() de PHP por defecto o SMTP (configurable en el panel).
 */
if (!defined('TLL_BOOT')) { http_response_code(403); exit; }

/** Elimina saltos de línea para evitar inyección de cabeceras. */
function mail_clean_header(string $s): string { return trim(preg_replace('/[\r\n\0]+/', ' ', $s)); }

function mail_default_from(): string {
    $host = preg_replace('/^www\./i', '', strtolower($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host);
    if (!preg_match('/^[a-z0-9.\-]+$/', $host) || strpos($host, '.') === false) $host = 'telollevogt.com';
    return 'no-reply@' . $host;
}

/**
 * @return array [bool ok, string error]
 */
function send_mail(string $to, string $subject, string $text, string $html, string $replyTo = '', string $fromName = 'Te Lo Llevo'): array {
    $s = get_settings();
    $to = mail_clean_header($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Correo de destino inválido'];
    $from = $s['mail_from'] !== '' && filter_var($s['mail_from'], FILTER_VALIDATE_EMAIL) ? $s['mail_from'] : mail_default_from();
    if (!empty($s['smtp']['enabled']) && $s['smtp']['host'] !== '' && filter_var($s['smtp']['user'], FILTER_VALIDATE_EMAIL) && $s['mail_from'] === '') {
        $from = $s['smtp']['user'];
    }
    $fromName = mail_clean_header($fromName);
    $subject = mail_clean_header($subject);
    $replyTo = filter_var(mail_clean_header($replyTo), FILTER_VALIDATE_EMAIL) ? mail_clean_header($replyTo) : '';

    $boundary = 'b' . bin2hex(random_bytes(12));
    $headers = [];
    $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
    if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $headers[] = 'X-Mailer: TeLoLlevo';

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text)) . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . '--' . $boundary . "--\r\n";
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (!empty($s['smtp']['enabled']) && $s['smtp']['host'] !== '') {
        return smtp_send($s['smtp'], $from, $to, $encSubject, $headers, $body);
    }
    $ok = @mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) $ok = @mail($to, $encSubject, $body, implode("\r\n", $headers));
    return [$ok, $ok ? '' : 'La función mail() del servidor no pudo enviar el mensaje'];
}

function smtp_send(array $cfg, string $from, string $to, string $encSubject, array $headers, string $body): array {
    $host = $cfg['host']; $port = (int)$cfg['port'] ?: 587; $secure = $cfg['secure'];
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, 'SMTP: no se pudo conectar (' . $errstr . ')'];
    stream_set_timeout($fp, 12);
    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read) {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        $code = (int)substr($r, 0, 3);
        return [in_array($code, $ok, true), $r];
    };
    $fail = function (string $m) use ($fp) { @fclose($fp); return [false, 'SMTP: ' . $m]; };

    $r = $read();
    if ((int)substr($r, 0, 3) !== 220) return $fail('saludo inesperado');
    $ehlo = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
    [$ok, $r] = $cmd('EHLO ' . $ehlo, [250]);
    if (!$ok) return $fail('EHLO rechazado');
    if ($secure === 'tls') {
        [$ok, $r] = $cmd('STARTTLS', [220]);
        if (!$ok) return $fail('STARTTLS rechazado');
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $fail('falló el cifrado TLS');
        [$ok, $r] = $cmd('EHLO ' . $ehlo, [250]);
        if (!$ok) return $fail('EHLO (TLS) rechazado');
    }
    if ($cfg['user'] !== '') {
        [$ok, $r] = $cmd('AUTH LOGIN', [334]);
        if (!$ok) return $fail('AUTH no soportado');
        [$ok, $r] = $cmd(base64_encode($cfg['user']), [334]);
        if (!$ok) return $fail('usuario rechazado');
        [$ok, $r] = $cmd(base64_encode($cfg['pass']), [235]);
        if (!$ok) return $fail('credenciales incorrectas');
    }
    [$ok, $r] = $cmd('MAIL FROM:<' . $from . '>', [250]);
    if (!$ok) return $fail('remitente rechazado');
    [$ok, $r] = $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    if (!$ok) return $fail('destinatario rechazado');
    [$ok, $r] = $cmd('DATA', [354]);
    if (!$ok) return $fail('DATA rechazado');
    $msg = 'Date: ' . date('r') . "\r\n"
         . 'To: ' . $to . "\r\n"
         . 'Subject: ' . $encSubject . "\r\n"
         . 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $ehlo . ">\r\n"
         . implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $msg = preg_replace('/(?<!\r)\n/', "\r\n", $msg);
    $msg = preg_replace('/^\./m', '..', $msg);
    fwrite($fp, $msg . "\r\n.\r\n");
    $r = $read();
    $accepted = (int)substr($r, 0, 3) === 250;
    $cmd('QUIT', [221]);
    @fclose($fp);
    return [$accepted, $accepted ? '' : 'SMTP: el servidor no aceptó el mensaje'];
}
