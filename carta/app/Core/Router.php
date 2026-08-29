<?php
namespace MenuGold\Core;

final class Router
{
    /** @var array<string,array<int,array>> */
    private $routes = array('GET' => array(), 'POST' => array());
    /** @var array<string,string> nombre => patrón */
    private $names = array();

    public function get($pattern, $action, $name = null)  { return $this->add('GET', $pattern, $action, $name); }
    public function post($pattern, $action, $name = null) { return $this->add('POST', $pattern, $action, $name); }
    public function any($pattern, $action, $name = null)
    {
        $this->add('GET', $pattern, $action, $name);
        $this->add('POST', $pattern, $action, null);
        return $this;
    }

    private function add($method, $pattern, $action, $name)
    {
        $this->routes[$method][] = array(
            'pattern' => $pattern,
            'regex'   => $this->compile($pattern),
            'action'  => $action,
        );
        if ($name !== null) { $this->names[$name] = $pattern; }
        return $this;
    }

    /**
     * {slug} => un segmento; {id:\d+} => con restricción; {path*} => resto de la ruta.
     * Se usa # como delimitador para no tener que escapar las barras de la ruta.
     */
    private function compile($pattern)
    {
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\*)?(?::([^}]+))?\}|([^{}]+)/', function ($m) {
            if (isset($m[4]) && $m[4] !== '') {
                // Tramo literal de la ruta: se escapa entero.
                return preg_quote($m[4], '#');
            }
            $name = $m[1];
            if (!empty($m[2])) { return '(?P<' . $name . '>.+)'; }
            $rule = isset($m[3]) && $m[3] !== '' ? $m[3] : '[^/]+';
            return '(?P<' . $name . '>' . $rule . ')';
        }, $pattern);
        return '#^' . $regex . '$#u';
    }

    /**
     * @return array{0:mixed,1:array}|null  [acción, parámetros]
     */
    public function match($method, $path)
    {
        $method = $method === 'POST' ? 'POST' : 'GET';
        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['regex'], $path, $m)) {
                $params = array();
                foreach ($m as $k => $v) {
                    if (!is_int($k)) { $params[$k] = $v; }
                }
                return array($route['action'], $params);
            }
        }
        return null;
    }

    /** ¿La ruta existe con otro verbo? Sirve para responder 405 en vez de 404. */
    public function pathExists($path)
    {
        foreach (array('GET', 'POST') as $m) {
            foreach ($this->routes[$m] as $route) {
                if (preg_match($route['regex'], $path)) { return true; }
            }
        }
        return false;
    }
}
