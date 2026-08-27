<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Mail;
use App\Core\Notificador;
use App\Core\Settings;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Cobranza;
use App\Servicios\Documentos;
use Vendor\Xlsx\Xlsx;

final class Cobros extends Controller
{
    public function index(): string
    {
        $this->requirePermiso('cobranza.ver');
        $ciclo = Academico::cicloActivoId();
        return $this->view('admin/cobros/index', [
            'titulo'     => 'Cobranza',
            'scripts'    => ['js/cobros.js'],
            'kpi'        => \App\Models\Kpi::panel(),
            'porAprobar' => Database::all(
                'SELECT p.*, a.nombres, a.apellidos, a.codigo FROM pagos p
                 JOIN alumnos a ON a.id = p.alumno_id
                 WHERE p.estado = \'revision\' ORDER BY p.creado_en',
                []
            ),
            'ultimos'    => Database::all(
                'SELECT p.*, a.nombres, a.apellidos, a.codigo FROM pagos p
                 JOIN alumnos a ON a.id = p.alumno_id
                 WHERE p.estado = \'aprobado\' ORDER BY p.id DESC LIMIT 12'
            ),
            'proximos'   => Cobranza::proximosVencimientos(10),
            'ciclo'      => $ciclo,
        ]);
    }

    // ---------------- Conceptos ----------------

    public function conceptos(): string
    {
        $this->requirePermiso('cobranza.editar');
        return $this->view('admin/cobros/conceptos', [
            'titulo'    => 'Conceptos de cobro',
            'conceptos' => Cobranza::conceptos(null, false),
            'niveles'   => Academico::niveles(),
            'ciclo'     => Academico::cicloActivo(),
        ]);
    }

    public function guardarConcepto(): string
    {
        $this->requirePermiso('cobranza.editar');
        $this->requireCsrf();
        $v = Validator::make($this->req->all(), [
            'nombre'          => 'required|len:3,120',
            'tipo'            => 'required|in:inscripcion,colegiatura,transporte,uniforme,actividad,otro',
            'monto'           => 'required|numeric|min:0|max:1000000',
            'dia_vencimiento' => 'required|int|min:1|max:28',
            'mora_tipo'       => 'required|in:fijo,porcentaje',
            'mora_valor'      => 'nullable|numeric|min:0|max:100000',
            'mora_gracia'     => 'nullable|int|min:0|max:60',
            'nivel_id'        => 'nullable|int',
        ], ['nombre' => 'nombre', 'monto' => 'monto', 'dia_vencimiento' => 'dia de vencimiento']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('cobranza/conceptos');
        }
        $id = $this->req->int('id');
        $campos = [
            'nombre'          => $v->get('nombre'),
            'tipo'            => $v->get('tipo'),
            'monto'           => round((float)$v->get('monto'), 2),
            'recurrente'      => $this->req->bool('recurrente') ? 1 : 0,
            'dia_vencimiento' => (int)$v->get('dia_vencimiento'),
            'mora_tipo'       => $v->get('mora_tipo'),
            'mora_valor'      => round((float)($v->get('mora_valor') ?? 0), 2),
            'mora_gracia'     => (int)($v->get('mora_gracia') ?? 0),
            'aplica_beca'     => $this->req->bool('aplica_beca') ? 1 : 0,
            'aplica_hermanos' => $this->req->bool('aplica_hermanos') ? 1 : 0,
            'nivel_id'        => $v->get('nivel_id') ?: null,
            'activo'          => $this->req->bool('activo') ? 1 : 0,
        ];
        if ($id > 0) {
            $sets = [];
            foreach (array_keys($campos) as $c) {
                $sets[] = "$c = :$c";
            }
            $campos['id'] = $id;
            $campos['ciclo'] = Academico::cicloActivoId();
            Database::run('UPDATE conceptos SET ' . implode(', ', $sets) . ' WHERE id = :id AND ciclo_id = :ciclo', $campos);
            Audit::log('concepto.actualizar', 'conceptos', $id, (string)$v->get('nombre'));
        } else {
            $campos['ciclo_id'] = Academico::cicloActivoId();
            $cols = implode(', ', array_keys($campos));
            $vals = ':' . implode(', :', array_keys($campos));
            $id = Database::insert("INSERT INTO conceptos ($cols) VALUES ($vals)", $campos);
            Audit::log('concepto.crear', 'conceptos', $id, (string)$v->get('nombre'));
        }
        $this->ok('El concepto de cobro fue guardado.');
        return $this->redirect('cobranza/conceptos');
    }

