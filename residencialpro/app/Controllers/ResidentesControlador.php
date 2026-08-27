<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Correo;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Validador;
use App\Models\Casa;
use App\Models\Usuario;

final class ResidentesControlador extends Controlador
{
    public function index(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        [$porPagina, $desde, $pagina] = $this->paginacion(30);
        $buscar = Peticion::texto('buscar');
        $tipo   = Peticion::texto('tipo');
        $activo = Peticion::texto('activo', '1');

        $where = ['1=1'];
        $params = [];
        if ($buscar !== '') {
            $where[] = '(r.nombre LIKE :b OR r.dpi LIKE :b OR r.correo LIKE :b OR r.telefono LIKE :b OR c.codigo LIKE :b)';
            $params['b'] = '%' . $buscar . '%';
        }
        if ($tipo !== '') {
            $where[] = 'r.tipo = :t';
            $params['t'] = $tipo;
        }
        if ($activo !== '') {
            $where[] = 'r.activo = :a';
            $params['a'] = (int) $activo;
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) DB::valor(
            'SELECT COUNT(*) FROM residentes r LEFT JOIN casas c ON c.id = r.casa_id WHERE ' . $sqlWhere,
            $params, 0
        );
        $residentes = DB::todos(
            'SELECT r.*, c.codigo AS casa, f.nombre AS fase, u.usuario AS acceso, u.activo AS acceso_activo
             FROM residentes r
             LEFT JOIN casas c ON c.id = r.casa_id
             LEFT JOIN fases f ON f.id = c.fase_id
             LEFT JOIN usuarios u ON u.id = r.usuario_id
             WHERE ' . $sqlWhere . '
             ORDER BY c.codigo, (r.tipo = "propietario") DESC, r.nombre
             LIMIT ' . $porPagina . ' OFFSET ' . $desde,
            $params
        );

        $this->mostrar('admin/residentes/index', [
            'tituloPagina' => 'Residentes',
            'subtitulo'    => number_format($total) . ' persona(s)',
            'residentes'   => $residentes,
            'total'        => $total,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'buscar'       => $buscar,
            'tipo'         => $tipo,
            'activo'       => $activo,
        ]);
    }

    public function nuevo(): void
    {
        $this->exigirRol('admin');
        $this->formulario(0);
    }

    public function editar(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->formulario($id);
    }

    private function formulario(int $id): void
    {
        $residente = $id > 0 ? DB::uno('SELECT * FROM residentes WHERE id = :id', ['id' => $id]) : null;
        if ($id > 0 && $residente === null) {
            $this->error('El residente no existe.', '/admin/residentes');
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $v = new Validador();
            $nombre = Peticion::texto('nombre');
            $casaId = Peticion::entero('casa_id');
            $correo = mb_strtolower(Peticion::texto('correo'));
            $v->requerido('nombre', $nombre, 'El nombre')
              ->largoMax('nombre', $nombre, 140, 'El nombre')
              ->numero('casa_id', $casaId, 'La vivienda', 1)
              ->correo('correo', $correo, 'El correo', true)
              ->en('tipo', Peticion::texto('tipo'), ['propietario', 'inquilino', 'familiar'], 'El tipo de residente');

            if ($v->ok()) {
                $datos = [
                    'casa_id'      => $casaId,
                    'nombre'       => $nombre,
                    'tipo'         => Peticion::texto('tipo'),
                    'dpi'          => Peticion::texto('dpi') ?: null,
                    'nit'          => Peticion::texto('nit') ?: null,
                    'correo'       => $correo ?: null,
                    'telefono'     => Peticion::texto('telefono') ?: null,
                    'whatsapp'     => Peticion::texto('whatsapp') ?: (Peticion::texto('telefono') ?: null),
                    'fecha_inicio' => Peticion::texto('fecha_inicio') ?: null,
                    'fecha_fin'    => Peticion::texto('fecha_fin') ?: null,
                    'activo'       => Peticion::bool('activo') ? 1 : 0,
                    'notas'        => Peticion::texto('notas') ?: null,
                ];
                if ($id > 0) {
                    DB::actualizar('residentes', $datos, 'id = :id', ['id' => $id]);
                    DB::insertar('residentes_historial', [
                        'casa_id'    => $casaId,
                        'residente'  => $nombre,
                        'tipo'       => $datos['tipo'],
                        'accion'     => 'actualización',
                        'detalle'    => 'Datos del residente actualizados.',
                        'usuario_id' => Auth::id() ?: null,
                    ]);
                    Auditoria::registrar('editar_residente', 'residentes', $id, $nombre);
                    $this->exito('Residente actualizado.', '/admin/casas/' . $casaId);
                }
                $nuevo = DB::insertar('residentes', $datos);
                DB::insertar('residentes_historial', [
                    'casa_id'    => $casaId,
                    'residente'  => $nombre,
                    'tipo'       => $datos['tipo'],
                    'accion'     => 'ingreso',
                    'detalle'    => 'Alta de residente en la vivienda.',
                    'usuario_id' => Auth::id() ?: null,
                ]);
                Auditoria::registrar('crear_residente', 'residentes', $nuevo, $nombre);
                if (Peticion::bool('crear_acceso') && $correo !== '') {
                    $this->generarAcceso($nuevo, true);
                }
                $this->exito('Residente registrado.', '/admin/casas/' . $casaId);
            }
            $this->error($v->primerError(), $id > 0 ? '/admin/residentes/' . $id . '/editar' : '/admin/residentes/nuevo');
        }

        $casaPre = Peticion::entero('casa');
        $this->mostrar('admin/residentes/editar', [
            'tituloPagina' => $id > 0 ? 'Editar residente' : 'Nuevo residente',
            'residente'    => $residente,
            'casas'        => Casa::opciones(),
            'casaPre'      => $casaPre,
        ]);
    }

