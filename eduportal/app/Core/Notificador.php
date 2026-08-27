<?php
declare(strict_types=1);

namespace App\Core;

final class Notificador
{
    public static function crear(int $userId, string $titulo, string $cuerpo = '', string $url = ''): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            Database::run(
                'INSERT INTO notificaciones (user_id, titulo, cuerpo, url) VALUES (:u, :t, :c, :url)',
                ['u' => $userId, 't' => mb_substr($titulo, 0, 160), 'c' => mb_substr($cuerpo, 0, 255), 'url' => mb_substr($url, 0, 190)]
            );
        } catch (\Throwable $e) {
            Logger::error('No se pudo crear la notificacion', ['e' => $e->getMessage()]);
        }
    }

    public static function pendientes(int $userId): int
    {
        return (int)Database::value(
            'SELECT COUNT(*) FROM notificaciones WHERE user_id = :u AND leido_en IS NULL',
            ['u' => $userId],
            0
        );
    }

    public static function ultimas(int $userId, int $limite = 12): array
    {
        return Database::all(
            'SELECT * FROM notificaciones WHERE user_id = :u ORDER BY creado_en DESC LIMIT ' . max(1, min(50, $limite)),
            ['u' => $userId]
        );
    }

    public static function marcarLeidas(int $userId): void
    {
        Database::run(
            'UPDATE notificaciones SET leido_en = :t WHERE user_id = :u AND leido_en IS NULL',
            ['t' => date('Y-m-d H:i:s'), 'u' => $userId]
        );
    }

    /** Registra la suscripcion Web Push del navegador. */
    public static function guardarSuscripcion(int $userId, string $endpoint, ?string $p256dh, ?string $auth): void
    {
        $existe = Database::value('SELECT id FROM push_subs WHERE endpoint = :e', ['e' => $endpoint]);
        if ($existe) {
            Database::run(
                'UPDATE push_subs SET user_id = :u, p256dh = :p, auth_key = :a WHERE id = :id',
                ['u' => $userId, 'p' => $p256dh, 'a' => $auth, 'id' => (int)$existe]
            );
            return;
        }
        Database::run(
            'INSERT INTO push_subs (user_id, endpoint, p256dh, auth_key) VALUES (:u, :e, :p, :a)',
            ['u' => $userId, 'e' => mb_substr($endpoint, 0, 500), 'p' => $p256dh, 'a' => $auth]
        );
    }
}
