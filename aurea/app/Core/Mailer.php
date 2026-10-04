<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Cliente SMTP nativo (SSL implícito, STARTTLS, AUTH LOGIN/PLAIN) con respaldo a mail(). */
final class Mailer
{
    /** @return array{0:bool,1:string} [ok, mensaje de error] */
    public static function send(string $to, string $subject, string $html, string $text = ''): array
    {
        $to = self::clean($to);
        if (!Util::isEmail($to)) { return [false, 'Correo de destino inválido']; }
        $fromEmail = self::clean((string)Settings::get('mail_from_email', ''));
        $fromName = self::clean((string)Settings::get('mail_from_name', Settings::get('business_name', 'AUREA')));
        if (!Util::isEmail($fromEmail)) {
            $host = preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $fromEmail = 'no-responder@' . (preg_match('/^[A-Za-z0-9.\-]+$/', $host) ? $host : 'localhost');
        }
        if ($text === '') { $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $html) ?? $html), ENT_QUOTES, 'UTF-8')); }
        $boundary = 'b' . Util::token(12);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeName($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader(self::clean($subject)),
            'Message-ID: <' . Util::token(12) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $replyTo = self::clean((string)Settings::get('business_email', ''));
        if (Util::isEmail($replyTo)) { $headers[] = 'Reply-To: <' . $replyTo . '>'; }
        $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n--" . $boundary . "--\r\n";

        $host = trim((string)Settings::get('smtp_host', ''));
        if ($host === '') {
            return self::viaMail($to, $subject, $headers, $body);
        }
        return self::viaSmtp($host, $fromEmail, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
    }

    private static function clean(string $s): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $s));
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function encodeName(string $s): string
    {
        $s = str_replace(['"', '\\'], '', $s);
        return preg_match('/[^\x20-\x7E]/', $s) ? self::encodeHeader($s) : '"' . $s . '"';
    }

    private static function viaMail(string $to, string $subject, array $headers, string $body): array
    {
        $h = array_values(array_filter($headers, static fn($x) => !preg_match('/^(To|Subject):/', $x)));
        $ok = @mail($to, self::encodeHeader(self::clean($subject)), $body, implode("\r\n", $h));
        return $ok ? [true, ''] : [false, 'mail() no pudo enviar el mensaje (configura SMTP en Sistema).'];
    }

    private static function viaSmtp(string $host, string $from, string $to, string $data): array
    {
        $port = Settings::int('smtp_port', 587);
        $secure = (string)Settings::get('smtp_secure', 'tls'); // ssl | tls | none
        $verify = Settings::bool('smtp_verify_cert', true);
        $user = (string)Settings::get('smtp_user', '');
        $pass = Secret::decrypt((string)Settings::get('smtp_pass', ''));
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) { return [false, 'No se pudo conectar al servidor SMTP (' . $errstr . ')']; }
        stream_set_timeout($fp, 20);
        try {
            self::expect($fp, [220]);
            $ehlo = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
            $caps = self::cmd($fp, 'EHLO ' . $ehlo, [250]);
            if ($secure === 'tls') {
                self::cmd($fp, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Falló el cifrado STARTTLS');
                }
                $caps = self::cmd($fp, 'EHLO ' . $ehlo, [250]);
            }
            if ($user !== '') {
                if (stripos($caps, 'AUTH') !== false && stripos($caps, 'LOGIN') === false && stripos($caps, 'PLAIN') !== false) {
                    self::cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235]);
                } else {
                    self::cmd($fp, 'AUTH LOGIN', [334]);
                    self::cmd($fp, base64_encode($user), [334]);
                    self::cmd($fp, base64_encode($pass), [235]);
                }
            }
            self::cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
            self::cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::cmd($fp, 'DATA', [354]);
            $data = preg_replace('/(?<!\r)\n/', "\r\n", $data) ?? $data;
            $data = preg_replace('/^\./m', '..', $data) ?? $data;
            fwrite($fp, $data . "\r\n.\r\n");
            self::expect($fp, [250]);
            @self::cmd($fp, 'QUIT', [221]);
            fclose($fp);
            return [true, ''];
        } catch (\Throwable $e) {
            @fclose($fp);
            return [false, $e->getMessage()];
        }
    }

    private static function cmd($fp, string $cmd, array $ok): string
    {
        fwrite($fp, $cmd . "\r\n");
        return self::expect($fp, $ok);
    }

    private static function expect($fp, array $codes): string
    {
        $all = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $all .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') { break; }
        }
        $code = (int)substr($all, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('SMTP: ' . trim(substr($all, 0, 200)));
        }
        return $all;
    }
}
