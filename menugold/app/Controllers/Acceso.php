<?php
declare(strict_types=1);

namespace MenuGold\Controllers;

use MenuGold\Core\App;
use MenuGold\Core\Auth;
use MenuGold\Core\Controller;
use MenuGold\Core\Csrf;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Mailer;
use MenuGold\Core\RateLimit;
use MenuGold\Core\Request;
use MenuGold\Core\Security;
use MenuGold\Core\Session;
use MenuGold\Core\Setting;
use MenuGold\Core\Validator;
use MenuGold\Models\User;

/**
 * Ingreso, salida y recuperacion de contrasena.
 */
class Acceso extends Controller
{
    public function formulario(): void
    {
        Auth::tryRemember();
        if (Auth::check()) redirect(Auth::homeFor());
        $this->view('auth/ingresar', [
            'plataforma' => (string)Setting::plat('nombre_plataforma', 'MenúGold'),
        ], 'acceso');
    }

    public function entrar(): void
    {
        Csrf::enforce();
        $identidad = Request::str('usuario', '', 190);
        $clave     = (string)Request::input('password', '');
        $recordar  = Request::bool('recordar');

        if ($identidad === '' || $clave === '') {
            flash('error', 'Escribe tu usuario y tu contraseña.');
            $this->keepOld(['usuario' => $identidad]);
            redirect('ingresar');
        }

        [$ok, $mensaje] = Auth::attempt($identidad, $clave, $recordar);
        if (!$ok) {
            flash('error', $mensaje);
            $this->keepOld(['usuario' => $identidad]);
            redirect('ingresar');
        }

        flash('exito', $mensaje);
        $destino = (string)Session::pull('_intended', '');
        if ($destino !== '' && strpos($destino, App::baseUrl()) === 0) {
            redirect($destino);
        }
        redirect(Auth::homeFor());
    }

    public function salir(): void
    {
        if (Request::isPost()) Csrf::enforce();
        Auth::logout();
        flash('exito', 'Cerraste tu sesión correctamente.');
        redirect('ingresar');
    }

    // ---------------------------------------------------------------- olvido
    public function recuperarForm(): void
    {
        $this->view('auth/recuperar', ['captcha' => Security::captchaMake()], 'acceso');
    }

    public function recuperarEnviar(): void
    {
        Csrf::enforce();
        $email = Request::email('email');
        $rl = RateLimit::hit('recuperar:' . client_ip(), 5, 3600);
        if (!$rl['permitido']) {
            flash('error', 'Demasiados intentos. Intenta más tarde.');
            redirect('recuperar');
        }
        if (!Security::captchaCheck(Request::input('captcha'))) {
            flash('error', 'La suma de verificación no es correcta.');
            redirect('recuperar');
        }

        // Respuesta siempre igual: no revelamos si el correo existe
        $generico = 'Si ese correo está registrado, te enviamos un enlace para restablecer tu contraseña.';
        if ($email === '') {
            flash('aviso', $generico);
            redirect('ingresar');
        }

        $u = (new User())->byEmail($email);
        if ($u && (int)$u['activo'] === 1) {
            $token = Security::randomToken(32);
            DB::ejecutar('UPDATE password_resets SET usado = 1 WHERE user_id = :u AND usado = 0', ['u' => (int)$u['id']]);
            DB::insert('password_resets', [
                'user_id'    => (int)$u['id'],
                'token_hash' => hash('sha256', $token),
                'expira'     => date('Y-m-d H:i:s', time() + 1800),  // 30 minutos
                'ip'         => client_ip(),
                'creado'     => date('Y-m-d H:i:s'),
            ]);
            $enlace = url('restablecer/' . $token);
            $cuerpo = '<p>Hola ' . e((string)$u['nombre']) . ',</p>'
                . '<p>Recibimos una solicitud para restablecer tu contraseña. '
                . 'Este enlace funciona una sola vez y vence en 30 minutos.</p>'
                . Mailer::boton('Crear una nueva contraseña', $enlace)
                . '<p style="font-size:13px;color:#8a8578">Si no fuiste tú, puedes ignorar este mensaje: '
                . 'tu contraseña seguirá igual.</p>';
            Mailer::send($email, 'Restablece tu contraseña', $cuerpo,
                !empty($u['restaurant_id']) ? (int)$u['restaurant_id'] : null, (string)$u['nombre']);
        }

        flash('aviso', $generico);
        redirect('ingresar');
    }

    public function restablecerForm(array $p = []): void
    {
        $token = (string)($p['token'] ?? '');
        $fila = $this->tokenValido($token);
        if (!$fila) {
            flash('error', 'Ese enlace ya venció o no es válido. Solicita uno nuevo.');
            redirect('recuperar');
        }
        $this->view('auth/restablecer', ['token' => $token], 'acceso');
    }

    public function restablecerGuardar(array $p = []): void
    {
        Csrf::enforce();
        $token = (string)($p['token'] ?? '');
        $fila = $this->tokenValido($token);
        if (!$fila) {
            flash('error', 'Ese enlace ya venció o no es válido. Solicita uno nuevo.');
            redirect('recuperar');
        }

        $datos = [
            'password'  => (string)Request::input('password', ''),
            'password2' => (string)Request::input('password2', ''),
        ];
        $v = Validator::make($datos)
            ->requerido('password', 'La contraseña')
            ->password('password')
            ->iguales('password', 'password2', 'Las contraseñas');
        if ($v->falla()) {
            flash('error', $v->primerError());
            redirect('restablecer/' . $token);
        }

        DB::update('users', ['password_hash' => Security::hashPassword($datos['password'])],
            'id = :i', ['i' => (int)$fila['user_id']]);
        DB::update('password_resets', ['usado' => 1], 'id = :i', ['i' => (int)$fila['id']]);
        DB::delete('remember_tokens', 'user_id = :u', ['u' => (int)$fila['user_id']]);

        flash('exito', 'Tu contraseña quedó actualizada. Ya puedes ingresar.');
        redirect('ingresar');
    }

    private function tokenValido(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        return DB::one(
            'SELECT * FROM password_resets WHERE token_hash = :t AND usado = 0 AND expira > NOW() LIMIT 1',
            ['t' => hash('sha256', $token)]
        );
    }
}
