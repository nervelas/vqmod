<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Super;

use MenuGold\Core\Auth;
use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\View;

/**
 * Escritorio del administrador de la plataforma.
 */
class Panel extends Controller
{
    protected function super(string $vista, array $datos = []): void
    {
        View::share('usuario', Auth::user());
        View::share('flashes', flash());
        View::display($vista, $datos, 'super');
    }

    public function index(): void
    {
        $hoy = date('Y-m-d');
        $mes = date('Y-m-01');

        $restaurantes = DB::all(
            "SELECT r.*, p.nombre AS plan,
                    (SELECT COUNT(*) FROM products x WHERE x.restaurant_id = r.id) AS productos,
                    (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.id AND o.creado >= :m
                        AND o.estado IN ('entregado','pagado')) AS pedidos_mes,
                    (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.restaurant_id = r.id AND o.creado >= :m2
                        AND o.estado IN ('entregado','pagado')) AS ventas_mes
             FROM restaurants r LEFT JOIN plans p ON p.id = r.plan_id
             ORDER BY r.creado DESC",
            ['m' => $mes, 'm2' => $mes]
        );

        $porVencer = array_values(array_filter($restaurantes, static function ($r) {
            if (empty($r['vence_el'])) return false;
            $d = (strtotime((string)$r['vence_el']) - strtotime(date('Y-m-d'))) / 86400;
            return $d <= 15;
        }));

        $this->super('super/panel', [
            'restaurantes' => $restaurantes,
            'porVencer'    => $porVencer,
            'resumen'      => [
                'total'      => count($restaurantes),
                'activos'    => count(array_filter($restaurantes, static fn($r) => $r['estado'] === 'activo')),
                'suspendidos'=> count(array_filter($restaurantes, static fn($r) => $r['estado'] === 'suspendido')),
                'prueba'     => count(array_filter($restaurantes, static fn($r) => $r['estado'] === 'prueba')),
                'pedidos_hoy'=> DB::int("SELECT COUNT(*) FROM orders WHERE DATE(creado)=:d AND estado<>'anulado'", ['d' => $hoy]),
                'ventas_mes' => (float)DB::value("SELECT COALESCE(SUM(total),0) FROM orders WHERE creado>=:m AND estado IN ('entregado','pagado')", ['m' => $mes], 0),
                'mensajes'   => DB::int('SELECT COUNT(*) FROM contact_messages WHERE leido = 0'),
                'usuarios'   => DB::int('SELECT COUNT(*) FROM users'),
            ],
            'ultimosMensajes' => DB::all('SELECT * FROM contact_messages ORDER BY creado DESC LIMIT 6'),
        ]);
    }
}
