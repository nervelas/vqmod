<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Cobranza;
use App\Models\Evaluacion;
use App\Models\Kpi;
use App\Servicios\Documentos;
use Vendor\Xlsx\Xlsx;

final class Reportes extends Controller
{
    private const TIPOS = [
        'ingresos'    => 'Ingresos por mes',
        'morosidad'   => 'Morosidad por grado',
        'alumnos'     => 'Alumnos inscritos',
        'rendimiento' => 'Rendimiento academico',
        'asistencia'  => 'Asistencia por grupo',
        'proyeccion'  => 'Proyeccion de ingresos',
    ];

    public function index(): string
    {
        $this->requirePermiso('reportes.ver');
        return $this->view('admin/reportes/index', [
            'titulo' => 'Reportes',
            'tipos'  => self::TIPOS,
            'kpi'    => Kpi::panel(),
        ]);
    }

    public function ver(string $tipo): string
    {
        $this->requirePermiso('reportes.ver');
        if (!isset(self::TIPOS[$tipo])) {
            throw new HttpException(404, 'El reporte no existe.');
        }
        [$columnas, $filas, $subtitulo] = $this->datos($tipo);
        return $this->view('admin/reportes/ver', [
            'titulo'    => self::TIPOS[$tipo],
            'tipo'      => $tipo,
            'columnas'  => $columnas,
            'filas'     => $filas,
            'subtitulo' => $subtitulo,
            'tipos'     => self::TIPOS,
        ]);
    }

    public function exportar(string $tipo, string $formato): string
    {
        $this->requirePermiso('reportes.ver');
        if (!isset(self::TIPOS[$tipo])) {
            throw new HttpException(404, 'El reporte no existe.');
        }
        [$columnas, $filas, $subtitulo] = $this->datos($tipo);
        if ($formato === 'excel') {
            $x = new Xlsx();
            $x->agregarHoja(
                mb_substr(self::TIPOS[$tipo], 0, 30),
                array_map(static fn($c) => $c['titulo'], $columnas),
                $filas,
                array_map(static fn($c) => max(10, (float)$c['peso'] * 1.4), $columnas)
            );
            return $x->descargar($tipo . '.xlsx');
        }
        $pdf = Documentos::tabla(self::TIPOS[$tipo], $columnas, $filas, count($columnas) > 6 ? 'L' : 'P', $subtitulo);
        return $pdf->descargar($tipo . '.pdf');
    }

