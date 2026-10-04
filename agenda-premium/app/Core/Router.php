<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router propio. Patrones: /e/{slug}, /reserva/{token:[a-f0-9]{32}}, /api/v1/events/{id:\d+}
 * Opciones por ruta: auth (bool), roles (array), csrf ('session'|'public'|false), api ('read'|'write'), perm (string)
 */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler, array $opts = []): void
    {
        $regex = preg_replace_callback('/\{([a-z_]+)(?::((?:[^{}]|\{[^{}]*\})+))?\}/i', static function (array $m): string {
            $re = $m[2] ?? '[^/]+';
            return '(?P<' . $m[1] . '>' . $re . ')';
        }, $pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'opts' => $opts,
            'pattern' => $pattern,
        ];
    }

    public function get(string $p, array $h, array $o = []): void
    {
        $this->add('GET', $p, $h, $o);
    }

    public function post(string $p, array $h, array $o = []): void
    {
        $this->add('POST', $p, $h, $o);
    }

    /** @return array{handler:array,params:array,opts:array}|null  o ['methodNotAllowed'=>true] */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $pathMatched = false;
        foreach ($this->routes as $r) {
            if (preg_match($r['regex'], $path, $m)) {
                if ($r['method'] !== $method) {
                    $pathMatched = true;
                    continue;
                }
                $params = [];
                foreach ($m as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }
                return ['handler' => $r['handler'], 'params' => $params, 'opts' => $r['opts']];
            }
        }
        return $pathMatched ? ['methodNotAllowed' => true] : null;
    }
}