    public function eliminarConcepto(string $id): string
    {
        $this->requirePermiso('cobranza.editar');
        $this->requireCsrf();
        $usados = (int)Database::value('SELECT COUNT(*) FROM cargos WHERE concepto_id = :c', ['c' => (int)$id], 0);
        if ($usados > 0) {
            Database::run('UPDATE conceptos SET activo = 0 WHERE id = :c', ['c' => (int)$id]);
            $this->aviso('El concepto ya tiene cargos generados, por lo que se desactivo en lugar de eliminarse.');
        } else {
            Database::run('DELETE FROM conceptos WHERE id = :c', ['c' => (int)$id]);
            $this->ok('Concepto eliminado.');
        }
        Audit::log('concepto.eliminar', 'conceptos', (int)$id);
        return $this->redirect('cobranza/conceptos');
    }

    // ---------------- Generacion de cargos ----------------

    public function generarForm(): string
    {
        $this->requirePermiso('cobranza.editar');
        return $this->view('admin/cobros/generar', [
            'titulo'    => 'Generar cargos del mes',
            'conceptos' => Cobranza::conceptos(),
            'ciclo'     => Academico::cicloActivo(),
        ]);
    }

    public function generar(): string
    {
        $this->requirePermiso('cobranza.editar');
        $this->requireCsrf();
        $anio  = $this->req->int('anio', (int)date('Y'));
        $desde = $this->req->int('mes_desde', (int)date('n'));
        $hasta = $this->req->int('mes_hasta', $desde);
        $conceptoId = $this->req->int('concepto_id');
        if ($anio < 2000 || $anio > 2100 || $desde < 1 || $desde > 12 || $hasta < 1 || $hasta > 12) {
            $this->error('El periodo indicado no es valido.');
            return $this->redirect('cobranza/generar');
        }
        if ($hasta < $desde) {
            $this->error('El mes final no puede ser anterior al mes inicial.');
            return $this->redirect('cobranza/generar');
        }
        $r = Cobranza::generarRango(Academico::cicloActivoId(), $anio, $desde, $hasta, $conceptoId > 0 ? $conceptoId : null);
        $meses = $r['meses'] === 1
            ? mes_nombre($desde)
            : mes_nombre($desde) . ' a ' . mes_nombre($hasta);
        $this->ok("Se generaron {$r['creados']} cargos mensuales de {$meses} ({$r['omitidos']} ya existian).");
        return $this->redirect('cobranza/generar');
    }

    // ---------------- Estado de cuenta y cobro ----------------

    public function estadoCuenta(string $alumno): string
    {
        $this->requirePermiso('cobranza.ver');
        $alumnoId = (int)$alumno;
        if (!Auth::puedeVerAlumno($alumnoId)) {
            throw new HttpException(403, 'No tiene acceso a este alumno.');
        }
        $datos = Alumno::porId($alumnoId);
        if (!$datos) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        return $this->view('admin/cobros/estado', [
            'titulo'  => 'Estado de cuenta',
            'alumno'  => $datos,
            'cuenta'  => Cobranza::estadoCuenta($alumnoId),
            'pagos'   => Database::all(
                'SELECT * FROM pagos WHERE alumno_id = :a ORDER BY id DESC LIMIT 30',
                ['a' => $alumnoId]
            ),
            'encargado' => Alumno::encargadoPrincipal($alumnoId),
        ]);
    }

