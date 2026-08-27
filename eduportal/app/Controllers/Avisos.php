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

final class Avisos extends Controller
{
    public function index(): string
    {
        $this->requirePermiso('avisos.ver');
        $mios = Auth::is('docente');
        $w = $mios ? 'WHERE a.autor_id = :u' : '';
        $p = $mios ? ['u' => (int)Auth::id()] : [];
        return $this->view('admin/avisos/index', [
            'titulo' => 'Avisos y comunicados',
            'avisos' => Database::all(
                'SELECT a.*, u.nombre AS autor,
                        (SELECT COUNT(*) FROM aviso_lecturas al WHERE al.aviso_id = a.id) AS lecturas
                 FROM avisos a LEFT JOIN users u ON u.id = a.autor_id
                 ' . $w . ' ORDER BY a.id DESC LIMIT 100',
                $p
            ),
        ]);
    }

    public function crear(): string
    {
        $this->requirePermiso('avisos.editar');
        return $this->formulario(null);
    }

    public function editar(string $id): string
    {
        $this->requirePermiso('avisos.editar');
        $aviso = Database::one('SELECT * FROM avisos WHERE id = :id', ['id' => (int)$id]);
        if (!$aviso) {
            throw new HttpException(404, 'El aviso no existe.');
        }
        if (Auth::is('docente') && (int)$aviso['autor_id'] !== (int)Auth::id()) {
            throw new HttpException(403, 'Solo puede editar sus propios avisos.');
        }
        return $this->formulario($aviso);
    }

    private function formulario(?array $aviso): string
    {
        return $this->view('admin/avisos/form', [
            'titulo'    => $aviso ? 'Editar aviso' : 'Nuevo aviso',
            'aviso'     => $aviso ?? ['destino' => 'todos', 'activo' => 1],
            'niveles'   => Academico::niveles(),
            'grados'    => Academico::grados(),
            'secciones' => Academico::secciones(),
        ]);
    }

