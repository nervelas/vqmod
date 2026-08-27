<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\Academico;
use App\Models\Asistencia;
use App\Servicios\Documentos;
use Vendor\Xlsx\Xlsx;

final class Asistencias extends Controller
{
    public function index(): string
    {
        $this->requirePermiso('asistencia.ver');
        $secciones = Auth::is('docente') ? $this->seccionesDocente() : Academico::secciones();
        return $this->view('docente/asistencia-index', [
            'titulo'    => 'Asistencia',
            'secciones' => $secciones,
            'hoy'       => date('Y-m-d'),
        ]);
    }

    private function seccionesDocente(): array
    {
        $ids = [];
        foreach (Academico::asignaciones(['docente_id' => (int)Auth::id()]) as $a) {
            $ids[(int)$a['seccion_id']] = true;
        }
        return array_values(array_filter(
            Academico::secciones(),
            static fn($s) => isset($ids[(int)$s['id']]) || (int)($s['docente_guia_id'] ?? 0) === (int)Auth::id()
        ));
    }

    public function pase(string $seccion): string
    {
        $this->requirePermiso('asistencia.ver');
        $seccionId = (int)$seccion;
        if (!Auth::puedeUsarSeccion($seccionId)) {
            throw new HttpException(403, 'No tiene acceso a este grupo.');
        }
        $sec = Academico::seccion($seccionId);
        if (!$sec) {
            throw new HttpException(404, 'El grupo no existe.');
        }
        $fecha = (string)$this->req->input('fecha', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }
        return $this->view('docente/asistencia', [
            'titulo'  => 'Pase de lista · ' . $sec['etiqueta'],
            'seccion' => $sec,
            'fecha'   => $fecha,
            'datos'   => Asistencia::delDia($seccionId, $fecha),
        ]);
    }

    public function guardar(string $seccion): string
    {
        $this->requirePermiso('asistencia.editar');
        $this->requireCsrf();
        $seccionId = (int)$seccion;
        if (!Auth::puedeUsarSeccion($seccionId)) {
            throw new HttpException(403, 'No tiene acceso a este grupo.');
        }
        $fecha = (string)$this->req->input('fecha', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d')) {
            $this->error('La fecha no es valida.');
            return $this->redirect('asistencia/' . $seccionId);
        }
        $n = Asistencia::guardar($seccionId, $fecha, $this->req->arr('estado'), $this->req->arr('nota'));
        Audit::log('asistencia.guardar', 'secciones', $seccionId, $fecha . ' - ' . $n . ' registros');
        if ($this->req->wantsJson()) {
            return $this->json(['ok' => true, 'guardados' => $n]);
        }
        $this->ok('Se guardo la asistencia de ' . $n . ' alumnos.');
        return $this->redirect('asistencia/' . $seccionId . '?fecha=' . $fecha);
    }

    public function reporte(string $seccion): string
    {
        $this->requirePermiso('asistencia.ver');
        $seccionId = (int)$seccion;
        if (!Auth::puedeUsarSeccion($seccionId)) {
            throw new HttpException(403, 'No tiene acceso a este grupo.');
        }
        $anio = $this->req->int('anio', (int)date('Y'));
        $mes = max(1, min(12, $this->req->int('mes', (int)date('n'))));
        return $this->view('docente/asistencia-reporte', [
            'titulo'  => 'Reporte mensual de asistencia',
            'seccion' => Academico::seccion($seccionId),
            'filas'   => Asistencia::reporteSeccion($seccionId, $anio, $mes),
            'anio'    => $anio,
            'mes'     => $mes,
        ]);
    }

    public function reporteExportar(string $seccion, string $formato): string
    {
        $this->requirePermiso('asistencia.ver');
        $seccionId = (int)$seccion;
        if (!Auth::puedeUsarSeccion($seccionId)) {
            throw new HttpException(403, 'No tiene acceso a este grupo.');
        }
        $anio = $this->req->int('anio', (int)date('Y'));
        $mes = max(1, min(12, $this->req->int('mes', (int)date('n'))));
        $sec = Academico::seccion($seccionId);
        $filas = Asistencia::reporteSeccion($seccionId, $anio, $mes);
        $datos = [];
        foreach ($filas as $f) {
            $datos[] = [
                $f['codigo'],
                trim($f['apellidos'] . ', ' . $f['nombres']),
                (int)$f['presente'],
                (int)$f['ausente'],
                (int)$f['tarde'],
                (int)$f['justificado'],
            ];
        }
        $encabezados = ['Codigo', 'Alumno', 'Presente', 'Ausente', 'Tarde', 'Justificado'];
        if ($formato === 'excel') {
            $x = new Xlsx();
            $x->agregarHoja('Asistencia', $encabezados, $datos, [14, 32, 12, 12, 12, 14]);
            return $x->descargar('asistencia-' . $anio . '-' . $mes . '.xlsx');
        }
        $pdf = Documentos::tabla(
            'Asistencia mensual',
            [
                ['titulo' => 'Codigo', 'peso' => 14],
                ['titulo' => 'Alumno', 'peso' => 40],
                ['titulo' => 'Presente', 'peso' => 12, 'alinear' => 'C'],
                ['titulo' => 'Ausente', 'peso' => 12, 'alinear' => 'C'],
                ['titulo' => 'Tarde', 'peso' => 10, 'alinear' => 'C'],
                ['titulo' => 'Justif.', 'peso' => 12, 'alinear' => 'C'],
            ],
            $datos,
            'P',
            (string)($sec['etiqueta'] ?? '') . ' · ' . mes_nombre($mes) . ' ' . $anio
        );
        return $pdf->descargar('asistencia-' . $anio . '-' . $mes . '.pdf');
    }
}