    /** @return array{0:array,1:array,2:string} */
    private function datos(string $tipo): array
    {
        $anio = $this->req->int('anio', (int)date('Y'));
        switch ($tipo) {
            case 'ingresos':
                $serie = Cobranza::ingresosPorMes($anio);
                $columnas = [
                    ['titulo' => 'Mes', 'peso' => 40],
                    ['titulo' => 'Ingresos', 'peso' => 30, 'alinear' => 'R'],
                    ['titulo' => 'Pagos', 'peso' => 30, 'alinear' => 'C'],
                ];
                $filas = [];
                foreach ($serie as $mes => $total) {
                    $n = (int)Database::value(
                        'SELECT COUNT(*) FROM pagos WHERE estado = \'aprobado\' AND YEAR(fecha) = :y AND MONTH(fecha) = :m',
                        ['y' => $anio, 'm' => $mes],
                        0
                    );
                    $filas[] = [mes_nombre($mes), moneda($total), $n];
                }
                $filas[] = ['TOTAL', moneda(array_sum($serie)), ''];
                return [$columnas, $filas, 'Ano ' . $anio];

            case 'morosidad':
                $datos = Kpi::morosidadPorGrado();
                $columnas = [
                    ['titulo' => 'Grado', 'peso' => 45],
                    ['titulo' => 'Alumnos', 'peso' => 20, 'alinear' => 'C'],
                    ['titulo' => 'Saldo vencido', 'peso' => 35, 'alinear' => 'R'],
                ];
                $filas = array_map(static fn($r) => [$r['grupo'], (int)$r['alumnos'], moneda((float)$r['saldo'])], $datos);
                $filas[] = ['TOTAL', '', moneda(array_sum(array_map(static fn($r) => (float)$r['saldo'], $datos)))];
                return [$columnas, $filas, 'Al ' . date('d/m/Y')];

            case 'alumnos':
                $alumnos = Alumno::buscar(['estado' => 'activo'], 5000, 0);
                $columnas = [
                    ['titulo' => 'Codigo', 'peso' => 14],
                    ['titulo' => 'Alumno', 'peso' => 34],
                    ['titulo' => 'Grado', 'peso' => 24],
                    ['titulo' => 'Nivel', 'peso' => 18],
                    ['titulo' => 'Estado', 'peso' => 10, 'alinear' => 'C'],
                ];
                $filas = array_map(
                    static fn($a) => [$a['codigo'], trim($a['apellidos'] . ', ' . $a['nombres']), $a['grupo'] ?? '', $a['nivel'] ?? '', $a['estado']],
                    $alumnos
                );
                return [$columnas, $filas, count($filas) . ' alumnos activos'];

            case 'rendimiento':
                $columnas = [
                    ['titulo' => 'Grado', 'peso' => 34],
                    ['titulo' => 'Alumnos', 'peso' => 16, 'alinear' => 'C'],
                    ['titulo' => 'Promedio', 'peso' => 18, 'alinear' => 'C'],
                    ['titulo' => 'Aprobados', 'peso' => 16, 'alinear' => 'C'],
                    ['titulo' => 'En riesgo', 'peso' => 16, 'alinear' => 'C'],
                ];
                $filas = [];
                $min = Evaluacion::notaMinima();
                foreach (Academico::secciones() as $s) {
                    $promedios = Evaluacion::promediosSeccion((int)$s['id']);
                    $conNota = array_filter($promedios, static fn($p) => $p['promedio'] !== null && (float)$p['promedio'] > 0);
                    if ($conNota === []) {
                        continue;
                    }
                    $vals = array_map(static fn($p) => (float)$p['promedio'], $conNota);
                    $aprob = count(array_filter($vals, static fn($v) => $v >= $min));
                    $filas[] = [
                        $s['etiqueta'],
                        count($conNota),
                        number_format(array_sum($vals) / count($vals), 2),
                        $aprob,
                        count($vals) - $aprob,
                    ];
                }
                return [$columnas, $filas, 'Ciclo ' . (string)(Academico::cicloActivo()['nombre'] ?? '')];

            case 'asistencia':
                $mes = max(1, min(12, $this->req->int('mes', (int)date('n'))));
                $columnas = [
                    ['titulo' => 'Grado', 'peso' => 34],
                    ['titulo' => 'Presente', 'peso' => 17, 'alinear' => 'C'],
                    ['titulo' => 'Ausente', 'peso' => 17, 'alinear' => 'C'],
                    ['titulo' => 'Tarde', 'peso' => 16, 'alinear' => 'C'],
                    ['titulo' => '% Asistencia', 'peso' => 16, 'alinear' => 'C'],
                ];
                $filas = [];
                foreach (Academico::secciones() as $s) {
                    $r = \App\Models\Asistencia::reporteSeccion((int)$s['id'], $anio, $mes);
                    $p = array_sum(array_map(static fn($x) => (int)$x['presente'], $r));
                    $a = array_sum(array_map(static fn($x) => (int)$x['ausente'], $r));
                    $t = array_sum(array_map(static fn($x) => (int)$x['tarde'], $r));
                    $j = array_sum(array_map(static fn($x) => (int)$x['justificado'], $r));
                    $total = $p + $a + $t + $j;
                    if ($total === 0) {
                        continue;
                    }
                    $filas[] = [$s['etiqueta'], $p, $a, $t, number_format(($p + $t + $j) / $total * 100, 1) . '%'];
                }
                return [$columnas, $filas, mes_nombre($mes) . ' ' . $anio];

            case 'proyeccion':
            default:
                $columnas = [
                    ['titulo' => 'Mes', 'peso' => 28],
                    ['titulo' => 'Facturado', 'peso' => 24, 'alinear' => 'R'],
                    ['titulo' => 'Cobrado', 'peso' => 24, 'alinear' => 'R'],
                    ['titulo' => 'Pendiente', 'peso' => 24, 'alinear' => 'R'],
                ];
                $filas = [];
                $ciclo = Academico::cicloActivoId();
                for ($m = 1; $m <= 12; $m++) {
                    $fila = Database::one(
                        'SELECT COALESCE(SUM(monto - descuento + mora),0) AS facturado,
                                COALESCE(SUM(pagado),0) AS cobrado
                         FROM cargos WHERE ciclo_id = :c AND anio = :y AND mes = :m AND estado <> \'anulado\'',
                        ['c' => $ciclo, 'y' => $anio, 'm' => $m]
                    ) ?? ['facturado' => 0, 'cobrado' => 0];
                    if ((float)$fila['facturado'] <= 0) {
                        continue;
                    }
                    $filas[] = [
                        mes_nombre($m),
                        moneda((float)$fila['facturado']),
                        moneda((float)$fila['cobrado']),
                        moneda((float)$fila['facturado'] - (float)$fila['cobrado']),
                    ];
                }
                return [$columnas, $filas, 'Ciclo ' . $anio];
        }
    }
}
