<?php
declare(strict_types=1);

namespace App\Core;

use Vendor\Mailer\Correo;

final class Mail
{
    public static function disponible(): bool
    {
        return Settings::bool('smtp_activo', false) || function_exists('mail');
    }

    public static function nuevo(): Correo
    {
        $c = new Correo();
        if (Settings::bool('smtp_activo', false) && Settings::get('smtp_host', '') !== '') {
            $c->configurarSmtp(
                (string)Settings::get('smtp_host', ''),
                Settings::int('smtp_puerto', 587),
                (string)Settings::get('smtp_usuario', ''),
                (string)Settings::get('smtp_password', ''),
                (string)Settings::get('smtp_seguridad', 'tls')
            );
        }
        $remitente = (string)Settings::get('smtp_remitente', '');
        if ($remitente === '') {
            $remitente = (string)Settings::get('colegio_email', '');
        }
        if ($remitente === '') {
            $remitente = 'no-reply@' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $c->remitente($remitente, (string)Settings::get('smtp_nombre', Settings::get('colegio_nombre', 'EduPortal')));
        return $c;
    }

    /** Envia un correo con la plantilla institucional. */
    public static function enviar(string $para, string $nombre, string $asunto, string $htmlInterno, array $adjuntos = []): bool
    {
        try {
            $c = self::nuevo();
            if (!$c->agregarDestinatario($para, $nombre)) {
                return false;
            }
            $c->asunto($asunto);
            $c->cuerpo(self::plantillaHtml($asunto, $htmlInterno));
            foreach ($adjuntos as $a) {
                $c->adjuntar($a['nombre'], $a['contenido'], $a['mime'] ?? 'application/octet-stream');
            }
            $ok = $c->enviar();
            if (!$ok) {
                Logger::warn('Correo no enviado', ['para' => $para, 'error' => $c->error()]);
            }
            return $ok;
        } catch (\Throwable $e) {
            Logger::error('Fallo al enviar correo', ['e' => $e->getMessage()]);
            return false;
        }
    }

    public static function plantillaHtml(string $titulo, string $contenido): string
    {
        $colegio = htmlspecialchars((string)Settings::get('colegio_nombre', 'EduPortal'), ENT_QUOTES, 'UTF-8');
        $tit = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $anio = date('Y');
        return <<<HTML
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F7F5F0;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#1B2430">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F5F0;padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(11,31,58,.08)">
<tr><td style="background:#0B1F3A;padding:24px 28px">
<div style="color:#C9A961;font-size:12px;letter-spacing:.14em;text-transform:uppercase">{$colegio}</div>
<div style="color:#ffffff;font-size:22px;font-weight:600;margin-top:6px">{$tit}</div>
</td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.65;color:#2A3344">{$contenido}</td></tr>
<tr><td style="padding:18px 28px;background:#F7F5F0;font-size:12px;color:#6B7280;text-align:center">
Este mensaje fue enviado automaticamente por el portal de {$colegio}. &copy; {$anio}
</td></tr>
</table></td></tr></table></body></html>
HTML;
    }
}
