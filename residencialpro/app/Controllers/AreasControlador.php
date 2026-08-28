<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Subida;
use App\Models\Reserva;

final class AreasControlador extends Controlador
{
    public function index(): void
    {
        $this->exigirRol('admin', 'junta');
        if ($this->post()) {
            $this->exigirRol('admin');
            $this->guardarArea(0);
        }
        $this->mostrar('admin/areas/index', [
            'tituloPagina' => 'Áreas comunes',
            'subtitulo'    => 'Espacios que los residentes pueden reservar',
            'areas'        => Reserva::areas(false),
            'reservasMes'  => DB::todos(
                'SELECT a.nombre, COUNT(*) AS n, COALESCE(SUM(r.costo),0) AS total
                 FROM reservas r INNER JOIN areas a ON a.id = r.area_id
                 WHERE DATE_FORMAT(r.fecha, "%Y-%m") = :m AND r.estado IN ("aprobada","completada")
                 GROUP BY a.id, a.nombre ORDER BY n DESC',
                ['m' => date('Y-m')]
            ),
        ]);
    }

    public function editar(int $id = 0): void
    {
        $this->exigirRol('admin');
        $area = Reserva::area($id);
        if ($area === null) {
            $this->error('El área no existe.', '/admin/areas');
        }
        if ($this->post()) {
            $this->guardarArea($id);
        }
        $this->mostrar('admin/areas/editar', [
            'tituloPagina' => 'Editar ' . $area['nombre'],
            'area'         => $area,
        ]);
    }

    private function guardarArea(int $id): void
    {
        $this->verificarCsrf();
        $nombre = Peticion::texto('nombre');
        if ($nombre === '') {
            $this->error('Escriba el nombre del área.', $id > 0 ? '/admin/areas/' . $id . '/editar' : '/admin/areas');
        }
        $dias = Peticion::arreglo('dias');
        $datos = [
            'nombre'       => $nombre,
            'descripcion'  => Peticion::texto('descripcion') ?: null,
            'reglas'       => Peticion::texto('reglas') ?: null,
            'capacidad'    => Peticion::entero('capacidad'),
            'costo'        => Peticion::decimal('costo'),
            'deposito'     => Peticion::decimal('deposito'),
            'hora_desde'   => Peticion::texto('hora_desde', '08:00'),
            'hora_hasta'   => Peticion::texto('hora_hasta', '22:00'),
            'duracion_min' => max(30, Peticion::entero('duracion_min', 240)),
            'aprobacion'   => Peticion::texto('aprobacion') === 'automatica' ? 'automatica' : 'manual',
            'bloquea_mora' => Peticion::bool('bloquea_mora') ? 1 : 0,
            'dias'         => $dias !== [] ? implode(',', array_map('intval', $dias)) : '0,1,2,3,4,5,6',
            'activo'       => Peticion::bool('activo') ? 1 : 0,
        ];
        $foto = Subida::guardar('foto', 'areas', Subida::IMAGENES, 6);
        if ($foto !== null) {
            $datos['foto'] = $foto;
        }
        if ($id > 0) {
            DB::actualizar('areas', $datos, 'id = :id', ['id' => $id]);
            Auditoria::registrar('editar_area', 'areas', $id, $nombre);
            $this->exito('Área actualizada.', '/admin/areas');
        }
        $nuevo = DB::insertar('areas', $datos);
        Auditoria::registrar('crear_area', 'areas', $nuevo, $nombre);
        $this->exito('Área común creada.', '/admin/areas');
    }

    public function reservas(): void
    {
        $this->exigirRol('admin', 'junta');
        $filtros = [
            'estado' => Peticion::texto('estado'),
            'area'   => Peticion::entero('area'),
            'desde'  => Peticion::texto('desde'),
        ];
        $this->mostrar('admin/areas/reservas', [
            'tituloPagina' => 'Reservas',
            'subtitulo'    => 'Solicitudes de las áreas comunes',
            'reservas'     => Reserva::listar($filtros, 200),
            'pendientes'   => Reserva::listar(['estado' => 'pendiente'], 50),
            'areas'        => Reserva::areas(false),
            'filtros'      => $filtros,
        ]);
    }

    public function aprobar(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        if (!Reserva::aprobar($id)) {
            $this->error('La reserva ya fue procesada.', '/admin/reservas');
        }
        $this->exito('Reserva aprobada. El residente fue notificado.', '/admin/reservas');
    }

    public function rechazar(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $motivo = Peticion::texto('motivo');
        if (mb_strlen($motivo) < 5) {
            $this->error('Escriba el motivo para que el residente lo entienda.', '/admin/reservas');
        }
        if (!Reserva::rechazar($id, $motivo)) {
            $this->error('La reserva ya fue procesada.', '/admin/reservas');
        }
        $this->exito('Reserva rechazada. El residente fue notificado.', '/admin/reservas');
    }
}
