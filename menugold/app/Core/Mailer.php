<?php
namespace MenuGold\Core;

/**
 * Envío de correo por SMTP (PHPMailer local) con respaldo a mail().
 */
final class Mailer
{
    public static function send($to, $subject, $htmlBody, $textBody = '', array $options = array())
    {
        $cfg = array_merge(array(
            'host' => '', 'port' => 587, 'user' => '', 'pass' => '',
            'secure' => 'tls', 'from' => '', 'from_name' => 'MenúGold',
        ), (array)Config::get('mail', array()), $options);

        if ($cfg['from'] === '') {
            $cfg['from'] = 'no-reply@' . preg_replace('/^www\./', '', Url::host());
        }
        if ($textBody === '') {
            $textBody = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)), ENT_QUOTES, 'UTF-8'));
        }

        if ($cfg['host'] !== '' && is_file(MG_ROOT . '/vendor/PHPMailer/PHPMailer.php')) {
            try {
                require_once MG_ROOT . '/vendor/PHPMailer/Exception.php';
                require_once MG_ROOT . '/vendor/PHPMailer/PHPMailer.php';
                require_once MG_ROOT . '/vendor/PHPMailer/SMTP.php';
                $m = new \PHPMailer\PHPMailer\PHPMailer(true);
                $m->isSMTP();
                $m->Host       = $cfg['host'];
                $m->Port       = (int)$cfg['port'];
                $m->SMTPAuth   = $cfg['user'] !== '';
                $m->Username   = $cfg['user'];
                $m->Password   = $cfg['pass'];
                $m->CharSet    = 'UTF-8';
                if ($cfg['secure'] === 'ssl')      { $m->SMTPSecure = 'ssl'; }
                elseif ($cfg['secure'] === 'tls')  { $m->SMTPSecure = 'tls'; }
                else                               { $m->SMTPAutoTLS = false; }
                $m->setFrom($cfg['from'], $cfg['from_name']);
                $m->addAddress($to);
                $m->Subject = $subject;
                $m->isHTML(true);
                $m->Body    = $htmlBody;
                $m->AltBody = $textBody;
                $m->send();
                return true;
            } catch (\Throwable $e) {
                Logger::error('SMTP: ' . $e->getMessage());
                return false;
            }
        }

        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: ' . self::encodeName($cfg['from_name']) . ' <' . $cfg['from'] . '>' . "\r\n";
        $ok = @mail($to, self::encodeName($subject), $htmlBody, $headers);
        if (!$ok) { Logger::warn('mail() falló para ' . $to); }
        return (bool)$ok;
    }

    private static function encodeName($v)
    {
        return '=?UTF-8?B?' . base64_encode((string)$v) . '?=';
    }

    /** Plantilla HTML con la identidad visual del sistema. */
    public static function template($title, $bodyHtml, $ctaText = '', $ctaUrl = '')
    {
        $cta = '';
        if ($ctaText !== '' && $ctaUrl !== '') {
            $cta = '<p style="margin:32px 0 0"><a href="' . Security::e($ctaUrl) . '" style="display:inline-block;background:#D8B26E;color:#0C0B09;'
                 . 'text-decoration:none;padding:14px 28px;border-radius:999px;font-weight:600;font-family:Georgia,serif">'
                 . Security::e($ctaText) . '</a></p>';
        }
        return '<!doctype html><html lang="es"><body style="margin:0;background:#0C0B09;padding:32px 16px;font-family:Helvetica,Arial,sans-serif">'
             . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
             . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#161412;border:1px solid rgba(216,178,110,.24);border-radius:20px;padding:40px">'
             . '<tr><td style="color:#F4EDE1">'
             . '<h1 style="font-family:Georgia,\'Times New Roman\',serif;font-weight:400;font-size:28px;margin:0 0 20px;color:#F4EDE1">' . Security::e($title) . '</h1>'
             . '<div style="font-size:15px;line-height:1.7;color:rgba(244,237,225,.78)">' . $bodyHtml . '</div>' . $cta
             . '<p style="margin:36px 0 0;font-size:12px;color:rgba(244,237,225,.4)">MenúGold · menú digital con pedidos</p>'
             . '</td></tr></table></td></tr></table></body></html>';
    }
}
