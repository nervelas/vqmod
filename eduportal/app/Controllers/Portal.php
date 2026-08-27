<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Notificador;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Cobranza;
use App\Models\Comunicacion;
use App\Models\Evaluacion;

final class Portal extends Controller
{
    /** Alumno activo del portal, siempre validado contra los hijos del usuario. */
    private function alumnoActivo(): array
    {
        $this->requireAuth();
        $hijos = Auth::is('padre')
            ? Alumno::hijosDe((int)Auth::id())
            : [];
        if ($hijos === []) {
            throw new HttpException(403, 'Su usuario no tiene alumnos asociados. Comuniquese con la secretaria del colegio.');
        }
        $ids = array_map(static fn($h) => (int)$h['id'], $hijos);
        $activo = (int)Session::get('portal_alumno', 0);
        if (!in_array($activo, $ids, true)) {
            $activo = $ids[0];
            Session::set('portal_alumno', $activo);
        }
        $alumno = Alumno::porId($activo);
        if (!$alumno) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        return ['alumno' => $alumno, 'hijos' => $hijos];
    }

    public function index(): string
    {
        ['alumno' => $alumno, 'hijos' => $hijos] = $this->alumnoActivo();
        $id = (int)$alumno['id'];
        $cuenta = Cobranza::estadoCuenta($id);
        $boleta = Evaluacion::boleta($id);
        return $this->view('portal/index', [
            'titulo'   => 'Portal del alumno',
            'alumno'   => $alumno,
            'hijos'    => $hijos,
            'cuenta'   => $cuenta,
            'boleta'   => $boleta,
            'avisos'   => Comunicacion::avisosPara($id, 5),
            'tareas'   => array_slice(Comunicacion::tareasDeAlumno($id, 6), 0, 6),
            'asistencia' => Asistencia::resumenMensual($id, (int)date('Y'), (int)date('n')),
            'eventos'  => Comunicacion::eventos(date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), true),
        ], 'layouts/portal');
    }

    public function cambiar(string $alumno): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $id = (int)$alumno;
        if (!Auth::puedeVerAlumno($id)) {
            throw new HttpException(403, 'Ese alumno no esta asociado a su cuenta.');
        }
        Session::set('portal_alumno', $id);
        return $this->back('portal');
    }

    public function cuenta(): string
    {
        ['alumno' => $alumno, 'hijos' => $hijos] = $this->alumnoActivo();
        $id = (int)$alumno['id'];
        return $this->view('portal/cuenta', [
            'titulo'    => 'Estado de cuenta',
            'alumno'    => $alumno,
            'hijos'     => $hijos,
            'cuenta'    => Cobranza::estadoCuenta($id),
            'pagos'     => Database::all(
                'SELECT * FROM pagos WHERE alumno_id = :a ORDER BY id DESC LIMIT 24',
                ['a' => $id]
            ),
            'pagoLink'  => (string)Settings::get('pago_link', ''),
        ], 'layouts/portal');
    }

    public function comprobante(): string
    {
        ['alumno' => $alumno] = $this->alumnoActivo();
        $this->requireCsrf();
        $id = (int)$alumno['id'];
        $v = Validator::make($this->req->all(), [
            'metodo'     => 'required|in:transferencia,deposito,tarjeta,linea',
            'fecha'      => 'required|date',
            'referencia' => 'nullable|max:90',
            'notas'      => 'nullable|max:255',
        ], ['metodo' => 'metodo de pago', 'fecha' => 'fecha del pago']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('portal/cuenta');
        }
        if ((string)$v->get('fecha') > date('Y-m-d')) {
            $this->error('La fecha del pago no puede ser futura.');
            return $this->redirect('portal/cuenta');
        }
        $subida = Upload::store($this->req->file('comprobante'), 'comprobantes', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
        if (!$subida['ok']) {
            $this->error($subida['error']);
            return $this->redirect('portal/cuenta');
        }
        $montos = $this->req->arr('monto');
        $aplicaciones = [];
        foreach ($montos as $cargoId => $monto) {
            $m = is_string($monto) ? str_replace([',', ' '], '', $monto) : $monto;
            if (is_numeric($m) && (float)$m > 0) {
                $aplicaciones[(int)$cargoId] = (float)$m;
            }
        }
        $r = Cobranza::registrarPago(
            $id,
            $aplicaciones,
            (string)$v->get('metodo'),
            (string)$v->get('fecha'),
            (string)($v->get('referencia') ?? ''),
            (string)($v->get('notas') ?? ''),
            'revision',
            $subida['archivo'],
            (int)Auth::id()
        );
        if (!$r['ok']) {
            Upload::delete($subida['archivo']);
            $this->error($r['error']);
            return $this->redirect('portal/cuenta');
        }
        Audit::log('pago.comprobante', 'pagos', (int)$r['pago_id'], 'Alumno ' . $id);
        foreach (Database::all("SELECT id FROM users WHERE rol IN ('superadmin','secretaria') AND activo = 1") as $u) {
            Notificador::crear(
                (int)$u['id'],
                'Comprobante por revisar',
                Alumno::nombre($alumno) . ' envio un comprobante por ' . moneda((float)$r['total']),
                'cobranza'
            );
        }
        $this->ok('Su comprobante fue enviado y quedo en revision. Le avisaremos cuando sea aprobado.');
        return $this->redirect('portal/cuenta');
    }

    public function notas(): string
    {
        ['alumno' => $alumno, 'hijos' => $hijos] = $this->alumnoActivo();
        return $this->view('portal/notas', [
            'titulo' => 'Calificaciones',
            'alumno' => $alumno,
            'hijos'  => $hijos,
            'boleta' => Evaluacion::boleta((int)$alumno['id']),
            'minima' => Evaluacion::notaMinima(),
        ], 'layouts/portal');
    }

    public function asistencia(): string
    {
        ['alumno' => $alumno, 'hijos' => $hijos] = $this->alumnoActivo();
        $anio = $this->req->int('anio', (int)date('Y'));
        $mes = max(1, min(12, $this->req->int('mes', (int)date('n'))));
        return $this->view('portal/asistencia', [
            'titulo'  => 'Asistencia',
            'alumno'  => $alumno,
            'hijos'   => $hijos,
            'resumen' => Asistencia::resumenMensual((int)$alumno['id'], $anio, $mes),
            'detalle' => Asistencia::detalleMes((int)$alumno['id'], $anio, $mes),
            'anio'    => $anio,
            'mes'     => $mes,
        ], 'layouts/portal');
    }

    public function avisos(): string
    {
        ['alumno' => $alumno, 'hijos' => $hijos] = $this->alumnoActivo();
        return $this->view('portal/avisos', [
            'titulo'  => 'Avisos',
            'alumno'  => $alumno,
            'hijos'   => $hijos,
            'avisos'  => Comunicacion::avisosPara((int)$alumno['id'], 40),
            'eventos' => Comunicacion::eventos(date('Y-m-01'), date('Y-m-d', strtotime('+120 days')), true),
        ], 'layouts/portal');
    }

    public function tareas(): string
    {
        ['alumno' => $alumno, 'hijos' => $hijos] = $this->alumnoActivo();
        return $this->view('portal/tareas', [
            'titulo' => 'Tareas',
            'alumno' => $alumno,
            'hijos'  => $hijos,
            'tareas' => Comunicacion::tareasDeAlumno((int)$alumno['id'], 60),
        ], 'layouts/portal');
    }

    public function entregarTarea(string $id): string
    {
        ['alumno' => $alumno] = $this->alumnoActivo();
        $this->requireCsrf();
        $tareaId = (int)$id;
        $permitidas = array_map(static fn($t) => (int)$t['id'], Comunicacion::tareasDeAlumno((int)$alumno['id'], 200));
        if (!in_array($tareaId, $permitidas, true)) {
            throw new HttpException(403, 'Esta tarea no corresponde al alumno.');
        }
        $archivo = null;
        if (($this->req->file('archivo')['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = Upload::store($this->req->file('archivo'), 'entregas', Upload::DOCUMENTOS);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->redirect('portal/tareas');
            }
            $archivo = $r['archivo'];
        }
        $comentario = mb_substr((string)$this->req->input('comentario', ''), 0, 255);
        $existe = Database::value(
            'SELECT id FROM tarea_entregas WHERE tarea_id = :t AND alumno_id = :a',
            ['t' => $tareaId, 'a' => (int)$alumno['id']]
        );
        if ($existe) {
            Database::run(
                'UPDATE tarea_entregas SET estado = \'entregado\', comentario = :c, entregado_en = :f'
                . ($archivo ? ', archivo = :ar' : '') . ' WHERE id = :id',
                $archivo
                    ? ['c' => $comentario, 'f' => date('Y-m-d H:i:s'), 'ar' => $archivo, 'id' => (int)$existe]
                    : ['c' => $comentario, 'f' => date('Y-m-d H:i:s'), 'id' => (int)$existe]
            );
        } else {
            Database::run(
                'INSERT INTO tarea_entregas (tarea_id, alumno_id, estado, comentario, archivo)
                 VALUES (:t, :a, \'entregado\', :c, :ar)',
                ['t' => $tareaId, 'a' => (int)$alumno['id'], 'c' => $comentario, 'ar' => $archivo]
            );
        }
        Audit::log('tarea.entregar', 'tareas', $tareaId, 'Alumno ' . (int)$alumno['id']);
        $this->ok('Se registro la entrega de la tarea.');
        return $this->redirect('portal/tareas');
    }

    public function perfilAlumno(): string
    {
        ['alumno' => $alumno, 'hijos' => $hijos] = $this->alumnoActivo();
        return $this->view('portal/alumno', [
            'titulo'     => 'Ficha del alumno',
            'alumno'     => $alumno,
            'hijos'      => $hijos,
            'encargados' => Alumno::encargados((int)$alumno['id']),
            'historial'  => Alumno::historial((int)$alumno['id']),
            'ciclo'      => Academico::cicloActivo(),
        ], 'layouts/portal');
    }
}
