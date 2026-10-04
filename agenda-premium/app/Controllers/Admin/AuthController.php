<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Totp;
use App\Core\Validator;

/** Acceso al panel: inicio de sesión, segundo factor, recuperación de contraseña e invitaciones. */
final class AuthController extends Controller
{
    private const RESET_TTL = 3600;

    public function loginForm(Request $req, array $p): Response
    {
        if (Auth::check()) {
            return $this->redirect(A1Support::safeNext((string) $req->get('next', '')));
        }
        $notice = $req->get('salio') !== null ? 'Cerraste sesión correctamente. ¡Hasta pronto!' : '';
        return $this->view('admin/auth/login', [
            'title' => 'Iniciar sesión',
            'next' => A1Support::safeNext((string) $req->get('next', ''), ''),
            'email' => '',
            'error' => '',
            'notice' => $notice,
        ], 'layouts/auth');
    }

    public function login(Request $req, array $p): Response
    {
        $email = strtolower($req->str('email', 190));
        $password = (string) ($req->post['password'] ?? '');
        $next = A1Support::safeNext($req->str('next', 300), '');
        $fail = function (string $msg, int $status) use ($email, $next): Response {
            $r = $this->view('admin/auth/login', ['title' => 'Iniciar sesión', 'next' => $next, 'email' => $email, 'error' => $msg, 'notice' => ''], 'layouts/auth');
            $r->status = $status;
            return $r;
        };
        if ($email === '' || $password === '' || strlen($password) > 1024) {
            return $fail('Escribe tu correo y tu contraseña para continuar.', 422);
        }
        $ip = $req->ip();
        if (Auth::isLockedOut($email, $ip)) {
            return $fail('Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.', 429);
        }
        $res = Auth::attempt($email, $password, $ip);
        if (!$res['ok']) {
            return $fail((string) ($res['error'] ?? 'Correo o contraseña incorrectos.'), 401);
        }
        if (!empty($res['totp'])) {
            Session::set('login_next', $next);
            return $this->redirect('/admin/2fa');
        }
        Auth::audit('login', 'user', Auth::user()['id'] ?? null, 'Inicio de sesión');
        return $this->redirect($next !== '' ? $next : '/admin');
    }

    public function twoFactorForm(Request $req, array $p): Response
    {
        if (!(int) Session::get('pending_uid', 0)) {
            return $this->redirect('/admin/login');
        }
        return $this->view('admin/auth/2fa', ['title' => 'Verificación en dos pasos', 'error' => ''], 'layouts/auth');
    }

