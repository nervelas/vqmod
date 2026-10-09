<?php
declare(strict_types=1);
namespace S5\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $re = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '/?$#';
        $this->routes[] = [$method, $re, $handler];
    }

    public function get(string $p, callable $h): void { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(string $method, string $path): bool
    {
        foreach ($this->routes as [$m, $re, $h]) {
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
                continue;
            }
            if (preg_match($re, $path, $mm)) {
                $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
                $h($params);
                return true;
            }
        }
        return false;
    }
}
