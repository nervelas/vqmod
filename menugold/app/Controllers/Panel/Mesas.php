<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Request;
use MenuGold\Models\Restaurant;
use MenuGold\Models\RestaurantTable;
use MenuGold\Models\Zone;

/**
 * Mesas, zonas y sus codigos QR.
 */
class Mesas extends Base
{
    private function mesas(): RestaurantTable { return (new RestaurantTable())->forRestaurant($this->rid); }
    private function zonas(): Zone { return (new Zone())->forRestaurant($this->rid); }

    public function index(): void
    {
        $this->exigir('mesas');
        $m = $this->mesas();
        $mesas = $m->where('1=1', [], 'orden ASC, id ASC');
        foreach ($mesas as &$x) {
            $x['token'] = $m->token($x);
            $x['url']   = Restaurant::urlMenu($this->r, (string)$x['nombre'], $x['token']);
        }
        unset($x);

        $this->panel('panel/mesas', [
            'mesas'   => $mesas,
            'zonas'   => $this->zonas()->all('orden ASC'),
            'limites' => $this->limites(),
            'urlGeneral' => Restaurant::urlMenu($this->r),
        ]);
    }

    public function guardar(): void
    {
        $this->exigir('mesas');
        $id = Request::int('id');
        if ($id <= 0) {
            [$cabe, $msg] = $this->cabeEnPlan('mesas');
            if (!$cabe) $this->fail($msg);
        }
        $datos = [
            'nombre'    => Request::str('nombre', '', 40),
            'capacidad' => max(1, min(60, Request::int('capacidad', 4))),
            'zone_id'   => Request::int('zone_id') ?: null,
            'activo'    => Request::bool('activo', true) ? 1 : 0,
        ];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre de la mesa.');

        $m = $this->mesas();
        if ($m->exists('nombre = :n AND id <> :i', ['n' => $datos['nombre'], 'i' => $id])) {
            $this->fail('Ya existe una mesa con ese nombre.');
        }
        if ($id > 0) {
            $m->findOrFail($id);
            $m->updateById($id, $datos);
            $this->ok([], 'Mesa actualizada');
        }
        $datos['orden'] = $m->maxOrder() + 1;
        $nuevo = $m->create($datos);
        Audit::log('mesa.crear', 'tables', $nuevo, null, $datos);
        $this->ok(['id' => $nuevo], 'Mesa creada');
    }

    public function borrar(): void
    {
        $this->exigir('mesas');
        $id = Request::int('id');
        $m = $this->mesas();
        $mesa = $m->findOrFail($id);
        $abiertos = DB::int(
            "SELECT COUNT(*) FROM orders WHERE table_id=:t AND estado IN ('nuevo','preparando','listo','entregado')",
            ['t' => $id]
        );
        if ($abiertos > 0) $this->fail('Esa mesa tiene pedidos abiertos. Ciérrala antes de eliminarla.');
        $m->deleteById($id);
        Audit::log('mesa.borrar', 'tables', $id, $mesa);
        $this->ok([], 'Mesa eliminada');
    }

    public function lote(): void
    {
        $this->exigir('mesas');
        $desde = max(1, Request::int('desde', 1));
        $hasta = max($desde, min($desde + 199, Request::int('hasta', 10)));
        $lim = $this->limites();
        if ((int)$lim['max_mesas'] > 0) {
            $uso = (new Restaurant())->uso($this->rid);
            $disponibles = (int)$lim['max_mesas'] - (int)$uso['mesas'];
            if ($disponibles <= 0) $this->fail('Alcanzaste el límite de mesas de tu plan.');
            $hasta = min($hasta, $desde + $disponibles - 1);
        }
        $n = $this->mesas()->crearLote(
            $desde, $hasta,
            Request::str('prefijo', 'Mesa', 20) ?: 'Mesa',
            Request::int('zone_id') ?: null,
            max(1, min(60, Request::int('capacidad', 4)))
        );
        Audit::log('mesa.lote', 'tables', 0, null, ['creadas' => $n]);
        $this->ok(['creadas' => $n], $n > 0 ? "Se crearon {$n} mesa(s)" : 'No se creó ninguna mesa nueva');
    }

    public function zonaGuardar(): void
    {
        $this->exigir('mesas');
        $id = Request::int('id');
        $datos = ['nombre' => Request::str('nombre', '', 80)];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre de la zona.');
        $z = $this->zonas();
        if ($id > 0) {
            $z->findOrFail($id);
            $z->updateById($id, $datos);
            $this->ok([], 'Zona actualizada');
        }
        $datos['orden'] = $z->maxOrder() + 1;
        $this->ok(['id' => $z->create($datos)], 'Zona creada');
    }

    public function zonaBorrar(): void
    {
        $this->exigir('mesas');
        $id = Request::int('id');
        $z = $this->zonas();
        $z->findOrFail($id);
        DB::update('tables', ['zone_id' => null], 'zone_id = :z AND restaurant_id = :r', ['z' => $id, 'r' => $this->rid]);
        $z->deleteById($id);
        $this->ok([], 'Zona eliminada');
    }
}
