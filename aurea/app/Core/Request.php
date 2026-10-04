<?php
declare(strict_types=1);

namespace Aurea\Core;

final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $post;
    public array $files;
    public array $server;
    public array $params = [];

    public function __construct(?array $server = null, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        $this->server = $server ?? $_SERVER;
        $this->query = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        $uri = (string)($this->server['REQUEST_URI'] ?? '/');
        $path = (string)parse_url($uri, PHP_URL_PATH);
        $path = rawurldecode($path);
        $base = base_path();
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        $this->path = '/' . trim($path, '/');
        // Si el cuerpo es JSON, se mezcla con $post
        $ct = (string)($this->server['CONTENT_TYPE'] ?? '');
        if ($this->method === 'POST' && stripos($ct, 'application/json') !== false && $post === null) {
            $raw = file_get_contents('php://input');
            $j = json_decode((string)$raw, true);
            if (is_array($j)) {
                $this->post = $j;
            }
        }
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string)$v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? (int)$v : $default;
    }

    public function header(string $name): string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string)($this->server[$k] ?? '');
    }

    public function isPost(): bool { return $this->method === 'POST'; }

    public function isHttps(): bool
    {
        $s = $this->server;
        if (!empty($s['HTTPS']) && $s['HTTPS'] !== 'off') {
            return true;
        }
        if (config('trust_proxy', false) && strtolower((string)($s['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        return ($s['SERVER_PORT'] ?? '') == 443;
    }

    public function ip(): string
    {
        $ip = (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if (config('trust_proxy', false)) {
            $cf = (string)($this->server['HTTP_CF_CONNECTING_IP'] ?? '');
            if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
                return $cf;
            }
            $xff = trim(explode(',', (string)($this->server['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
            if ($xff !== '' && filter_var($xff, FILTER_VALIDATE_IP)) {
                return $xff;
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With')) === 'xmlhttprequest'
            || stripos((string)($this->server['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || stripos((string)($this->server['CONTENT_TYPE'] ?? ''), 'application/json') !== false;
    }

    public function userAgent(): string
    {
        return substr((string)($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
