<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $post;
    public array $files;
    public array $server;
    private ?array $jsonBody = null;

    public function __construct(?array $server = null, ?array $query = null, ?array $post = null, ?array $files = null)
    {
        $this->server = $server ?? $_SERVER;
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $path = rawurldecode($path);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        if ($base !== '' && strncmp($path, $base, strlen($base)) === 0) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        $this->path = $path === '' ? '/' : $path;
        if ($this->path === '/index.php') {
            $this->path = '/';
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        $j = $this->json();
        return $j[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, int $max = 255, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? Str::clean((string) $v, $max) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_scalar($v) && is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        $v = $this->input($key, false);
        return in_array($v, [true, 1, '1', 'on', 'true', 'yes', 'si', 'sí'], true);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->json(), $this->post);
    }

    public function json(): array
    {
        if ($this->jsonBody === null) {
            $this->jsonBody = [];
            $ct = strtolower((string) $this->header('Content-Type'));
            if (strpos($ct, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                if (is_string($raw) && $raw !== '' && strlen($raw) < 1048576) {
                    $d = json_decode($raw, true);
                    if (is_array($d)) {
                        $this->jsonBody = $d;
                    }
                }
            }
        }
        return $this->jsonBody;
    }

    public function header(string $name): ?string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            $k = 'CONTENT_TYPE';
        }
        $v = $this->server[$k] ?? null;
        return is_string($v) ? $v : null;
    }

    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if (Config::get('trust_proxy', false)) {
            $xff = (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($xff !== '') {
                $first = trim(explode(',', $xff)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return $ip;
    }

    public function isHttps(): bool
    {
        $s = $this->server;
        if (!empty($s['HTTPS']) && strtolower((string) $s['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($s['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return Config::get('trust_proxy', false) && strtolower((string) ($s['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        return strpos((string) ($this->server['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest'
            || strpos($this->path, '/api/') === 0 || strpos($this->path, '/_/') === 0;
    }

    public function host(): string
    {
        $h = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        return preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $h) ? $h : 'localhost';
    }

    /** URL base completa: https://dominio/subcarpeta (sin barra final). */
    public function baseUrl(): string
    {
        $cfg = (string) Config::get('base_url', '');
        if ($cfg !== '') {
            return rtrim($cfg, '/');
        }
        return ($this->isHttps() ? 'https' : 'http') . '://' . $this->host() . (defined('BASE_PATH') ? BASE_PATH : '');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isMobile(): bool
    {
        return (bool) preg_match('/Mobi|Android|iPhone|iPad/i', $this->userAgent());
    }
}
