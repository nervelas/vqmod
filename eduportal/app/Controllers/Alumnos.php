<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Imagen;
use App\Core\Qr;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Cobranza;
use App\Models\Evaluacion;
use App\Models\Usuario;
use Vendor\Pdf\Pdf;
use Vendor\Xlsx\Xlsx;

final class Alumnos extends Controller
{
    public function index(): string
    {
        $this->requirePermiso('alumnos.ver');
        [$p, $pp, $off] = $this->pagina(25);
        $f = [
            'q'          => $this->req->input('q', ''),
            'seccion_id' => $this->req->int('seccion'),
            'nivel_id'   => $this->req->int('nivel'),
            'estado'     => $this->req->input('estado', ''),
        ];
        return $this->view('admin/alumnos/index', [
            'titulo'    => 'Alumnos',
            'alumnos'   => Alumno::buscar($f, $pp, $off),
            'total'     => Alumno::contar($f),
            'pagina'    => $p,
            'porPagina' => $pp,
            'filtros'   => $f,
            'secciones' => Academico::secciones(),
            'niveles'   => Academico::niveles(),
        ]);
    }

    public function crear(): string
    {
        $this->requirePermiso('alumnos.editar');
        return $this->view('admin/alumnos/form', [
            'titulo'    => 'Nuevo alumno',
            'alumno'    => ['codigo' => Alumno::siguienteCodigo(), 'estado' => 'activo'],
            'encargados' => [],
            'secciones' => Academico::secciones(),
        ]);
    }

    public function editar(string $id): string
    {
        $this->requirePermiso('alumnos.editar');
        $alumno = Alumno::porId((int)$id);
        if (!$alumno) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        return $this->view('admin/alumnos/form', [
            'titulo'     => 'Editar alumno',
            'alumno'     => $alumno,
            'encargados' => Alumno::encargados((int)$id),
            'secciones'  => Academico::secciones(),
        ]);
    }

    public function guardar(?string $id = null): string
    {
        $this->requirePermiso('alumnos.editar');
        $this->requireCsrf();
        $id = $id !== null ? (int)$id : 0;

        $v = Validator::make($this->req->all(), [
            'nombres'           => 'required|len:2,120',
            'apellidos'         => 'required|len:2,120',
            'codigo'            => 'required|len:3,30',
            'dpi'               => 'nullable|max:30',
            'partida'           => 'nullable|max:60',
            'fecha_nacimiento'  => 'nullable|date',
            'genero'            => 'nullable|in:M,F,O',
            'direccion'         => 'nullable|max:255',
            'alergias'          => 'nullable|max:1000',
            'observaciones'     => 'nullable|max:1000',
            'emergencia_nombre' => 'nullable|max:120',
            'emergencia_tel'    => 'nullable|max:40',
            'estado'            => 'required|in:activo,retirado,graduado',
            'seccion_id'        => 'nullable|int',
            'beca_pct'          => 'nullable|numeric|min:0|max:100',
        ], [
            'nombres' => 'nombres', 'apellidos' => 'apellidos', 'codigo' => 'codigo',
            'fecha_nacimiento' => 'fecha de nacimiento', 'estado' => 'estado',
        ]);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->back($id ? 'alumnos/' . $id . '/editar' : 'alumnos/nuevo');
        }
        $codigo = (string)$v->get('codigo');
        $dup = Database::value(
            'SELECT id FROM alumnos WHERE codigo = :c' . ($id ? ' AND id <> :id' : ''),
            $id ? ['c' => $codigo, 'id' => $id] : ['c' => $codigo]
        );
        if ($dup) {
            $this->error('Ya existe otro alumno con ese codigo.');
            return $this->back($id ? 'alumnos/' . $id . '/editar' : 'alumnos/nuevo');
        }

