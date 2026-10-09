<?php
declare(strict_types=1);
namespace S5\Core;

/** Envío de correo con PHPMailer (SMTP si está configurado; si no, mail() del servidor). */
final class Mailer
{
    /** Cola de pruebas: si S5_MAIL_SINK está definido, los correos se guardan como archivos en vez de enviarse. */
    public static function send(string $to, string $subject, string $html, array $opts = []): bool
    {
        $to = Sanitize::email($to);
        if ($to === '') {
            return false;
        }
        $sink = getenv('S5_MAIL_SINK');
        if ($sink) {
            @mkdir($sink, 0777, true);
            file_put_contents($sink . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json', json_encode(['to' => $to, 'subject' => $subject, 'html' => $html, 'opts' => $opts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return true;
        }
        $autoload = S5_ROOT . '/vendor/phpmailer/src/PHPMailer.php';
        if (!is_file($autoload)) {
            Log::error('PHPMailer no encontrado');
            return false;
        }
        require_once S5_ROOT . '/vendor/phpmailer/src/Exception.php';
        require_once S5_ROOT . '/vendor/phpmailer/src/PHPMailer.php';
        require_once S5_ROOT . '/vendor/phpmailer/src/SMTP.php';
        try {
            $m = new \PHPMailer\PHPMailer\PHPMailer(true);
            $m->CharSet = 'UTF-8';
            $host = (string) Settings::get('smtp_host', '');
            if ($host !== '') {
                $m->isSMTP();
                $m->Host = $host;
                $m->Port = Settings::int('smtp_port', 587);
                $m->SMTPAuth = (string) Settings::get('smtp_user', '') !== '';
                $m->Username = (string) Settings::get('smtp_user', '');
                $m->Password = (string) Settings::get('smtp_pass', '');
                $sec = (string) Settings::get('smtp_secure', 'tls');
                $m->SMTPSecure = $sec === 'ssl' ? 'ssl' : ($sec === 'none' ? '' : 'tls');
                $m->SMTPAutoTLS = $sec !== 'none';
                $m->Timeout = 15;
            }
            $from = Sanitize::email((string) Settings::get('smtp_from', '')) ?: ('no-responder@' . Settings::baseDomain());
            $m->setFrom($from, (string) Settings::get('smtp_from_name', 'Servicom'));
            $m->addAddress($to);
            if (!empty($opts['reply_to'])) {
                $m->addReplyTo($opts['reply_to']);
            }
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body = $html;
            $m->AltBody = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", $html) ?? $html)));
            $m->send();
            return true;
        } catch (\Throwable $e) {
            Log::error('Correo no enviado', ['to' => $to, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /** Plantilla de correo sencilla y limpia. */
    public static function wrap(string $title, string $bodyHtml): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#1b1b1b">'
            . '<h2 style="margin:0 0 16px;color:#8a6d2f">' . $t . '</h2>' . $bodyHtml
            . '<hr style="border:0;border-top:1px solid #e5dcc3;margin:24px 0"><p style="font-size:12px;color:#777">Servicom · Guatemala</p></div>';
    }
}
