<?php
declare(strict_types=1);

namespace Fel\Certificador;

use Fel\Core\Config;

/**
 * Selecciona el adaptador de certificador segun la configuracion.
 */
final class Fabrica
{
    public static function crear(?string $proveedor = null): CertificadorInterface
    {
        $proveedor ??= (string) Config::get('certificador.proveedor', 'simulador');
        $proveedor = strtolower(trim($proveedor));

        if ($proveedor === 'simulador') {
            return new SimuladorCertificador();
        }

        if ($proveedor === 'infile') {
            return new InfileCertificador();
        }

        /** @var array<string,mixed>|null $config */
        $config = Config::get('certificador.' . $proveedor);

        if (!is_array($config) || $config === []) {
            throw new \RuntimeException(
                "Certificador '{$proveedor}' no configurado. Agregue la seccion " .
                "certificador.{$proveedor} en config/config.php, o use 'simulador' para pruebas."
            );
        }

        return new GenericoRestCertificador($config, $proveedor);
    }

    /** @return list<string> */
    public static function disponibles(): array
    {
        $config = (array) Config::get('certificador', []);
        $claves = array_filter(
            array_keys($config),
            static fn ($clave): bool => is_array($config[$clave]) && $clave !== 'proveedor'
        );

        return array_values(array_unique(array_merge(['simulador'], array_map('strval', $claves))));
    }
}
