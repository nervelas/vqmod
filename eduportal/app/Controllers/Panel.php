<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Cobranza;
use App\Models\Comunicacion;
use App\Models\Kpi;

final class Panel extends Controller
{
    public function index(): string
    {
        $this->requireAuth();
        if (Auth::is('padre')) {
            return $this->redirect('portal');
        }
        if (Auth::is('docente')) {
            return $this->docente();
        }
        $kpi = Kpi::panel();
        return $this->view('admin/panel', [
            'titulo'      => 'Panel de control',
            'scripts'     => ['vendor/chart.umd.js', 'js/panel.js'],
            'kpi'         => $kpi,
            'ciclo'       => Academico::cicloActivo(),
            'morosidad'   => Kpi::morosidadPorGrado(),
            'distribucion' => Kpi::distribucionAlumnos(),
            'ultimosPagos' => Database::all(
                'SELECT p.*, a.nombres, a.apellidos FROM pagos p
                 JOIN alumnos a ON a.id = p.alumno_id
                 ORDER BY p.id DESC LIMIT 8'
            ),
            'avisos'      => Comunicacion::avisosPara(null, 5),
        ]);
    }

    private function docente(): string
    {
        $uid = (int)Auth::id();
        $asignaciones = Academico::asignaciones(['docente_id' => $uid]);
        $secciones = [];
        foreach ($asignaciones as $a) {
            $secciones[(int)$a['seccion_id']] = $a['grupo'];
        }
        $periodo = Academico::periodoActual();
        $tareas = Comunicacion::tareasDe(array_map(static fn($a) => (int)$a['id'], $asignaciones), 6);
        $totalAlumnos = 0;
        foreach (array_keys($secciones) as $sid) {
            $totalAlumnos += count(Alumno::deSeccion((int)$sid));
        }
        return $this->view('docente/panel', [
            'titulo'       => 'Mis grupos',
            'asignaciones' => $asignaciones,
            'secciones'    => $secciones,
            'periodo'      => $periodo,
            'tareas'       => $tareas,
            'totalAlumnos' => $totalAlumnos,
            'avisos'       => Comunicacion::avisosPara(null, 5),
        ]);
    }

    /** Series para las graficas del panel (Chart.js). */
    public function datos(): string
    {
        $this->requireRol('superadmin', 'secretaria');
        $anio = (int)date('Y');
        $ingresos = Cobranza::ingresosPorMes($anio);
        $morosidad = Kpi::morosidadPorGrado();
        $dist = Kpi::distribucionAlumnos();
        $asis = Kpi::asistenciaUltimosDias(14);
        return $this->json([
            'ok' => true,
            'ingresos' => [
                'etiquetas' => array_map(static fn($m) => mb_substr(mes_nombre($m), 0, 3), range(1, 12)),
                'valores'   => array_values($ingresos),
            ],
            'morosidad' => [
                'etiquetas' => array_map(static fn($r) => $r['grupo'], $morosidad),
                'valores'   => array_map(static fn($r) => round((float)$r['saldo'], 2), $morosidad),
            ],
            'distribucion' => [
                'etiquetas' => array_map(static fn($r) => $r['nivel'], $dist),
                'valores'   => array_map(static fn($r) => (int)$r['total'], $dist),
            ],
            'asistencia' => [
                'etiquetas' => array_map(static fn($r) => fecha($r['fecha'], 'd/m'), $asis),
                'valores'   => array_map(static fn($r) => (float)$r['porcentaje'], $asis),
            ],
        ]);
    }
}