    public function guardar(?string $id = null): string
    {
        $this->requirePermiso('avisos.editar');
        $this->requireCsrf();
        $id = $id !== null ? (int)$id : 0;
        $v = Validator::make($this->req->all(), [
            'titulo'      => 'required|len:3,180',
            'contenido'   => 'required|len:5,20000',
            'destino'     => 'required|in:todos,nivel,grado,seccion,alumno,rol',
            'destino_id'  => 'nullable|int',
            'destino_rol' => 'nullable|in:superadmin,secretaria,docente,padre',
            'publicar_en' => 'nullable|max:20',
            'caduca_en'   => 'nullable|max:20',
        ], ['titulo' => 'titulo', 'contenido' => 'contenido', 'destino' => 'destinatario']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->back('avisos');
        }
        $destino = (string)$v->get('destino');
        if (in_array($destino, ['nivel', 'grado', 'seccion', 'alumno'], true) && !$v->get('destino_id')) {
            $this->error('Debe seleccionar el destinatario especifico.');
            return $this->back('avisos');
        }
        if ($destino === 'rol' && !$v->get('destino_rol')) {
            $this->error('Debe seleccionar el rol destinatario.');
            return $this->back('avisos');
        }
        $imagen = null;
        $adjunto = null;
        if (($this->req->file('imagen')['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = Upload::store($this->req->file('imagen'), 'avisos', Upload::IMAGENES);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->back('avisos');
            }
            $imagen = $r['archivo'];
            \App\Core\Imagen::redimensionar(BASE_PATH . '/storage/uploads/' . $imagen, 1400, 900);
        }
        if (($this->req->file('adjunto')['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = Upload::store($this->req->file('adjunto'), 'avisos', Upload::DOCUMENTOS);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->back('avisos');
            }
            $adjunto = $r['archivo'];
        }
        $campos = [
            'titulo'      => $v->get('titulo'),
            'contenido'   => $this->limpiarHtml((string)$v->get('contenido')),
            'destino'     => $destino,
            'destino_id'  => in_array($destino, ['nivel', 'grado', 'seccion', 'alumno'], true) ? (int)$v->get('destino_id') : null,
            'destino_rol' => $destino === 'rol' ? $v->get('destino_rol') : null,
            'publicar_en' => $this->fechaHora((string)($v->get('publicar_en') ?? '')),
            'caduca_en'   => $this->fechaHora((string)($v->get('caduca_en') ?? '')),
            'activo'      => $this->req->bool('activo') ? 1 : 0,
        ];
        if ($imagen) {
            $campos['imagen'] = $imagen;
        }
        if ($adjunto) {
            $campos['adjunto'] = $adjunto;
        }
        if ($id > 0) {
            $existente = Database::one('SELECT * FROM avisos WHERE id = :id', ['id' => $id]);
            if (!$existente) {
                throw new HttpException(404, 'El aviso no existe.');
            }
            if (Auth::is('docente') && (int)$existente['autor_id'] !== (int)Auth::id()) {
                throw new HttpException(403, 'Solo puede editar sus propios avisos.');
            }
            $sets = [];
            foreach (array_keys($campos) as $c) {
                $sets[] = "$c = :$c";
            }
            $campos['id'] = $id;
            Database::run('UPDATE avisos SET ' . implode(', ', $sets) . ' WHERE id = :id', $campos);
            Audit::log('aviso.actualizar', 'avisos', $id, (string)$v->get('titulo'));
        } else {
            $campos['autor_id'] = (int)Auth::id();
            $cols = implode(', ', array_keys($campos));
            $vals = ':' . implode(', :', array_keys($campos));
            $id = Database::insert("INSERT INTO avisos ($cols) VALUES ($vals)", $campos);
            Audit::log('aviso.crear', 'avisos', $id, (string)$v->get('titulo'));
            $this->notificar($id, (string)$v->get('titulo'), $destino, $campos['destino_id'], $campos['destino_rol']);
        }
        $this->ok('El aviso fue guardado.');
        return $this->redirect('avisos');
    }

    private function fechaHora(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        $ts = strtotime(str_replace('T', ' ', $valor));
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    /** Depura el HTML permitido en los avisos. */
    private function limpiarHtml(string $html): string
    {
        $permitidas = '<p><br><strong><b><em><i><u><ul><ol><li><h3><h4><blockquote><a>';
        $limpio = strip_tags($html, $permitidas);
        $limpio = preg_replace('/\s(on\w+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $limpio) ?? $limpio;
        $limpio = preg_replace('/javascript\s*:/i', '', $limpio) ?? $limpio;
        return $limpio;
    }

    private function notificar(int $avisoId, string $titulo, string $destino, ?int $destinoId, ?string $rol): void
    {
        $sql = 'SELECT id FROM users WHERE activo = 1';
        $p = [];
        if ($destino === 'rol' && $rol) {
            $sql .= ' AND rol = :r';
            $p['r'] = $rol;
        } elseif ($destino === 'alumno' && $destinoId) {
            $sql = 'SELECT DISTINCT user_id AS id FROM encargados WHERE alumno_id = :a AND user_id IS NOT NULL';
            $p = ['a' => $destinoId];
        } elseif (in_array($destino, ['nivel', 'grado', 'seccion'], true) && $destinoId) {
            $campo = ['nivel' => 'g.nivel_id', 'grado' => 's.grado_id', 'seccion' => 'i.seccion_id'][$destino];
            $sql = 'SELECT DISTINCT e.user_id AS id
                    FROM inscripciones i
                    JOIN secciones s ON s.id = i.seccion_id
                    JOIN grados g ON g.id = s.grado_id
                    JOIN encargados e ON e.alumno_id = i.alumno_id
                    WHERE i.ciclo_id = :c AND e.user_id IS NOT NULL AND ' . $campo . ' = :d';
            $p = ['c' => Academico::cicloActivoId(), 'd' => $destinoId];
        }
        foreach (Database::all($sql, $p) as $u) {
            Notificador::crear((int)$u['id'], 'Nuevo aviso', $titulo, 'avisos/' . $avisoId);
        }
    }

    public function eliminar(string $id): string
    {
        $this->requirePermiso('avisos.editar');
        $this->requireCsrf();
        $aviso = Database::one('SELECT * FROM avisos WHERE id = :id', ['id' => (int)$id]);
        if (!$aviso) {
            throw new HttpException(404, 'El aviso no existe.');
        }
        if (Auth::is('docente') && (int)$aviso['autor_id'] !== (int)Auth::id()) {
            throw new HttpException(403, 'Solo puede eliminar sus propios avisos.');
        }
        Upload::delete($aviso['imagen'] ?? null);
        Upload::delete($aviso['adjunto'] ?? null);
        Database::run('DELETE FROM aviso_lecturas WHERE aviso_id = :id', ['id' => (int)$id]);
        Database::run('DELETE FROM avisos WHERE id = :id', ['id' => (int)$id]);
        Audit::log('aviso.eliminar', 'avisos', (int)$id);
        $this->ok('Aviso eliminado.');
        return $this->redirect('avisos');
    }

    public function ver(string $id): string
    {
        $this->requireAuth();
        $aviso = Database::one(
            'SELECT a.*, u.nombre AS autor FROM avisos a LEFT JOIN users u ON u.id = a.autor_id WHERE a.id = :id',
            ['id' => (int)$id]
        );
        if (!$aviso) {
            throw new HttpException(404, 'El aviso no existe.');
        }
        $visibles = array_map(static fn($a) => (int)$a['id'], Comunicacion::avisosPara(null, 100));
        if (!in_array((int)$id, $visibles, true) && !Auth::is('superadmin', 'secretaria')) {
            throw new HttpException(403, 'Este aviso no esta dirigido a usted.');
        }
        Comunicacion::marcarLeido((int)$id, (int)Auth::id());
        return $this->view('admin/avisos/ver', [
            'titulo'   => (string)$aviso['titulo'],
            'aviso'    => $aviso,
            'lecturas' => Comunicacion::lecturas((int)$id),
        ]);
    }

    // ---------------- Calendario escolar ----------------

    public function calendario(): string
    {
        $this->requirePermiso('avisos.ver');
        return $this->view('admin/calendario', [
            'titulo'  => 'Calendario escolar',
            'eventos' => Comunicacion::eventos(date('Y-01-01'), date('Y-12-31')),
        ]);
    }

    public function guardarEvento(): string
    {
        $this->requirePermiso('calendario.editar');
        $this->requireCsrf();
        $v = Validator::make($this->req->all(), [
            'titulo'       => 'required|len:3,160',
            'descripcion'  => 'nullable|max:2000',
            'tipo'         => 'required|in:evento,feriado,examen,entrega,otro',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'nullable|date',
        ], ['titulo' => 'titulo', 'fecha_inicio' => 'fecha de inicio']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('calendario-escolar');
        }
        $id = $this->req->int('id');
        $campos = [
            'titulo'       => $v->get('titulo'),
            'descripcion'  => $v->get('descripcion'),
            'tipo'         => $v->get('tipo'),
            'fecha_inicio' => $v->get('fecha_inicio'),
            'fecha_fin'    => $v->get('fecha_fin'),
            'publico'      => $this->req->bool('publico') ? 1 : 0,
            'color'        => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$this->req->input('color', '')) ? $this->req->input('color') : null,
        ];
        if ($id > 0) {
            $sets = [];
            foreach (array_keys($campos) as $c) {
                $sets[] = "$c = :$c";
            }
            $campos['id'] = $id;
            Database::run('UPDATE eventos SET ' . implode(', ', $sets) . ' WHERE id = :id', $campos);
        } else {
            $cols = implode(', ', array_keys($campos));
            $vals = ':' . implode(', :', array_keys($campos));
            $id = Database::insert("INSERT INTO eventos ($cols) VALUES ($vals)", $campos);
        }
        Audit::log('evento.guardar', 'eventos', $id, (string)$v->get('titulo'));
        $this->ok('Evento guardado en el calendario.');
        return $this->redirect('calendario-escolar');
    }

    public function eliminarEvento(string $id): string
    {
        $this->requirePermiso('calendario.editar');
        $this->requireCsrf();
        Database::run('DELETE FROM eventos WHERE id = :id', ['id' => (int)$id]);
        Audit::log('evento.eliminar', 'eventos', (int)$id);
        $this->ok('Evento eliminado.');
        return $this->redirect('calendario-escolar');
    }
}
