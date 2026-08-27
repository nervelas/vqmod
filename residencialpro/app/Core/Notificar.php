<?php
declare(strict_types=1);

namespace App\Core;

use Vendor\Push\WebPush;

/**
 * Notificaciones al residente: campana interna, Web Push y enlace de WhatsApp.
 */
final class Notificar
{
    /** Crea una notificación interna y la envía por push si hay suscripción. */
    public static function usuario(int $usuarioId, string $titulo, string $cuerpo = '', string $url = '', string $icono = 'campana'): void
    {
        if ($usuarioId <= 0) {
            return;
        }
        try {
            DB::insertar('notificaciones', [
                'usuario_id' => $usuarioId,
                'titulo'     => mb_substr($titulo, 0, 190),
                'cuerpo'     => mb_substr($cuerpo, 0, 255),
                'url'        => $url !== '' ? mb_substr($url, 0, 190) : null,
                'icono'      => $icono,
            ]);
        } catch (\Throwable $e) {
            Log::error('Notificación: ' . $e->getMessage());
        }
        self::push($usuarioId, $titulo, $cuerpo, $url);
    }

    /** Notifica a todos los residentes activos de una casa. */
    public static function casa(int $casaId, string $titulo, string $cuerpo = '', string $url = ''): void
    {
        try {
            $ids = DB::todos(
                'SELECT DISTINCT usuario_id FROM residentes WHERE casa_id = :c AND activo = 1 AND usuario_id IS NOT NULL',
                ['c' => $casaId]
            );
            foreach ($ids as $f) {
                self::usuario((int) $f['usuario_id'], $titulo, $cuerpo, $url);
            }
        } catch (\Throwable $e) {
            Log::error('Notificar casa: ' . $e->getMessage());
        }
    }

    /** Notifica a todos los usuarios de uno o más roles. */
    public static function rol(array $roles, string $titulo, string $cuerpo = '', string $url = '', string $icono = 'campana'): void
    {
        if ($roles === []) {
            return;
        }
        try {
            $ph = implode(',', array_fill(0, count($roles), '?'));
            $ids = DB::todos('SELECT id FROM usuarios WHERE activo = 1 AND rol IN (' . $ph . ')', array_values($roles));
            foreach ($ids as $f) {
                self::usuario((int) $f['id'], $titulo, $cuerpo, $url, $icono);
            }
        } catch (\Throwable $e) {
            Log::error('Notificar rol: ' . $e->getMessage());
        }
    }

    public static function push(int $usuarioId, string $titulo, string $cuerpo, string $url = ''): int
    {
        $publica = Ajustes::get('vapid_publica', '');
        $privada = Ajustes::get('vapid_privada', '');
        if ($publica === '' || $privada === '' || !WebPush::disponible()) {
            return 0;
        }
        $enviados = 0;
        try {
            $subs = DB::todos('SELECT * FROM push_subs WHERE usuario_id = :u', ['u' => $usuarioId]);
            if ($subs === []) {
                return 0;
            }
            $wp = new WebPush($publica, $privada, 'mailto:' . Ajustes::get('correo', 'admin@localhost'));
            foreach ($subs as $s) {
                $codigo = $wp->enviar(
                    ['endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth_key']],
                    [
                        'titulo' => $titulo,
                        'cuerpo' => $cuerpo,
                        'url'    => $url !== '' ? Url::a($url) : Url::a('/portal'),
                        'icono'  => Url::a('/assets/img/icono-192.png'),
                    ]
                );
                if (in_array($codigo, [404, 410], true)) {
                    DB::eliminar('push_subs', 'id = :id', ['id' => (int) $s['id']]);
                } elseif ($codigo >= 200 && $codigo < 300) {
                    $enviados++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Push: ' . $e->getMessage());
        }
        return $enviados;
    }

    public static function noLeidas(int $usuarioId): int
    {
        try {
            return (int) DB::valor(
                'SELECT COUNT(*) FROM notificaciones WHERE usuario_id = :u AND leido_en IS NULL',
                ['u' => $usuarioId],
                0
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
