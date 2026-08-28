<?php
declare(strict_types=1);

namespace App\Core;

final class Respuesta
{
    private static string $nonce = '';

    /** Valor aleatorio por petición para permitir los <script> propios en la CSP. */
    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }
        return self::$nonce;
    }

    public static function cabecerasSeguridad(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(self), camera=(self), microphone=(), payment=(), usb=()');
        header('X-Permitted-Cross-Domain-Policies: none');
        if (Peticion::esHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        $csp = "default-src 'self'; "
             . "base-uri 'self'; "
             . "object-src 'none'; "
             . "frame-ancestors 'none'; "
             . "form-action 'self'; "
             . "img-src 'self' data: blob:; "
             . "media-src 'self' blob:; "
             . "font-src 'self' https://fonts.gstatic.com data:; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "script-src 'self' 'nonce-" . self::nonce() . "'; "
             . "connect-src 'self'; "
             . "worker-src 'self'; "
             . "manifest-src 'self'";
        header('Content-Security-Policy: ' . $csp);
    }

    public static function redirigir(string $ruta, int $codigo = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . (str_starts_with($ruta, 'http') ? $ruta : Url::a($ruta)), true, $codigo);
        }
        exit;
    }

    public static function json(array $datos, int $codigo = 200): void
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function descargar(string $contenido, string $nombre, string $mime): void
    {
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nombre) . '"');
            header('Content-Length: ' . strlen($contenido));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        echo $contenido;
        exit;
    }

    public static function verEnLinea(string $contenido, string $nombre, string $mime): void
    {
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . str_replace('"', '', $nombre) . '"');
            header('Content-Length: ' . strlen($contenido));
        }
        echo $contenido;
        exit;
    }

    public static function abortar(int $codigo, string $mensaje = ''): void
    {
        if (Peticion::esAjax()) {
            self::json(['ok' => false, 'error' => $mensaje !== '' ? $mensaje : 'Error ' . $codigo], $codigo);
        }
        http_response_code($codigo);
        $titulos = [
            400 => 'Solicitud incorrecta',
            403 => 'Acceso denegado',
            404 => 'Página no encontrada',
            419 => 'Sesión expirada',
            429 => 'Demasiadas solicitudes',
            500 => 'Error del servidor',
        ];
        $titulo = $titulos[$codigo] ?? 'Error';
        $vista  = RUTA_BASE . '/app/Views/errors/error.php';
        if (is_file($vista)) {
            $codigoHttp = $codigo;
            include $vista;
        } else {
            echo '<h1>' . e($titulo) . '</h1><p>' . e($mensaje) . '</p>';
        }
        exit;
    }
}
