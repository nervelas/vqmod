<?php
namespace MenuGold\Controllers\Api;

use MenuGold\Controllers\Admin\BaseController;
use MenuGold\Core\Response;
use MenuGold\Models\Order;

/**
 * Tiempo real por Server-Sent Events.
 *
 * El panel además sondea cada 5 s: ese sondeo es la base fiable y el flujo
 * SSE solo acelera. Así funciona igual en hosting que corta conexiones largas.
 */
class StreamController extends BaseController
{
    protected $ability = 'orders';

    /** Sondeo: la base fiable del tiempo real. */
    public function pulse()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return Response::json(Order::pulse())->header('Cache-Control', 'no-store');
    }

    public function stream()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        // Clave: soltar el archivo de sesión antes del bucle largo. Si no, PHP
        // lo mantiene bloqueado y cualquier otra petición del mismo navegador
        // (abrir el salón, guardar un platillo) se queda esperando.
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

        @set_time_limit(0);
        ignore_user_abort(false);
        while (ob_get_level() > 0) { ob_end_clean(); }

        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=UTF-8');
            header('Cache-Control: no-cache, no-store');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');   // evita el buffer de Nginx
        }

        echo "retry: 3000\n\n";
        @flush();

        $last = '';
        $start = time();
        // Se corta a los 25 s: en hosting compartido hay pocos procesos PHP y no
        // conviene ocupar uno más tiempo. El navegador reconecta solo.
        while ((time() - $start) < 25) {
            if (connection_aborted()) { break; }
            $pulse = Order::pulse();
            if ($pulse['hash'] !== $last) {
                $last = $pulse['hash'];
                echo "event: pulse\n";
                echo 'data: ' . json_encode($pulse, JSON_UNESCAPED_UNICODE) . "\n\n";
            } else {
                echo ": ping\n\n";
            }
            @flush();
            sleep(2);
        }
        echo "event: bye\ndata: {}\n\n";
        @flush();
        exit;
    }
}
