<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Mail;
use App\Core\Validator;
use App\Models\Usuario;

final class Usuarios extends Controller
{
    public function index(): string
    {
        $this->requireRol('superadmin');
        [$p, $pp, $off] = $this->pagina(30);
        $f = ['rol' => $this->req->input('rol', ''), 'q' => $this->req->input('q', '')];
        return $this->view('admin/usuarios', [
            'titulo'    => 'Usuarios y accesos',
            'usuarios'  => Usuario::listar($f, $pp, $off),
            'total'     => Usuario::contar($f),
            'pagina'    => $p,
            'porPagina' => $pp,
            'filtros'   => $f,
        ]);
    }

    public function guardar(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $id = $this->req->int('id');
        $v = Validator::make($this->req->all(), [
            'nombre'   => 'required|len:3,120',
            'email'    => 'required|email|max:160',
            'rol'      => 'required|in:superadmin,secretaria,docente,padre',
            'telefono' => 'nullable|max:40',
        ], ['nombre' => 'nombre', 'email' => 'correo', 'rol' => 'rol']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('configuracion/usuarios');
        }
        $email = mb_strtolower((string)$v->get('email'));
        if (!Usuario::emailDisponible($email, $id > 0 ? $id : null)) {
            $this->error('Ya existe otro usuario con ese correo.');
            return $this->redirect('configuracion/usuarios');
        }
        if ($id > 0) {
            $actual = Usuario::porId($id);
            if (!$actual) {
                throw new HttpException(404, 'El usuario no existe.');
            }
            if ((int)$actual['id'] === (int)Auth::id() && $v->get('rol') !== 'superadmin') {
                $this->error('No puede quitarse a si mismo el rol de administrador.');
                return $this->redirect('configuracion/usuarios');
            }
            Database::run(
                'UPDATE users SET nombre = :n, email = :e, rol = :r, telefono = :t WHERE id = :id',
                ['n' => $v->get('nombre'), 'e' => $email, 'r' => $v->get('rol'), 't' => $v->get('telefono'), 'id' => $id]
            );
            Audit::log('usuario.actualizar', 'users', $id, $email);
            $this->ok('Usuario actualizado.');
        } else {
            $password = Usuario::generarPassword();
            $id = Usuario::crear((string)$v->get('nombre'), $email, $password, (string)$v->get('rol'), (string)($v->get('telefono') ?? ''));
            Database::run('UPDATE users SET debe_cambiar = 1 WHERE id = :id', ['id' => $id]);
            Audit::log('usuario.crear', 'users', $id, $email);
            Mail::enviar($email, (string)$v->get('nombre'), 'Su acceso a ' . (string)\App\Core\Settings::get('colegio_nombre', 'EduPortal'),
                '<p>Se creo su cuenta con el rol <strong>' . e(rol_nombre((string)$v->get('rol'))) . '</strong>.</p>'
                . '<p>Usuario: <strong>' . e($email) . '</strong><br>'
                . 'Contrasena temporal: <strong>' . e($password) . '</strong></p>'
                . '<p><a href="' . e(url_absoluta('ingresar')) . '">Ingresar al portal</a></p>');
            $this->ok('Usuario creado. Contrasena temporal: ' . $password);
        }
        return $this->redirect('configuracion/usuarios');
    }

    public function estado(string $id): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $usuario = Usuario::porId((int)$id);
        if (!$usuario) {
            throw new HttpException(404, 'El usuario no existe.');
        }
        if ((int)$usuario['id'] === (int)Auth::id()) {
            $this->error('No puede desactivar su propio usuario.');
            return $this->redirect('configuracion/usuarios');
        }
        $nuevo = (int)$usuario['activo'] === 1 ? 0 : 1;
        Database::run('UPDATE users SET activo = :a WHERE id = :id', ['a' => $nuevo, 'id' => (int)$id]);
        if ($nuevo === 0) {
            Auth::logoutEverywhere((int)$id);
        }
        Audit::log('usuario.estado', 'users', (int)$id, $nuevo ? 'activado' : 'desactivado');
        $this->ok($nuevo ? 'Usuario activado.' : 'Usuario desactivado.');
        return $this->redirect('configuracion/usuarios');
    }

    public function restablecer(string $id): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $usuario = Usuario::porId((int)$id);
        if (!$usuario) {
            throw new HttpException(404, 'El usuario no existe.');
        }
        $password = Usuario::generarPassword();
        Database::run('UPDATE users SET password_hash = :h, debe_cambiar = 1 WHERE id = :id', [
            'h' => Auth::hash($password), 'id' => (int)$id,
        ]);
        Auth::logoutEverywhere((int)$id);
        Audit::log('usuario.restablecer', 'users', (int)$id);
        Mail::enviar((string)$usuario['email'], (string)$usuario['nombre'], 'Su contrasena fue restablecida',
            '<p>La administracion restablecio su contrasena.</p>'
            . '<p>Nueva contrasena temporal: <strong>' . e($password) . '</strong></p>'
            . '<p>Le recomendamos cambiarla al ingresar.</p>');
        $this->ok('Contrasena restablecida: ' . $password);
        return $this->redirect('configuracion/usuarios');
    }
}
