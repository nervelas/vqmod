<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controlador
{
    protected function mostrar(string $vista, array $datos = [], string $layout = 'app'): void
    {
        echo Vista::render($vista, $datos, $layout);
        exit;
    }

    protected function renderizar(string $vista, array $datos = [], string $layout = 'app'): string
    {
        return Vista::render($vista, $datos, $layout);
    }

    protected function json(array $datos, int $codigo = 200): void
    {
        Respuesta::json($datos, $codigo);
    }

    protected function redirigir(string $ruta): void
    {
        Respuesta::redirigir($ruta);
    }

    protected function exito(string $mensaje, string $ruta): void
    {
        Sesion::flash('exito', $mensaje);
        Sesion::limpiarViejos();
        Respuesta::redirigir($ruta);
    }

    protected function error(string $mensaje, string $ruta): void
    {
        Sesion::flash('error', $mensaje);
        Sesion::guardarViejos($_POST);
        Respuesta::redirigir($ruta);
    }

    protected function exigirRol(string ...$roles): void
    {
        if (Auth::invitado()) {
            Sesion::set('_destino', Peticion::uri());
            Respuesta::redirigir('/acceso');
        }
        if ($roles !== [] && !Auth::es(...$roles)) {
            Auditoria::registrar('acceso_denegado', null, null, 'Ruta: ' . Peticion::uri());
            Respuesta::abortar(403, 'Su perfil no tiene permiso para esta sección.');
        }
    }

    protected function post(): bool
    {
        return Peticion::metodo() === 'POST';
    }

    protected function verificarCsrf(): void
    {
        Csrf::verificar();
    }

    /** Paginación simple: devuelve [limite, desplazamiento, pagina] */
    protected function paginacion(int $porPagina = 25): array
    {
        $pagina = max(1, Peticion::entero('p', 1));
        return [$porPagina, ($pagina - 1) * $porPagina, $pagina];
    }
}
