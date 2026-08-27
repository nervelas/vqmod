<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Validator;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Evaluacion;
use App\Servicios\Documentos;

final class Notas extends Controller
{
    public function index(): string
    {
        $this->requirePermiso('notas.ver');
        $filtros = Auth::is('docente') ? ['docente_id' => (int)Auth::id()] : [];
        return $this->view('docente/notas-index', [
            'titulo'       => 'Captura de notas',
            'asignaciones' => Academico::asignaciones($filtros),
            'periodos'     => Academico::periodos(),
            'periodoActual' => Academico::periodoActual(),
        ]);
    }

    public function cuadricula(string $asignacion): string
    {
        $this->requirePermiso('notas.ver');
        $asignacionId = (int)$asignacion;
        if (!Auth::puedeUsarAsignacion($asignacionId)) {
            throw new HttpException(403, 'Esta materia no le fue asignada.');
        }
        $asig = Academico::asignacion($asignacionId);
        if (!$asig) {
            throw new HttpException(404, 'La asignacion no existe.');
        }
        $periodos = Academico::periodos((int)$asig['ciclo_id']);
        $periodoId = $this->req->int('periodo');
        if ($periodoId === 0) {
            $actual = Academico::periodoActual((int)$asig['ciclo_id']);
            $periodoId = $actual ? (int)$actual['id'] : (int)($periodos[0]['id'] ?? 0);
        }
        $periodo = null;
        foreach ($periodos as $p) {
            if ((int)$p['id'] === $periodoId) {
                $periodo = $p;
            }
        }
        if (!$periodo) {
            throw new HttpException(404, 'El periodo no existe.');
        }
        return $this->view('docente/notas', [
            'titulo'      => $asig['materia'] . ' · ' . $asig['grupo'],
            'scripts'     => ['js/notas.js'],
            'asignacion'  => $asig,
            'periodos'    => $periodos,
            'periodo'     => $periodo,
            'datos'       => Evaluacion::cuadricula($asignacionId, $periodoId, (int)$asig['seccion_id']),
            'ponderacion' => Evaluacion::ponderacionUsada($asignacionId, $periodoId),
        ]);
    }

    public function guardarActividad(): string
    {
        $this->requirePermiso('notas.editar');
        $this->requireCsrf();
        $asignacionId = $this->req->int('asignacion_id');
        $periodoId = $this->req->int('periodo_id');
        if (!Auth::puedeUsarAsignacion($asignacionId)) {
            throw new HttpException(403, 'Esta materia no le fue asignada.');
        }
        $v = Validator::make($this->req->all(), [
            'nombre'      => 'required|len:2,120',
            'tipo'        => 'required|in:zona,examen',
            'ponderacion' => 'required|numeric|min:0.5|max:100',
            'fecha'       => 'nullable|date',
        ], ['nombre' => 'nombre', 'ponderacion' => 'ponderacion']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('notas/' . $asignacionId . '?periodo=' . $periodoId);
        }
        $id = $this->req->int('id');
        $tipo = (string)$v->get('tipo');
        $pond = round((float)$v->get('ponderacion'), 2);

        $usada = Evaluacion::ponderacionUsada($asignacionId, $periodoId);
        $actual = $id > 0 ? (float)(Evaluacion::actividad($id)['ponderacion'] ?? 0) : 0.0;
        $limite = $tipo === 'examen' ? Evaluacion::pondExamen() : Evaluacion::pondZona();
        if (($usada[$tipo] - $actual + $pond) > $limite + 0.001) {
            $this->error('La suma de ponderaciones de ' . $tipo . ' excede el maximo de ' . number_format($limite, 0) . ' puntos.');
            return $this->redirect('notas/' . $asignacionId . '?periodo=' . $periodoId);
        }
        if ($id > 0) {
            Database::run(
                'UPDATE actividades SET nombre = :n, tipo = :t, ponderacion = :p, fecha = :f
                 WHERE id = :id AND asignacion_id = :a',
                ['n' => $v->get('nombre'), 't' => $tipo, 'p' => $pond, 'f' => $v->get('fecha'), 'id' => $id, 'a' => $asignacionId]
            );
        } else {
            $id = Database::insert(
                'INSERT INTO actividades (asignacion_id, periodo_id, nombre, tipo, ponderacion, fecha)
                 VALUES (:a, :p, :n, :t, :po, :f)',
                ['a' => $asignacionId, 'p' => $periodoId, 'n' => $v->get('nombre'), 't' => $tipo, 'po' => $pond, 'f' => $v->get('fecha')]
            );
        }
        Audit::log('actividad.guardar', 'actividades', $id, (string)$v->get('nombre'));
        $this->ok('Actividad guardada.');
        return $this->redirect('notas/' . $asignacionId . '?periodo=' . $periodoId);
    }