        $foto = null;
        if (($this->req->file('foto')['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = Upload::store($this->req->file('foto'), 'alumnos', Upload::IMAGENES);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->back();
            }
            $foto = $r['archivo'];
            Imagen::redimensionar(BASE_PATH . '/storage/uploads/' . $foto, 600, 600);
        }

        $campos = [
            'nombres' => $v->get('nombres'), 'apellidos' => $v->get('apellidos'), 'codigo' => $codigo,
            'dpi' => $v->get('dpi'), 'partida' => $v->get('partida'),
            'fecha_nacimiento' => $v->get('fecha_nacimiento'), 'genero' => $v->get('genero'),
            'direccion' => $v->get('direccion'), 'alergias' => $v->get('alergias'),
            'observaciones' => $v->get('observaciones'),
            'emergencia_nombre' => $v->get('emergencia_nombre'), 'emergencia_tel' => $v->get('emergencia_tel'),
            'estado' => $v->get('estado'),
        ];
        if ($id > 0) {
            $sets = [];
            foreach (array_keys($campos) as $c) {
                $sets[] = "$c = :$c";
            }
            if ($foto) {
                $sets[] = 'foto = :foto';
                $campos['foto'] = $foto;
            }
            $campos['id'] = $id;
            Database::run('UPDATE alumnos SET ' . implode(', ', $sets) . ' WHERE id = :id', $campos);
            Audit::log('alumno.actualizar', 'alumnos', $id, $codigo);
        } else {
            if ($foto) {
                $campos['foto'] = $foto;
            }
            $cols = implode(', ', array_keys($campos));
            $vals = ':' . implode(', :', array_keys($campos));
            $id = Database::insert("INSERT INTO alumnos ($cols) VALUES ($vals)", $campos);
            Audit::log('alumno.crear', 'alumnos', $id, $codigo);
        }

        $seccionId = (int)($v->get('seccion_id') ?? 0);
        if ($seccionId > 0) {
            $ciclo = Academico::cicloActivoId();
            $beca = max(0.0, min(100.0, (float)($v->get('beca_pct') ?? 0)));
            $existe = Database::value(
                'SELECT id FROM inscripciones WHERE alumno_id = :a AND ciclo_id = :c',
                ['a' => $id, 'c' => $ciclo]
            );
            if ($existe) {
                Database::run(
                    'UPDATE inscripciones SET seccion_id = :s, beca_pct = :b, estado = :e WHERE id = :id',
                    ['s' => $seccionId, 'b' => $beca, 'e' => $v->get('estado'), 'id' => (int)$existe]
                );
            } else {
                Database::run(
                    'INSERT INTO inscripciones (alumno_id, ciclo_id, seccion_id, fecha, beca_pct, estado)
                     VALUES (:a, :c, :s, :f, :b, :e)',
                    ['a' => $id, 'c' => $ciclo, 's' => $seccionId, 'f' => date('Y-m-d'), 'b' => $beca, 'e' => $v->get('estado')]
                );
            }
        }
        $this->ok('Los datos del alumno fueron guardados.');
        return $this->redirect('alumnos/' . $id);
    }

    public function ver(string $id): string
    {
        $this->requirePermiso('alumnos.ver');
        $alumnoId = (int)$id;
        if (!Auth::puedeVerAlumno($alumnoId)) {
            throw new HttpException(403, 'No tiene acceso a este alumno.');
        }
        $alumno = Alumno::porId($alumnoId);
        if (!$alumno) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        return $this->view('admin/alumnos/ver', [
            'titulo'     => Alumno::nombre($alumno),
            'alumno'     => $alumno,
            'encargados' => Alumno::encargados($alumnoId),
            'cuenta'     => Auth::can('cobranza.ver') ? Cobranza::estadoCuenta($alumnoId) : null,
            'boleta'     => Evaluacion::boleta($alumnoId),
            'historial'  => Alumno::historial($alumnoId),
            'documentos' => Database::all('SELECT * FROM documentos WHERE alumno_id = :a ORDER BY id DESC', ['a' => $alumnoId]),
        ]);
    }

