<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Validator;
use App\Core\View;
use Throwable;

/** Correo saliente: cola con reintentos, SMTP nativo o mail() de respaldo, plantilla de marca. */
final class Mailer
{
    /** Espera (minutos) tras cada intento fallido; el 5.º fallo es definitivo. */
    private const BACKOFF_MINUTES = [1, 5, 15, 60, 240];
    private const MAX_ATTEMPTS = 5;

    // ---------------------------------------------------------------- cola

    /** Pone un correo en la cola y devuelve su id (0 si la dirección no es válida; queda registrado como fallido). */
    public static function queue(string $toEmail, ?string $toName, string $subject, string $html, ?string $text = null, array $attachments = []): int
    {
        $toEmail = trim($toEmail);
        $now = Clock::utc();
        $valid = self::validEmail($toEmail);
        $stored = [];
        foreach ($attachments as $a) {
            if (!isset($a['name'], $a['content'])) {
                continue;
            }
            $stored[] = [
                'name' => (string) $a['name'],
                'mime' => (string) ($a['mime'] ?? 'application/octet-stream'),
                'b64' => base64_encode((string) $a['content']),
            ];
        }
        $id = Db::insert('email_queue', [
            'to_email' => mb_substr($toEmail, 0, 190),
            'to_name' => $toName !== null && $toName !== '' ? mb_substr(self::oneLine($toName), 0, 160) : null,
            'subject' => mb_substr(self::oneLine($subject), 0, 255),
            'body_html' => $html,
            'body_text' => $text,
            'attachments' => $stored ? (string) json_encode($stored) : null,
            'status' => $valid ? 'pending' : 'failed',
            'attempts' => 0,
            'last_error' => $valid ? null : 'La dirección de correo no es válida.',
            'send_after' => $now,
            'created_at' => $now,
        ]);
        return $valid ? $id : 0;
    }

