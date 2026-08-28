<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Server-Sent Events para cocina, mesero y seguimiento del cliente.
 * Pensado para hosting compartido: ciclo corto con reconexion automatica
 * del navegador. El cliente cae a sondeo cada 5 s si SSE no esta disponible.
 */
final class Sse
{
    public const DURACION = 20;   // segundos por conexion
    public const INTERVALO = 2;   // segundos entre consultas

    public static function start(): void
    {
        while (ob_get_level() > 0) @ob_end_clean();
        @set_time_limit(self::DURACION + 10);
        ignore_user_abort(false);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        echo "retry: 3000\n\n";
        self::flush();
    }

    public static function send(string $event, $data, ?string $id = null): void
    {
        if ($id !== null) echo 'id: ' . $id . "\n";
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        self::flush();
    }

    public static function comment(string $txt = 'ping'): void
    {
        echo ': ' . $txt . "\n\n";
        self::flush();
    }

    public static function flush(): void
    {
        if (ob_get_level() > 0) @ob_flush();
        @flush();
    }

    /**
     * Bucle principal. $productor devuelve null si no hay cambios,
     * o un array con la carga util a enviar.
     */
    public static function loop(callable $productor, string $event = 'actualizacion'): void
    {
        self::start();
        $fin = time() + self::DURACION;
        $ultimoHash = (string)($_SERVER['HTTP_LAST_EVENT_ID'] ?? '');
        $primero = true;

        while (time() < $fin) {
            $payload = $productor();
            if ($payload !== null) {
                $hash = substr(md5(json_encode($payload) ?: ''), 0, 12);
                if ($primero || $hash !== $ultimoHash) {
                    self::send($event, $payload, $hash);
                    $ultimoHash = $hash;
                } else {
                    // Escribimos siempre algo: así PHP detecta si el navegador
                    // ya cerró la pestaña y liberamos el proceso de inmediato.
                    // En hosting compartido cada proceso ocupado cuenta.
                    self::comment('.');
                }
            }
            $primero = false;
            if (connection_aborted()) break;
            sleep(self::INTERVALO);
            if (connection_aborted()) break;
        }
        self::comment('fin');
    }
}
