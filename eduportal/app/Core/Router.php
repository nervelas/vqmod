<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<int, array{0:string,1:array,2:array}>> */
    private array $routes = ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []];

    public function get(string $path, array $action, array $opts = []): void { $this->add('GET', $path, $action, $opts); }
    public function post(string $path, array $action, array $opts = []): void { $this->add('POST', $path, $action, $opts); }
    public function put(string $path, array $action, array $opts = []): void { $this->add('PUT', $path, $action, $opts); }
    public function delete(string $path, array $action, array $opts = []): void { $this->add('DELETE', $path, $action, $opts); }

    private function add(string $method, string $path, array $action, array $opts): void
    {
        $this->routes[$method][] = [$path, $action, $opts];
    }

    /**
     * @return array{action:array, params:array, opts:array}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = $method === 'PATCH' ? 'PUT' : $method;
        foreach ($this->routes[$method] ?? [] as [$route, $action, $opts]) {
            if ($route === $path) {
                return ['action' => $action, 'params' => [], 'opts' => $opts];
            }
            if (!str_contains($route, '{')) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $route) . '$#';
            if (preg_match($regex, $path, $m)) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (!is_int($k)) {
                        $params[$k] = $v;
                    }
                }
                return ['action' => $action, 'params' => $params, 'opts' => $opts];
            }
        }
        return null;
    }

    public function methodsFor(string $path): array
    {
        $found = [];
        foreach ($this->routes as $method => $list) {
            foreach ($list as [$route]) {
                $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '([^/]+)', $route) . '$#';
                if ($route === $path || preg_match($regex, $path)) {
                    $found[] = $method;
                    break;
                }
            }
        }
        return $found;
    }
}
