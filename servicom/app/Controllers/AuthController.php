<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Security;
use App\Models\Company;

final class AuthController extends Controller
{
    private const MAX_TRIES = 5;
    private const WINDOW    = 900; // 15 minutos

    public function login(array $params = []): void
    {
        if (Auth::check()) {
            redirect('/panel');
        }
        $error = '';
        if (Request::isPost()) {
            Csrf::verify();
            $ident = mb_strtolower(Request::str('identity'));
            $pass  = Request::raw('password');
            $ipKey = App::ip();

            if (!RateLimit::hit('login_ip', $ipKey, self::MAX_TRIES * 3, self::WINDOW)) {
                $error = 'Demasiados intentos desde esta conexión. Espere 15 minutos.';
            } elseif ($ident === '' || $pass === '') {
                $error = 'Ingrese su usuario y su contraseña.';
            } elseif (!RateLimit::hit('login_user', $ident, self::MAX_TRIES, self::WINDOW)) {
                $mins = (int) ceil(RateLimit::blockedFor('login_user', $ident) / 60);
                $error = "Cuenta bloqueada temporalmente. Intente de nuevo en {$mins} minuto(s).";
            } else {
                $u = DB::one(
                    'SELECT * FROM users WHERE (email = ? OR username = ?) AND status = "activo" LIMIT 1',
                    [$ident, $ident]
                );
                if ($u && Security::verifyPassword($pass, (string) $u['password'])) {
                    if (Security::needsRehash((string) $u['password'])) {
                        DB::update('users', ['password' => Security::hashPassword($pass)], 'id = :id', ['id' => (int) $u['id']]);
                    }
                    RateLimit::clear('login_user', $ident);
                    if ((int) $u['twofa_enabled'] === 1 && $u['role'] === 'admin') {
                        Auth::login($u, true);
                        $this->sendTwoFactor($u);
                        redirect('/verificar');
                    }
                    Auth::login($u);
                    Audit::log('login.ok', 'user', (int) $u['id']);
                    App::startSession();
                    $to = (string) ($_SESSION['intended'] ?? '');
                    unset($_SESSION['intended']);
                    if ($to !== '' && str_starts_with($to, '/panel')) {
                        redirect($to);
                    }
                    redirect('/panel');
                }
                $left = RateLimit::remaining('login_user', $ident, self::MAX_TRIES);
                $error = 'Usuario o contraseña incorrectos.' . ($left > 0 && $left < 3 ? " Le quedan {$left} intento(s)." : '');
                Audit::log('login.fallido', 'user', 0, ['identidad' => mb_substr($ident, 0, 60)]);
            }
        }
        $this->render($error);
    }

    private function render(string $error): void
    {
        $this->view('auth/login', [
            'title' => 'Iniciar sesión',
            'error' => $error,
        ], 'layout/auth');
    }