    public function eliminar(string $id): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $alumnoId = (int)$id;
        $alumno = Alumno::porId($alumnoId);
        if (!$alumno) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        $tienePagos = (int)Database::value('SELECT COUNT(*) FROM pagos WHERE alumno_id = :a', ['a' => $alumnoId], 0);
        if ($tienePagos > 0) {
            Database::run('UPDATE alumnos SET estado = \'retirado\' WHERE id = :a', ['a' => $alumnoId]);
            Database::run('UPDATE inscripciones SET estado = \'retirado\' WHERE alumno_id = :a', ['a' => $alumnoId]);
            Audit::log('alumno.retirar', 'alumnos', $alumnoId, 'Tiene pagos: se marco como retirado');
            $this->aviso('El alumno tiene pagos registrados, por lo que se marco como retirado en lugar de eliminarse.');
            return $this->redirect('alumnos/' . $alumnoId);
        }
        Upload::delete($alumno['foto'] ?? null);
        Database::run('DELETE FROM alumnos WHERE id = :a', ['a' => $alumnoId]);
        Database::run('DELETE FROM inscripciones WHERE alumno_id = :a', ['a' => $alumnoId]);
        Database::run('DELETE FROM encargados WHERE alumno_id = :a', ['a' => $alumnoId]);
        Database::run('DELETE FROM cargos WHERE alumno_id = :a', ['a' => $alumnoId]);
        Audit::log('alumno.eliminar', 'alumnos', $alumnoId, (string)$alumno['codigo']);
        $this->ok('El alumno fue eliminado.');
        return $this->redirect('alumnos');
    }

    // ---------------- Encargados ----------------

    public function guardarEncargado(string $id): string
    {
        $this->requirePermiso('encargados.editar');
        $this->requireCsrf();
        $alumnoId = (int)$id;
        if (!Alumno::porId($alumnoId)) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        $encId = $this->req->int('encargado_id');
        $existentes = count(Alumno::encargados($alumnoId));
        if ($encId === 0 && $existentes >= 3) {
            $this->error('Un alumno puede tener como maximo 3 encargados.');
            return $this->redirect('alumnos/' . $alumnoId);
        }
        $v = Validator::make($this->req->all(), [
            'nombre'     => 'required|len:3,140',
            'parentesco' => 'nullable|max:40',
            'telefono'   => 'nullable|max:40',
            'email'      => 'nullable|email|max:160',
            'dpi'        => 'nullable|max:30',
        ], ['nombre' => 'nombre del encargado', 'email' => 'correo']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('alumnos/' . $alumnoId);
        }
        $principal = $this->req->bool('principal') ? 1 : 0;
        $crearAcceso = $this->req->bool('crear_acceso');
        $userId = null;
        $passwordGenerada = null;

        if ($v->get('email')) {
            $u = Usuario::porEmail((string)$v->get('email'));
            if ($u) {
                $userId = (int)$u['id'];
            } elseif ($crearAcceso) {
                $passwordGenerada = Usuario::generarPassword();
                $userId = Usuario::crear((string)$v->get('nombre'), (string)$v->get('email'), $passwordGenerada, 'padre', (string)($v->get('telefono') ?? ''));
                Database::run('UPDATE users SET debe_cambiar = 1 WHERE id = :id', ['id' => $userId]);
            }
        }

        if ($principal) {
            Database::run('UPDATE encargados SET principal = 0 WHERE alumno_id = :a', ['a' => $alumnoId]);
        }
        $campos = [
            'nombre' => $v->get('nombre'), 'parentesco' => $v->get('parentesco'),
            'telefono' => $v->get('telefono'), 'email' => $v->get('email'),
            'dpi' => $v->get('dpi'), 'principal' => $principal,
        ];
        if ($encId > 0) {
            $campos['id'] = $encId;
            $campos['a'] = $alumnoId;
            if ($userId !== null) {
                $campos['user_id'] = $userId;
                Database::run(
                    'UPDATE encargados SET nombre = :nombre, parentesco = :parentesco, telefono = :telefono,
                     email = :email, dpi = :dpi, principal = :principal, user_id = :user_id
                     WHERE id = :id AND alumno_id = :a',
                    $campos
                );
            } else {
                Database::run(
                    'UPDATE encargados SET nombre = :nombre, parentesco = :parentesco, telefono = :telefono,
                     email = :email, dpi = :dpi, principal = :principal WHERE id = :id AND alumno_id = :a',
                    $campos
                );
            }
        } else {
            $campos['alumno_id'] = $alumnoId;
            $campos['user_id'] = $userId;
            $campos['orden'] = $existentes + 1;
            Database::run(
                'INSERT INTO encargados (alumno_id, user_id, nombre, parentesco, telefono, email, dpi, principal, orden)
                 VALUES (:alumno_id, :user_id, :nombre, :parentesco, :telefono, :email, :dpi, :principal, :orden)',
                $campos
            );
        }
        Audit::log('encargado.guardar', 'alumnos', $alumnoId, (string)$v->get('nombre'));
        if ($passwordGenerada !== null) {
            \App\Core\Mail::enviar(
                (string)$v->get('email'),
                (string)$v->get('nombre'),
                'Acceso al portal de padres',
                '<p>Se creo su acceso al portal del colegio.</p>'
                . '<p>Usuario: <strong>' . e((string)$v->get('email')) . '</strong><br>'
                . 'Contrasena temporal: <strong>' . e($passwordGenerada) . '</strong></p>'
                . '<p>Le recomendamos cambiarla al ingresar por primera vez.</p>'
                . '<p><a href="' . e(url_absoluta('ingresar')) . '">Ingresar al portal</a></p>'
            );
            $this->ok('Encargado guardado. Se envio la contrasena temporal a su correo: ' . $passwordGenerada);
        } else {
            $this->ok('Encargado guardado.');
        }
        return $this->redirect('alumnos/' . $alumnoId);
    }

    public function eliminarEncargado(string $id): string
    {
        $this->requirePermiso('encargados.editar');
        $this->requireCsrf();
        $enc = Database::one('SELECT * FROM encargados WHERE id = :id', ['id' => (int)$id]);
        if (!$enc) {
            throw new HttpException(404, 'El encargado no existe.');
        }
        Database::run('DELETE FROM encargados WHERE id = :id', ['id' => (int)$id]);
        Audit::log('encargado.eliminar', 'encargados', (int)$id);
        $this->ok('Encargado eliminado.');
        return $this->redirect('alumnos/' . (int)$enc['alumno_id']);
    }

    // ---------------- Documentos ----------------

    public function subirDocumento(string $id): string
    {
        $this->requirePermiso('alumnos.editar');
        $this->requireCsrf();
        $alumnoId = (int)$id;
        $r = Upload::store($this->req->file('documento'), 'alumnos', Upload::DOCUMENTOS);
        if (!$r['ok']) {
            $this->error($r['error']);
            return $this->redirect('alumnos/' . $alumnoId);
        }
        Database::run(
            'INSERT INTO documentos (alumno_id, nombre, archivo, mime, tamano, subido_por)
             VALUES (:a, :n, :ar, :m, :t, :u)',
            [
                'a'  => $alumnoId,
                'n'  => mb_substr((string)$this->req->input('nombre', 'Documento'), 0, 160),
                'ar' => $r['archivo'],
                'm'  => $r['mime'],
                't'  => $r['tamano'],
                'u'  => Auth::id(),
            ]
        );
        Audit::log('documento.subir', 'alumnos', $alumnoId);
        $this->ok('Documento adjuntado.');
        return $this->redirect('alumnos/' . $alumnoId);
    }

    public function eliminarDocumento(string $id): string
    {
        $this->requirePermiso('alumnos.editar');
        $this->requireCsrf();
        $doc = Database::one('SELECT * FROM documentos WHERE id = :id', ['id' => (int)$id]);
        if (!$doc) {
            throw new HttpException(404, 'El documento no existe.');
        }
        Upload::delete((string)$doc['archivo']);
        Database::run('DELETE FROM documentos WHERE id = :id', ['id' => (int)$id]);
        $this->ok('Documento eliminado.');
        return $this->redirect('alumnos/' . (int)$doc['alumno_id']);
    }

    // ---------------- Carne y QR ----------------

    public function qr(string $id): string
    {
        $this->requirePermiso('alumnos.ver');
        $alumnoId = (int)$id;
        if (!Auth::puedeVerAlumno($alumnoId)) {
            throw new HttpException(403, 'No tiene acceso a este alumno.');
        }
        $alumno = Alumno::porId($alumnoId);
        if (!$alumno) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        $png = Imagen::qrPng(url_absoluta('alumnos/' . $alumnoId), 6);
        if ($png === null) {
            throw new HttpException(500, 'No se pudo generar el codigo QR.');
        }
        if (!headers_sent()) {
            header('Content-Type: image/png');
            header('Content-Length: ' . strlen($png));
        }
        echo $png;
        return '';
    }

    public function carne(string $id): string
    {
        $this->requirePermiso('alumnos.ver');
        $alumnoId = (int)$id;
        if (!Auth::puedeVerAlumno($alumnoId)) {
            throw new HttpException(403, 'No tiene acceso a este alumno.');
        }
        $alumno = Alumno::porId($alumnoId);
        if (!$alumno) {
            throw new HttpException(404, 'El alumno no existe.');
        }
        $pdf = \App\Servicios\Documentos::carne($alumno);
        Audit::log('carne.generar', 'alumnos', $alumnoId);
        return $pdf->descargar('carne-' . $alumno['codigo'] . '.pdf');
    }

    public function carnesSeccion(string $seccion): string
    {
        $this->requirePermiso('alumnos.ver');
        $seccionId = (int)$seccion;
        if (!Auth::puedeUsarSeccion($seccionId)) {
            throw new HttpException(403, 'No tiene acceso a este grupo.');
        }
        $alumnos = Alumno::deSeccion($seccionId);
        if ($alumnos === []) {
            $this->aviso('El grupo no tiene alumnos inscritos.');
            return $this->back('alumnos');
        }
        $completos = [];
        foreach ($alumnos as $a) {
            $completos[] = Alumno::porId((int)$a['id']);
        }
        $pdf = \App\Servicios\Documentos::carnes(array_filter($completos));
        return $pdf->descargar('carnes-grupo.pdf');
    }

    // ---------------- Importacion y exportacion ----------------

    public function importarForm(): string
    {
        $this->requirePermiso('alumnos.editar');
        return $this->view('admin/alumnos/importar', [
            'titulo'    => 'Importacion masiva',
            'secciones' => Academico::secciones(),
        ]);
    }

    public function plantilla(): string
    {
        $this->requirePermiso('alumnos.editar');
        $x = new Xlsx();
        $x->agregarHoja(
            'Alumnos',
            ['codigo', 'nombres', 'apellidos', 'dpi', 'fecha_nacimiento', 'genero', 'direccion',
             'encargado', 'parentesco', 'telefono', 'email', 'beca_pct'],
            [[
                '2026-0001', 'Ana Lucia', 'Garcia Lopez', '', '2015-04-12', 'F', 'Zona 10, Guatemala',
                'Maria Lopez', 'Madre', '55551234', 'maria@ejemplo.com', '0',
            ]],
            [14, 22, 24, 16, 18, 10, 30, 24, 14, 14, 26, 10]
        );
        return $x->descargar('plantilla-alumnos.xlsx');
    }

    public function importar(): string
    {
        $this->requirePermiso('alumnos.editar');
        $this->requireCsrf();
        $seccionId = $this->req->int('seccion_id');
        if ($seccionId <= 0) {
            $this->error('Debe seleccionar el grado y seccion de destino.');
            return $this->redirect('alumnos/importar');
        }
        $r = Upload::store($this->req->file('archivo'), 'importaciones', Upload::HOJAS);
        if (!$r['ok']) {
            $this->error($r['error']);
            return $this->redirect('alumnos/importar');
        }
        $ruta = BASE_PATH . '/storage/uploads/' . $r['archivo'];
        try {
            $filas = \App\Servicios\Importador::leer($ruta);
        } catch (\Throwable $e) {
            Upload::delete($r['archivo']);
            $this->error('No se pudo leer el archivo: ' . $e->getMessage());
            return $this->redirect('alumnos/importar');
        }
        $resultado = \App\Servicios\Importador::procesarAlumnos($filas, $seccionId);
        Upload::delete($r['archivo']);
        Audit::log('alumnos.importar', 'alumnos', null, json_encode($resultado, JSON_UNESCAPED_UNICODE));
        return $this->view('admin/alumnos/importar', [
            'titulo'    => 'Importacion masiva',
            'secciones' => Academico::secciones(),
            'resultado' => $resultado,
        ]);
    }

    public function exportar(): string
    {
        $this->requirePermiso('alumnos.ver');
        $f = [
            'q'          => $this->req->input('q', ''),
            'seccion_id' => $this->req->int('seccion'),
            'nivel_id'   => $this->req->int('nivel'),
            'estado'     => $this->req->input('estado', ''),
        ];
        $alumnos = Alumno::buscar($f, 5000, 0);
        $filas = [];
        foreach ($alumnos as $a) {
            $enc = Alumno::encargadoPrincipal((int)$a['id']);
            $filas[] = [
                $a['codigo'],
                $a['nombres'],
                $a['apellidos'],
                $a['grupo'] ?? '',
                ['tipo' => 'fecha', 'valor' => $a['fecha_nacimiento']],
                $a['estado'],
                $enc['nombre'] ?? '',
                $enc['telefono'] ?? '',
                $enc['email'] ?? '',
            ];
        }
        $x = new Xlsx();
        $x->agregarHoja(
            'Alumnos',
            ['Codigo', 'Nombres', 'Apellidos', 'Grado', 'Nacimiento', 'Estado', 'Encargado', 'Telefono', 'Correo'],
            $filas,
            [14, 24, 26, 20, 14, 12, 26, 14, 28]
        );
        Audit::log('alumnos.exportar', 'alumnos', null, count($filas) . ' registros');
        return $x->descargar('alumnos.xlsx');
    }
}