    public function cobrar(string $alumno): string
    {
        $this->requirePermiso('cobranza.editar');
        $this->requireCsrf();
        $alumnoId = (int)$alumno;
        if (!Alumno::porId($alumnoId)) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        $montos = $this->req->arr('monto');
        $aplicaciones = [];
        foreach ($montos as $cargoId => $monto) {
            $m = is_string($monto) ? str_replace([',', ' '], '', $monto) : $monto;
            if (is_numeric($m) && (float)$m > 0) {
                $aplicaciones[(int)$cargoId] = (float)$m;
            }
        }
        $v = Validator::make($this->req->all(), [
            'metodo'     => 'required|in:efectivo,transferencia,tarjeta,deposito,linea',
            'fecha'      => 'required|date',
            'referencia' => 'nullable|max:90',
            'notas'      => 'nullable|max:255',
        ], ['metodo' => 'metodo de pago', 'fecha' => 'fecha']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('cobranza/estado/' . $alumnoId);
        }
        $r = Cobranza::registrarPago(
            $alumnoId,
            $aplicaciones,
            (string)$v->get('metodo'),
            (string)$v->get('fecha'),
            (string)($v->get('referencia') ?? ''),
            (string)($v->get('notas') ?? ''),
            'aprobado'
        );
        if (!$r['ok']) {
            $this->error($r['error']);
            return $this->redirect('cobranza/estado/' . $alumnoId);
        }
        $this->enviarRecibo((int)$r['pago_id']);
        $this->ok('Pago registrado. Recibo ' . $r['recibo'] . ' por ' . moneda((float)$r['total']) . '.');
        return $this->redirect('recibo/' . (int)$r['pago_id']);
    }

    public function cargoManual(string $alumno): string
    {
        $this->requirePermiso('cobranza.editar');
        $this->requireCsrf();
        $alumnoId = (int)$alumno;
        if (!Alumno::porId($alumnoId)) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        $v = Validator::make($this->req->all(), [
            'descripcion'       => 'required|len:3,160',
            'monto'             => 'required|numeric|min:0.01|max:1000000',
            'descuento'         => 'nullable|numeric|min:0|max:1000000',
            'fecha_vencimiento' => 'required|date',
        ], ['descripcion' => 'descripcion', 'monto' => 'monto', 'fecha_vencimiento' => 'fecha de vencimiento']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('cobranza/estado/' . $alumnoId);
        }
        $monto = round((float)$v->get('monto'), 2);
        $descuento = min($monto, round((float)($v->get('descuento') ?? 0), 2));
        $id = Database::insert(
            'INSERT INTO cargos (alumno_id, ciclo_id, concepto_id, descripcion, anio, mes, monto, descuento,
                                 mora, pagado, fecha_vencimiento, estado)
             VALUES (:a, :c, NULL, :d, :y, 0, :mo, :de, 0, 0, :fv, \'pendiente\')',
            [
                'a'  => $alumnoId,
                'c'  => Academico::cicloActivoId(),
                'd'  => $v->get('descripcion'),
                'y'  => (int)date('Y', strtotime((string)$v->get('fecha_vencimiento'))),
                'mo' => $monto,
                'de' => $descuento,
                'fv' => $v->get('fecha_vencimiento'),
            ]
        );
        Audit::log('cargo.crear', 'cargos', $id, (string)$v->get('descripcion'));
        $this->ok('Cargo agregado al estado de cuenta.');
        return $this->redirect('cobranza/estado/' . $alumnoId);
    }

    public function anularCargo(string $id): string
    {
        $this->requireRol('superadmin', 'secretaria');
        $this->requireCsrf();
        $cargo = Cobranza::cargo((int)$id);
        if (!$cargo) {
            throw new HttpException(404, 'El cargo no existe.');
        }
        if ((float)$cargo['pagado'] > 0) {
            $this->error('No se puede anular un cargo con pagos aplicados. Anule primero el pago.');
            return $this->redirect('cobranza/estado/' . (int)$cargo['alumno_id']);
        }
        Database::run('UPDATE cargos SET estado = \'anulado\' WHERE id = :id', ['id' => (int)$id]);
        Audit::log('cargo.anular', 'cargos', (int)$id);
        $this->ok('Cargo anulado.');
        return $this->redirect('cobranza/estado/' . (int)$cargo['alumno_id']);
    }

    // ---------------- Pagos ----------------