    public function twoFactor(array $params = []): void
    {
        App::startSession();
        $uid = (int) ($_SESSION['2fa_uid'] ?? 0);
        if ($uid <= 0 || (time() - (int) ($_SESSION['2fa_at'] ?? 0)) > 900) {
            unset($_SESSION['2fa_uid'], $_SESSION['2fa_at']);
            redirect('/entrar');
        }
        $error = '';
        if (Request::isPost()) {
            Csrf::verify();
            $code = preg_replace('/\D+/', '', Request::str('code')) ?: '';
            if (!RateLimit::hit('2fa', (string) $uid, 6, 900)) {
                $error = 'Demasiados intentos. Vuelva a iniciar sesión.';
            } else {
                $row = DB::one(
                    'SELECT * FROM two_factor_codes WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
                    [$uid]
                );
                if ($row && hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
                    DB::update('two_factor_codes', ['used_at' => nowSql()], 'id = :id', ['id' => (int) $row['id']]);
                    $u = DB::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$uid]);
                    if ($u) {
                        RateLimit::clear('2fa', (string) $uid);
                        Auth::login($u);
                        Audit::log('login.2fa', 'user', $uid);
                        redirect('/panel');
                    }
                }
                $error = 'El código no es válido o ya venció.';
            }
        }
        $this->view('auth/twofactor', ['title' => 'Verificación en dos pasos', 'error' => $error], 'layout/auth');
    }

    private function sendTwoFactor(array $u): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::insert('two_factor_codes', [
            'user_id'    => (int) $u['id'],
            'code_hash'  => hash('sha256', $code),
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
            'created_at' => nowSql(),
        ]);
        $company = Company::get();
        $body = '<p>Su código de verificación es:</p>'
              . '<p style="font:700 30px/1 Helvetica,Arial,sans-serif;letter-spacing:.22em;margin:18px 0">' . e($code) . '</p>'
              . '<p>Vence en 15 minutos. Si no fue usted quien inició sesión, cambie su contraseña.</p>';
        Mailer::send((string) $u['email'], 'Código de verificación', Mailer::template('Verificación en dos pasos', $body, $company), $company);
    }

    public function logout(array $params = []): void
    {
        $id = Auth::id();
        if ($id) {
            Audit::log('logout', 'user', $id);
        }
        Auth::logout();
        Flash::ok('Su sesión se cerró correctamente.');
        redirect('/entrar');
    }

    public function forgot(array $params = []): void
    {
        $sent = false;
        $error = '';
        if (Request::isPost()) {
            Csrf::verify();
            $email = Request::email('email');
            if ($email === '') {
                $error = 'Escriba un correo válido.';
            } elseif (!RateLimit::hit('reset', App::ip(), 5, 3600)) {
                $error = 'Demasiadas solicitudes. Intente más tarde.';
            } else {
                $u = DB::one('SELECT * FROM users WHERE email = ? AND status = "activo" LIMIT 1', [$email]);
                if ($u) {
                    DB::run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $u['id']]);
                    $token = Security::randomToken(32);
                    DB::insert('password_resets', [
                        'user_id'    => (int) $u['id'],
                        'token_hash' => hash('sha256', $token),
                        'expires_at' => date('Y-m-d H:i:s', time() + 1800),
                        'ip'         => App::ip(),
                        'created_at' => nowSql(),
                    ]);
                    $company = Company::get();
                    $link = absUrl('/restablecer/' . $token);
                    $body = '<p>Recibimos una solicitud para restablecer la contraseña de <strong>' . e($u['email']) . '</strong>.</p>'
                          . '<p>El enlace es de un solo uso y vence en 30 minutos.</p>';
                    Mailer::send($email, 'Restablecer su contraseña', Mailer::template('Restablecer contraseña', $body, $company, 'Crear nueva contraseña', $link), $company);
                    Audit::log('password.solicitud', 'user', (int) $u['id']);
                }
                // Respuesta idéntica exista o no la cuenta (no revela usuarios).
                $sent = true;
            }
        }
        $this->view('auth/forgot', ['title' => 'Recuperar contraseña', 'sent' => $sent, 'error' => $error], 'layout/auth');
    }

    public function reset(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        $row = DB::one(
            'SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
        if (!$row) {
            $this->view('auth/reset', ['title' => 'Enlace vencido', 'invalid' => true, 'error' => '', 'token' => ''], 'layout/auth');
            return;
        }
        $error = '';
        if (Request::isPost()) {
            Csrf::verify();
            $p1 = Request::raw('password');
            $p2 = Request::raw('password2');
            if ($p1 !== $p2) {
                $error = 'Las contraseñas no coinciden.';
            } elseif (!Security::passwordOk($p1)) {
                $error = 'Use al menos 8 caracteres combinando mayúsculas, minúsculas y números.';
            } else {
                DB::update('users', ['password' => Security::hashPassword($p1), 'updated_at' => nowSql()], 'id = :id', ['id' => (int) $row['user_id']]);
                DB::update('password_resets', ['used_at' => nowSql()], 'id = :id', ['id' => (int) $row['id']]);
                Audit::log('password.cambio', 'user', (int) $row['user_id']);
                Flash::ok('Su contraseña se actualizó. Ya puede iniciar sesión.');
                redirect('/entrar');
            }
        }
        $this->view('auth/reset', ['title' => 'Nueva contraseña', 'invalid' => false, 'error' => $error, 'token' => $token], 'layout/auth');
    }
}
