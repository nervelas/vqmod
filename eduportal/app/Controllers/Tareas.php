<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Notificador;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\Academico;
use App\Models\Comunicacion;

final class Tareas extends Controller
{
    public function index(): string
    {
        $this->requirePermiso('tareas.ver');
        $filtros = Auth::is('docente') ? ['docente_id' => (int)Auth::id()] : [];
        $asignaciones = Academico::asignaciones($filtros);
        return $this->view('docente/tareas', [
            'titulo'       => 'Tareas',
            'asignaciones' => $asignaciones,
            'tareas'       => Comunicacion::tareasDe(array_map(static fn($a) => (int)$a['id'], $asignaciones), 100),
        ]);
    }

    public function guardar(): string
    {
        $this->requirePermiso('tareas.editar');
        $this->requireCsrf();
        $asignacionId = $this->req->int('asignacion_id');
        if (!Auth::puedeUsarAsignacion($asignacionId)) {
            throw new HttpException(403, 'Esta materia no le fue asignada.');
        }
        $v = Validator::make($this->req->all(), [
            'titulo'        => 'required|len:3,180',
            'descripcion'   => 'nullable|max:5000',
            'fecha_entrega' => 'nullable|date',
            'puntos'        => 'nullable|numeric|min:0|max:100',
        ], ['titulo' => 'titulo', 'fecha_entrega' => 'fecha de entrega']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('tareas');
        }
        $adjunto = null;
        if (($this->req->file('adjunto')['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = Upload::store($this->req->file('adjunto'), 'tareas', Upload::DOCUMENTOS);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->redirect('tareas');
            }
            $adjunto = $r['archivo'];
        }
        $id = $this->req->int('id');
        $campos = [
            'titulo'        => $v->get('titulo'),
            'descripcion'   => $v->get('descripcion'),
            'fecha_entrega' => $v->get('fecha_entrega'),
            'puntos'        => round((float)($v->get('puntos') ?? 0), 2),
        ];
        if ($adjunto) {
            $campos['adjunto'] = $adjunto;
        }
        if ($id > 0) {
            $tarea = Database::one('SELECT * FROM tareas WHERE id = :id', ['id' => $id]);
            if (!$tarea || !Auth::puedeUsarAsignacion((int)$tarea['asignacion_id'])) {
                throw new HttpException(403, 'No puede editar esta tarea.');
            }
            $sets = [];
            foreach (array_keys($campos) as $c) {
                $sets[] = "$c = :$c";
            }
            $campos['id'] = $id;
            Database::run('UPDATE tareas SET ' . implode(', ', $sets) . ' WHERE id = :id', $campos);
            Audit::log('tarea.actualizar', 'tareas', $id, (string)$v->get('titulo'));
        } else {
            $campos['asignacion_id'] = $asignacionId;
            $cols = implode(', ', array_keys($campos));
            $vals = ':' . implode(', :', array_keys($campos));
            $id = Database::insert("INSERT INTO tareas ($cols) VALUES ($vals)", $campos);
            Audit::log('tarea.crear', 'tareas', $id, (string)$v->get('titulo'));
            $this->notificarGrupo($asignacionId, (string)$v->get('titulo'));
        }
        $this->ok('La tarea fue publicada.');
        return $this->redirect('tareas');
    }

    private function notificarGrupo(int $asignacionId, string $titulo): void
    {
        $asig = Academico::asignacion($asignacionId);
        if (!$asig) {
            return;
        }
        foreach (Database::all(
            'SELECT DISTINCT e.user_id AS id
             FROM inscripciones i JOIN encargados e ON e.alumno_id = i.alumno_id
             WHERE i.seccion_id = :s AND i.ciclo_id = :c AND e.user_id IS NOT NULL',
            ['s' => (int)$asig['seccion_id'], 'c' => Academico::cicloActivoId()]
        ) as $u) {
            Notificador::crear((int)$u['id'], 'Nueva tarea de ' . (string)$asig['materia'], $titulo, 'portal/tareas');
        }
    }

    public function eliminar(string $id): string
    {
        $this->requirePermiso('tareas.editar');
        $this->requireCsrf();
        $tarea = Database::one('SELECT * FROM tareas WHERE id = :id', ['id' => (int)$id]);
        if (!$tarea) {
            throw new HttpException(404, 'La tarea no existe.');
        }
        if (!Auth::puedeUsarAsignacion((int)$tarea['asignacion_id'])) {
            throw new HttpException(403, 'No puede eliminar esta tarea.');
        }
        Upload::delete($tarea['adjunto'] ?? null);
        Database::run('DELETE FROM tarea_entregas WHERE tarea_id = :id', ['id' => (int)$id]);
        Database::run('DELETE FROM tareas WHERE id = :id', ['id' => (int)$id]);
        Audit::log('tarea.eliminar', 'tareas', (int)$id);
        $this->ok('Tarea eliminada.');
        return $this->redirect('tareas');
    }

    public function entregas(string $id): string
    {
        $this->requirePermiso('tareas.ver');
        $tarea = Database::one(
            'SELECT t.*, m.nombre AS materia, a.seccion_id, CONCAT(g.nombre, \' \', s.nombre) AS grupo
             FROM tareas t
             JOIN asignaciones a ON a.id = t.asignacion_id
             JOIN materias m ON m.id = a.materia_id
             JOIN secciones s ON s.id = a.seccion_id
             JOIN grados g ON g.id = s.grado_id
             WHERE t.id = :id',
            ['id' => (int)$id]
        );
        if (!$tarea) {
            throw new HttpException(404, 'La tarea no existe.');
        }
        if (!Auth::puedeUsarAsignacion((int)$tarea['asignacion_id']) && !Auth::is('superadmin')) {
            throw new HttpException(403, 'No tiene acceso a esta tarea.');
        }
        return $this->view('docente/tarea-entregas', [
            'titulo'   => 'Entregas · ' . (string)$tarea['titulo'],
            'tarea'    => $tarea,
            'alumnos'  => \App\Models\Alumno::deSeccion((int)$tarea['seccion_id']),
            'entregas' => $this->mapaEntregas((int)$id),
        ]);
    }

    private function mapaEntregas(int $tareaId): array
    {
        $out = [];
        foreach (Database::all('SELECT * FROM tarea_entregas WHERE tarea_id = :t', ['t' => $tareaId]) as $e) {
            $out[(int)$e['alumno_id']] = $e;
        }
        return $out;
    }

    public function marcarRevisada(string $id): string
    {
        $this->requirePermiso('tareas.editar');
        $this->requireCsrf();
        $entrega = Database::one('SELECT * FROM tarea_entregas WHERE id = :id', ['id' => (int)$id]);
        if (!$entrega) {
            throw new HttpException(404, 'La entrega no existe.');
        }
        $tarea = Database::one('SELECT * FROM tareas WHERE id = :id', ['id' => (int)$entrega['tarea_id']]);
        if (!$tarea || !Auth::puedeUsarAsignacion((int)$tarea['asignacion_id'])) {
            throw new HttpException(403, 'No tiene acceso a esta entrega.');
        }
        Database::run('UPDATE tarea_entregas SET estado = \'revisado\' WHERE id = :id', ['id' => (int)$id]);
        $this->ok('Entrega marcada como revisada.');
        return $this->redirect('tareas/' . (int)$entrega['tarea_id'] . '/entregas');
    }
}
