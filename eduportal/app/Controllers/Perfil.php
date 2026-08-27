<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\Usuario;

final class Perfil extends Controller
{
    public function index(): string
    {
        $this->requireAuth();
        return $this->view('admin/perfil', [
            'titulo'   => 'Mi perfil',
            'usuario'  => Usuario::porId((int)Auth::id()),
            'sesiones' => Database::all(
                'SELECT accion, ip, agente, creado_en FROM bitacora
                 WHERE user_id = :u AND accion = \'login\' ORDER BY id DESC LIMIT 8',
                ['u' => (int)Auth::id()]
            ),
        ]);
    }

    public function guardar(): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $v = Validator::make($this->req->all(), [
            'nombre'   => 'required|len:3,120',
            'telefono' => 'nullable|max:40',
        ], ['nombre' => 'nombre', 'telefono' => 'telefono']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('perfil');
        }
        $foto = null;
        if (($this->req->file('foto')['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = Upload::store($this->req->file('foto'), 'usuarios', Upload::IMAGENES);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->redirect('perfil');
            }
            $foto = $r['archivo'];
            \App\Core\Imagen::redimensionar(BASE_PATH . '/storage/uploads/' . $foto, 480, 480);
        }
        $sql = 'UPDATE users SET nombre = :n, telefono = :t' . ($foto ? ', foto = :f' : '') . ' WHERE id = :id';
        $p = ['n' => $v->get('nombre'), 't' => $v->get('telefono'), 'id' => (int)Auth::id()];
        if ($foto) {
            $p['f'] = $foto;
        }
        Database::run($sql, $p);
        Audit::log('perfil.actualizar', 'users', (int)Auth::id());
        $this->ok('Su perfil fue actualizado.');
        return $this->redirect('perfil');
    }

    public function password(): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $actual = (string)$this->req->raw('password_actual', '');
        $nueva  = (string)$this->req->raw('password', '');
        $user = Usuario::porId((int)Auth::id());
        if (!$user || !password_verify($actual, (string)$user['password_hash'])) {
            $this->error('La contrasena actual no es correcta.');
            return $this->redirect('perfil');
        }
        $v = Validator::make(
            ['password' => $nueva, 'password_confirmacion' => (string)$this->req->raw('password_confirmacion', '')],
            ['password' => 'required|password|confirmed'],
            ['password' => 'contrasena']
        );
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('perfil');
        }
        Database::run('UPDATE users SET password_hash = :h, debe_cambiar = 0 WHERE id = :id', [
            'h' => Auth::hash($nueva), 'id' => (int)$user['id'],
        ]);
        Audit::log('password.cambiar', 'users', (int)$user['id']);
        $this->ok('Su contrasena fue actualizada.');
        return $this->redirect('perfil');
    }

    public function apariencia(): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $tema = (string)$this->req->input('tema', 'default');
        $temas = array_keys(\App\Controllers\Configuracion::TEMAS);
        if (!in_array($tema, $temas, true)) {
            $tema = 'default';
        }
        $oscuro = $this->req->bool('modo_oscuro') ? 1 : 0;
        Database::run('UPDATE users SET tema = :t, modo_oscuro = :o WHERE id = :id', [
            't' => $tema, 'o' => $oscuro, 'id' => (int)Auth::id(),
        ]);
        if ($this->req->wantsJson()) {
            return $this->json(['ok' => true, 'tema' => $tema, 'modo_oscuro' => $oscuro]);
        }
        $this->ok('Preferencias de apariencia guardadas.');
        return $this->back('perfil');
    }

    public function cerrarSesiones(): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        Auth::logoutEverywhere((int)Auth::id());
        Audit::log('sesiones.cerrar', 'users', (int)Auth::id());
        \App\Core\Session::destroy();
        \App\Core\Session::start();
        \App\Core\Session::flash('ok', 'Se cerraron todas las sesiones. Vuelva a ingresar.');
        return $this->redirect('ingresar');
    }

    public function twofa(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $activar = $this->req->bool('twofa') ? 1 : 0;
        Database::run('UPDATE users SET twofa = :t WHERE id = :id', ['t' => $activar, 'id' => (int)Auth::id()]);
        Audit::log('2fa.' . ($activar ? 'activar' : 'desactivar'), 'users', (int)Auth::id());
        $this->ok($activar ? 'Verificacion en dos pasos activada.' : 'Verificacion en dos pasos desactivada.');
        return $this->redirect('perfil');
    }
}
