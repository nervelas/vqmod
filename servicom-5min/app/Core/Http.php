<?php
declare(strict_types=1);
namespace S5\Core;

final class Http
{
    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Solo se confía en X-Forwarded-For si el dueño lo habilita (detrás de proxy/CDN)
        if (Settings::bool('trust_proxy') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $f = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($f, FILTER_VALIDATE_IP)) {
                $ip = $f;
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function json(array $data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (Settings::bool('trust_proxy') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return false;
    }

    /** Petición HTTP saliente simple con curl. */
    public static function request(string $method, string $url, array $opts = []): array
    {
        $ch = curl_init($url);
        $headers = $opts['headers'] ?? [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => !empty($opts['follow']),
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $opts['connect_timeout'] ?? 10,
            CURLOPT_TIMEOUT => $opts['timeout'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => $opts['verify'] ?? true,
            CURLOPT_SSL_VERIFYHOST => ($opts['verify'] ?? true) ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'ServicomPortal/1.0',
        ]);
        if (isset($opts['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
        }
        if (!empty($opts['resolve'])) {
            curl_setopt($ch, CURLOPT_RESOLVE, $opts['resolve']);
        }
        if (!empty($opts['cafile'])) {
            curl_setopt($ch, CURLOPT_CAINFO, $opts['cafile']);
        }
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ssl = (int) curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
        curl_close($ch);
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $err, 'errno' => $errno, 'ssl_result' => $ssl];
    }
}
