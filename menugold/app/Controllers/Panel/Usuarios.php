<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Request;
use MenuGold\Core\Security;
use MenuGold\Core\Validator;
use MenuGold\Models\User;

/**
 * Usuarios del restaurante y perfil propio.
 */
class Usuarios extends Base
{
    private const ROLES = ['dueno' => 'Dueño', 'admin' => 'Administrador', 'cocina' => 'Cocina', 'mesero' => 'Mesero / Caja'];

    private function usr(): User { return (new User())->forRestaurant($this->rid); }

    public function index(): void
    {
        $this->exigir('usuarios');
        $this->panel('panel/usuarios', [
            'usuarios' => $this->usr()->all('rol ASC, nombre ASC'),
            'roles'    => self::ROLES,
            'limites'  => $this->limites(),
        ]);
    }

    public function guardar(): void
    {
        $this->exigir('usuarios');
        $id = Request::int('id');
        if ($id <= 0) {
            [$cabe, $msg] = $this->cabeEnPlan('usuarios');
            if (!$cabe) $this->fail($msg);
        }

        $datos = [
            'nombre'   => Request::str('nombre', '', 120),
            'email'    => Request::email('email') ?: null,
            'usuario'  => mb_strtolower(preg_replace('/[^A-Za-z0-9._-]/', '', Request::str('usuario', '', 60)) ?? ''),
            'rol'      => Request::enum('rol', array_keys(self::ROLES), 'mesero'),
            'telefono' => Request::str('telefono', '', 30),
            'activo'   => Request::bool('activo', true) ? 1 : 0,
        ];
        $clave = (string)Request::input('password', '');

        $v = Validator::make($datos + ['password' => $clave])
            ->requerido('nombre', 'El nombre')
            ->min('nombre', 3, 'El nombre')
            ->email('email');
        if ($id <= 0) $v->requerido('password', 'La contraseña');
        if ($clave !== '') $v->password('password');
        if ($v->falla()) $this->fail($v->primerError());

        if ($datos['usuario'] === '' && $datos['email'] === null) {
            $this->fail('Necesitas un correo o un nombre de usuario para poder ingresar.');
        }
        if ($datos['usuario'] === '') $datos['usuario'] = (new User())->usuarioUnico($datos['nombre']);

        $m = new User();
        if ($datos['email'] !== null && !$m->disponible('email', $datos['email'], $id)) {
            $this->fail('Ese correo ya está en uso.');
        }
        if (!$m->disponible('usuario', $datos['usuario'], $id)) {
            $this->fail('Ese nombre de usuario ya está en uso.');
        }

        $ms = $this->usr();
        if ($id > 0) {
            $antes = $ms->findOrFail($id);
            // Un dueño no puede degradarse a sí mismo si es el único
            if ((int)$antes['id'] === Auth::id() && $datos['rol'] !== $antes['rol'] && $antes['rol'] === 'dueno') {
                $otros = $ms->count("rol='dueno' AND id <> :i", ['i' => $id]);
                if ($otros === 0) $this->fail('Debe quedar al menos un dueño en el restaurante.');
            }
            if ($clave !== '') $datos['password_hash'] = Security::hashPassword($clave);
            $ms->updateById($id, $datos);
            Audit::diff('usuario.editar', 'users', $id, $antes, $datos);
            $this->ok([], 'Usuario actualizado');
        }

        $datos['password_hash'] = Security::hashPassword($clave);
        $datos['onboarding'] = 1;
        $nuevo = $ms->create($datos);
        Audit::log('usuario.crear', 'users', $nuevo, null, ['nombre' => $datos['nombre'], 'rol' => $datos['rol']]);
        $this->ok(['id' => $nuevo], 'Usuario creado');
    }

    public function borrar(): void
    {
        $this->exigir('usuarios');
        $id = Request::int('id');
        if ($id === Auth::id()) $this->fail('No puedes eliminar tu propio usuario.');
        $ms = $this->usr();
        $u = $ms->findOrFail($id);
        if ($u['rol'] === 'dueno' && $ms->count("rol='dueno'") <= 1) {
            $this->fail('Debe quedar al menos un dueño en el restaurante.');
        }
        $ms->deleteById($id);
        Audit::log('usuario.borrar', 'users', $id, ['nombre' => $u['nombre'], 'rol' => $u['rol']]);
        $this->ok([], 'Usuario eliminado');
    }

    // ---------------------------------------------------------------- perfil
    public function perfil(): void
    {
        $this->panel('panel/perfil', ['yo' => Auth::user()]);
    }

    public function perfilGuardar(): void
    {
        $id = Auth::id();
        $datos = [
            'nombre'     => Request::str('nombre', '', 120),
            'telefono'   => Request::str('telefono', '', 30),
            'tema_panel' => Request::enum('tema_panel', ['claro', 'oscuro', 'auto'], 'auto'),
        ];
        $email = Request::email('email');
        if ($email !== '' && (new User())->disponible('email', $email, $id)) {
            $datos['email'] = $email;
        }
        if ($datos['nombre'] === '') {
            flash('error', 'Escribe tu nombre.');
            redirect('panel/perfil');
        }

        $actual = (string)Request::input('password_actual', '');
        $nueva  = (string)Request::input('password', '');
        if ($nueva !== '') {
            $yo = Auth::user();
            if (!password_verify($actual, (string)$yo['password_hash'])) {
                flash('error', 'Tu contraseña actual no es correcta.');
                redirect('panel/perfil');
            }
            $v = Validator::make(['password' => $nueva, 'password2' => (string)Request::input('password2', '')])
                ->password('password')->iguales('password', 'password2', 'Las contraseñas');
            if ($v->falla()) {
                flash('error', $v->primerError());
                redirect('panel/perfil');
            }
            $datos['password_hash'] = Security::hashPassword($nueva);
            DB::delete('remember_tokens', 'user_id = :u', ['u' => $id]);
        }

        $foto = Request::file('avatar');
        if ($foto) {
            [$ok, $res] = Image::upload($foto, 'avatares/' . $this->rid, 400, 400, 85);
            if ($ok) $datos['avatar'] = $res;
        }

        DB::update('users', $datos, 'id = :i', ['i' => $id]);
        Auth::refresh();
        Audit::log('perfil.editar', 'users', $id);
        flash('exito', 'Tu perfil quedó actualizado.');
        redirect('panel/perfil');
    }
}