    public function baja(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $r = DB::uno('SELECT * FROM residentes WHERE id = :id', ['id' => $id]);
        if ($r === null) {
            $this->error('El residente no existe.', '/admin/residentes');
        }
        DB::actualizar('residentes', [
            'activo'    => 0,
            'fecha_fin' => date('Y-m-d'),
        ], 'id = :id', ['id' => $id]);
        DB::insertar('residentes_historial', [
            'casa_id'    => (int) $r['casa_id'],
            'residente'  => (string) $r['nombre'],
            'tipo'       => (string) $r['tipo'],
            'accion'     => 'salida',
            'detalle'    => Peticion::texto('motivo', 'Baja del residente.'),
            'usuario_id' => Auth::id() ?: null,
        ]);
        if (!empty($r['usuario_id'])) {
            DB::actualizar('usuarios', ['activo' => 0], 'id = :id', ['id' => (int) $r['usuario_id']]);
        }
        Auditoria::registrar('baja_residente', 'residentes', $id, (string) $r['nombre']);
        $this->exito('Residente dado de baja. Su acceso al portal quedó desactivado.', '/admin/casas/' . (int) $r['casa_id']);
    }

    public function crearAcceso(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $this->generarAcceso($id, true);
        $r = DB::uno('SELECT casa_id FROM residentes WHERE id = :id', ['id' => $id]);
        $this->exito('Acceso creado. Se envió la contraseña por correo al residente.', '/admin/casas/' . (int) ($r['casa_id'] ?? 0));
    }

    /** Crea (o reactiva) el usuario del portal para un residente. */
    private function generarAcceso(int $residenteId, bool $enviarCorreo): void
    {
        $r = DB::uno('SELECT * FROM residentes WHERE id = :id', ['id' => $residenteId]);
        if ($r === null) {
            return;
        }
        $casa = Casa::porId((int) $r['casa_id']);
        if (empty($r['correo'])) {
            $this->error('El residente no tiene correo electrónico. Agréguelo antes de crear el acceso.', '/admin/casas/' . (int) $r['casa_id']);
        }
        if (!empty($r['usuario_id'])) {
            DB::actualizar('usuarios', ['activo' => 1], 'id = :id', ['id' => (int) $r['usuario_id']]);
            return;
        }
        $existente = Usuario::porCorreo((string) $r['correo']);
        if ($existente !== null) {
            DB::actualizar('residentes', ['usuario_id' => (int) $existente['id']], 'id = :id', ['id' => $residenteId]);
            return;
        }
        $clave   = self::claveTemporal();
        $usuario = Usuario::sugerirUsuario((string) $r['nombre'], (string) ($casa['codigo'] ?? ''));
        $uid = Usuario::crear([
            'rol'      => 'residente',
            'nombre'   => (string) $r['nombre'],
            'usuario'  => $usuario,
            'correo'   => (string) $r['correo'],
            'telefono' => $r['telefono'] ?? null,
            'clave'    => $clave,
        ]);
        DB::actualizar('residentes', ['usuario_id' => $uid], 'id = :id', ['id' => $residenteId]);

        if ($enviarCorreo) {
            Correo::enviar(
                (string) $r['correo'],
                (string) $r['nombre'],
                'Su acceso al portal de ' . Ajustes::get('nombre', 'el residencial'),
                Correo::plantillaHtml(
                    'Bienvenido al portal del residente',
                    '<p>Estimado(a) ' . e((string) $r['nombre']) . ',</p>'
                    . '<p>La administración le creó su acceso al portal de residentes de <strong>'
                    . e(Ajustes::get('nombre', '')) . '</strong>, correspondiente a la vivienda <strong>'
                    . e((string) ($casa['codigo'] ?? '')) . '</strong>.</p>'
                    . '<table style="width:100%;border-collapse:collapse;margin:16px 0">'
                    . '<tr><td style="padding:8px 0;color:#8A8F8B">Usuario</td><td style="padding:8px 0"><strong>' . e($usuario) . '</strong></td></tr>'
                    . '<tr><td style="padding:8px 0;color:#8A8F8B">Contraseña temporal</td><td style="padding:8px 0"><strong>' . e($clave) . '</strong></td></tr>'
                    . '</table>'
                    . '<p>Le recomendamos cambiarla la primera vez que ingrese, desde <em>Mi perfil</em>.</p>'
                    . '<p>Desde el portal podrá consultar su estado de cuenta, subir comprobantes de pago, '
                    . 'autorizar visitas con código QR y reservar las áreas comunes.</p>',
                    'Ingresar al portal',
                    \App\Core\Url::absoluta('/acceso')
                )
            );
        }
    }

