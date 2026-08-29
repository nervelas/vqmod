<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Security;
use MenuGold\Core\Session;
use MenuGold\Core\Validator;

/** Personal del restaurante: dueño, gerente, cocina y meseros. */
class UsersController extends BaseController
{
    protected $ability = 'users';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return $this->view('admin/settings/users', array(
            'users' => DB::all('SELECT * FROM mg_users ORDER BY FIELD(role,"owner","manager","kitchen","waiter"), name'),
        ));
    }

    public function edit(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $id = $params['id'] === 'nuevo' ? 0 : (int)$params['id'];
        $existing = $id > 0 ? $this->row('mg_users', $id) : null;
        if ($id > 0 && !$existing) { return $this->notFound('Ese usuario no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('admin/settings/user-edit', array('u' => $existing));
        }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $v = new Validator($this->request->post);
        $v->required('name', 'El nombre')->required('username', 'El usuario')->email('email', 'El correo');
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            return $this->redirect('/panel/usuarios');
        }

        $role = $this->request->str('role', 'waiter');
        $allowed = array(Auth::ROLE_OWNER, Auth::ROLE_MANAGER, Auth::ROLE_KITCHEN, Auth::ROLE_WAITER);
        if (!in_array($role, $allowed, true)) { $role = Auth::ROLE_WAITER; }

        $username = preg_replace('/[^A-Za-z0-9._@\-]/', '', $this->request->str('username'));
        if ($username === '') {
            Session::flash('error', 'El usuario solo admite letras, números, punto, guion y arroba.');
            return $this->redirect('/panel/usuarios');
        }
        if (DB::value('SELECT id FROM mg_users WHERE username = :u AND id <> :i', array('u' => $username, 'i' => $id))) {
            Session::flash('error', 'Ese nombre de usuario ya está ocupado.');
            return $this->redirect('/panel/usuarios');
        }

        // Siempre debe quedar al menos un dueño activo, o nadie podría entrar.
        if ($existing && $existing['role'] === Auth::ROLE_OWNER && ($role !== Auth::ROLE_OWNER || !$this->request->bool('is_active'))) {
            $otros = (int)DB::value("SELECT COUNT(*) FROM mg_users WHERE role='owner' AND is_active=1 AND id <> :i",
                array('i' => $id), 0);
            if ($otros === 0) {
                Session::flash('error', 'Debe quedar al menos un dueño activo.');
                return $this->redirect('/panel/usuarios');
            }
        }

        $data = array(
            'name'      => $this->request->str('name'),
            'username'  => $username,
            'email'     => $this->request->str('email'),
            'role'      => $role,
            'is_active' => $this->request->bool('is_active') ? 1 : 0,
        );

        $password = (string)$this->request->input('password', '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                Session::flash('error', 'La contraseña debe tener al menos 8 caracteres.');
                return $this->redirect('/panel/usuarios');
            }
            $data['password_hash'] = Security::hashPassword($password);
        }
        $pin = preg_replace('/\D+/', '', (string)$this->request->input('pin', ''));
        if ($pin !== '') {
            if (strlen($pin) < 4) {
                Session::flash('error', 'El PIN debe tener al menos 4 dígitos.');
                return $this->redirect('/panel/usuarios');
            }
            $data['pin'] = Security::hashPassword($pin);
        }

        if ($existing) {
            DB::update('mg_users', $data, 'id = :id', array('id' => $id));
            Audit::log('user_updated', 'user', $id, array('role' => $role));
        } else {
            if (!isset($data['password_hash'])) {
                Session::flash('error', 'Define una contraseña para el usuario nuevo.');
                return $this->redirect('/panel/usuarios');
            }
            $newId = DB::insert('mg_users', $data);
            Audit::log('user_created', 'user', $newId, array('role' => $role));
        }
        Session::flash('success', 'Usuario guardado.');
        return $this->redirect('/panel/usuarios');
    }

    public function delete(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $id = (int)$params['id'];
        if ($id === Auth::id()) {
            Session::flash('error', 'No puedes eliminar tu propio usuario.');
            return $this->redirect('/panel/usuarios');
        }
        $u = $this->row('mg_users', $id);
        if ($u && $u['role'] === Auth::ROLE_OWNER) {
            $otros = (int)DB::value("SELECT COUNT(*) FROM mg_users WHERE role='owner' AND is_active=1 AND id <> :i",
                array('i' => $id), 0);
            if ($otros === 0) {
                Session::flash('error', 'Debe quedar al menos un dueño activo.');
                return $this->redirect('/panel/usuarios');
            }
        }
        DB::delete('mg_users', 'id = :id', array('id' => $id));
        Audit::log('user_deleted', 'user', $id);
        Session::flash('success', 'Usuario eliminado.');
        return $this->redirect('/panel/usuarios');
    }
}