    public function pagos(): string
    {
        $this->requirePermiso('cobranza.ver');
        [$p, $pp, $off] = $this->pagina(30);
        $desde = (string)$this->req->input('desde', date('Y-m-01'));
        $hasta = (string)$this->req->input('hasta', date('Y-m-t'));
        $estado = (string)$this->req->input('estado', '');
        $w = ['p.fecha BETWEEN :d AND :h'];
        $par = ['d' => $desde, 'h' => $hasta];
        if (in_array($estado, ['revision', 'aprobado', 'rechazado', 'anulado'], true)) {
            $w[] = 'p.estado = :e';
            $par['e'] = $estado;
        }
        $sql = 'FROM pagos p JOIN alumnos a ON a.id = p.alumno_id WHERE ' . implode(' AND ', $w);
        return $this->view('admin/cobros/pagos', [
            'titulo' => 'Pagos registrados',
            'pagos'  => Database::all(
                'SELECT p.*, a.nombres, a.apellidos, a.codigo ' . $sql . ' ORDER BY p.id DESC LIMIT ' . $pp . ' OFFSET ' . $off,
                $par
            ),
            'total'     => (int)Database::value('SELECT COUNT(*) ' . $sql, $par, 0),
            'suma'      => (float)Database::value('SELECT COALESCE(SUM(p.monto),0) ' . $sql . ' AND p.estado = \'aprobado\'', $par, 0),
            'pagina'    => $p,
            'porPagina' => $pp,
            'filtros'   => ['desde' => $desde, 'hasta' => $hasta, 'estado' => $estado],
        ]);
    }

