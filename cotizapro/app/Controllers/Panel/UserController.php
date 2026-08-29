<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Security;
use App\Core\Uploader;
use App\Models\Company;

final class UserController extends Controller
{
    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        if (Request::isPost()) {
            Csrf::verify();
            $this->store(0, $c);
        }
        $this->view('panel/users', [
            'title' => 'Vendedores y usuarios',
            'rows'  => DB::all('SELECT * FROM users ORDER BY FIELD(role,"admin","vendedor","visor"), name'),
            'assignMode' => (string) $c['assign_mode'],
        ], 'layout/panel');
    }

    public function form(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $id  = (int) $params['id'];
        $row = DB::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$row) {
            ErrorHandler::render(404);
        }
        if (Request::isPost()) {
            Csrf::verify();
            $this->store($id, $c);
        }
        $this->view('panel/user-form', ['title' => $row['name'], 'row' => $row], 'layout/panel');
    }

    private function store(int $id, array $c): never
    {
        $name  = mb_substr(Request::str('name'), 0, 120);
        $email = Request::email('email');
        $role  = Request::str('role');
        if (!in_array($role, [Auth::ROLE_ADMIN, Auth::ROLE_SELLER, Auth::ROLE_VIEWER], true)) {
            $role = Auth::ROLE_SELLER;
        }
        if ($name === '' || $email === '') {
            Flash::error('Nombre y correo válido son obligatorios.');
            Flash::keep($_POST);
            redirect($id ? '/panel/usuarios/' . $id : '/panel/usuarios');
        }
        $dupe = DB::one('SELECT id FROM users WHERE email = ?' . ($id ? ' AND id <> ?' : '') . ' LIMIT 1', $id ? [$email, $id] : [$email]);
        if ($dupe) {
            Flash::error('Ya existe un usuario con ese correo.');
            redirect($id ? '/panel/usuarios/' . $id : '/panel/usuarios');
        }
        $username = mb_substr(preg_replace('/[^a-zA-Z0-9_\.\-]/', '', Request::str('username')) ?: '', 0, 60) ?: null;
        if ($username) {
            $du = DB::one('SELECT id FROM users WHERE username = ?' . ($id ? ' AND id <> ?' : '') . ' LIMIT 1', $id ? [$username, $id] : [$username]);
            if ($du) {
                Flash::error('Ese nombre de usuario ya está ocupado.');
                redirect($id ? '/panel/usuarios/' . $id : '/panel/usuarios');
            }
        }
        $data = [
            'name'           => $name,
            'email'          => $email,
            'username'       => $username,
            'role'           => $role,
            'phone'          => mb_substr(Request::str('phone'), 0, 40) ?: null,
            'whatsapp'       => mb_substr(Request::str('whatsapp'), 0, 30) ?: null,
            'position'       => mb_substr(Request::str('position'), 0, 90) ?: null,
            'status'         => Request::bool('active') ? 'activo' : 'inactivo',
            'twofa_enabled'  => Request::bool('twofa') ? 1 : 0,
            'receives_leads' => Request::bool('receives_leads') ? 1 : 0,
            'updated_at'     => nowSql(),
        ];
        $pass = Request::raw('password');
        if ($pass !== '') {
            if (!Security::passwordOk($pass)) {
                Flash::error('La contraseña debe tener 8+ caracteres con mayúsculas, minúsculas y números.');
                redirect($id ? '/panel/usuarios/' . $id : '/panel/usuarios');
            }
            $data['password'] = Security::hashPassword($pass);
        }
        $f = Uploader::files('avatar');
        if ($f) {
            $res = Uploader::image($f[0], 'usuarios', 400, 400);
            if ($res) {
                $data['avatar'] = $res['path_webp'] ?: $res['path'];
            }
        }
        if ($id) {
            // Nunca dejar la empresa sin administrador activo.
            if ($role !== Auth::ROLE_ADMIN || $data['status'] !== 'activo') {
                $admins = (int) DB::value('SELECT COUNT(*) FROM users WHERE role = "admin" AND status = "activo" AND id <> ?', [$id], 0);
                if ($admins === 0) {
                    Flash::error('Debe existir al menos un administrador activo en la empresa.');
                    redirect('/panel/usuarios/' . $id);
                }
            }
            DB::update('users', $data, 'id = :id', ['id' => $id]);
            Audit::log('usuario.editar', 'user', $id, ['email' => $email, 'rol' => $role]);
        } else {
            if ($pass === '') {
                Flash::error('Asigne una contraseña al nuevo usuario.');
                redirect('/panel/usuarios');
            }
            $data['created_at'] = nowSql();
            $id = DB::insert('users', $data);
            Audit::log('usuario.crear', 'user', $id, ['email' => $email, 'rol' => $role]);
        }
        Flash::ok('Usuario guardado.');
        redirect('/panel/usuarios');
    }

    public function destroy(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $id  = (int) $params['id'];
        if ($id === (int) $u['id']) {
            Flash::error('No puede eliminar su propio usuario.');
            redirect('/panel/usuarios');
        }
        $row = DB::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$row) {
            ErrorHandler::render(404);
        }
        $admins = (int) DB::value('SELECT COUNT(*) FROM users WHERE role = "admin" AND status = "activo" AND id <> ?', [$id], 0);
        if ($row['role'] === 'admin' && $admins === 0) {
            Flash::error('Debe quedar al menos un administrador activo.');
            redirect('/panel/usuarios');
        }
        DB::run('UPDATE quotes SET user_id = NULL WHERE user_id = ?', [$id]);
        DB::run('UPDATE customers SET assigned_user_id = NULL WHERE assigned_user_id = ?', [$id]);
        DB::delete('users', 'id = :id', ['id' => $id]);
        Audit::log('usuario.eliminar', 'user', $id, ['email' => $row['email']]);
        Flash::ok('Usuario eliminado.');
        redirect('/panel/usuarios');
    }

    public function profile(array $params = []): void
    {
        [$u, $c] = $this->panel();
        if (Request::isPost()) {
            Csrf::verify();
            $data = [
                'name'     => mb_substr(Request::str('name'), 0, 120) ?: (string) $u['name'],
                'phone'    => mb_substr(Request::str('phone'), 0, 40) ?: null,
                'whatsapp' => mb_substr(Request::str('whatsapp'), 0, 30) ?: null,
                'position' => mb_substr(Request::str('position'), 0, 90) ?: null,
                'twofa_enabled' => Request::bool('twofa') ? 1 : 0,
                'updated_at' => nowSql(),
            ];
            $new = Request::raw('password');
            if ($new !== '') {
                if (!Security::verifyPassword(Request::raw('current'), (string) $u['password'])) {
                    Flash::error('La contraseña actual no es correcta.');
                    redirect('/panel/perfil');
                }
                if (!Security::passwordOk($new)) {
                    Flash::error('La nueva contraseña debe tener 8+ caracteres con mayúsculas, minúsculas y números.');
                    redirect('/panel/perfil');
                }
                $data['password'] = Security::hashPassword($new);
            }
            $f = Uploader::files('avatar');
            if ($f) {
                $res = Uploader::image($f[0], 'usuarios', 400, 400);
                if ($res) {
                    $data['avatar'] = $res['path_webp'] ?: $res['path'];
                }
            }
            DB::update('users', $data, 'id = :id', ['id' => (int) $u['id']]);
            Audit::log('perfil.actualizar', 'user', (int) $u['id'], []);
            Flash::ok('Perfil actualizado.');
            redirect('/panel/perfil');
        }
        $this->view('panel/profile', ['title' => 'Mi perfil', 'row' => $u], 'layout/panel');
    }
}
