<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Logger;
use App\Core\Settings;

/**
 * Captcha opcional (Cloudflare Turnstile o hCaptcha). Mientras el proveedor no esté activo y configurado
 * no se carga ningún script externo ni se pide verificación.
 */
final class CaptchaService
{
    public static function provider(): string
    {
        $p = (string) Settings::get('captcha_provider', 'none');
        if (!in_array($p, ['turnstile', 'hcaptcha'], true)) {
            return 'none';
        }
        if (self::siteKey() === '' || self::secret() === '') {
            return 'none';
        }
        return $p;
    }

    public static function enabled(): bool
    {
        return self::provider() !== 'none';
    }

    public static function siteKey(): string
    {
        return trim((string) Settings::get('captcha_site_key', ''));
    }

    /** Dirección del script del widget (solo existe si el proveedor está activo). */
    public static function scriptUrl(): ?string
    {
        return match (self::provider()) {
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'hcaptcha' => 'https://js.hcaptcha.com/1/api.js',
            default => null,
        };
    }

    /** Nombre del campo donde el widget deja la respuesta. */
    public static function fieldName(): string
    {
        return self::provider() === 'hcaptcha' ? 'h-captcha-response' : 'cf-turnstile-response';
    }

    /** @return array{ok:bool,error:?string} */
    public static function verify(?string $token, string $ip): array
    {
        if (!self::enabled()) {
            return ['ok' => true, 'error' => null];
        }
        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 4096) {
            return ['ok' => false, 'error' => 'Confirma que eres una persona marcando la verificación de seguridad.'];
        }
        $url = self::provider() === 'hcaptcha'
            ? 'https://api.hcaptcha.com/siteverify'
            : 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        try {
            $body = http_build_query(['secret' => self::secret(), 'response' => $token, 'remoteip' => $ip]);
            $r = SafeHttp::post($url, $body, ['Content-Type: application/x-www-form-urlencoded'], ['timeout' => 8]);
            if (empty($r['ok'])) {
                Logger::error('Captcha: sin respuesta del proveedor (' . (string) ($r['error'] ?? '') . ')');
                return ['ok' => false, 'error' => 'No pudimos completar la verificación de seguridad. Inténtalo de nuevo en un momento.'];
            }
            $j = json_decode((string) $r['body'], true);
            if (is_array($j) && !empty($j['success'])) {
                return ['ok' => true, 'error' => null];
            }
        } catch (\Throwable $e) {
            Logger::error('Captcha: error al verificar', $e);
            return ['ok' => false, 'error' => 'No pudimos completar la verificación de seguridad. Inténtalo de nuevo en un momento.'];
        }
        return ['ok' => false, 'error' => 'La verificación de seguridad no pasó. Inténtalo de nuevo.'];
    }

    private static function secret(): string
    {
        $raw = (string) Settings::get('captcha_secret', '');
        if ($raw === '') {
            return '';
        }
        $dec = Crypto::decrypt($raw);
        return $dec !== null && $dec !== '' ? $dec : $raw;
    }
}
