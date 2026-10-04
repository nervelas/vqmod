<?php
declare(strict_types=1);

namespace App\Core;

/** Cabeceras de seguridad y nonce de CSP. */
final class Security
{
    private static ?string $nonce = null;
    private static bool $embed = false;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    /** Marca la respuesta actual como incrustable (módulo de reserva embebible). */
    public static function allowEmbed(bool $on = true): void
    {
        self::$embed = $on;
    }

    public static function isEmbed(): bool
    {
        return self::$embed;
    }

    public static function headers(Request $req): array
    {
        $captcha = (string) Settings::get('captcha_provider', 'none');
        $script = "'self'";
        $frame = '';
        if ($captcha === 'turnstile') {
            $script .= ' https://challenges.cloudflare.com';
            $frame = ' https://challenges.cloudflare.com';
        } elseif ($captcha === 'hcaptcha') {
            $script .= ' https://js.hcaptcha.com https://hcaptcha.com https://*.hcaptcha.com';
            $frame = ' https://hcaptcha.com https://*.hcaptcha.com';
        }
        $ancestors = "'self'";
        if (self::$embed) {
            $allowed = trim((string) Settings::get('embed_allowed_origins', '*'));
            $ancestors = $allowed === '' ? "'self'" : $allowed;
        }
        $csp = "default-src 'self'; script-src {$script}; style-src 'self' 'nonce-" . self::nonce() . "'; img-src 'self' data: blob:; font-src 'self'; "
            . "connect-src 'self'; frame-src 'self'{$frame}; form-action 'self'; base-uri 'self'; object-src 'none'; manifest-src 'self'; worker-src 'self'; frame-ancestors {$ancestors}";
        $h = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if (!self::$embed) {
            $h['X-Frame-Options'] = 'SAMEORIGIN';
        }
        if ($req->isHttps()) {
            $h['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        return $h;
    }
}
