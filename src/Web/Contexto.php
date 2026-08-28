<?php
declare(strict_types=1);

namespace Fel\Web;

use Fel\Plataforma\Empresa;
use Fel\Repositorio\EmpresaRepositorio;

/**
 * Resuelve la empresa sobre la que trabaja la peticion actual.
 *
 * Es el punto unico donde se decide "de quien son los datos que se estan
 * viendo". Todo lo demas (repositorios, servicios) recibe ese id ya resuelto,
 * de modo que no exista ninguna ruta en la que se puedan mezclar datos de
 * dos contribuyentes.
 */
final class Contexto
{
    private static ?Empresa $empresa = null;
    private static bool $resuelto    = false;

    public static function empresa(): ?Empresa
    {
        if (self::$resuelto) {
            return self::$empresa;
        }

        self::$resuelto = true;
        $empresaId      = Sesion::empresaActiva();

        if ($empresaId === null) {
            return null;
        }

        $empresa = (new EmpresaRepositorio())->buscar($empresaId);

        // Un usuario normal no puede quedar apuntando a una empresa que no es
        // la suya, aunque la sesion traiga otro valor.
        if ($empresa !== null && !Sesion::esSuperadmin()) {
            $propia = Sesion::usuario()['empresa_id'] ?? null;
            if ($propia === null || (int) $propia !== $empresa->id()) {
                return null;
            }
        }

        self::$empresa = $empresa;

        return self::$empresa;
    }

    /** Empresa activa o error: para las rutas que no tienen sentido sin una. */
    public static function empresaRequerida(): Empresa
    {
        $empresa = self::empresa();

        if ($empresa === null) {
            throw new \RuntimeException('No hay una empresa seleccionada.');
        }

        return $empresa;
    }

    public static function empresaId(): int
    {
        return self::empresaRequerida()->id();
    }

    public static function hayEmpresa(): bool
    {
        return self::empresa() !== null;
    }

    public static function reiniciar(): void
    {
        self::$empresa  = null;
        self::$resuelto = false;
    }
}
