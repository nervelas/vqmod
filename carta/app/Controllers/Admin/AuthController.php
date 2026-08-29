<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\Controller;
use MenuGold\Core\Csrf;
use MenuGold\Core\DB;
use MenuGold\Core\RateLimiter;
use MenuGold\Core\Security;
use MenuGold\Core\Session;

class AuthController extends Controller
{
    public function login()
    {
        if (Auth::check()) {
            return $this->redirect(Auth::homeFor(Auth::role()));
        }
        if (!$this->request->isPost()) {
            return $this->view('admin/login', array('error' => ''));
        }

        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $username = $this->request->str('username', '');
        $password = (string)$this->request->input('password', '');

        // Doble límite: por dirección IP y por nombre de usuario.
        if (!RateLimiter::attempt('login:ip', 10, 900) || !RateLimiter::attempt('login:user', 6, 900, strtolower($username))) {
            $wait = max(RateLimiter::retryAfter('login:ip', 900), RateLimiter::retryAfter('login:user', 900, strtolower($username)));
            return $this->view('admin/login', array('error' => 'Demasiados intentos. Espera ' . ceil($wait / 60) . ' minutos.'), 429);
        }

        $user = DB::first(
            'SELECT * FROM mg_users WHERE (username = :u OR email = :e) AND is_active = 1 LIMIT 1',
            array('u' => $username, 'e' => $username)
        );

        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            Audit::log('login_failed', 'user', $user ? (int)$user['id'] : 0, array('username' => $username));
            // Mensaje genérico: no revela si el usuario existe.
            return $this->view('admin/login', array('error' => 'Usuario o contraseña incorrectos.'), 401);
        }

        if (Security::needsRehash($user['password_hash'])) {
            DB::update('mg_users', array('password_hash' => Security::hashPassword($password)), 'id = :id', array('id' => (int)$user['id']));
        }

        Auth::login($user);
        RateLimiter::clear('login:ip');
        RateLimiter::clear('login:user', strtolower($username));
        Audit::log('login', 'user', (int)$user['id']);

        $after = Session::pull('after_login', '');
        if ($after !== '' && strpos($after, '/panel') === 0) {
            return $this->redirect($after);
        }
        return $this->redirect(Auth::homeFor($user['role']));
    }

    /** Acceso rápido con PIN para la tablet del salón. */
    public function pin()
    {
        if (!$this->request->isPost()) {
            return $this->view('admin/pin', array('error' => ''));
        }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        if (!RateLimiter::attempt('pin', 8, 600)) {
            return $this->view('admin/pin', array('error' => 'Demasiados intentos. Espera unos minutos.'), 429);
        }
        $pin = preg_replace('/\D+/', '', (string)$this->request->input('pin', ''));
        if (strlen($pin) < 4) {
            return $this->view('admin/pin', array('error' => 'El PIN debe tener al menos 4 dígitos.'));
        }

        $candidates = DB::all("SELECT * FROM mg_users WHERE is_active = 1 AND pin <> '' AND role IN ('waiter','kitchen','manager')");
        foreach ($candidates as $u) {
            if (Security::verifyPassword($pin, $u['pin'])) {
                Auth::login($u);
                Audit::log('login_pin', 'user', (int)$u['id']);
                return $this->redirect(Auth::homeFor($u['role']));
            }
        }
        return $this->view('admin/pin', array('error' => 'PIN no reconocido.'), 401);
    }

    public function logout()
    {
        if (Auth::check()) { Audit::log('logout', 'user', Auth::id()); }
        Auth::logout();
        Csrf::rotate();
        return $this->redirect('/panel/entrar');
    }
}
