<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Totp;
use App\Core\Validator;

/** Perfil propio: datos, contraseña, verificación en dos pasos y tema. */
final class ProfileController extends Controller
{
    public function index(Request $req, array $p): Response
    {
        return $this->page('', '');
    }

    private function page(string $error, string $tab, int $status = 200): Response
    {
        $u = Auth::user();
        $setup = (string) Session::get('totp_setup', '');
        $r = $this->view('admin/profile/index', [
            'title' => 'Mi perfil',
            'u' => $u,
            'host' => Db::one('SELECT name, title FROM hosts WHERE user_id = ?', [$u['id']]),
            'roles' => ['admin' => 'Administración', 'host' => 'Anfitrión', 'reception' => 'Recepción'],
            'setup' => $setup,
            'otpauth' => $setup !== '' ? Totp::uri($setup, (string) $u['email'], (string) Settings::get('business_name', 'Agenda Premium')) : '',
            'error' => $error,
            'tab' => $tab,
            'scripts' => ['vendor/qrcode-generator/qrcode.js', 'js/admin-profile.js'],
        ], 'layouts/admin');
        $r->status = $status;
        return $r;
    }

    public function update(Request $req, array $p): Response
    {
        $u = Auth::user();
        $name = $req->str('name', 120);
        $email = strtolower($req->str('email', 190));
        if ($name === '') {
            return $this->page('Escribe tu nombre.', 'datos', 422);
        }
        if (!Validator::email($email)) {
            return $this->page('El correo no parece válido.', 'datos', 422);
        }
        $upd = ['name' => $name, 'updated_at' => Clock::utc()];
        if ($email !== $u['email']) {
            if (!password_verify((string) ($req->post['current'] ?? ''), (string) $u['password_hash'])) {
                return $this->page('Para cambiar tu correo escribe tu contraseña actual.', 'datos', 422);
            }
            if (Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
                return $this->page('Ya hay otra cuenta con ese correo.', 'datos', 422);
            }
            $upd['email'] = $email;
        }
        Db::update('users', $upd, 'id = ?', [$u['id']]);
        Auth::audit('profile_update', 'user', (int) $u['id'], 'Datos del perfil actualizados');
        $this->flash('success', 'Tus datos se guardaron.');
        return $this->redirect('/admin/perfil');
    }

    public function password(Request $req, array $p): Response
    {
        $u = Auth::user();
        if (!password_verify((string) ($req->post['current'] ?? ''), (string) $u['password_hash'])) {
            return $this->page('Tu contraseña actual no es correcta.', 'clave', 422);
        }
        $new = (string) ($req->post['password'] ?? '');
        if ($new !== (string) ($req->post['password2'] ?? '')) {
            return $this->page('Las dos contraseñas nuevas no coinciden.', 'clave', 422);
        }
        if (strlen($new) > 200) {
            return $this->page('La contraseña es demasiado larga.', 'clave', 422);
        }
        if ($err = Crypto::passwordStrongEnough($new)) {
            return $this->page($err, 'clave', 422);
        }
        Db::update('users', ['password_hash' => Crypto::hashPassword($new), 'updated_at' => Clock::utc()], 'id = ?', [$u['id']]);
        Session::regenerate();
        Auth::audit('password_change', 'user', (int) $u['id'], 'Contraseña cambiada');
        $this->flash('success', 'Tu contraseña se cambió correctamente.');
        return $this->redirect('/admin/perfil');
    }

    public function twoFactorStart(Request $req, array $p): Response
    {
        if ((int) Auth::user()['totp_enabled'] === 1) {
            return $this->redirect('/admin/perfil');
        }
        Session::set('totp_setup', Totp::newSecret());
        return $this->redirect('/admin/perfil', ['paso' => '2fa']);
    }

    public function twoFactorEnable(Request $req, array $p): Response
    {
        $secret = (string) Session::get('totp_setup', '');
        if ($secret === '') {
            return $this->redirect('/admin/perfil');
        }
        if (!Totp::verify($secret, (string) ($req->post['code'] ?? ''))) {
            return $this->page('El código no coincide. Revisa que tu aplicación muestre el de esta cuenta e inténtalo de nuevo.', '2fa', 422);
        }
        $u = Auth::user();
        Db::update('users', ['totp_secret' => Crypto::encrypt($secret), 'totp_enabled' => 1, 'updated_at' => Clock::utc()], 'id = ?', [$u['id']]);
        Session::forget('totp_setup');
        Auth::audit('2fa_enable', 'user', (int) $u['id'], 'Verificación en dos pasos activada');
        $this->flash('success', 'Verificación en dos pasos activada. Desde ahora te pediremos un código al iniciar sesión.');
        return $this->redirect('/admin/perfil');
    }

    public function twoFactorDisable(Request $req, array $p): Response
    {
        $u = Auth::user();
        if (!password_verify((string) ($req->post['current'] ?? ''), (string) $u['password_hash'])) {
            return $this->page('Tu contraseña no es correcta; la verificación sigue activa.', '2fa', 422);
        }
        Db::update('users', ['totp_secret' => null, 'totp_enabled' => 0, 'updated_at' => Clock::utc()], 'id = ?', [$u['id']]);
        Session::forget('totp_setup');
        Auth::audit('2fa_disable', 'user', (int) $u['id'], 'Verificación en dos pasos desactivada');
        $this->flash('success', 'La verificación en dos pasos se desactivó.');
        return $this->redirect('/admin/perfil');
    }

    public function theme(Request $req, array $p): Response
    {
        $t = $req->str('theme', 10);
        if (!in_array($t, ['dark', 'light'], true)) {
            return $this->json(['ok' => false, 'error' => 'Tema no válido.'], 422);
        }
        $u = Auth::user();
        Db::update('users', ['theme' => $t], 'id = ?', [$u['id']]);
        if ($req->wantsJson()) {
            return $this->json(['ok' => true, 'theme' => $t]);
        }
        return $this->redirect('/admin/perfil');
    }
}