    public function aprobarPago(string $id): string
    {
        $this->requirePermiso('pagos.aprobar');
        $this->requireCsrf();
        $pago = Cobranza::pago((int)$id);
        if (!$pago) {
            throw new HttpException(404, 'El pago no existe.');
        }
        if ($pago['estado'] !== 'revision') {
            $this->aviso('Este pago ya fue procesado.');
            return $this->back('cobranza');
        }
        Database::begin();
        try {
            $recibo = Cobranza::siguienteRecibo();
            Database::run(
                'UPDATE pagos SET estado = \'aprobado\', recibo_no = :r, revisado_por = :u, revisado_en = :t WHERE id = :id',
                ['r' => $recibo, 'u' => Auth::id(), 't' => date('Y-m-d H:i:s'), 'id' => (int)$id]
            );
            $aplicado = Cobranza::aplicarDetalle((int)$id);
            if (abs($aplicado - (float)$pago['monto']) > 0.009) {
                // El saldo cambio entre el envio y la aprobacion: el recibo refleja lo aplicado.
                Database::run('UPDATE pagos SET monto = :m WHERE id = :id', ['m' => $aplicado, 'id' => (int)$id]);
                $this->aviso('El saldo del alumno cambio desde el envio del comprobante: se aplicaron '
                    . moneda($aplicado) . ' de los ' . moneda((float)$pago['monto']) . ' reportados.');
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
        Audit::log('pago.aprobar', 'pagos', (int)$id, $recibo);
        $this->enviarRecibo((int)$id);
        $this->notificarEncargados((int)$pago['alumno_id'], 'Pago aprobado',
            'Su pago por ' . moneda((float)$pago['monto']) . ' fue aprobado. Recibo ' . $recibo . '.',
            'portal/cuenta');
        $this->ok('Pago aprobado. Recibo ' . $recibo . '.');
        return $this->back('cobranza');
    }

    public function rechazarPago(string $id): string
    {
        $this->requirePermiso('pagos.aprobar');
        $this->requireCsrf();
        $pago = Cobranza::pago((int)$id);
        if (!$pago) {
            throw new HttpException(404, 'El pago no existe.');
        }
        $motivo = mb_substr((string)$this->req->input('motivo', ''), 0, 255);
        if (mb_strlen($motivo) < 5) {
            $this->error('Indique el motivo del rechazo (minimo 5 caracteres).');
            return $this->back('cobranza');
        }
        if ($pago['estado'] === 'aprobado') {
            Cobranza::revertirDetalle((int)$id);
        }
        Database::run(
            'UPDATE pagos SET estado = \'rechazado\', motivo_rechazo = :m, revisado_por = :u, revisado_en = :t WHERE id = :id',
            ['m' => $motivo, 'u' => Auth::id(), 't' => date('Y-m-d H:i:s'), 'id' => (int)$id]
        );
        Audit::log('pago.rechazar', 'pagos', (int)$id, $motivo);
        $this->notificarEncargados((int)$pago['alumno_id'], 'Comprobante rechazado',
            'Motivo: ' . $motivo, 'portal/cuenta');
        $this->ok('El comprobante fue rechazado y se notifico al encargado.');
        return $this->back('cobranza');
    }

    public function anularPago(string $id): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $pago = Cobranza::pago((int)$id);
        if (!$pago) {
            throw new HttpException(404, 'El pago no existe.');
        }
        if ($pago['estado'] === 'aprobado') {
            Cobranza::revertirDetalle((int)$id);
        }
        Database::run(
            'UPDATE pagos SET estado = \'anulado\', revisado_por = :u, revisado_en = :t WHERE id = :id',
            ['u' => Auth::id(), 't' => date('Y-m-d H:i:s'), 'id' => (int)$id]
        );
        Audit::log('pago.anular', 'pagos', (int)$id);
        $this->ok('El pago fue anulado y los cargos se restablecieron.');
        return $this->back('cobranza/pagos');
    }

    public function recibo(string $id): string
    {
        $this->requireAuth();
        $pago = Cobranza::pago((int)$id);
        if (!$pago) {
            throw new HttpException(404, 'El recibo no existe.');
        }
        if (!Auth::puedeVerAlumno((int)$pago['alumno_id'])) {
            throw new HttpException(403, 'No tiene acceso a este recibo.');
        }
        if ($pago['estado'] !== 'aprobado') {
            throw new HttpException(403, 'El recibo solo esta disponible para pagos aprobados.');
        }
        $alumno = Alumno::porId((int)$pago['alumno_id']);
        $pdf = Documentos::recibo($pago, Cobranza::detallePago((int)$id), $alumno ?? []);
        return $pdf->descargar('recibo-' . (string)$pago['recibo_no'] . '.pdf');
    }

    private function enviarRecibo(int $pagoId): void
    {
        try {
            $pago = Cobranza::pago($pagoId);
            if (!$pago || $pago['estado'] !== 'aprobado') {
                return;
            }
            $alumno = Alumno::porId((int)$pago['alumno_id']);
            if (!$alumno) {
                return;
            }
            $enc = Alumno::encargadoPrincipal((int)$pago['alumno_id']);
            $correo = (string)($enc['email'] ?? '');
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $pdf = Documentos::recibo($pago, Cobranza::detallePago($pagoId), $alumno);
            Mail::enviar(
                $correo,
                (string)($enc['nombre'] ?? ''),
                'Recibo de pago ' . (string)$pago['recibo_no'],
                '<p>Estimado/a ' . e((string)($enc['nombre'] ?? '')) . ',</p>'
                . '<p>Adjuntamos el recibo <strong>' . e((string)$pago['recibo_no']) . '</strong> por '
                . e(moneda((float)$pago['monto'])) . ' correspondiente a '
                . e(\App\Models\Alumno::nombre($alumno)) . '.</p>'
                . '<p>Gracias por su pago puntual.</p>',
                [['nombre' => 'recibo-' . (string)$pago['recibo_no'] . '.pdf', 'contenido' => $pdf->salida(), 'mime' => 'application/pdf']]
            );
        } catch (\Throwable $e) {
            \App\Core\Logger::error('No se pudo enviar el recibo', ['e' => $e->getMessage()]);
        }
    }

    private function notificarEncargados(int $alumnoId, string $titulo, string $cuerpo, string $url): void
    {
        foreach (Database::all(
            'SELECT DISTINCT user_id FROM encargados WHERE alumno_id = :a AND user_id IS NOT NULL',
            ['a' => $alumnoId]
        ) as $e) {
            Notificador::crear((int)$e['user_id'], $titulo, $cuerpo, $url);
        }
    }

    // ---------------- Reportes ----------------

    public function morosidad(): string
    {
        $this->requirePermiso('cobranza.ver');
        $f = [
            'nivel_id'   => $this->req->int('nivel'),
            'grado_id'   => $this->req->int('grado'),
            'seccion_id' => $this->req->int('seccion'),
        ];
        $filas = Cobranza::morosidad($f);
        return $this->view('admin/cobros/morosidad', [
            'titulo'    => 'Reporte de morosidad',
            'filas'     => $filas,
            'total'     => array_sum(array_map(static fn($r) => (float)$r['saldo'], $filas)),
            'secciones' => Academico::secciones(),
            'niveles'   => Academico::niveles(),
            'filtros'   => $f,
        ]);
    }

    public function morosidadExportar(string $formato): string
    {
        $this->requirePermiso('cobranza.ver');
        $f = [
            'nivel_id'   => $this->req->int('nivel'),
            'seccion_id' => $this->req->int('seccion'),
        ];
        $filas = Cobranza::morosidad($f);
        if ($formato === 'excel') {
            $datos = [];
            foreach ($filas as $r) {
                $datos[] = [
                    $r['codigo'],
                    trim($r['nombres'] . ' ' . $r['apellidos']),
                    $r['grupo'],
                    (int)$r['cargos_vencidos'],
                    ['tipo' => 'moneda', 'valor' => (float)$r['saldo']],
                    ['tipo' => 'fecha', 'valor' => $r['mas_antiguo']],
                ];
            }
            $x = new Xlsx();
            $x->agregarHoja('Morosidad', ['Codigo', 'Alumno', 'Grado', 'Cargos', 'Saldo', 'Mas antiguo'], $datos, [14, 30, 20, 10, 14, 14]);
            return $x->descargar('morosidad.xlsx');
        }
        $datos = [];
        foreach ($filas as $r) {
            $datos[] = [
                $r['codigo'],
                trim($r['nombres'] . ' ' . $r['apellidos']),
                $r['grupo'],
                (int)$r['cargos_vencidos'],
                moneda((float)$r['saldo']),
                fecha((string)$r['mas_antiguo']),
            ];
        }
        $pdf = Documentos::tabla('Reporte de morosidad', [
            ['titulo' => 'Codigo', 'peso' => 12],
            ['titulo' => 'Alumno', 'peso' => 30],
            ['titulo' => 'Grado', 'peso' => 20],
            ['titulo' => 'Cargos', 'peso' => 10, 'alinear' => 'C'],
            ['titulo' => 'Saldo', 'peso' => 14, 'alinear' => 'R'],
            ['titulo' => 'Mas antiguo', 'peso' => 14, 'alinear' => 'C'],
        ], $datos, 'P', 'Al ' . date('d/m/Y'));
        return $pdf->descargar('morosidad.pdf');
    }

    public function caja(): string
    {
        $this->requirePermiso('cobranza.ver');
        $fecha = (string)$this->req->input('fecha', date('Y-m-d'));
        $usuarioId = $this->req->int('usuario');
        if (!Auth::is('superadmin')) {
            $usuarioId = (int)Auth::id();
        }
        return $this->view('admin/cobros/caja', [
            'titulo'   => 'Cierre de caja',
            'fecha'    => $fecha,
            'cierre'   => Cobranza::cierreCaja($fecha, $usuarioId > 0 ? $usuarioId : null),
            'usuarios' => Database::all("SELECT id, nombre FROM users WHERE rol IN ('superadmin','secretaria') ORDER BY nombre"),
            'usuarioId' => $usuarioId,
        ]);
    }

    public function cajaPdf(): string
    {
        $this->requirePermiso('cobranza.ver');
        $fecha = (string)$this->req->input('fecha', date('Y-m-d'));
        $usuarioId = Auth::is('superadmin') ? $this->req->int('usuario') : (int)Auth::id();
        $cierre = Cobranza::cierreCaja($fecha, $usuarioId > 0 ? $usuarioId : null);
        $datos = [];
        foreach ($cierre['pagos'] as $p) {
            $datos[] = [
                (string)$p['recibo_no'],
                trim($p['nombres'] . ' ' . $p['apellidos']),
                ucfirst((string)$p['metodo']),
                (string)($p['cajero'] ?? ''),
                moneda((float)$p['monto']),
            ];
        }
        $datos[] = ['', '', '', 'TOTAL', moneda($cierre['total'])];
        $pdf = Documentos::tabla('Cierre de caja', [
            ['titulo' => 'Recibo', 'peso' => 14],
            ['titulo' => 'Alumno', 'peso' => 34],
            ['titulo' => 'Metodo', 'peso' => 16],
            ['titulo' => 'Cajero', 'peso' => 22],
            ['titulo' => 'Monto', 'peso' => 14, 'alinear' => 'R'],
        ], $datos, 'P', fecha($fecha));
        return $pdf->descargar('cierre-caja-' . $fecha . '.pdf');
    }
}
