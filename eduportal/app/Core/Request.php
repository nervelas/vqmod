<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $body;
    private array $files;
    private string $method;
    private string $path;

    public function __construct()
    {
        $this->query  = $_GET;
        $this->body   = $_POST;
        $this->files  = $_FILES;
        $this->method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $uri = explode('?', $uri, 2)[0];
        $base = base_path_url();
        if ($base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim(rawurldecode($uri), '/');
        $this->path = $uri === '//' ? '/' : $uri;

        if ($this->method === 'POST' && isset($this->body['_method'])) {
            $m = strtoupper((string)$this->body['_method']);
            if (in_array($m, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->method = $m;
            }
        }
        if ($this->isJson()) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded)) {
                $this->body = array_merge($this->body, $decoded);
            }
        }
    }

    public function isJson(): bool
    {
        $ct = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        return str_contains($ct, 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return $this->isJson()
            || str_contains($accept, 'application/json')
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function all(): array { return array_merge($this->query, $this->body); }
    public function files(): array { return $this->files; }
    public function file(string $k): ?array { return $this->files[$k] ?? null; }

    public function input(string $key, mixed $default = null): mixed
    {
        $v = $this->body[$key] ?? $this->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, null);
        return is_numeric($v) ? (int)$v : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $v = $this->input($key, null);
        if (is_string($v)) {
            $v = str_replace([',', ' '], '', $v);
        }
        return is_numeric($v) ? (float)$v : $default;
    }

    public function bool(string $key): bool
    {
        $v = $this->input($key, null);
        return in_array($v, ['1', 1, true, 'true', 'on', 'si'], true);
    }

    public function arr(string $key): array
    {
        $v = $this->raw($key, []);
        return is_array($v) ? $v : [];
    }

    public function ip(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return substr($ip, 0, 45);
    }

    public function agent(): string
    {
        return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }
}
