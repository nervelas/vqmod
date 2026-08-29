<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string,array<int,array{0:string,1:callable|array,2:array<int,string>}>> */
    private array $routes = ['GET' => [], 'POST' => []];
    private array $notFound;

    public function get(string $pattern, array|callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array|callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, array|callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array|callable $handler): void
    {
        $names = [];
        $regex = preg_replace_callback('/\{([a-z_]+)(?::([^}]+))?\}/i', static function ($m) use (&$names) {
            $names[] = $m[1];
            return '(' . ($m[2] ?? '[^/]+') . ')';
        }, $pattern);
        $this->routes[$method][] = ['#^' . $regex . '$#u', $handler, $names];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        if (!isset($this->routes[$method])) {
            ErrorHandler::render(404);
        }
        foreach ($this->routes[$method] as [$regex, $handler, $names]) {
            if (preg_match($regex, $path, $m)) {
                array_shift($m);
                $params = [];
                foreach ($names as $i => $n) {
                    $params[$n] = $m[$i] ?? null;
                }
                $this->invoke($handler, $params);
                return;
            }
        }
        ErrorHandler::render(404);
    }

    private function invoke(array|callable $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $action] = $handler;
            if (!class_exists($class)) {
                ErrorHandler::render(404);
            }
            $c = new $class();
            if (!method_exists($c, $action)) {
                ErrorHandler::render(404);
            }
            $c->$action($params);
            return;
        }
        $handler($params);
    }
}
