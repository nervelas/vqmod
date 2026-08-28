<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Request;
use MenuGold\Models\Coupon;
use MenuGold\Models\Customer;

/**
 * Clientes de delivery, historial y cupones de descuento.
 */
class Clientes extends Base
{
    private function cli(): Customer { return (new Customer())->forRestaurant($this->rid); }
    private function cup(): Coupon { return (new Coupon())->forRestaurant($this->rid); }

    public function index(): void
    {
        $this->exigir('clientes');
        $q = Request::str('q', '', 60);
        $lista = $this->cli()->buscar($q);
        $this->panel('panel/clientes', [
            'clientes' => $lista,
            'q'        => $q,
            'resumen'  => DB::one(
                'SELECT COUNT(*) n, COALESCE(SUM(total_gastado),0) t, COALESCE(AVG(total_gastado),0) p
                 FROM customers WHERE restaurant_id=:r', ['r' => $this->rid]
            ),
        ]);
    }

    public function ver(array $p = []): void
    {
        $this->exigir('clientes');
        $m = $this->cli();
        $c = $m->findOrFail((int)($p['id'] ?? 0));
        $this->panel('panel/cliente-detalle', [
            'cliente'   => $c,
            'historial' => $m->historial((int)$c['id']),
        ]);
    }

    public function guardar(): void
    {
        $this->exigir('clientes');
        $id = Request::int('id');
        $datos = [
            'nombre'     => Request::str('nombre', '', 120),
            'telefono'   => Customer::normalizarTel(Request::str('telefono', '', 30)),
            'email'      => Request::email('email'),
            'direccion'  => Request::str('direccion', '', 255),
            'referencia' => Request::str('referencia', '', 255),
            'notas'      => Request::str('notas', '', 255),
        ];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre del cliente.');
        if (strlen($datos['telefono']) < 7) $this->fail('Escribe un teléfono válido.');

        $m = $this->cli();
        if ($m->exists('telefono = :t AND id <> :i', ['t' => $datos['telefono'], 'i' => $id])) {
            $this->fail('Ya tienes un cliente con ese teléfono.');
        }
        if ($id > 0) {
            $m->findOrFail($id);
            $m->updateById($id, $datos);
            $this->ok([], 'Cliente actualizado');
        }
        $this->ok(['id' => $m->create($datos)], 'Cliente registrado');
    }

    public function borrar(): void
    {
        $this->exigir('clientes');
        $id = Request::int('id');
        $m = $this->cli();
        $m->findOrFail($id);
        $m->deleteById($id);
        Audit::log('cliente.borrar', 'customers', $id);
        $this->ok([], 'Cliente eliminado');
    }

    // ---------------------------------------------------------------- cupones
    public function cupones(): void
    {
        $this->exigir('cupones');
        $this->panel('panel/cupones', [
            'cupones' => $this->cup()->all('id DESC'),
        ]);
    }

    public function cuponGuardar(): void
    {
        $this->exigir('cupones');
        $id = Request::int('id');
        $datos = [
            'codigo'      => mb_strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', Request::str('codigo', '', 40)) ?? ''),
            'descripcion' => Request::str('descripcion', '', 190),
            'tipo'        => Request::enum('tipo', ['porcentaje', 'monto', 'envio_gratis'], 'porcentaje'),
            'valor'       => round(max(0, Request::float('valor')), 2),
            'min_compra'  => round(max(0, Request::float('min_compra')), 2),
            'usos_max'    => max(0, Request::int('usos_max')),
            'desde'       => Request::date('desde') ?: null,
            'hasta'       => Request::date('hasta') ?: null,
            'activo'      => Request::bool('activo', true) ? 1 : 0,
        ];
        if (mb_strlen($datos['codigo']) < 3) $this->fail('El código debe tener al menos 3 caracteres.');
        if ($datos['tipo'] === 'porcentaje' && ($datos['valor'] <= 0 || $datos['valor'] > 100)) {
            $this->fail('El porcentaje debe estar entre 1 y 100.');
        }

        $m = $this->cup();
        if ($m->exists('codigo = :c AND id <> :i', ['c' => $datos['codigo'], 'i' => $id])) {
            $this->fail('Ya tienes un cupón con ese código.');
        }
        if ($id > 0) {
            $m->findOrFail($id);
            $m->updateById($id, $datos);
            Audit::log('cupon.editar', 'coupons', $id, null, $datos);
            $this->ok([], 'Cupón actualizado');
        }
        $nuevo = $m->create($datos);
        Audit::log('cupon.crear', 'coupons', $nuevo, null, $datos);
        $this->ok(['id' => $nuevo], 'Cupón creado');
    }

    public function cuponBorrar(): void
    {
        $this->exigir('cupones');
        $id = Request::int('id');
        $m = $this->cup();
        $m->findOrFail($id);
        $m->deleteById($id);
        Audit::log('cupon.borrar', 'coupons', $id);
        $this->ok([], 'Cupón eliminado');
    }
}
