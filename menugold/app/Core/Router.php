<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Router con parametros {nombre} y comodines opcionales {nombre?}.
 */
final class Router
{
    /** @var array<string,array<int,array{0:string,1:mixed,2:array}>> */
    private array $routes = ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []];
    private array $groupStack = [];

    public function get(string $path, $action, array $opts = []): void { $this->add('GET', $path, $action, $opts); }
    public function post(string $path, $action, array $opts = []): void { $this->add('POST', $path, $action, $opts); }
    public function any(string $path, $action, array $opts = []): void
    {
        $this->add('GET', $path, $action, $opts);
        $this->add('POST', $path, $action, $opts);
    }

    /** Agrupa rutas con prefijo y middleware comunes. */
    public function group(string $prefix, array $opts, callable $fn): void
    {
        $this->groupStack[] = ['prefijo' => trim($prefix, '/'), 'opts' => $opts];
        $fn($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $path, $action, array $opts): void
    {
        $prefix = '';
        $merged = $opts;
        foreach ($this->groupStack as $g) {
            if ($g['prefijo'] !== '') $prefix .= '/' . $g['prefijo'];
            $merged = array_merge($g['opts'], $merged);
        }
        $full = rtrim($prefix . '/' . trim($path, '/'), '/');
        if ($full === '') $full = '/';
        $this->routes[$method][] = [$full, $action, $merged];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . trim($uri, '/');
        if ($uri === '/') $uri = '/';

        $candidates = $this->routes[$method] ?? [];
        foreach ($candidates as $route) {
            [$pattern, $action, $opts] = $route;
            $params = $this->match($pattern, $uri);
            if ($params === null) continue;
            $this->runMiddleware($opts);
            $this->invoke($action, $params);
            return;
        }
        // Metodo no permitido pero ruta existente
        foreach ($this->routes as $m => $list) {
            if ($m === $method) continue;
            foreach ($list as $route) {
                if ($this->match($route[0], $uri) !== null) {
                    throw new HttpException('Método no permitido para esta dirección.', 405);
                }
            }
        }
        throw HttpException::notFound();
    }

    /** @return array<string,string>|null */
    private function match(string $pattern, string $uri): ?array
    {
        if (strpos($pattern, '{') === false) {
            return $pattern === $uri ? [] : null;
        }
        $names = [];
        $regex = '';
        foreach (explode('/', ltrim($pattern, '/')) as $seg) {
            if (preg_match('/^\{(\w+)\?\}$/', $seg, $m)) {
                $names[] = $m[1];
                $regex .= '(?:/([^/]+))?';
            } elseif (preg_match('/^\{(\w+)\}$/', $seg, $m)) {
                $names[] = $m[1];
                $regex .= '/([^/]+)';
            } else {
                $regex .= '/' . preg_quote($seg, '~');
            }
        }
        if ($regex === '') $regex = '/';
        if (!preg_match('~^' . $regex . '$~u', $uri, $m)) return null;
        $out = [];
        foreach ($names as $i => $n) {
            $out[$n] = $m[$i + 1] ?? '';
        }
        return $out;
    }

    private function runMiddleware(array $opts): void
    {
        foreach ((array)($opts['middleware'] ?? []) as $mw) {
            Middleware::run((string)$mw, $opts);
        }
    }

    private function invoke($action, array $params): void
    {
        if (is_callable($action)) { $action($params); return; }
        if (is_string($action) && strpos($action, '@') !== false) {
            [$class, $method] = explode('@', $action, 2);
            // 'Menu@index' o 'Panel\Dashboard@index' se resuelven bajo el
            // espacio de nombres de controladores; una clase completa se usa tal cual.
            $fqcn = (strncmp($class, 'MenuGold\\', 9) === 0 || $class[0] === '\\')
                ? ltrim($class, '\\')
                : 'MenuGold\\Controllers\\' . $class;
            if (!class_exists($fqcn)) throw HttpException::notFound('Controlador no encontrado.');
            $obj = new $fqcn();
            if (!method_exists($obj, $method)) throw HttpException::notFound('Acción no encontrada.');
            $obj->$method($params);
            return;
        }
        throw new \RuntimeException('Ruta mal definida.');
    }
}
