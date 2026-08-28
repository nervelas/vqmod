<?php
declare(strict_types=1);

namespace Fel\Certificador;

use Fel\Core\Config;
use Fel\Plataforma\Empresa;

/**
 * Selecciona el adaptador de certificador que corresponde.
 */
final class Fabrica
{
    /**
     * Certificador de una empresa concreta, con sus propias credenciales.
     * Es la via normal en una instalacion que atiende a varios emisores.
     */
    public static function paraEmpresa(Empresa $empresa): CertificadorInterface
    {
        return self::crear($empresa->proveedorCertificador(), $empresa->configCertificador());
    }

    /**
     * @param array<string,mixed> $config Credenciales. Si viene vacio se leen
     *                                    de config/config.php.
     */
    public static function crear(?string $proveedor = null, array $config = []): CertificadorInterface
    {
        $proveedor ??= (string) Config::get('certificador.proveedor', 'simulador');
        $proveedor   = strtolower(trim($proveedor));

        if ($proveedor === '' || $proveedor === 'simulador') {
            return new SimuladorCertificador();
        }

        if ($proveedor === 'infile') {
            return new InfileCertificador($config);
        }

        if ($config === []) {
            /** @var array<string,mixed>|null $config */
            $config = Config::get('certificador.' . $proveedor);
        }

        if (!is_array($config) || $config === []) {
            throw new \RuntimeException(
                "Certificador '{$proveedor}' sin configurar. Complete las credenciales de la empresa, "
                . "o use 'simulador' para pruebas."
            );
        }

        return new GenericoRestCertificador($config, $proveedor);
    }

    /**
     * Proveedores para los que hay adaptador. Cualquier otro nombre se atiende
     * con el adaptador REST generico declarando su configuracion.
     *
     * @return array<string,string>
     */
    public static function proveedores(): array
    {
        return [
            'simulador' => 'Simulador (pruebas, sin validez fiscal)',
            'infile'    => 'INFILE',
            'generico'  => 'Otro certificador (REST configurable)',
        ];
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
