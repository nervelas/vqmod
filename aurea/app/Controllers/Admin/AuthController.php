<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Audit;
use Aurea\Core\Auth;
use Aurea\Core\Controller;
use Aurea\Core\Db;
use Aurea\Core\Mailer;
use Aurea\Core\RateLimit;
use Aurea\Core\Response;
use Aurea\Core\Settings;
use Aurea\Core\Totp;
use Aurea\Core\Util;
use Aurea\Core\View;

final class AuthController extends Controller
{
    private function page(string $tpl, array $data = [], int $status = 200): Response
    {
        return Response::html(View::page('auth', 'admin/' . $tpl, $data), $status);
    }

    public function loginForm(): Response
    {
        if (Auth::user()) { return $this->redirect('/admin'); }
        return $this->page('login', ['error' => '', 'email' => '']);
    }

    public function login(): Response
    {
        $email = $this->req->str('email');
        $pw = (string)($this->req->post['password'] ?? '');
        $r = Auth::attempt($this->req, $email, $pw);
        if ($r === 'ok') { return $this->redirect('/admin'); }
        if ($r === '2fa') { return $this->redirect('/admin/2fa'); }
        $msg = $r === 'locked' ? 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.' : 'Correo o contraseña incorrectos.';
        return $this->page('login', ['error' => $msg, 'email' => $email], $r === 'locked' ? 429 : 401);
    }

    public function twoFactorForm(): Response
    {
        if (empty($_SESSION['pending_2fa'])) { return $this->redirect('/admin/login'); }
        return $this->page('twofa', ['error' => '']);
    }

    public function twoFactor(): Response
    {
        if (empty($_SESSION['pending_2fa'])) { return $this->redirect('/admin/login'); }
        if (Auth::verify2fa($this->req, $this->req->str('code'))) { return $this->redirect('/admin'); }
        return $this->page('twofa', ['error' => 'Código incorrecto o vencido.'], 401);
    }

    public function logout(): Response
    {
        Auth::logout();
        return $this->redirect('/admin/login');
    }

    public function forgotForm(): Response
    {
        return $this->page('forgot', ['sent' => false]);
    }

    public function forgot(): Response
    {
        $email = mb_strtolower($this->req->str('email'));
        // Respuesta idéntica exista o no el usuario (no revela cuentas)
        if (RateLimit::hit('reset', $this->req->ip(), 5, 3600) && Util::isEmail($email)) {
            $u = Db::one('SELECT id,name FROM users WHERE email=? AND active=1', [$email]);
            if ($u) {
                $tok = Util::token(32);
                Db::update('users', (int)$u['id'], ['reset_token' => hash('sha256', $tok), 'reset_expires' => date('Y-m-d H:i:s', time() + 3600)]);
                $link = abs_url('/admin/restablecer/' . substr($tok, 0, 32) . '?k=' . substr($tok, 32));
                Mailer::send($email, 'Restablecer tu contraseña', \Aurea\Services\NotificationService::htmlWrap('Restablecer contraseña',
                    "Hola " . $u['name'] . ",\n\nRecibimos una solicitud para restablecer tu contraseña. Este enlace es de un solo uso y vence en 1 hora:\n" . $link . "\n\nSi no fuiste tú, ignora este mensaje."));
                Audit::log('reset_requested', 'user', (int)$u['id'], '', $u);
            }
        }
        return $this->page('forgot', ['sent' => true]);
    }

    private function tokenUser(): ?array
    {
        $full = (string)($this->req->params['token'] ?? '') . $this->req->str('k');
        if (!preg_match('/^[a-f0-9]{64}$/', $full)) { return null; }
        $u = Db::one('SELECT * FROM users WHERE reset_token=? AND reset_expires>? AND active=1', [hash('sha256', $full), date('Y-m-d H:i:s')]);
        return $u ?: null;
    }

    public function resetForm(): Response
    {
        $u = $this->tokenUser();
        return $this->page('reset', ['valid' => (bool)$u, 'error' => '', 'k' => $this->req->str('k')]);
    }

