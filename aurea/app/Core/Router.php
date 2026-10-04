<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Router propio: patrones como /admin/citas/{id:\d+} -> [Clase, método]. */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace_callback('/\{(\w+)(?::((?:[^{}]|\{[^{}]*\})+))?\}/', static function ($m) {
            return '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
        }, $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    public function get(string $p, array $h): void { $this->add('GET', $p, $h); }
    public function post(string $p, array $h): void { $this->add('POST', $p, $h); }

    /** @return array{0:array,1:array}|null  [handler, params] */
    public function match(string $method, string $path): ?array
    {
        $allowed = false;
        foreach ($this->routes as [$m, $re, $h]) {
            if (preg_match($re, $path, $mm)) {
                if ($m === $method || ($method === 'HEAD' && $m === 'GET')) {
                    $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
                    return [$h, $params];
                }
                $allowed = true;
            }
        }
        return $allowed ? [['', '405'], []] : null;
    }
}
