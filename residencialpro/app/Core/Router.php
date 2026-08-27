<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string,array<int,array{patron:string,regex:string,params:array,destino:string}>> */
    private array $rutas = ['GET' => [], 'POST' => []];

    public function get(string $patron, string $destino): void
    {
        $this->agregar('GET', $patron, $destino);
    }

    public function post(string $patron, string $destino): void
    {
        $this->agregar('POST', $patron, $destino);
    }

    public function cualquiera(string $patron, string $destino): void
    {
        $this->agregar('GET', $patron, $destino);
        $this->agregar('POST', $patron, $destino);
    }

    private function agregar(string $metodo, string $patron, string $destino): void
    {
        $params = [];
        $regex  = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(:[^}]+)?\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                $tipo = isset($m[2]) ? substr($m[2], 1) : '[^/]+';
                if ($tipo === 'num') {
                    $tipo = '\d+';
                }
                return '(' . $tipo . ')';
            },
            $patron
        );
        $this->rutas[$metodo][] = [
            'patron'  => $patron,
            'regex'   => '#^' . $regex . '$#u',
            'params'  => $params,
            'destino' => $destino,
        ];
    }

    public function despachar(string $metodo, string $uri): void
    {
        $metodo = $metodo === 'POST' ? 'POST' : 'GET';
        $uri    = $uri === '' ? '/' : $uri;
        foreach ($this->rutas[$metodo] as $r) {
            if (preg_match($r['regex'], $uri, $m)) {
                array_shift($m);
                $args = [];
                foreach ($r['params'] as $i => $nombre) {
                    $args[$nombre] = $m[$i] ?? null;
                }
                $this->invocar($r['destino'], $args);
                return;
            }
        }
        // ¿Existe con otro método?
        $otro = $metodo === 'GET' ? 'POST' : 'GET';
        foreach ($this->rutas[$otro] as $r) {
            if (preg_match($r['regex'], $uri)) {
                Respuesta::abortar(405, 'Método no permitido para esta dirección.');
            }
        }
        Respuesta::abortar(404, 'La página que busca no existe o fue movida.');
    }

    private function invocar(string $destino, array $args): void
    {
        [$clase, $metodo] = explode('@', $destino, 2);
        $fqn = 'App\\Controllers\\' . $clase;
        if (!class_exists($fqn)) {
            throw new \RuntimeException('Controlador inexistente: ' . $fqn);
        }
        $obj = new $fqn();
        if (!method_exists($obj, $metodo)) {
            throw new \RuntimeException('Acción inexistente: ' . $fqn . '::' . $metodo);
        }
        $reflex = new \ReflectionMethod($obj, $metodo);
        $pasar  = [];
        foreach ($reflex->getParameters() as $p) {
            $nombre = $p->getName();
            $valor  = $args[$nombre] ?? null;
            if ($valor === null && $p->isDefaultValueAvailable()) {
                $valor = $p->getDefaultValue();
            }
            $tipo = $p->getType();
            if ($tipo instanceof \ReflectionNamedType && $tipo->getName() === 'int') {
                $valor = (int) $valor;
            }
            $pasar[] = $valor;
        }
        $obj->{$metodo}(...$pasar);
    }
}