    /** Envía de inmediato (sin pasar por la cola). Nunca lanza. */
    public static function sendNow(string $toEmail, ?string $toName, string $subject, string $html, ?string $text = null, array $attachments = []): array
    {
        try {
            $toEmail = trim($toEmail);
            if (!self::validEmail($toEmail)) {
                return ['ok' => false, 'error' => 'La dirección de correo no es válida.'];
            }
            $msg = self::buildMessage($toEmail, $toName, $subject, $html, $text, $attachments);
            $cfg = self::smtpConfig();
            if ($cfg['host'] === '') {
                return self::viaMail($toEmail, $msg);
            }
            $client = self::smtpClient($cfg);
            try {
                $client->connect();
                $client->send($msg['from'], [$toEmail], $msg['headers'] . "\r\n" . $msg['body']);
            } finally {
                $client->close();
            }
            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => self::friendlyError($e)];
        }
    }

    /** Procesa los pendientes cuya hora llegó. @return array{sent:int,failed:int,retry:int} */
    public static function processQueue(int $limit = 20): array
    {
        $res = ['sent' => 0, 'failed' => 0, 'retry' => 0];
        $limit = max(1, min(200, $limit));
        $rows = Db::all(
            "SELECT * FROM email_queue WHERE status = 'pending' AND send_after <= ? ORDER BY send_after, id LIMIT " . $limit,
            [Clock::utc()]
        );
        foreach ($rows as $row) {
            $n = (int) $row['attempts'] + 1;
            $delay = self::BACKOFF_MINUTES[min($n, self::MAX_ATTEMPTS) - 1];
            // Reserva el correo: si el proceso muriera a medias, se reintenta más tarde y no se envía dos veces a la vez.
            $claimed = Db::exec(
                "UPDATE email_queue SET attempts = ?, send_after = ? WHERE id = ? AND status = 'pending' AND attempts = ?",
                [$n, Clock::utc(Clock::now() + $delay * 60), $row['id'], $row['attempts']]
            );
            if ($claimed !== 1) {
                continue;
            }
            $atts = [];
            foreach ((array) json_decode((string) $row['attachments'], true) as $a) {
                if (isset($a['name'], $a['b64'])) {
                    $atts[] = ['name' => $a['name'], 'mime' => $a['mime'] ?? 'application/octet-stream', 'content' => (string) base64_decode((string) $a['b64'])];
                }
            }
            $r = self::sendNow((string) $row['to_email'], $row['to_name'], (string) $row['subject'], (string) $row['body_html'], $row['body_text'], $atts);
            if ($r['ok']) {
                Db::update('email_queue', ['status' => 'sent', 'sent_at' => Clock::utc(), 'last_error' => null], 'id = ?', [$row['id']]);
                $res['sent']++;
                continue;
            }
            $err = mb_substr((string) $r['error'], 0, 500);
            if ($n >= self::MAX_ATTEMPTS) {
                Db::update('email_queue', ['status' => 'failed', 'last_error' => $err], 'id = ?', [$row['id']]);
                Logger::error('Correo #' . $row['id'] . ' falló definitivamente: ' . $err);
                $res['failed']++;
            } else {
                Db::update('email_queue', ['last_error' => $err], 'id = ?', [$row['id']]);
                $res['retry']++;
            }
        }
        return $res;
    }

    /** Prueba el acceso al servidor de correo con los ajustes actuales (o con $override: host, port, secure, user, pass). */
    public static function testConnection(?array $override = null): array
    {
        try {
            $cfg = $override !== null ? array_merge(self::smtpConfig(), $override) : self::smtpConfig();
            if ((string) $cfg['host'] === '') {
                $ok = function_exists('mail');
                return [
                    'ok' => $ok,
                    'error' => $ok ? null : 'Tu hosting no permite enviar correo con mail(). Configura un servidor SMTP.',
                    'detail' => $ok ? 'Sin servidor SMTP configurado: se usará el envío del propio hosting (mail()).' : '',
                ];
            }
            $client = self::smtpClient($cfg);
            try {
                $client->connect();
            } finally {
                $client->close();
            }
            return ['ok' => true, 'error' => null, 'detail' => 'Conexión correcta con ' . $cfg['host'] . ':' . $cfg['port'] . ($cfg['user'] !== '' ? ' y autenticación aceptada.' : '.')];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => self::friendlyError($e), 'detail' => ''];
        }
    }

    /** Correo de prueba para el panel de ajustes. */
    public static function sendTest(string $toEmail): array
    {
        $biz = (string) Settings::get('business_name', 'Agenda Premium');
        $html = self::layout('Prueba de correo', View::partial('mail/test', ['business' => $biz]), ['preheader' => 'Si lees este mensaje, el correo está bien configurado.']);
        return self::sendNow($toEmail, null, 'Prueba de correo de ' . $biz, $html);
    }

    // ------------------------------------------------------------ plantilla

    /**
     * Plantilla HTML sobria de marca (tablas y estilos en línea: es correo).
     * $opts: preheader, button => ['label','url'], footer (texto plano), signature (texto plano).
     */
    public static function layout(string $title, string $contentHtml, array $opts = []): string
    {
        $gold = (string) Settings::get('color_gold', '#C9A050');
        $logo = '';
        $fileId = (int) Settings::get('logo_file_id', 0);
        if ($fileId > 0) {
            try {
                $tok = Db::val('SELECT token FROM files WHERE id = ? AND is_public = 1', [$fileId]);
                $base = self::baseUrl();
                if ($tok && $base !== '') {
                    $logo = $base . '/f/' . $tok;
                }
            } catch (Throwable $e) {
                $logo = '';
            }
        }
        $footer = [];
        foreach (['address', 'phone', 'email'] as $k) {
            $v = trim((string) Settings::get($k, ''));
            if ($v !== '') {
                $footer[] = $k === 'phone' ? \App\Core\Str::phoneDisplay($v) : $v;
            }
        }
        return View::partial('mail/layout', [
            'title' => $title,
            'content' => $contentHtml,
            'brand' => (string) Settings::get('business_name', 'Agenda Premium'),
            'gold' => Validator::color($gold) ? $gold : '#C9A050',
            'logo' => $logo,
            'preheader' => (string) ($opts['preheader'] ?? ''),
            'button' => isset($opts['button']['url']) && preg_match('~^https?://~i', (string) $opts['button']['url']) ? $opts['button'] : null,
            'footer' => (string) ($opts['footer'] ?? ''),
            'signature' => (string) ($opts['signature'] ?? ''),
            'contact' => implode(' · ', $footer),
        ]);
    }

    /** Texto plano de la empresa -> HTML seguro: escapa, **negrita**, enlaces automáticos, párrafos y saltos. */
    public static function textToHtml(string $text): string
    {
        $h = htmlspecialchars(str_replace(["\r\n", "\r"], "\n", $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $h = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $h) ?? $h;
        $h = preg_replace_callback('~(https?://[^\s<]+)~i', static function (array $m): string {
            $url = rtrim($m[1], '.,;:!?)');
            $tail = substr($m[1], strlen($url));
            return '<a href="' . $url . '" style="color:#7A5420;text-decoration:underline;">' . $url . '</a>' . $tail;
        }, $h) ?? $h;
        $out = '';
        foreach (preg_split('/\n{2,}/', trim($h)) ?: [] as $p) {
            $out .= '<p style="margin:0 0 16px 0;">' . nl2br(trim($p), false) . '</p>';
        }
        return $out;
    }

    /** Versión de texto plano de un correo HTML. */
    public static function htmlToText(string $html): string
    {
        $t = preg_replace('~<(head|style|script)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $t = preg_replace_callback('~<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>~is', static function (array $m): string {
            $label = trim(strip_tags($m[2]));
            $href = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            return ($label === '' || $label === $href) ? $href : $label . ' (' . $href . ')';
        }, $t) ?? $t;
        $t = preg_replace('~<br\s*/?>~i', "\n", $t) ?? $t;
        $t = preg_replace('~</(p|div|tr|h[1-6]|li|table)>~i', "\n\n", $t) ?? $t;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);
        $lines = array_map(static fn (string $l): string => trim((string) preg_replace('/[ \t]+/', ' ', $l)), explode("\n", $t));
        $t = implode("\n", $lines);
        return trim((string) preg_replace('/\n{3,}/', "\n\n", $t));
    }

    // ----------------------------------------------------- URL base y marca

    /**
     * URL base pública (sin barra final). En CLI no hay petición: se usa la que el panel o el cron guardaron
     * en el ajuste site_base_url. Solo se recuerda desde una sesión de administrador (nunca desde una cabecera Host anónima).
     */
    public static function baseUrl(): string
    {
        $cfg = rtrim((string) Config::get('base_url', ''), '/');
        if ($cfg !== '') {
            return $cfg;
        }
        $req = $GLOBALS['__request'] ?? null;
        if ($req instanceof Request) {
            $b = $req->baseUrl();
            try {
                $u = Auth::user();
                if ($u !== null && ($u['role'] ?? '') === 'admin') {
                    self::rememberBaseUrl($b);
                }
            } catch (Throwable $e) {
                // sin sesión disponible
            }
            return $b;
        }
        return rtrim((string) Settings::get('site_base_url', ''), '/');
    }

    public static function rememberBaseUrl(string $base): void
    {
        $base = rtrim($base, '/');
        if (Validator::url($base) && $base !== (string) Settings::get('site_base_url', '')) {
            Settings::set('site_base_url', $base);
        }
    }

    public static function absUrl(string $path): string
    {
        return self::baseUrl() . '/' . ltrim($path, '/');
    }

    // ------------------------------------------------------------- interno

    /** @return array{host:string,port:int,secure:string,user:string,pass:string} */
    private static function smtpConfig(): array
    {
        $pass = (string) Settings::get('smtp_pass', '');
        if (strncmp($pass, 'v1:', 3) === 0) {
            $plain = Crypto::decrypt($pass);
            if ($plain === null) {
                throw new \RuntimeException('No se pudo leer la contraseña de correo guardada. Vuelve a escribirla en Ajustes.');
            }
            $pass = $plain;
        }
        return [
            'host' => trim((string) Settings::get('smtp_host', '')),
            'port' => (int) Settings::get('smtp_port', '587') ?: 587,
            'secure' => (string) Settings::get('smtp_secure', 'tls'),
            'user' => trim((string) Settings::get('smtp_user', '')),
            'pass' => $pass,
        ];
    }

    private static function smtpClient(array $cfg): SmtpClient
    {
        $domain = (string) parse_url(self::baseUrl(), PHP_URL_HOST);
        return new SmtpClient(
            (string) $cfg['host'],
            (int) $cfg['port'],
            (string) $cfg['secure'],
            (string) $cfg['user'],
            (string) $cfg['pass'],
            15,
            $domain !== '' ? $domain : null,
            Config::get('smtp_insecure_tls') !== true // solo pruebas: certificados autofirmados
        );
    }

    /** @return array{from:string,to:string,subject:string,headers:string,body:string} */
    private static function buildMessage(string $toEmail, ?string $toName, string $subject, string $html, ?string $text, array $attachments): array
    {
        $from = trim((string) Settings::get('mail_from_email', ''));
        if (!self::validEmail($from)) {
            $from = trim((string) Settings::get('email', ''));
        }
        if (!self::validEmail($from)) {
            $host = (string) parse_url(self::baseUrl(), PHP_URL_HOST);
            $from = 'no-responder@' . ($host !== '' ? $host : 'localhost.localdomain');
        }
        $fromName = trim((string) Settings::get('mail_from_name', '')) ?: (string) Settings::get('business_name', 'Agenda Premium');
        $replyTo = trim((string) Settings::get('email', ''));
        $domain = substr($from, (int) strrpos($from, '@') + 1);
        $text = $text !== null && trim($text) !== '' ? $text : self::htmlToText($html);

        $alt = 'ap_a_' . bin2hex(random_bytes(10));
        $body = '--' . $alt . "\r\n" . self::part('text/plain', $text) . '--' . $alt . "\r\n" . self::part('text/html', $html) . '--' . $alt . "--\r\n";
        $contentType = 'multipart/alternative; boundary="' . $alt . '"';
        if ($attachments) {
            $mix = 'ap_m_' . bin2hex(random_bytes(10));
            $mixBody = '--' . $mix . "\r\nContent-Type: " . $contentType . "\r\n\r\n" . $body;
            foreach ($attachments as $a) {
                $name = self::oneLine((string) ($a['name'] ?? 'adjunto'));
                $mime = preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i', (string) ($a['mime'] ?? '')) ? (string) $a['mime'] : 'application/octet-stream';
                $fname = self::headerParam($name);
                $mixBody .= '--' . $mix . "\r\nContent-Type: " . $mime . '; name=' . $fname . "\r\n"
                    . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=" . $fname . "\r\n\r\n"
                    . chunk_split(base64_encode((string) $a['content']), 76, "\r\n");
            }
            $body = $mixBody . '--' . $mix . "--\r\n";
            $contentType = 'multipart/mixed; boundary="' . $mix . '"';
        }

        $subjectEnc = self::encodeHeader(self::oneLine($subject));
        $headers = 'Date: ' . gmdate('D, d M Y H:i:s', Clock::now()) . " +0000\r\n"
            . 'From: ' . self::address($fromName, $from) . "\r\n"
            . 'To: ' . self::address((string) $toName, $toEmail) . "\r\n"
            . ($replyTo !== '' && self::validEmail($replyTo) && strcasecmp($replyTo, $from) !== 0 ? 'Reply-To: ' . $replyTo . "\r\n" : '')
            . 'Subject: ' . $subjectEnc . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . ">\r\n"
            . "MIME-Version: 1.0\r\nAuto-Submitted: auto-generated\r\nX-Mailer: Agenda Premium\r\n"
            . 'Content-Type: ' . $contentType . "\r\n";
        return ['from' => $from, 'to' => $toEmail, 'subject' => $subjectEnc, 'headers' => $headers, 'body' => $body];
    }

    /** Respaldo con mail() cuando no hay servidor SMTP. */
    private static function viaMail(string $toEmail, array $msg): array
    {
        if (!function_exists('mail')) {
            return ['ok' => false, 'error' => 'Tu hosting no permite enviar correo con mail(). Configura un servidor SMTP en Ajustes.'];
        }
        // mail() agrega "To" y "Subject" por su cuenta
        $headers = preg_replace('/^(To|Subject): .*(\r\n[ \t].*)*\r\n/mi', '', $msg['headers']) ?? $msg['headers'];
        $ok = @mail($toEmail, $msg['subject'], $msg['body'], rtrim($headers, "\r\n"), '-f' . $msg['from']);
        return $ok
            ? ['ok' => true, 'error' => null]
            : ['ok' => false, 'error' => 'El servidor no pudo enviar el correo con mail(). Configura un servidor SMTP en Ajustes.'];
    }

    private static function part(string $type, string $content): string
    {
        $content = preg_replace('/\r\n|\r|\n/', "\r\n", $content) ?? $content;
        return 'Content-Type: ' . $type . "; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($content) . "\r\n";
    }

    private static function validEmail(string $e): bool
    {
        return $e !== '' && strlen($e) <= 190 && !preg_match('/[\r\n<>,;\s]/', $e) && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function oneLine(string $s): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/', ' ', $s));
    }

    /** Texto de cabecera: ASCII tal cual (plegado) o palabras codificadas UTF-8 en base64 de ≤45 bytes. */
    private static function encodeHeader(string $s): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $s)) {
            return wordwrap($s, 70, "\r\n ", false);
        }
        $chunks = [];
        $cur = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($cur) + strlen($ch) > 45) {
                $chunks[] = $cur;
                $cur = '';
            }
            $cur .= $ch;
        }
        if ($cur !== '') {
            $chunks[] = $cur;
        }
        return implode("\r\n ", array_map(static fn (string $c): string => '=?UTF-8?B?' . base64_encode($c) . '?=', $chunks));
    }

    private static function address(string $name, string $email): string
    {
        $name = self::oneLine($name);
        if ($name === '') {
            return '<' . $email . '>';
        }
        if (preg_match('/^[\x20-\x7E]+$/', $name)) {
            return '"' . addcslashes($name, '"\\') . '" <' . $email . '>';
        }
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    /** Nombre de archivo para Content-Type/Content-Disposition. */
    private static function headerParam(string $name): string
    {
        if (preg_match('/^[\x20-\x7E]+$/', $name)) {
            return '"' . addcslashes($name, '"\\') . '"';
        }
        return '"=?UTF-8?B?' . base64_encode($name) . '?="';
    }

    private static function friendlyError(Throwable $e): string
    {
        if ($e instanceof \RuntimeException) {
            return $e->getMessage();
        }
        Logger::error('Error inesperado al enviar correo', $e);
        return 'No se pudo enviar el correo por un problema interno.';
    }
}
