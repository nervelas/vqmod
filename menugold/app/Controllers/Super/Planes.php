<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Super;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Request;
use MenuGold\Models\Plan;

/**
 * Planes comerciales de la plataforma.
 */
class Planes extends Panel
{
    public function index(): void
    {
        $planes = (new Plan())->all('orden ASC');
        foreach ($planes as &$p) {
            $p['restaurantes'] = DB::int('SELECT COUNT(*) FROM restaurants WHERE plan_id = :p', ['p' => (int)$p['id']]);
        }
        unset($p);
        $this->super('super/planes', ['planes' => $planes]);
    }

    public function guardar(): void
    {
        $id = Request::int('id');
        $carac = array_values(array_filter(array_map(
            static fn($x) => mb_substr(trim((string)$x), 0, 150),
            explode("\n", Request::str('caracteristicas', '', 2000))
        )));

        $datos = [
            'nombre'         => Request::str('nombre', '', 60),
            'descripcion'    => Request::str('descripcion', '', 255),
            'precio_mensual' => round(max(0, Request::float('precio_mensual')), 2),
            'precio_anual'   => round(max(0, Request::float('precio_anual')), 2),
            'max_productos'  => max(0, Request::int('max_productos')),
            'max_mesas'      => max(0, Request::int('max_mesas')),
            'max_sucursales' => max(1, Request::int('max_sucursales', 1)),
            'max_usuarios'   => max(0, Request::int('max_usuarios')),
            'caracteristicas'=> $carac,
            'destacado'      => Request::bool('destacado') ? 1 : 0,
            'activo'         => Request::bool('activo', true) ? 1 : 0,
        ];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre del plan.');

        $m = new Plan();
        if ($id > 0) {
            $m->findOrFail($id);
            $m->updateById($id, $datos);
            Audit::log('plataforma.plan', 'plans', $id, null, $datos);
            $this->ok([], 'Plan actualizado');
        }
        $datos['slug'] = str_slug($datos['nombre']);
        $base = $datos['slug'];
        $i = 2;
        while (DB::int('SELECT COUNT(*) FROM plans WHERE slug=:s', ['s' => $datos['slug']]) > 0) {
            $datos['slug'] = $base . '-' . $i++;
        }
        $datos['orden'] = $m->maxOrder() + 1;
        $nuevo = $m->create($datos);
        Audit::log('plataforma.plan', 'plans', $nuevo, null, $datos);
        $this->ok(['id' => $nuevo], 'Plan creado');
    }

    public function borrar(): void
    {
        $id = Request::int('id');
        $m = new Plan();
        $m->findOrFail($id);
        $usados = DB::int('SELECT COUNT(*) FROM restaurants WHERE plan_id = :p', ['p' => $id]);
        if ($usados > 0) $this->fail("Ese plan lo usan {$usados} restaurante(s). Cámbialos antes de eliminarlo.");
        $m->deleteById($id);
        $this->ok([], 'Plan eliminado');
    }
}
