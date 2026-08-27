<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Correo;
use App\Core\DB;
use App\Core\LimiteIntentos;
use App\Core\Peticion;
use App\Core\Sesion;
use App\Models\Usuario;

final class AccesoControlador extends Controlador
{
    private const MAX_INTENTOS = 5;
    private const BLOQUEO_MIN  = 15;

    public function entrar(): void
    {
        if (!Auth::invitado()) {
            $this->redirigir($this->destinoPorRol());
        }
        $error = '';
        if (Sesion::get('_expirada')) {
            Sesion::quitar('_expirada');
            $error = 'Su sesión expiró por inactividad. Ingrese nuevamente.';
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $identificador = Peticion::texto('usuario');
            $clave         = (string) ($_POST['clave'] ?? '');
            $llave         = 'acceso:' . mb_strtolower($identificador) . ':' . Peticion::ip();

            if (!LimiteIntentos::permitido($llave, self::MAX_INTENTOS, self::BLOQUEO_MIN)) {
                $min = LimiteIntentos::minutosRestantes($llave, self::BLOQUEO_MIN);
                Auditoria::registrar('acceso_bloqueado', null, null, $identificador);
                $error = 'Demasiados intentos fallidos. Intente de nuevo en ' . $min . ' minutos.';
            } elseif ($identificador === '' || $clave === '') {
                $error = 'Ingrese su usuario y su contraseña.';
            } else {
                $u = Usuario::porIdentificador($identificador);
                if ($u === null || !Auth::verificar($clave, (string) $u['password_hash'])) {
                    LimiteIntentos::registrar($llave);
                    $restantes = self::MAX_INTENTOS - LimiteIntentos::contar($llave, self::BLOQUEO_MIN);
                    $error = 'Usuario o contraseña incorrectos.'
                        . ($restantes > 0 && $restantes <= 2 ? ' Le quedan ' . $restantes . ' intento(s).' : '');
                } else {
                    LimiteIntentos::limpiar($llave);
                    if ((int) $u['dos_factores'] === 1 && !empty($u['correo'])) {
                        $this->enviarCodigo($u);
                        Sesion::set('_2fa_usuario', (int) $u['id']);
                        $this->redirigir('/acceso/verificar');
                    }
                    Auth::entrar($u);
                    $destino = Sesion::get('_destino');
                    Sesion::quitar('_destino');
                    $this->redirigir(is_string($destino) && $destino !== '' && $destino !== '/acceso'
                        ? $destino : $this->destinoPorRol());
                }
            }
        }

        $this->mostrar('auth/acceso', [
            'tituloPagina' => 'Acceso al sistema',
            'error'        => $error,
        ], 'limpio');
    }

    public function dosFactores(): void
    {
        $usuarioId = (int) Sesion::get('_2fa_usuario', 0);
        if ($usuarioId <= 0) {
            $this->redirigir('/acceso');
        }
        $error = '';
        if ($this->post()) {
            $this->verificarCsrf();
            $codigo = preg_replace('/\D+/', '', Peticion::texto('codigo')) ?? '';
            $llave  = '2fa:' . $usuarioId . ':' . Peticion::ip();
            if (!LimiteIntentos::permitido($llave, 6, 15)) {
                $error = 'Demasiados intentos. Solicite un código nuevo en unos minutos.';
            } else {
                $fila = DB::uno(
                    'SELECT * FROM codigos_2fa WHERE usuario_id = :u AND usado_en IS NULL AND expira_en > NOW()
                     ORDER BY id DESC LIMIT 1',
                    ['u' => $usuarioId]
                );
                if ($fila !== null && hash_equals((string) $fila['codigo_hash'], hash('sha256', $codigo))) {
                    DB::actualizar('codigos_2fa', ['usado_en' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $fila['id']]);
                    $u = Usuario::porId($usuarioId);
                    if ($u !== null) {
                        Sesion::quitar('_2fa_usuario');
                        Auth::entrar($u);
                        $this->redirigir($this->destinoPorRol());
                    }
                }
                LimiteIntentos::registrar($llave);
                $error = 'El código no es válido o ya expiró.';
            }
        }
        $this->mostrar('auth/dos-factores', [
            'tituloPagina' => 'Verificación en dos pasos',
            'error'        => $error,
        ], 'limpio');
    }

    public function salir(): void
    {
        Auth::salir();
        Sesion::iniciar();
        Sesion::flash('exito', 'Su sesión se cerró correctamente.');
        $this->redirigir('/acceso');
    }

