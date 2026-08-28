<?php
declare(strict_types=1);

namespace MenuGold\Core;

use MenuGold\Vendor\Mailer\PHPMailer;

/**
 * Envio de correo con plantilla elegante. Usa la configuracion SMTP del
 * restaurante y, si no existe, la de la plataforma; en ultimo caso mail().
 */
final class Mailer
{
    public static function send(string $para, string $asunto, string $htmlCuerpo, ?int $restaurantId = null, string $nombreDestino = ''): bool
    {
        $cfg = self::config($restaurantId);
        $html = self::plantilla($asunto, $htmlCuerpo, $cfg['nombre']);
        try {
            $m = new PHPMailer();
            if (!empty($cfg['host'])) {
                $m->isSMTP();
                $m->Host       = $cfg['host'];
                $m->Port       = (int)$cfg['puerto'];
                $m->SMTPAuth   = $cfg['usuario'] !== '';
                $m->Username   = $cfg['usuario'];
                $m->Password   = $cfg['clave'];
                $m->SMTPSecure = $cfg['seguridad'];
            }
            $m->CharSet = 'UTF-8';
            $m->setFrom($cfg['desde'], $cfg['nombre']);
            $m->addAddress($para, $nombreDestino);
            $m->Subject = $asunto;
            $m->isHTML(true);
            $m->Body    = $html;
            $m->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlCuerpo)));
            $ok = $m->send();
            if (!$ok) Logger::warn('Correo no enviado: ' . $m->ErrorInfo, ['para' => $para]);
            return $ok;
        } catch (\Throwable $e) {
            Logger::error('Error enviando correo: ' . $e->getMessage(), ['para' => $para]);
            return false;
        }
    }

    public static function config(?int $restaurantId = null): array
    {
        $r = static function (string $k, $d = '') use ($restaurantId) {
            $v = $restaurantId ? Setting::get('smtp_' . $k, null, $restaurantId) : null;
            return $v !== null && $v !== '' ? $v : (Setting::plat('smtp_' . $k, $d));
        };
        $nombre = $restaurantId
            ? (string)(Setting::get('smtp_nombre', null, $restaurantId) ?? (App::restaurant()['nombre'] ?? ''))
            : '';
        if ($nombre === '') $nombre = (string)Setting::plat('nombre_plataforma', 'MenuGold');
        return [
            'host'      => (string)$r('host', ''),
            'puerto'    => (int)$r('puerto', 587),
            'usuario'   => (string)$r('usuario', ''),
            'clave'     => (string)$r('clave', ''),
            'seguridad' => (string)$r('seguridad', 'tls'),
            'desde'     => (string)$r('desde', 'no-responder@' . preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'))),
            'nombre'    => $nombre,
        ];
    }

    public static function plantilla(string $titulo, string $cuerpo, string $marca = 'MenuGold'): string
    {
        $anio = date('Y');
        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($titulo) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f1ea;font-family:Helvetica,Arial,sans-serif;color:#2a2a2a">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ea;padding:28px 12px">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.07)">'
            . '<tr><td style="background:#141414;padding:26px 28px;text-align:center">'
            . '<div style="color:#D4AF37;font-size:22px;letter-spacing:2px;font-weight:700">' . e(mb_strtoupper($marca)) . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:30px 28px">'
            . '<h1 style="margin:0 0 16px;font-size:20px;color:#141414">' . e($titulo) . '</h1>'
            . '<div style="font-size:15px;line-height:1.65;color:#43413c">' . $cuerpo . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:#faf8f3;text-align:center;font-size:12px;color:#8a8578">'
            . 'Este mensaje fue enviado automaticamente por ' . e($marca) . ' &middot; ' . $anio
            . '</td></tr></table></td></tr></table></body></html>';
    }

    public static function boton(string $texto, string $url): string
    {
        return '<p style="text-align:center;margin:26px 0">'
            . '<a href="' . e($url) . '" style="display:inline-block;background:#D4AF37;color:#141414;'
            . 'text-decoration:none;padding:13px 28px;border-radius:10px;font-weight:700;font-size:15px">'
            . e($texto) . '</a></p>';
    }
}
