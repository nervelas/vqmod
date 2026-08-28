<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Subida;
use App\Models\Egreso;

final class EgresosControlador extends Controlador
{
    public function index(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $filtros = [
            'desde'     => Peticion::texto('desde', date('Y-m-01')),
            'hasta'     => Peticion::texto('hasta', date('Y-m-t')),
            'categoria' => Peticion::entero('categoria'),
            'proveedor' => Peticion::entero('proveedor'),
            'buscar'    => Peticion::texto('buscar'),
        ];
        $this->mostrar('admin/egresos/index', [
            'tituloPagina' => 'Egresos',
            'subtitulo'    => 'Gastos del residencial',
            'egresos'      => Egreso::listar($filtros, 400),
            'filtros'      => $filtros,
            'categorias'   => Egreso::categorias(),
            'proveedores'  => Egreso::proveedores(),
            'total'        => Egreso::total($filtros['desde'], $filtros['hasta']),
            'porCategoria' => Egreso::porCategoria($filtros['desde'], $filtros['hasta']),
            'cuentas'      => Egreso::saldosCuentas(),
        ]);
    }

    public function nuevo(int $id = 0): void
    {
        $this->exigirRol('admin', 'contabilidad');
        $egreso = $id > 0 ? Egreso::porId($id) : null;
        if ($id > 0 && $egreso === null) {
            $this->error('El egreso no existe.', '/admin/egresos');
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $monto = Peticion::decimal('monto');
            $descripcion = Peticion::texto('descripcion');
            if ($monto <= 0 || $descripcion === '') {
                $this->error('Indique la descripción y un monto mayor que cero.', '/admin/egresos/nuevo');
            }
            $archivo = Subida::guardar('archivo', 'facturas', array_merge(Subida::IMAGENES, Subida::DOCS), 8);
            $nuevo = Egreso::guardar([
                'categoria_id' => Peticion::entero('categoria_id'),
                'proveedor_id' => Peticion::entero('proveedor_id'),
                'cuenta_id'    => Peticion::entero('cuenta_id'),
                'fecha'        => Peticion::texto('fecha', date('Y-m-d')),
                'monto'        => $monto,
                'descripcion'  => $descripcion,
                'documento'    => Peticion::texto('documento') ?: null,
                'metodo'       => Peticion::texto('metodo', 'transferencia'),
                'archivo'      => $archivo,
            ], $id);
            unset($nuevo);
            $this->exito('Egreso guardado.', '/admin/egresos');
        }

        $this->mostrar('admin/egresos/nuevo', [
            'tituloPagina' => $id > 0 ? 'Editar egreso' : 'Registrar egreso',
            'egreso'       => $egreso,
            'categorias'   => Egreso::categorias(),
            'proveedores'  => Egreso::proveedores(),
            'cuentas'      => Egreso::cuentas(),
        ]);
    }

    public function anular(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        Egreso::anular($id, Peticion::texto('motivo', 'Anulado desde el panel'));
        $this->exito('Egreso anulado.', '/admin/egresos');
    }

