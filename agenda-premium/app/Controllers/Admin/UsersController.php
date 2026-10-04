<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Usuarios y roles del panel (solo administración): invitaciones por enlace, cambio de rol y activación. */
final class UsersController extends A2Controller
{
    public const ROLES = ['admin' => 'Administración', 'host' => 'Anfitrión', 'reception' => 'Recepción'];
    private const INVITE_DAYS = 7;

    public function index(Request $req, array $p): Response
    {
        $users = Db::all(
            'SELECT u.id, u.name, u.email, u.role, u.active, u.last_login_at, u.invite_expires_at,
                    (u.password_hash IS NULL AND u.invite_token_hash IS NOT NULL) AS pending,
                    (SELECT h.name FROM hosts h WHERE h.user_id = u.id LIMIT 1) AS host_name
               FROM users u ORDER BY u.active DESC, u.name'
        );
        $now = Tz::fromTs(Clock::now());
        $me = (int) (Auth::user()['id'] ?? 0);
        foreach ($users as &$u) {
            $u['expired'] = (int) $u['pending'] === 1 && !empty($u['invite_expires_at']) && (string) $u['invite_expires_at'] < $now;
            $u['is_me'] = (int) $u['id'] === $me;
            $u['last_login'] = $u['last_login_at'] ? \App\Core\Fmt::dateTime((string) $u['last_login_at'], Settings::tz()) : '';
        }
        unset($u);
        $invite = Session::get('a2_invite');
        Session::forget('a2_invite');
        return $this->page('admin/users/index', [
            'users' => $users,
            'invite' => is_array($invite) ? $invite : null,
            'roles' => self::ROLES,
        ], '/admin/usuarios', 'Usuarios y roles');
    }

    public function invite(Request $req, array $p): Response
    {
        $name = $req->str('name', 120);
        $email = strtolower($req->str('email', 190));
        $role = $req->str('role', 12);
        if ($name === '') {
            return $this->done($req, 'error', 'Escribe el nombre de la persona que vas a invitar.', '/admin/usuarios');
        }
        if (!Validator::email($email)) {
            return $this->done($req, 'error', 'El correo no tiene un formato válido.', '/admin/usuarios');
        }
        if (!isset(self::ROLES[$role])) {
            return $this->done($req, 'error', 'Elige un rol para la persona invitada.', '/admin/usuarios');
        }
        $existing = Db::one('SELECT id, password_hash, invite_token_hash FROM users WHERE email = ?', [$email]);
        $now = Clock::utc();
        if ($existing && ($existing['password_hash'] !== null || $existing['invite_token_hash'] === null)) {
            return $this->done($req, 'error', 'Ya existe un usuario con ese correo.', '/admin/usuarios');
        }
        $token = Str::token(16);
        $row = [
            'name' => $name, 'role' => $role, 'active' => 0,
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => Tz::fromTs(Clock::now() + self::INVITE_DAYS * 86400),
            'updated_at' => $now,
        ];
        if ($existing) {
            Db::update('users', $row, 'id = ?', [(int) $existing['id']]);
            $uid = (int) $existing['id'];
        } else {
            $uid = Db::insert('users', array_merge($row, ['email' => $email, 'password_hash' => null, 'theme' => 'dark', 'created_at' => $now]));
        }
        Auth::audit('users.invite', 'user', $uid, $email . ' · ' . $role);
        $emailed = $this->sendInvite($email, $name, abs_url('/admin/invitacion/' . $token));
        Session::set('a2_invite', ['name' => $name, 'email' => $email, 'link' => abs_url('/admin/invitacion/' . $token), 'emailed' => $emailed, 'days' => self::INVITE_DAYS]);
        return $this->done($req, 'success', 'Invitación creada para ' . $name . '.', '/admin/usuarios');
    }

