<?php
declare(strict_types=1);
namespace S5\Core;

/** Autenticación del dueño: password_hash, límite de intentos y 2FA TOTP opcional. */
final class Auth
{
    public static function user(): ?array
    {
        Session::start();
        if (empty($_SESSION['uid']) || empty($_SESSION['2fa_ok'])) {
            return null;
        }
        static $cache = null;
        if ($cache && (int) $cache['id'] === (int) $_SESSION['uid']) {
            return $cache;
        }
        $cache = Db::one('SELECT id,email,totp_enabled FROM ' . Db::t('users') . ' WHERE id=?', [(int) $_SESSION['uid']]);
        return $cache;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array{ok:bool,need2fa?:bool,error?:string} */
    public static function attempt(string $email, string $pass, string $code = ''): array
    {
        Session::start();
        $ip = Http::ip();
        $email = Sanitize::email($email);
        if (!RateLimit::hit('login-ip-' . $ip, 12, 900) || ($email && RateLimit::count('login-u-' . $email, 900) >= 6)) {
            return ['ok' => false, 'error' => 'Demasiados intentos. Espere unos minutos e inténtelo de nuevo.'];
        }
        $u = $email ? Db::one('SELECT * FROM ' . Db::t('users') . ' WHERE email=?', [$email]) : null;
        $hash = $u['pass_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuYB5V0Z0aXOUkp4yE0cH0RlK1T9yGq6e';
        // Tolerancia a espacios sobrantes (teclados de teléfono que agregan un espacio al autocompletar).
        $good = false;
        $okPass = $pass;
        foreach (array_unique([$pass, trim($pass), rtrim($pass), ltrim($pass), trim($pass) . ' ', ' ' . trim($pass), ' ' . trim($pass) . ' ']) as $cand) {
            if ($cand !== '' && password_verify($cand, $hash)) {
                $good = (bool) $u;
                $okPass = $cand;
                break;
            }
        }
        if (!$good) {
            if ($email) {
                RateLimit::hit('login-u-' . $email, 6, 900);
            }
            Log::audit('login_fallido', $email);
            return ['ok' => false, 'error' => 'Correo o contraseña incorrectos.'];
        }
        if ((int) $u['totp_enabled'] === 1) {
            if ($code === '') {
                $_SESSION['pending_uid'] = (int) $u['id'];
                return ['ok' => false, 'need2fa' => true];
            }
            $secret = Crypto::decrypt((string) $u['totp_secret']);
            if (!Totp::verify($secret, $code)) {
                RateLimit::hit('login-u-' . $email, 6, 900);
                Log::audit('login_2fa_fallido', $email);
                return ['ok' => false, 'need2fa' => true, 'error' => 'Código de verificación incorrecto.'];
            }
        }
        Session::regenerate();
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['2fa_ok'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['pass_hash' => password_hash($okPass, PASSWORD_DEFAULT)], 'id=?', [$u['id']]);
        }
        Log::audit('login', $email);
        return ['ok' => true];
    }

    /** Segundo paso del acceso cuando la cuenta tiene 2FA (la contraseña ya fue validada). */
    public static function verify2fa(string $code): array
    {
        Session::start();
        $uid = (int) ($_SESSION['pending_uid'] ?? 0);
        if (!$uid || !RateLimit::hit('2fa-ip-' . Http::ip(), 10, 900)) {
            return ['ok' => false, 'error' => 'Vuelva a iniciar sesión.'];
        }
        $u = Db::one('SELECT * FROM ' . Db::t('users') . ' WHERE id=?', [$uid]);
        if (!$u || !Totp::verify(Crypto::decrypt((string) $u['totp_secret']), $code)) {
            Log::audit('login_2fa_fallido', (string) ($u['email'] ?? ''));
            return ['ok' => false, 'need2fa' => true, 'error' => 'Código de verificación incorrecto.'];
        }
        Session::regenerate();
        unset($_SESSION['pending_uid']);
        $_SESSION['uid'] = $uid;
        $_SESSION['2fa_ok'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        Log::audit('login', (string) $u['email']);
        return ['ok' => true];
    }

    public static function logout(): void
    {
        Log::audit('logout');
        Session::destroy();
    }

    public static function hash(string $pass): string
    {
        return password_hash($pass, PASSWORD_DEFAULT);
    }
}