    public function proveedores(): void
    {
        $this->exigirRol('admin', 'contabilidad');
        if ($this->post()) {
            $this->verificarCsrf();
            $nombre = Peticion::texto('nombre');
            if ($nombre === '') {
                $this->error('Escriba el nombre del proveedor.', '/admin/proveedores');
            }
            $datos = [
                'nombre'   => $nombre,
                'nit'      => Peticion::texto('nit') ?: null,
                'contacto' => Peticion::texto('contacto') ?: null,
                'telefono' => Peticion::texto('telefono') ?: null,
                'correo'   => Peticion::texto('correo') ?: null,
                'servicio' => Peticion::texto('servicio') ?: null,
                'activo'   => Peticion::bool('activo') ? 1 : 0,
            ];
            $id = Peticion::entero('id');
            if ($id > 0) {
                DB::actualizar('proveedores', $datos, 'id = :id', ['id' => $id]);
            } else {
                DB::insertar('proveedores', $datos);
            }
            Auditoria::registrar('guardar_proveedor', 'proveedores', $id ?: null, $nombre);
            $this->exito('Proveedor guardado.', '/admin/proveedores');
        }

        $this->mostrar('admin/egresos/proveedores', [
            'tituloPagina' => 'Proveedores',
            'subtitulo'    => 'Empresas y personas que prestan servicios',
            'proveedores'  => DB::todos(
                'SELECT p.*, (SELECT COALESCE(SUM(e.monto),0) FROM egresos e
                              WHERE e.proveedor_id = p.id AND e.estado = "registrado"
                                AND YEAR(e.fecha) = YEAR(CURDATE())) AS pagado_anio
                 FROM proveedores p ORDER BY p.activo DESC, p.nombre'
            ),
        ]);
    }

    public function categorias(): void
    {
        $this->exigirRol('admin', 'contabilidad');
        if ($this->post()) {
            $this->verificarCsrf();
            $nombre = Peticion::texto('nombre');
            if ($nombre === '') {
                $this->error('Escriba el nombre de la categoría.', '/admin/categorias');
            }
            $datos = [
                'nombre' => $nombre,
                'color'  => preg_match('/^#[0-9a-f]{6}$/i', Peticion::texto('color')) ? Peticion::texto('color') : '#B94E27',
                'activo' => Peticion::bool('activo') ? 1 : 0,
            ];
            $id = Peticion::entero('id');
            if ($id > 0) {
                DB::actualizar('categorias_egreso', $datos, 'id = :id', ['id' => $id]);
            } else {
                DB::insertar('categorias_egreso', $datos);
            }
            $this->exito('Categoría guardada.', '/admin/categorias');
        }
        $this->mostrar('admin/egresos/categorias', [
            'tituloPagina' => 'Categorías de gasto',
            'categorias'   => Egreso::categorias(false),
        ]);
    }

    public function cuentas(): void
    {
        $this->exigirRol('admin', 'contabilidad');
        if ($this->post()) {
            $this->verificarCsrf();
            $nombre = Peticion::texto('nombre');
            if ($nombre === '') {
                $this->error('Escriba el nombre de la cuenta.', '/admin/cuentas');
            }
            $datos = [
                'nombre'        => $nombre,
                'tipo'          => Peticion::texto('tipo', 'banco') === 'caja' ? 'caja' : 'banco',
                'banco'         => Peticion::texto('banco') ?: null,
                'numero'        => Peticion::texto('numero') ?: null,
                'saldo_inicial' => Peticion::decimal('saldo_inicial'),
                'activo'        => Peticion::bool('activo') ? 1 : 0,
            ];
            $id = Peticion::entero('id');
            if ($id > 0) {
                DB::actualizar('cuentas', $datos, 'id = :id', ['id' => $id]);
            } else {
                DB::insertar('cuentas', $datos);
            }
            Auditoria::registrar('guardar_cuenta', 'cuentas', $id ?: null, $nombre);
            $this->exito('Cuenta guardada.', '/admin/cuentas');
        }
        $this->mostrar('admin/egresos/cuentas', [
            'tituloPagina' => 'Caja y bancos',
            'subtitulo'    => 'Saldos del residencial',
            'cuentas'      => Egreso::saldosCuentas(),
            'total'        => Egreso::saldoTotal(),
        ]);
    }

    public function presupuesto(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $anio = Peticion::entero('anio', (int) date('Y'));

        if ($this->post()) {
            $this->exigirRol('admin', 'contabilidad');
            $this->verificarCsrf();
            foreach (Peticion::arreglo('monto') as $catId => $valor) {
                $monto = (float) str_replace(',', '', (string) $valor);
                DB::q(
                    'INSERT INTO presupuestos (anio, categoria_id, monto) VALUES (:a, :c, :m)
                     ON DUPLICATE KEY UPDATE monto = VALUES(monto)',
                    ['a' => $anio, 'c' => (int) $catId, 'm' => $monto]
                );
            }
            Auditoria::registrar('guardar_presupuesto', 'presupuestos', null, 'Año ' . $anio);
            $this->exito('Presupuesto del año ' . $anio . ' actualizado.', '/admin/presupuesto?anio=' . $anio);
        }

        $this->mostrar('admin/egresos/presupuesto', [
            'tituloPagina' => 'Presupuesto anual',
            'subtitulo'    => 'Año ' . $anio . ' — presupuestado contra ejecutado',
            'anio'         => $anio,
            'filas'        => Egreso::presupuestoVsReal($anio),
            'flujo'        => Egreso::flujo(12),
        ]);
    }
}