    public function changeRole(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $role = $req->str('role', 12);
        $u = Db::one('SELECT id, name, role, active FROM users WHERE id = ?', [$id]);
        if (!$u) {
            $this->abort(404);
        }
        if (!isset(self::ROLES[$role])) {
            return $this->done($req, 'error', 'Ese rol no existe.', '/admin/usuarios');
        }
        if ($u['role'] === 'admin' && $role !== 'admin' && (int) $u['active'] === 1 && $this->activeAdmins() <= 1) {
            return $this->done($req, 'error', 'No puedes quitar el rol a la última persona administradora. Nombra primero a otra.', '/admin/usuarios');
        }
        if ($u['role'] !== $role) {
            Db::update('users', ['role' => $role, 'updated_at' => Clock::utc()], 'id = ?', [$id]);
            Auth::audit('users.role', 'user', $id, $u['role'] . ' -> ' . $role);
        }
        return $this->done($req, 'success', 'Ahora ' . $u['name'] . ' tiene el rol de ' . mb_strtolower(self::ROLES[$role]) . '.', '/admin/usuarios');
    }

    public function toggleActive(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $u = Db::one('SELECT id, name, role, active, password_hash FROM users WHERE id = ?', [$id]);
        if (!$u) {
            $this->abort(404);
        }
        $new = (int) $u['active'] === 1 ? 0 : 1;
        if ($new === 0) {
            if ((int) (Auth::user()['id'] ?? 0) === $id) {
                return $this->done($req, 'error', 'No puedes desactivar tu propia cuenta.', '/admin/usuarios');
            }
            if ($u['role'] === 'admin' && $this->activeAdmins() <= 1) {
                return $this->done($req, 'error', 'No puedes desactivar a la última persona administradora.', '/admin/usuarios');
            }
        } elseif ($u['password_hash'] === null) {
            return $this->done($req, 'error', $u['name'] . ' todavía no acepta su invitación. Cuando cree su contraseña se activará sola.', '/admin/usuarios');
        }
        Db::update('users', ['active' => $new, 'updated_at' => Clock::utc()], 'id = ?', [$id]);
        Auth::audit($new ? 'users.activate' : 'users.deactivate', 'user', $id, (string) $u['name']);
        return $this->done($req, 'success', $new ? $u['name'] . ' volvió a tener acceso.' : 'Desactivamos a ' . $u['name'] . ': ya no puede entrar al panel.', '/admin/usuarios');
    }

    public function resetLink(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $u = Db::one('SELECT id, name, email, password_hash FROM users WHERE id = ?', [$id]);
        if (!$u) {
            $this->abort(404);
        }
        if ((int) (Auth::user()['id'] ?? 0) === $id) {
            return $this->done($req, 'error', 'Para cambiar tu propia contraseña usa tu perfil.', '/admin/usuarios');
        }
        $token = Str::token(16);
        Db::update('users', [
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => Tz::fromTs(Clock::now() + self::INVITE_DAYS * 86400),
            'updated_at' => Clock::utc(),
        ], 'id = ?', [$id]);
        Auth::audit('users.reset_link', 'user', $id, (string) $u['email']);
        $link = abs_url('/admin/invitacion/' . $token);
        $emailed = $this->sendInvite((string) $u['email'], (string) $u['name'], $link);
        Session::set('a2_invite', ['name' => $u['name'], 'email' => $u['email'], 'link' => $link, 'emailed' => $emailed, 'days' => self::INVITE_DAYS]);
        return $this->done($req, 'success', 'Generamos un enlace nuevo para ' . $u['name'] . '.', '/admin/usuarios');
    }

    private function activeAdmins(): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
    }

    /** Envía el enlace por correo si el sistema de correo está disponible; nunca interrumpe la invitación. */
    private function sendInvite(string $email, string $name, string $link): bool
    {
        if (!class_exists('App\\Services\\Mailer')) {
            return false;
        }
        try {
            $brand = (string) Settings::get('business_name', 'Agenda Premium');
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $html = '<p>Hola ' . $safeName . ',</p><p>Te invitamos a entrar al panel de ' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8')
                . '. Crea tu contraseña desde este enlace; vence en ' . self::INVITE_DAYS . ' días:</p><p><a href="' . $safeLink . '">' . $safeLink . '</a></p>'
                . '<p>Si no esperabas este mensaje, puedes ignorarlo.</p>';
            $mailer = 'App\\Services\\Mailer';
            if (method_exists($mailer, 'layout')) {
                $html = $mailer::layout('Te invitamos a ' . $brand, $html);
            }
            $mailer::queue($email, $name, 'Tu invitación a ' . $brand, $html, 'Hola ' . $name . ', crea tu contraseña aquí: ' . $link);
            return true;
        } catch (\Throwable $e) {
            Logger::error('No se pudo encolar el correo de invitación', $e);
            return false;
        }
    }
}
