<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Verificación opcional de Cloudflare Turnstile / hCaptcha (solo si el cliente configura sus claves). */
final class Captcha
{
    public static function provider(): string
    {
        $p = (string)Settings::get('captcha_provider', 'none');
        return in_array($p, ['turnstile', 'hcaptcha'], true) && Settings::get('captcha_site_key', '') !== '' && Settings::get('captcha_secret', '') !== '' ? $p : 'none';
    }

    public static function verify(Request $r): bool
    {
        $p = self::provider();
        if ($p === 'none') { return true; }
        $field = $p === 'turnstile' ? 'cf-turnstile-response' : 'h-captcha-response';
        $resp = $r->str($field);
        if ($resp === '' || strlen($resp) > 4096) { return false; }
        $secret = Secret::decrypt((string)Settings::get('captcha_secret', ''));
        $url = $p === 'turnstile' ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify' : 'https://hcaptcha.com/siteverify';
        $url = (string)config('captcha_verify_url', $url);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 10, 'ignore_errors' => true,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['secret' => $secret, 'response' => $resp, 'remoteip' => $r->ip()])]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) { Logger::error('Captcha: sin respuesta del proveedor'); return false; }
        $j = json_decode($res, true);
        return is_array($j) && !empty($j['success']);
    }
}
