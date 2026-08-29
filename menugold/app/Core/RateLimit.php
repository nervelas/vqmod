<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Limitacion de intentos por clave (login, pedidos, formularios publicos).
 * Persistente en base de datos para que funcione con varios procesos PHP.
 */
final class RateLimit
{
    /**
     * @return array{permitido:bool,restantes:int,espera:int}
     */
    public static function hit(string $key, int $max, int $ventanaSeg): array
    {
        $clave = substr(hash('sha256', $key), 0, 40);
        $ahora = time();
        try {
            $row = DB::one('SELECT * FROM rate_limits WHERE clave=:c LIMIT 1', ['c' => $clave]);
            if (!$row || ($ahora - (int)strtotime((string)$row['ventana_inicio'])) > $ventanaSeg) {
                DB::upsert('rate_limits', [
                    'clave'          => $clave,
                    'contador'       => 1,
                    'ventana_inicio' => date('Y-m-d H:i:s', $ahora),
                    'bloqueado_hasta'=> null,
                ], ['contador', 'ventana_inicio', 'bloqueado_hasta']);
                return ['permitido' => true, 'restantes' => $max - 1, 'espera' => 0];
            }
            if (!empty($row['bloqueado_hasta']) && strtotime((string)$row['bloqueado_hasta']) > $ahora) {
                return ['permitido' => false, 'restantes' => 0, 'espera' => strtotime((string)$row['bloqueado_hasta']) - $ahora];
            }
            $c = (int)$row['contador'] + 1;
            $bloqueo = $c > $max ? date('Y-m-d H:i:s', $ahora + $ventanaSeg) : null;
            DB::update('rate_limits', ['contador' => $c, 'bloqueado_hasta' => $bloqueo], 'clave=:c', ['c' => $clave]);
            if ($c > $max) {
                return ['permitido' => false, 'restantes' => 0, 'espera' => $ventanaSeg];
            }
            return ['permitido' => true, 'restantes' => max(0, $max - $c), 'espera' => 0];
        } catch (\Throwable $e) {
            // Si la tabla aun no existe (instalacion), no bloqueamos
            return ['permitido' => true, 'restantes' => $max, 'espera' => 0];
        }
    }

    public static function clear(string $key): void
    {
        $clave = substr(hash('sha256', $key), 0, 40);
        try { DB::delete('rate_limits', 'clave=:c', ['c' => $clave]); } catch (\Throwable $e) {}
    }

    public static function purge(): void
    {
        try {
            DB::ejecutar('DELETE FROM rate_limits WHERE ventana_inicio < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        } catch (\Throwable $e) {}
    }
}