    public static function claveTemporal(): string
    {
        $silabas = ['ci', 'pre', 'sol', 'lu', 'ver', 'na', 'ro', 'mi', 'ta', 'be'];
        $palabra = '';
        for ($i = 0; $i < 3; $i++) {
            $palabra .= $silabas[random_int(0, count($silabas) - 1)];
        }
        return ucfirst($palabra) . random_int(1000, 9999);
    }

    // ---------------------------------------------------- Datos asociados

    public function guardarVehiculo(): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $casaId = Peticion::entero('casa_id');
        $placa  = mb_strtoupper(preg_replace('/\s+/', '', Peticion::texto('placa')) ?? '');
        if ($casaId <= 0 || $placa === '') {
            $this->error('Indique la vivienda y la placa del vehículo.', '/admin/casas/' . $casaId);
        }
        $id = Peticion::entero('id');
        $datos = [
            'casa_id'      => $casaId,
            'residente_id' => Peticion::entero('residente_id') ?: null,
            'placa'        => $placa,
            'marca'        => Peticion::texto('marca') ?: null,
            'linea'        => Peticion::texto('linea') ?: null,
            'color'        => Peticion::texto('color') ?: null,
            'anio'         => Peticion::entero('anio') ?: null,
            'activo'       => 1,
        ];
        if ($id > 0) {
            DB::actualizar('vehiculos', $datos, 'id = :id', ['id' => $id]);
        } else {
            DB::insertar('vehiculos', $datos);
        }
        Auditoria::registrar('guardar_vehiculo', 'vehiculos', $id ?: null, $placa);
        $this->exito('Vehículo guardado.', '/admin/casas/' . $casaId);
    }

    public function eliminarVehiculo(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $v = DB::uno('SELECT * FROM vehiculos WHERE id = :id', ['id' => $id]);
        if ($v !== null) {
            DB::eliminar('vehiculos', 'id = :id', ['id' => $id]);
            Auditoria::registrar('eliminar_vehiculo', 'vehiculos', $id, (string) $v['placa']);
        }
        $this->exito('Vehículo eliminado.', '/admin/casas/' . (int) ($v['casa_id'] ?? 0));
    }

    public function guardarMascota(): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $casaId = Peticion::entero('casa_id');
        $nombre = Peticion::texto('nombre');
        if ($casaId <= 0 || $nombre === '') {
            $this->error('Indique la vivienda y el nombre de la mascota.', '/admin/casas/' . $casaId);
        }
        DB::insertar('mascotas', [
            'casa_id' => $casaId,
            'nombre'  => $nombre,
            'especie' => Peticion::texto('especie') ?: null,
            'raza'    => Peticion::texto('raza') ?: null,
            'color'   => Peticion::texto('color') ?: null,
            'vacunas' => Peticion::texto('vacunas') ?: null,
        ]);
        $this->exito('Mascota registrada.', '/admin/casas/' . $casaId);
    }

    public function guardarEmpleado(): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $casaId = Peticion::entero('casa_id');
        $nombre = Peticion::texto('nombre');
        if ($casaId <= 0 || $nombre === '') {
            $this->error('Indique la vivienda y el nombre del empleado.', '/admin/casas/' . $casaId);
        }
        $dias = Peticion::arreglo('dias');
        DB::insertar('empleados_casa', [
            'casa_id'    => $casaId,
            'nombre'     => $nombre,
            'dpi'        => Peticion::texto('dpi') ?: null,
            'puesto'     => Peticion::texto('puesto') ?: null,
            'telefono'   => Peticion::texto('telefono') ?: null,
            'dias'       => $dias !== [] ? implode(',', array_map('intval', $dias)) : '1,2,3,4,5',
            'hora_desde' => Peticion::texto('hora_desde') ?: '07:00',
            'hora_hasta' => Peticion::texto('hora_hasta') ?: '17:00',
            'activo'     => 1,
        ]);
        Auditoria::registrar('registrar_empleado', 'empleados_casa', null, $nombre);
        $this->exito('Personal autorizado registrado. Ya puede ingresar por la garita en el horario indicado.', '/admin/casas/' . $casaId);
    }
}
