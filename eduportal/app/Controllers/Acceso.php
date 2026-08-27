<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Mail;
use App\Core\RateLimit;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\Usuario;

final class Acceso extends Controller
{
    public function formulario(): string
    {
        if (Auth::check()) {
            return $this->redirect($this->destinoPorRol());
        }
        $expirada = Session::get('_expired', false);
        Session::forget('_expired');
        return $this->view('auth/ingresar', ['expirada' => (bool)$expirada], 'layouts/auth');
    }

    public function entrar(): string
    {
        $this->requireCsrf();
        $email = mb_strtolower((string)$this->req->input('email', ''));
        $password = (string)$this->req->raw('password', '');
        $ip = $this->req->ip();

        $llaveIp = 'ip:' . $ip;
        $llaveUsuario = 'user:' . $email;
        if (RateLimit::blocked($llaveIp) || RateLimit::blocked($llaveUsuario)) {
            $this->error('Demasiados intentos fallidos. Intente de nuevo en ' . RateLimit::BLOQUEO_MIN . ' minutos.');
            Audit::log('login.bloqueado', 'users', null, $email);
            return $this->redirect('ingresar');
        }

        $v = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required|min:1'],
            ['email' => 'correo', 'password' => 'contrasena']
        );
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('ingresar');
        }

        $r = Auth::attempt($email, $password);
        if (!$r['ok']) {
            RateLimit::hit($llaveIp, false, $ip);
            RateLimit::hit($llaveUsuario, false, $ip);
            $restantes = min(RateLimit::remaining($llaveIp), RateLimit::remaining($llaveUsuario));
            $msg = $r['motivo'] === 'inactivo'
                ? 'Su usuario esta inactivo. Comuniquese con la administracion del colegio.'
                : 'Correo o contrasena incorrectos.' . ($restantes > 0 && $restantes <= 3 ? ' Le quedan ' . $restantes . ' intentos.' : '');
            $this->error($msg);
            Audit::log('login.fallido', 'users', null, $email);
            return $this->redirect('ingresar');
        }

        RateLimit::clear($llaveIp);
        RateLimit::clear($llaveUsuario);
        $user = $r['user'];

        if ((int)$user['twofa'] === 1 && $user['rol'] === 'superadmin') {
            return $this->iniciar2fa($user);
        }
        Auth::login($user);
        RateLimit::hit($llaveIp, true, $ip);
        $destino = Session::get('_intento');
        Session::forget('_intento');
        return $this->redirect($destino && is_string($destino) && $destino !== '/ingresar' ? $destino : $this->destinoPorRol());
    }

    private function iniciar2fa(array $user): string
    {
        $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Database::run(
            'UPDATE users SET twofa_codigo = :c, twofa_expira = :e WHERE id = :id',
            ['c' => password_hash($codigo, PASSWORD_DEFAULT), 'e' => date('Y-m-d H:i:s', time() + 600), 'id' => (int)$user['id']]
        );
        Session::set('_2fa_uid', (int)$user['id']);
        Mail::enviar(
            (string)$user['email'],
            (string)$user['nombre'],
            'Su codigo de verificacion',
            '<p>Su codigo de verificacion es:</p>'
            . '<p style="font-size:30px;letter-spacing:.2em;font-weight:700;color:#0B1F3A">' . $codigo . '</p>'
            . '<p>El codigo vence en 10 minutos. Si usted no intento ingresar, cambie su contrasena.</p>'
        );
        $this->aviso('Le enviamos un codigo de verificacion a su correo.');
        return $this->redirect('verificar');
    }

    public function verificar(): string
    {
        if (!Session::get('_2fa_uid')) {
            return $this->redirect('ingresar');
        }
        return $this->view('auth/verificar', [], 'layouts/auth');
    }

    public function confirmar2fa(): string
    {
        $this->requireCsrf();
        $uid = (int)Session::get('_2fa_uid', 0);
        if ($uid <= 0) {
            return $this->redirect('ingresar');
        }
        if (!RateLimit::throttleSession('2fa', 6, 600)) {
            Session::forget('_2fa_uid');
            $this->error('Demasiados intentos. Vuelva a iniciar sesion.');
            return $this->redirect('ingresar');
        }
        $codigo = preg_replace('/\D/', '', (string)$this->req->input('codigo', ''));
        $user = Usuario::porId($uid);
        if (!$user || !$user['twofa_codigo'] || !$user['twofa_expira'] || strtotime((string)$user['twofa_expira']) < time()) {
            Session::forget('_2fa_uid');
            $this->error('El codigo vencio. Inicie sesion nuevamente.');
            return $this->redirect('ingresar');
        }
        if (!password_verify((string)$codigo, (string)$user['twofa_codigo'])) {
            $this->error('El codigo no es correcto.');
            return $this->redirect('verificar');
        }
        Database::run('UPDATE users SET twofa_codigo = NULL, twofa_expira = NULL WHERE id = :id', ['id' => $uid]);
        Session::forget('_2fa_uid');
        Auth::login($user);
        return $this->redirect($this->destinoPorRol());
    }

    public function salir(): string
    {
        if ($this->req->method() === 'POST') {
            $this->requireCsrf();
        }
        Auth::logout();
        Session::start();
        Session::flash('ok', 'Su sesion se cerro correctamente.');
        return $this->redirect('ingresar');
    }

    public function recuperar(): string
    {
        return $this->view('auth/recuperar', [], 'layouts/auth');
    }

    public function enviarRecuperacion(): string
    {
        $this->requireCsrf();
        if (!RateLimit::throttleSession('recuperar', 3, 900)) {
            $this->error('Ha solicitado demasiados enlaces. Espere unos minutos.');
            return $this->redirect('recuperar');
        }
        $email = mb_strtolower((string)$this->req->input('email', ''));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? Usuario::porEmail($email) : null;
        if ($user && (int)$user['activo'] === 1) {
            $token = bin2hex(random_bytes(32));
            Database::run(
                'INSERT INTO password_resets (user_id, token_hash, expira_en) VALUES (:u, :t, :e)',
                ['u' => (int)$user['id'], 't' => hash('sha256', $token), 'e' => date('Y-m-d H:i:s', time() + 1800)]
            );
            $enlace = url_absoluta('restablecer/' . $token);
            Mail::enviar(
                (string)$user['email'],
                (string)$user['nombre'],
                'Restablecer su contrasena',
                '<p>Recibimos una solicitud para restablecer su contrasena.</p>'
                . '<p><a href="' . e($enlace) . '" style="display:inline-block;background:#0B1F3A;color:#fff;'
                . 'padding:12px 22px;border-radius:10px;text-decoration:none">Crear nueva contrasena</a></p>'
                . '<p>El enlace vence en 30 minutos y solo puede usarse una vez. '
                . 'Si usted no lo solicito, puede ignorar este mensaje.</p>'
                . '<p style="font-size:12px;color:#6B7280">' . e($enlace) . '</p>'
            );
            Audit::log('password.solicitud', 'users', (int)$user['id'], $email);
        }
        // Respuesta identica exista o no la cuenta (evita enumeracion de usuarios).
        $this->ok('Si el correo esta registrado, le enviamos un enlace para restablecer su contrasena.');
        return $this->redirect('ingresar');
    }

    public function restablecerForm(string $token): string
    {
        $fila = $this->tokenValido($token);
        if (!$fila) {
            $this->error('El enlace no es valido o ya vencio.');
            return $this->redirect('recuperar');
        }
        return $this->view('auth/restablecer', ['token' => $token], 'layouts/auth');
    }

    public function restablecer(): string
    {
        $this->requireCsrf();
        $token = (string)$this->req->input('token', '');
        $fila = $this->tokenValido($token);
        if (!$fila) {
            $this->error('El enlace no es valido o ya vencio.');
            return $this->redirect('recuperar');
        }
        $password = (string)$this->req->raw('password', '');
        $v = Validator::make(
            ['password' => $password, 'password_confirmacion' => (string)$this->req->raw('password_confirmacion', '')],
            ['password' => 'required|password|confirmed'],
            ['password' => 'contrasena']
        );
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('restablecer/' . $token);
        }
        Database::run(
            'UPDATE users SET password_hash = :h, debe_cambiar = 0 WHERE id = :id',
            ['h' => Auth::hash($password), 'id' => (int)$fila['user_id']]
        );
        Database::run('UPDATE password_resets SET usado = 1 WHERE id = :id', ['id' => (int)$fila['id']]);
        Auth::logoutEverywhere((int)$fila['user_id']);
        Audit::log('password.restablecer', 'users', (int)$fila['user_id']);
        $this->ok('Su contrasena fue actualizada. Ya puede iniciar sesion.');
        return $this->redirect('ingresar');
    }

    private function tokenValido(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Database::one(
            'SELECT * FROM password_resets WHERE token_hash = :t AND usado = 0 AND expira_en > :n LIMIT 1',
            ['t' => hash('sha256', $token), 'n' => date('Y-m-d H:i:s')]
        );
    }

    private function destinoPorRol(): string
    {
        return Auth::is('padre') ? 'portal' : 'panel';
    }
}
