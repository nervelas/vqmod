<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Proteccion CSRF por token de sesion (formularios y AJAX).
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    public static function check(?string $token = null): bool
    {
        $sent = $token
            ?? $_POST['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_GET['_token']
            ?? '';
        if (!is_string($sent) || $sent === '') {
            // Cuerpo JSON
            $raw = file_get_contents('php://input') ?: '';
            if ($raw !== '') {
                $j = json_decode($raw, true);
                if (is_array($j) && !empty($j['_token'])) $sent = (string)$j['_token'];
            }
        }
        $stored = (string)($_SESSION['_csrf'] ?? '');
        return $stored !== '' && is_string($sent) && hash_equals($stored, $sent);
    }

    public static function enforce(): void
    {
        if (!self::check()) {
            Logger::warn('CSRF invalido', ['uri' => App::uri(), 'ip' => client_ip()]);
            throw new HttpException('La sesión de seguridad expiró. Recarga la página e intenta de nuevo.', 419);
        }
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
