<?php
declare(strict_types=1);

namespace Fel\Certificador;

/**
 * Interpreta la respuesta JSON de un certificador.
 *
 * Los certificadores guatemaltecos usan estructuras parecidas pero no
 * identicas. Este lector acepta los nombres de campo mas comunes para
 * que el mismo codigo sirva con varios proveedores.
 *
 * @internal
 */
final class RespuestaJson
{
    /** @param array<string,mixed> $datos */
    public static function interpretar(array $datos, string $crudo, int $codigoHttp): Resultado
    {
        $exito = self::primerValor($datos, ['resultado', 'exito', 'success', 'Resultado'], false);
        $exito = filter_var($exito, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $exito;

        $mensajes = self::mensajes($datos);

        if (!$exito) {
            return new Resultado(
                exito: false,
                mensajes: $mensajes === [] ? ['El certificador rechazo el documento.'] : $mensajes,
                codigoError: (string) self::primerValor($datos, ['codigo', 'codigo_error', 'Codigo'], 'RECHAZADO'),
                respuestaCruda: $crudo,
                reintentable: $codigoHttp >= 500,
            );
        }

        $xml = (string) self::primerValor($datos, ['xml_certificado', 'xmlCertificado', 'xml_dte', 'archivo', 'XmlCertificado'], '');
        $decodificado = base64_decode($xml, true);
        if ($decodificado !== false && str_contains($decodificado, '<')) {
            $xml = $decodificado;
        }

        return new Resultado(
            exito: true,
            uuid: (string) self::primerValor($datos, ['uuid', 'UUID', 'numero_autorizacion', 'autorizacion'], ''),
            serie: (string) self::primerValor($datos, ['serie', 'Serie'], ''),
            numero: (string) self::primerValor($datos, ['numero', 'Numero', 'numero_dte'], ''),
            fechaCertificacion: (string) self::primerValor($datos, ['fecha', 'fecha_certificacion', 'FechaCertificacion'], ''),
            xmlCertificado: $xml,
            mensajes: $mensajes,
            respuestaCruda: $crudo,
        );
    }

    /**
     * @param array<string,mixed> $datos
     * @param list<string> $claves
     */
    private static function primerValor(array $datos, array $claves, mixed $porDefecto): mixed
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $datos) && $datos[$clave] !== null && $datos[$clave] !== '') {
                return $datos[$clave];
            }
        }

        return $porDefecto;
    }

    /**
     * @param array<string,mixed> $datos
     * @return list<string>
     */
    private static function mensajes(array $datos): array
    {
        $mensajes = [];

        foreach (['descripcion', 'mensaje', 'descripcion_errores', 'errores', 'mensajes', 'Descripcion'] as $clave) {
            if (!isset($datos[$clave])) {
                continue;
            }

            $valor = $datos[$clave];

            if (is_string($valor) && trim($valor) !== '') {
                $mensajes[] = trim($valor);
                continue;
            }

            if (is_array($valor)) {
                array_walk_recursive($valor, static function ($item) use (&$mensajes): void {
                    if (is_scalar($item) && trim((string) $item) !== '') {
                        $mensajes[] = trim((string) $item);
                    }
                });
            }
        }

        return array_values(array_unique($mensajes));
    }
}