    public function eliminarActividad(string $id): string
    {
        $this->requirePermiso('notas.editar');
        $this->requireCsrf();
        $act = Evaluacion::actividad((int)$id);
        if (!$act) {
            throw new HttpException(404, 'La actividad no existe.');
        }
        if (!Auth::puedeUsarAsignacion((int)$act['asignacion_id'])) {
            throw new HttpException(403, 'Esta materia no le fue asignada.');
        }
        Database::run('DELETE FROM notas WHERE actividad_id = :a', ['a' => (int)$id]);
        Database::run('DELETE FROM actividades WHERE id = :a', ['a' => (int)$id]);
        foreach (Alumno::deSeccion((int)(Academico::asignacion((int)$act['asignacion_id'])['seccion_id'] ?? 0)) as $al) {
            Evaluacion::recalcular((int)$al['id'], (int)$act['asignacion_id'], (int)$act['periodo_id']);
        }
        Audit::log('actividad.eliminar', 'actividades', (int)$id);
        $this->ok('Actividad eliminada.');
        return $this->redirect('notas/' . (int)$act['asignacion_id'] . '?periodo=' . (int)$act['periodo_id']);
    }

    /** Guardado rapido por AJAX desde la cuadricula. */
    public function guardarNota(): string
    {
        $this->requirePermiso('notas.editar');
        $this->requireCsrf();
        $actividadId = $this->req->int('actividad_id');
        $alumnoId = $this->req->int('alumno_id');
        $bruto = $this->req->raw('punteo', '');
        $punteo = ($bruto === '' || $bruto === null) ? null : (float)str_replace(',', '.', (string)$bruto);
        $r = Evaluacion::guardarNota($actividadId, $alumnoId, $punteo);
        return $this->json($r, $r['ok'] ? 200 : 422);
    }

    public function guardarConducta(): string
    {
        $this->requirePermiso('notas.editar');
        $this->requireCsrf();
        $asignacionId = $this->req->int('asignacion_id');
        $periodoId = $this->req->int('periodo_id');
        if (!Auth::puedeUsarAsignacion($asignacionId)) {
            throw new HttpException(403, 'Esta materia no le fue asignada.');
        }
        $conductas = $this->req->arr('conducta');
        $comentarios = $this->req->arr('comentario');
        $n = 0;
        foreach ($conductas as $alumnoId => $valor) {
            $alumnoId = (int)$alumnoId;
            if (!Auth::puedeVerAlumno($alumnoId)) {
                continue;
            }
            $c = ($valor === '' || $valor === null) ? null : max(0.0, min(100.0, (float)$valor));
            Evaluacion::guardarConducta($alumnoId, $asignacionId, $periodoId, $c, (string)($comentarios[$alumnoId] ?? ''));
            $n++;
        }
        $this->ok('Se guardaron ' . $n . ' registros de conducta.');
        return $this->redirect('notas/' . $asignacionId . '?periodo=' . $periodoId);
    }

    public function cerrarPeriodo(string $id): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $cerrar = !$this->req->bool('abrir');
        Evaluacion::cerrarPeriodo((int)$id, $cerrar);
        Audit::log($cerrar ? 'periodo.cerrar' : 'periodo.abrir', 'periodos', (int)$id);
        $this->ok($cerrar ? 'El periodo fue cerrado.' : 'El periodo fue reabierto.');
        return $this->back('configuracion/academico');
    }

    public function boleta(string $alumno): string
    {
        $this->requireAuth();
        $alumnoId = (int)$alumno;
        if (!Auth::puedeVerAlumno($alumnoId)) {
            throw new HttpException(403, 'No tiene acceso a este alumno.');
        }
        $datos = Alumno::porId($alumnoId);
        if (!$datos) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        $pdf = Documentos::boleta($datos);
        Audit::log('boleta.generar', 'alumnos', $alumnoId);
        return $pdf->descargar('boleta-' . (string)$datos['codigo'] . '.pdf');
    }

    public function boletasGrupo(string $seccion): string
    {
        $this->requirePermiso('notas.ver');
        $seccionId = (int)$seccion;
        if (!Auth::puedeUsarSeccion($seccionId)) {
            throw new HttpException(403, 'No tiene acceso a este grupo.');
        }
        $alumnos = [];
        foreach (Alumno::deSeccion($seccionId) as $a) {
            $completo = Alumno::porId((int)$a['id']);
            if ($completo) {
                $alumnos[] = $completo;
            }
        }
        if ($alumnos === []) {
            $this->aviso('El grupo no tiene alumnos inscritos.');
            return $this->back('notas');
        }
        $pdf = Documentos::boletasGrupo($alumnos);
        return $pdf->descargar('boletas-grupo.pdf');
    }

    public function cuadroHonor(): string
    {
        $this->requirePermiso('notas.ver');
        return $this->view('admin/cuadro-honor', [
            'titulo' => 'Cuadro de honor',
            'grupos' => Evaluacion::cuadroHonor(),
            'ciclo'  => Academico::cicloActivo(),
        ]);
    }
}
