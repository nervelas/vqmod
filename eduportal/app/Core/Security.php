<?php
declare(strict_types=1);

namespace App\Core;

final class Security
{
    public static function headers(bool $conCsp = true): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');
        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        if ($conCsp) {
            $csp = "default-src 'self'; "
                 . "base-uri 'self'; "
                 . "object-src 'none'; "
                 . "frame-ancestors 'none'; "
                 . "form-action 'self'; "
                 . "img-src 'self' data: blob: https:; "
                 . "font-src 'self' https://fonts.gstatic.com data:; "
                 . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                 . "script-src 'self'; "
                 . "connect-src 'self'; "
                 . "worker-src 'self'; "
                 . "manifest-src 'self'; "
                 . "frame-src 'self' https://www.google.com https://maps.google.com";
            header('Content-Security-Policy: ' . $csp);
        }
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
