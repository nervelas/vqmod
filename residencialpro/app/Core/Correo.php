<?php
declare(strict_types=1);

namespace App\Core;

use Vendor\Mailer\Mailer;

/**
 * Envío de correo con plantilla institucional y cola de reintentos.
 */
final class Correo
{
    public static function mailer(): Mailer
    {
        $m = new Mailer();
        $m->host       = Ajustes::get('smtp_host', '');
        $m->puerto     = (int) Ajustes::num('smtp_puerto', 587);
        $m->usuario    = Ajustes::get('smtp_usuario', '');
        $m->clave      = Ajustes::get('smtp_clave', '');
        $m->seguridad  = Ajustes::get('smtp_seguridad', 'tls');
        $m->deCorreo   = Ajustes::get('smtp_de_correo', Ajustes::get('correo', ''));
        $m->deNombre   = Ajustes::get('smtp_de_nombre', Ajustes::get('nombre', 'ResidencialPro'));
        $m->responderA = Ajustes::get('correo', '');
        return $m;
    }

    /**
     * Envía de inmediato. Si falla, deja el mensaje en la cola.
     * $adjuntos = [['nombre'=>..,'contenido'=>..,'mime'=>..], ...]
     */
    public static function enviar(string $para, string $nombre, string $asunto, string $html, array $adjuntos = [], bool $encolarSiFalla = true): bool
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (!Ajustes::esVerdadero('correo_activo', true)) {
            return false;
        }
        try {
            $m = self::mailer();
            $m->para($para, $nombre)->asunto($asunto)->cuerpoHtml($html);
            foreach ($adjuntos as $a) {
                $m->adjuntarContenido((string) $a['contenido'], (string) $a['nombre'], (string) ($a['mime'] ?? 'application/pdf'));
            }
            if ($m->enviar()) {
                return true;
            }
            Log::aviso('Correo no enviado a ' . $para . ': ' . $m->error());
        } catch (\Throwable $e) {
            Log::error('Correo: ' . $e->getMessage());
        }
        if ($encolarSiFalla) {
            self::encolar($para, $nombre, $asunto, $html);
        }
        return false;
    }

    public static function encolar(string $para, string $nombre, string $asunto, string $html, ?string $adjunto = null): void
    {
        try {
            DB::insertar('cola_correo', [
                'para'    => $para,
                'nombre'  => $nombre,
                'asunto'  => $asunto,
                'cuerpo'  => $html,
                'adjunto' => $adjunto,
            ]);
        } catch (\Throwable $e) {
            Log::error('Cola de correo: ' . $e->getMessage());
        }
    }

    /** Procesa la cola pendiente (invocado por el cron). */
    public static function procesarCola(int $maximo = 25): int
    {
        $enviados = 0;
        try {
            $filas = DB::todos(
                'SELECT * FROM cola_correo WHERE estado = "pendiente" AND intentos < 4 ORDER BY id ASC LIMIT :l',
                ['l' => $maximo]
            );
        } catch (\Throwable) {
            return 0;
        }
        foreach ($filas as $f) {
            $adjuntos = [];
            if (!empty($f['adjunto']) && is_file(RUTA_BASE . '/storage/tmp/' . basename((string) $f['adjunto']))) {
                $ruta = RUTA_BASE . '/storage/tmp/' . basename((string) $f['adjunto']);
                $adjuntos[] = ['nombre' => basename($ruta), 'contenido' => (string) file_get_contents($ruta), 'mime' => 'application/pdf'];
            }
            $ok = self::enviar((string) $f['para'], (string) $f['nombre'], (string) $f['asunto'], (string) $f['cuerpo'], $adjuntos, false);
            DB::actualizar('cola_correo', [
                'estado'     => $ok ? 'enviado' : ((int) $f['intentos'] >= 3 ? 'error' : 'pendiente'),
                'intentos'   => (int) $f['intentos'] + 1,
                'enviado_en' => $ok ? date('Y-m-d H:i:s') : null,
            ], 'id = :id', ['id' => (int) $f['id']]);
            if ($ok) {
                $enviados++;
            }
        }
        return $enviados;
    }

    /** Envuelve el contenido en la plantilla HTML del condominio. */
    public static function plantillaHtml(string $titulo, string $contenidoHtml, string $botonTexto = '', string $botonUrl = ''): string
    {
        $nombre  = Ajustes::get('nombre', 'ResidencialPro');
        $verde   = Ajustes::get('color_primario', '#0F2E24');
        $oro     = Ajustes::get('color_acento', '#C9A961');
        $pie     = Ajustes::get('correo_pie', 'Este es un mensaje automático de la administración. Por favor no responda a este correo.');
        $tel     = Ajustes::get('telefono', '');
        $dir     = Ajustes::get('direccion', '');
        $boton   = '';
        if ($botonTexto !== '' && $botonUrl !== '') {
            $boton = '<tr><td style="padding:8px 32px 28px;">'
                . '<a href="' . e($botonUrl) . '" style="display:inline-block;background:' . e($oro) . ';color:#1B2019;'
                . 'text-decoration:none;padding:13px 26px;border-radius:10px;font-weight:600;font-size:15px;">'
                . e($botonTexto) . '</a></td></tr>';
        }
        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($titulo) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#F1EEE6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1EEE6;padding:28px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(15,46,36,.10);">'
            . '<tr><td style="background:' . e($verde) . ';padding:26px 32px;">'
            . '<div style="color:' . e($oro) . ';font-size:21px;font-weight:700;letter-spacing:.4px;">' . e($nombre) . '</div>'
            . '<div style="color:#D8E0D9;font-size:12px;margin-top:4px;">Administración del residencial</div>'
            . '</td></tr>'
            . '<tr><td style="padding:30px 32px 10px;">'
            . '<h1 style="margin:0 0 14px;font-size:20px;color:#0F2E24;font-weight:700;">' . e($titulo) . '</h1>'
            . '<div style="font-size:15px;line-height:1.65;color:#3A413A;">' . $contenidoHtml . '</div>'
            . '</td></tr>'
            . $boton
            . '<tr><td style="padding:18px 32px 26px;border-top:1px solid #EDE8DC;">'
            . '<div style="font-size:12px;color:#8A8F8B;line-height:1.6;">' . e($pie)
            . ($dir !== '' ? '<br>' . e($dir) : '')
            . ($tel !== '' ? ' &middot; Tel. ' . e($tel) : '')
            . '</div></td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