    public function recuperar(): void
    {
        $enviado = false;
        $error   = '';
        if ($this->post()) {
            $this->verificarCsrf();
            $correo = mb_strtolower(Peticion::texto('correo'));
            $llave  = 'recuperar:' . Peticion::ip();
            if (!LimiteIntentos::permitido($llave, 5, 30)) {
                $error = 'Se solicitaron demasiados restablecimientos. Intente más tarde.';
            } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $error = 'Escriba un correo electrónico válido.';
            } else {
                LimiteIntentos::registrar($llave);
                $u = Usuario::porCorreo($correo);
                if ($u !== null && (int) $u['activo'] === 1) {
                    $token = bin2hex(random_bytes(32));
                    DB::insertar('password_resets', [
                        'usuario_id' => (int) $u['id'],
                        'token_hash' => hash('sha256', $token),
                        'expira_en'  => date('Y-m-d H:i:s', time() + 1800),
                    ]);
                    $enlace = \App\Core\Url::absoluta('/restablecer/' . $token);
                    Correo::enviar(
                        $correo,
                        (string) $u['nombre'],
                        'Restablecimiento de contraseña',
                        Correo::plantillaHtml(
                            'Restablecimiento de contraseña',
                            '<p>Recibimos una solicitud para restablecer la contraseña de su cuenta en '
                            . e(Ajustes::get('nombre', 'el residencial')) . '.</p>'
                            . '<p>El enlace estará disponible durante <strong>30 minutos</strong> y solo puede usarse una vez. '
                            . 'Si usted no hizo esta solicitud, puede ignorar este mensaje con tranquilidad.</p>',
                            'Crear una contraseña nueva',
                            $enlace
                        )
                    );
                    Auditoria::registrar('solicitar_restablecimiento', 'usuarios', (int) $u['id'], $correo);
                }
                $enviado = true;
            }
        }
        $this->mostrar('auth/recuperar', [
            'tituloPagina' => 'Recuperar contraseña',
            'enviado'      => $enviado,
            'error'        => $error,
        ], 'limpio');
    }

    public function restablecer(string $token = ''): void
    {
        $fila = DB::uno(
            'SELECT r.*, u.nombre, u.correo FROM password_resets r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.token_hash = :t AND r.usado_en IS NULL AND r.expira_en > NOW() LIMIT 1',
            ['t' => hash('sha256', $token)]
        );
        $error = '';
        if ($fila === null) {
            $this->mostrar('auth/restablecer', [
                'tituloPagina' => 'Enlace no válido',
                'invalido'     => true,
                'token'        => $token,
                'error'        => '',
            ], 'limpio');
        }
        if ($this->post()) {
            $this->verificarCsrf();
            $c1 = (string) ($_POST['clave'] ?? '');
            $c2 = (string) ($_POST['clave2'] ?? '');
            $problema = Auth::politicaClave($c1);
            if ($problema !== null) {
                $error = $problema;
            } elseif ($c1 !== $c2) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                DB::actualizar('usuarios', ['password_hash' => Auth::hash($c1)], 'id = :id', ['id' => (int) $fila['usuario_id']]);
                DB::actualizar('password_resets', ['usado_en' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $fila['id']]);
                DB::eliminar('password_resets', 'usuario_id = :u AND usado_en IS NULL', ['u' => (int) $fila['usuario_id']]);
                Auditoria::registrar('restablecer_clave', 'usuarios', (int) $fila['usuario_id'], (string) $fila['correo']);
                Sesion::flash('exito', 'Su contraseña se actualizó. Ya puede ingresar.');
                $this->redirigir('/acceso');
            }
        }
        $this->mostrar('auth/restablecer', [
            'tituloPagina' => 'Nueva contraseña',
            'invalido'     => false,
            'token'        => $token,
            'error'        => $error,
        ], 'limpio');
    }

    private function enviarCodigo(array $u): void
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::insertar('codigos_2fa', [
            'usuario_id'  => (int) $u['id'],
            'codigo_hash' => hash('sha256', $codigo),
            'expira_en'   => date('Y-m-d H:i:s', time() + 600),
        ]);
        Correo::enviar(
            (string) $u['correo'],
            (string) $u['nombre'],
            'Su código de verificación: ' . $codigo,
            Correo::plantillaHtml(
                'Código de verificación',
                '<p>Su código de acceso de un solo uso es:</p>'
                . '<p style="font-size:32px;font-weight:700;letter-spacing:8px;color:#0F2E24">' . $codigo . '</p>'
                . '<p>Vence en 10 minutos. Si no fue usted quien intentó ingresar, cambie su contraseña de inmediato.</p>'
            )
        );
    }

    private function destinoPorRol(): string
    {
        return match (Auth::rol()) {
            'admin', 'junta', 'contabilidad' => '/admin',
            'garita'                         => '/garita',
            default                          => '/portal',
        };
    }
}