    public function reset(): Response
    {
        $u = $this->tokenUser();
        if (!$u) { return $this->page('reset', ['valid' => false, 'error' => '', 'k' => ''], 400); }
        $pw = (string)($this->req->post['password'] ?? '');
        $e = Auth::strongPassword($pw);
        if ($e || $pw !== (string)($this->req->post['password2'] ?? '')) {
            return $this->page('reset', ['valid' => true, 'error' => $e ?: 'Las contraseñas no coinciden.', 'k' => $this->req->str('k')], 422);
        }
        Db::update('users', (int)$u['id'], ['password_hash' => Auth::hash($pw), 'reset_token' => null, 'reset_expires' => null]);
        Audit::log('password_reset', 'user', (int)$u['id'], '', $u);
        $_SESSION['flash'][] = ['ok', 'Contraseña actualizada. Ya puedes iniciar sesión.'];
        return $this->redirect('/admin/login');
    }

    /* ---- Perfil (requiere sesión) ---- */
    private function requireUser(): array
    {
        $u = Auth::user();
        if (!$u) { throw new \Aurea\Core\HttpException(Response::redirect(url('/admin/login'))); }
        return $u;
    }

    public function profile(): Response
    {
        $u = $this->requireUser();
        $secret = null; $uri = null;
        if (!(int)$u['totp_enabled'] && Auth::role() === 'admin') {
            if (empty($_SESSION['totp_setup'])) { $_SESSION['totp_setup'] = Totp::secret(); }
            $secret = (string)$_SESSION['totp_setup'];
            $uri = Totp::uri($secret, (string)$u['email'], (string)Settings::get('business_name', 'AUREA'));
        }
        return Response::html(View::page('admin', 'admin/profile', ['user' => $u, 'active' => 'perfil', 'title' => 'Mi perfil', 'secret' => $secret, 'uri' => $uri, 'scripts' => ['vendor/qrcode.js', 'js/qr.js']]));
    }

    public function changePassword(): Response
    {
        $u = $this->requireUser();
        $row = Db::one('SELECT password_hash FROM users WHERE id=?', [$u['id']]);
        $new = (string)($this->req->post['new'] ?? '');
        if (!password_verify((string)($this->req->post['current'] ?? ''), (string)$row['password_hash'])) {
            $_SESSION['flash'][] = ['err', 'La contraseña actual no es correcta.'];
        } elseif ($e = Auth::strongPassword($new)) {
            $_SESSION['flash'][] = ['err', $e];
        } elseif ($new !== (string)($this->req->post['new2'] ?? '')) {
            $_SESSION['flash'][] = ['err', 'Las contraseñas nuevas no coinciden.'];
        } else {
            Db::update('users', (int)$u['id'], ['password_hash' => Auth::hash($new)]);
            Audit::log('password_changed', 'user', (int)$u['id']);
            $_SESSION['flash'][] = ['ok', 'Contraseña actualizada.'];
        }
        return $this->redirect('/admin/perfil');
    }

    public function toggle2fa(): Response
    {
        $u = $this->requireUser();
        if (Auth::role() !== 'admin') { return $this->redirect('/admin/perfil'); }
        $row = Db::one('SELECT * FROM users WHERE id=?', [$u['id']]);
        if ($this->req->str('action') === 'enable') {
            $secret = (string)($_SESSION['totp_setup'] ?? '');
            if ($secret !== '' && Totp::verify($secret, $this->req->str('code'))) {
                Db::update('users', (int)$u['id'], ['totp_secret' => $secret, 'totp_enabled' => 1]);
                unset($_SESSION['totp_setup']);
                Audit::log('2fa_enabled', 'user', (int)$u['id']);
                $_SESSION['flash'][] = ['ok', 'Verificación en dos pasos activada.'];
            } else { $_SESSION['flash'][] = ['err', 'El código no es válido. Revisa la hora de tu teléfono e intenta de nuevo.']; }
        } else {
            if (password_verify((string)($this->req->post['current'] ?? ''), (string)$row['password_hash']) && Totp::verify((string)$row['totp_secret'], $this->req->str('code'))) {
                Db::update('users', (int)$u['id'], ['totp_secret' => null, 'totp_enabled' => 0]);
                Audit::log('2fa_disabled', 'user', (int)$u['id']);
                $_SESSION['flash'][] = ['ok', 'Verificación en dos pasos desactivada.'];
            } else { $_SESSION['flash'][] = ['err', 'Contraseña o código incorrectos.']; }
        }
        return $this->redirect('/admin/perfil');
    }
}