    /** Segundo paso. El secreto puede estar cifrado (Crypto::encrypt), por eso se verifica aquí y no con Auth::completeTotp. */
    public function twoFactor(Request $req, array $p): Response
    {
        $uid = (int) Session::get('pending_uid', 0);
        $at = (int) Session::get('pending_at', 0);
        $u = $uid > 0 ? Db::one('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]) : null;
        if (!$u || Clock::now() - $at > 600) {
            Session::forget('pending_uid');
            $this->flash('warn', 'La verificación caducó. Inicia sesión de nuevo.');
            return $this->redirect('/admin/login');
        }
        $ip = $req->ip();
        $again = function (string $msg, int $status) : Response {
            $r = $this->view('admin/auth/2fa', ['title' => 'Verificación en dos pasos', 'error' => $msg], 'layouts/auth');
            $r->status = $status;
            return $r;
        };
        if (Auth::isLockedOut((string) $u['email'], $ip)) {
            return $again('Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.', 429);
        }
        $secret = self::readSecret((string) $u['totp_secret']);
        $code = (string) ($req->post['code'] ?? '');
        $ok = $secret !== '' && Totp::verify($secret, $code);
        Db::insert('login_attempts', ['email' => substr((string) $u['email'], 0, 190), 'ip' => substr($ip, 0, 45), 'success' => $ok ? 1 : 0, 'created_at' => Clock::utc()]);
        if (!$ok) {
            return $again('El código no es correcto. Revisa tu aplicación de autenticación e inténtalo otra vez.', 401);
        }
        $next = (string) Session::get('login_next', '');
        Auth::login($uid);
        Session::forget('login_next');
        Auth::audit('login', 'user', $uid, 'Inicio de sesión con verificación en dos pasos');
        return $this->redirect($next !== '' ? A1Support::safeNext($next) : '/admin');
    }

    public static function readSecret(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        if (strncmp($stored, 'v1:', 3) === 0) {
            return (string) Crypto::decrypt($stored);
        }
        return $stored;
    }

    public function logout(Request $req, array $p): Response
    {
        Auth::audit('logout', 'user', Auth::user()['id'] ?? null, 'Cierre de sesión');
        Auth::logout();
        return $this->redirect('/admin/login', ['salio' => 1]);
    }

    // ---------------------------------------------------------------- Recuperación

    public function forgotForm(Request $req, array $p): Response
    {
        return $this->view('admin/auth/forgot', ['title' => 'Recuperar contraseña', 'sent' => false, 'email' => '', 'error' => ''], 'layouts/auth');
    }

    public function forgot(Request $req, array $p): Response
    {
        $email = strtolower($req->str('email', 190));
        if (!Validator::email($email)) {
            $r = $this->view('admin/auth/forgot', ['title' => 'Recuperar contraseña', 'sent' => false, 'email' => $email, 'error' => 'Escribe un correo válido.'], 'layouts/auth');
            $r->status = 422;
            return $r;
        }
        // La respuesta es idéntica exista o no el correo; los límites solo evitan el abuso.
        $allowed = RateLimiter::hit('forgot-ip:' . $req->ip(), 8, 900) && RateLimiter::hit('forgot-mail:' . hash('sha256', $email), 3, 3600);
        if ($allowed) {
            $u = Db::one('SELECT id, name, email FROM users WHERE email = ? AND active = 1', [$email]);
            if ($u) {
                $this->sendReset($u);
            }
        }
        return $this->view('admin/auth/forgot', ['title' => 'Revisa tu correo', 'sent' => true, 'email' => $email, 'error' => ''], 'layouts/auth');
    }

    private function sendReset(array $u): void
    {
        $token = Str::token(16); // 128 bits
        Db::update('users', [
            'reset_token_hash' => hash('sha256', $token),
            'reset_expires_at' => Clock::utc(Clock::now() + self::RESET_TTL),
            'updated_at' => Clock::utc(),
        ], 'id = ?', [$u['id']]);
        $brand = (string) Settings::get('business_name', 'Agenda Premium');
        $link = abs_url('/admin/restablecer/' . $token);
        $subject = 'Restablece tu contraseña · ' . $brand;
        $name = (string) $u['name'];
        $text = "Hola {$name},\n\nRecibimos una solicitud para restablecer tu contraseña del panel de {$brand}. Usa este enlace dentro de la próxima hora (solo funciona una vez):\n\n{$link}\n\nSi no lo pediste tú, ignora este mensaje: tu contraseña actual sigue siendo la misma.";
        try {
            if (!class_exists('App\\Services\\Mailer')) {
                Logger::error('No se pudo enviar el enlace de recuperación: el servicio de correo no está disponible.');
                return;
            }
            $body = '<p>Hola ' . e($name) . ',</p><p>Recibimos una solicitud para restablecer tu contraseña del panel de ' . e($brand) . '. El enlace funciona una sola vez y caduca en 1 hora.</p>'
                . '<p><a href="' . e($link) . '">Crear una contraseña nueva</a></p>'
                . '<p>Si no lo pediste tú, ignora este mensaje: tu contraseña actual sigue siendo la misma.</p>';
            $html = \App\Services\Mailer::layout('Restablece tu contraseña', $body);
            $r = \App\Services\Mailer::sendNow((string) $u['email'], $name, $subject, $html, $text);
            if (empty($r['ok'])) {
                \App\Services\Mailer::queue((string) $u['email'], $name, $subject, $html, $text);
            }
        } catch (\Throwable $e) {
            Logger::error('Falló el envío del enlace de recuperación', $e);
        }
    }

    /** Usuario con token de recuperación vigente (o null). */
    private function userByToken(string $col, string $expCol, string $token): ?array
    {
        $col = $col === 'invite_token_hash' ? 'invite_token_hash' : 'reset_token_hash';
        $expCol = $expCol === 'invite_expires_at' ? 'invite_expires_at' : 'reset_expires_at';
        return Db::one("SELECT * FROM users WHERE {$col} = ? AND {$expCol} IS NOT NULL AND {$expCol} > ?", [hash('sha256', $token), Clock::utc()]);
    }

    public function resetForm(Request $req, array $p): Response
    {
        $u = $this->userByToken('reset_token_hash', 'reset_expires_at', (string) $p['token']);
        if (!$u || (int) $u['active'] !== 1) {
            return $this->invalid('recuperación');
        }
        return $this->view('admin/auth/reset', ['title' => 'Nueva contraseña', 'mode' => 'reset', 'person' => $u, 'error' => ''], 'layouts/auth');
    }

    public function reset(Request $req, array $p): Response
    {
        $u = $this->userByToken('reset_token_hash', 'reset_expires_at', (string) $p['token']);
        if (!$u || (int) $u['active'] !== 1) {
            return $this->invalid('recuperación');
        }
        $err = $this->checkNewPassword($req);
        if ($err !== null) {
            $r = $this->view('admin/auth/reset', ['title' => 'Nueva contraseña', 'mode' => 'reset', 'person' => $u, 'error' => $err], 'layouts/auth');
            $r->status = 422;
            return $r;
        }
        Db::update('users', [
            'password_hash' => Crypto::hashPassword((string) $req->post['password']),
            'reset_token_hash' => null,
            'reset_expires_at' => null,
            'updated_at' => Clock::utc(),
        ], 'id = ?', [$u['id']]);
        Db::delete('login_attempts', 'email = ? AND success = 0', [$u['email']]);
        Auth::audit('password_reset', 'user', $u['id'], 'Contraseña restablecida por enlace de recuperación');
        $this->flash('success', 'Listo, tu contraseña se actualizó. Ya puedes iniciar sesión.');
        return $this->redirect('/admin/login');
    }

    public function inviteForm(Request $req, array $p): Response
    {
        $u = $this->userByToken('invite_token_hash', 'invite_expires_at', (string) $p['token']);
        if (!$u) {
            return $this->invalid('invitación');
        }
        return $this->view('admin/auth/reset', ['title' => 'Aceptar invitación', 'mode' => 'invite', 'person' => $u, 'error' => ''], 'layouts/auth');
    }

    public function invite(Request $req, array $p): Response
    {
        $u = $this->userByToken('invite_token_hash', 'invite_expires_at', (string) $p['token']);
        if (!$u) {
            return $this->invalid('invitación');
        }
        $err = $this->checkNewPassword($req);
        if ($err !== null) {
            $r = $this->view('admin/auth/reset', ['title' => 'Aceptar invitación', 'mode' => 'invite', 'person' => $u, 'error' => $err], 'layouts/auth');
            $r->status = 422;
            return $r;
        }
        Db::update('users', [
            'password_hash' => Crypto::hashPassword((string) $req->post['password']),
            'invite_token_hash' => null,
            'invite_expires_at' => null,
            'active' => 1,
            'updated_at' => Clock::utc(),
        ], 'id = ?', [$u['id']]);
        Auth::audit('invite_accepted', 'user', $u['id'], 'Invitación aceptada: ' . $u['email']);
        $this->flash('success', '¡Bienvenido a bordo! Tu cuenta quedó lista; inicia sesión con tu correo y tu nueva contraseña.');
        return $this->redirect('/admin/login');
    }

    private function checkNewPassword(Request $req): ?string
    {
        $pw = (string) ($req->post['password'] ?? '');
        $pw2 = (string) ($req->post['password2'] ?? '');
        if (strlen($pw) > 200) {
            return 'La contraseña es demasiado larga.';
        }
        if ($pw !== $pw2) {
            return 'Las dos contraseñas no coinciden.';
        }
        return Crypto::passwordStrongEnough($pw);
    }

    private function invalid(string $what): Response
    {
        $r = $this->view('admin/auth/invalid', ['title' => 'Enlace no válido', 'what' => $what], 'layouts/auth');
        $r->status = 410;
        return $r;
    }
}
