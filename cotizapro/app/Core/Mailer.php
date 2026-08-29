<?php
declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class Mailer
{
    private static bool $loaded = false;

    private static function boot(): void
    {
        if (self::$loaded) {
            return;
        }
        require_once VENDOR_PATH . '/phpmailer/Exception.php';
        require_once VENDOR_PATH . '/phpmailer/PHPMailer.php';
        require_once VENDOR_PATH . '/phpmailer/SMTP.php';
        self::$loaded = true;
    }

    /**
     * Envía un correo usando el SMTP de la empresa (o el del servidor).
     * @param array<int,array{0:string,1:string}> $attachments  [ruta, nombre]
     */
    public static function send(string $to, string $subject, string $html, ?array $company = null, array $attachments = [], string $replyTo = '', string $replyName = ''): bool
    {
        self::boot();
        $cfg = self::config($company);
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->XMailer  = ' ';
            if (($cfg['host'] ?? '') !== '') {
                $mail->isSMTP();
                $mail->Host       = $cfg['host'];
                $mail->Port       = (int) ($cfg['port'] ?: 587);
                $mail->SMTPAuth   = ($cfg['user'] ?? '') !== '';
                $mail->Username   = (string) ($cfg['user'] ?? '');
                $mail->Password   = (string) ($cfg['pass'] ?? '');
                $mail->Timeout    = 20;
                $sec = strtolower((string) ($cfg['secure'] ?? 'tls'));
                if ($sec === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($sec === 'ninguna' || $sec === 'none') {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
                $mail->SMTPDebug = SMTP::DEBUG_OFF;
            } else {
                $mail->isMail();
            }
            $mail->setFrom($cfg['from'], $cfg['from_name']);
            $mail->addAddress($to);
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, $replyName ?: '');
            }
            foreach ($attachments as $a) {
                if (is_file($a[0])) {
                    $mail->addAttachment($a[0], (string) ($a[1] ?? basename($a[0])));
                }
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace(['</p>', '<br>', '</tr>'], "\n", $html))));
            $mail->send();
            self::log($company, $to, $subject, 'enviado');
            return true;
        } catch (\Throwable $e) {
            ErrorHandler::log('Fallo de correo: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
            self::log($company, $to, $subject, 'error', $e->getMessage());
            return false;
        }
    }

    public static function config(?array $company): array
    {
        if ($company && !empty($company['smtp_host'])) {
            return [
                'host'      => (string) $company['smtp_host'],
                'port'      => (int) ($company['smtp_port'] ?: 587),
                'user'      => (string) ($company['smtp_user'] ?? ''),
                'pass'      => (string) ($company['smtp_pass'] ?? ''),
                'secure'    => (string) ($company['smtp_secure'] ?? 'tls'),
                'from'      => (string) ($company['smtp_from'] ?: $company['email'] ?: 'no-reply@' . App::host()),
                'from_name' => (string) ($company['smtp_from_name'] ?: $company['name']),
            ];
        }
        $s = \App\Models\Setting::all();
        return [
            'host'      => (string) ($s['smtp_host'] ?? ''),
            'port'      => (int) ($s['smtp_port'] ?? 587),
            'user'      => (string) ($s['smtp_user'] ?? ''),
            'pass'      => (string) ($s['smtp_pass'] ?? ''),
            'secure'    => (string) ($s['smtp_secure'] ?? 'tls'),
            'from'      => (string) ($s['smtp_from'] ?? ($company['email'] ?? 'no-reply@' . App::host())),
            'from_name' => (string) ($s['smtp_from_name'] ?? ($company['name'] ?? \App\Models\Setting::get('app_name', 'CotizaPro'))),
        ];
    }

    private static function log(?array $company, string $to, string $subject, string $status, string $error = ''): void
    {
        try {
            DB::insert('email_log', [
                'to_email'   => mb_substr($to, 0, 190),
                'subject'    => mb_substr($subject, 0, 220),
                'status'     => $status,
                'error'      => $error !== '' ? mb_substr($error, 0, 400) : null,
                'created_at' => nowSql(),
            ]);
        } catch (\Throwable) {
            // El registro de correo nunca debe romper el envío.
        }
    }

    /** Plantilla HTML técnica para todos los correos del sistema. */
    public static function template(string $title, string $bodyHtml, ?array $company = null, string $ctaLabel = '', string $ctaUrl = ''): string
    {
        $accent = $company ? \App\Models\Company::theme($company)['accent'] : '#E8590C';
        $ink    = '#1C1F22';
        $name   = e($company['name'] ?? \App\Models\Setting::get('app_name', 'CotizaPro B2B'));
        $cta = '';
        if ($ctaLabel !== '' && $ctaUrl !== '') {
            $cta = '<tr><td style="padding:8px 32px 32px">'
                 . '<a href="' . e($ctaUrl) . '" style="display:inline-block;background:' . e($accent) . ';color:#fff;text-decoration:none;'
                 . 'font:600 14px/1 Helvetica,Arial,sans-serif;letter-spacing:.04em;padding:15px 26px;border-radius:6px">'
                 . e($ctaLabel) . '</a></td></tr>';
        }
        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;padding:24px 12px;background:#EDEEEB;font-family:Helvetica,Arial,sans-serif;color:' . $ink . '">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #DCDED8;border-radius:8px">'
            . '<tr><td style="padding:26px 32px 0"><div style="font:600 11px/1 Helvetica,Arial,sans-serif;letter-spacing:.22em;text-transform:uppercase;color:#5A6470">' . $name . '</div>'
            . '<div style="height:3px;width:44px;background:' . e($accent) . ';margin:14px 0 0"></div></td></tr>'
            . '<tr><td style="padding:22px 32px 4px"><h1 style="margin:0;font:700 24px/1.2 Helvetica,Arial,sans-serif;letter-spacing:-.02em">' . e($title) . '</h1></td></tr>'
            . '<tr><td style="padding:8px 32px 4px;font:400 15px/1.65 Helvetica,Arial,sans-serif;color:#2B3036">' . $bodyHtml . '</td></tr>'
            . $cta
            . '<tr><td style="padding:18px 32px 26px;border-top:1px solid #E7E9E4;font:400 12px/1.6 Helvetica,Arial,sans-serif;color:#7A828C">'
            . 'Este mensaje se generó automáticamente desde el sistema de cotizaciones de ' . $name . '.</td></tr>'
            . '</table></body></html>';
    }
}
