<?php
declare(strict_types=1);

namespace Fel\Certificador;

use Fel\Core\Config;
use Fel\Core\Logger;

/**
 * Adaptador para INFILE (certificador autorizado por SAT).
 *
 * Flujo: 1) firma del XML con el servicio de firma  ->  2) certificacion.
 *
 * ATENCION: las URL y los nombres de cabecera se leen de la configuracion.
 * Los valores por omision corresponden a los endpoints publicos de uso
 * corriente, pero DEBE confirmarlos contra el manual tecnico que le entregue
 * su certificador al contratar el servicio; cambian entre planes y versiones
 * de API. No modifique el codigo: ajuste config/config.php.
 */
final class InfileCertificador implements CertificadorInterface
{
    public function nombre(): string
    {
        return 'infile';
    }

    public function firmar(string $xml, bool $esAnulacion = false): string
    {
        $url = (string) Config::requerido('certificador.infile.url_firma');

        $peticion = [
            'llave'        => (string) Config::requerido('certificador.infile.llave_firma'),
            'archivo'      => base64_encode($xml),
            'codigo'       => (string) Config::get('certificador.infile.codigo_firma', 'DTE'),
            'alias'        => (string) Config::requerido('certificador.infile.alias_firma'),
            'es_anulacion' => $esAnulacion ? 'S' : 'N',
        ];

        $respuesta = HttpClient::post(
            $url,
            (string) json_encode($peticion, JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json']
        );

        Logger::info('Firma INFILE', ['codigo_http' => $respuesta['codigo']]);

        $datos = json_decode($respuesta['cuerpo'], true);

        if (!is_array($datos)) {
            throw new \RuntimeException(
                'El servicio de firma no devolvio JSON valido (HTTP ' . $respuesta['codigo'] . '): '
                . substr($respuesta['cuerpo'] . $respuesta['error'], 0, 500)
            );
        }

        $exito = (bool) ($datos['resultado'] ?? false);
        if (!$exito) {
            throw new \RuntimeException(
                'El servicio de firma rechazo el documento: ' . (string) ($datos['descripcion'] ?? 'sin descripcion')
            );
        }

        $firmado = (string) ($datos['archivo'] ?? '');
        $decodificado = base64_decode($firmado, true);

        return $decodificado !== false && str_contains($decodificado, '<') ? $decodificado : $firmado;
    }

    public function certificar(string $xmlFirmado, string $identificadorInterno): Resultado
    {
        return $this->enviar(
            (string) Config::requerido('certificador.infile.url_certificacion'),
            $xmlFirmado,
            $identificadorInterno
        );
    }

    public function anular(string $xmlAnulacionFirmado, string $identificadorInterno): Resultado
    {
        return $this->enviar(
            (string) Config::get(
                'certificador.infile.url_anulacion',
                (string) Config::requerido('certificador.infile.url_certificacion')
            ),
            $xmlAnulacionFirmado,
            $identificadorInterno
        );
    }

    private function enviar(string $url, string $xml, string $identificador): Resultado
    {
        /** @var array<string,string> $mapa */
        $mapa = (array) Config::get('certificador.infile.cabeceras', [
            'usuario'       => 'UsuarioApi',
            'llave'         => 'LlaveApi',
            'identificador' => 'Identificador',
        ]);

        $cabeceras = [
            'Content-Type'          => 'application/xml; charset=utf-8',
            $mapa['usuario']        => (string) Config::requerido('certificador.infile.usuario_api'),
            $mapa['llave']          => (string) Config::requerido('certificador.infile.llave_api'),
            $mapa['identificador']  => $identificador,
        ];

        $respuesta = HttpClient::post($url, $xml, $cabeceras);

        Logger::info('Certificacion INFILE', [
            'codigo_http'   => $respuesta['codigo'],
            'identificador' => $identificador,
        ]);

        if ($respuesta['codigo'] === 0) {
            return Resultado::error(
                'No se pudo contactar al certificador: ' . $respuesta['error'],
                'SIN_CONEXION',
                $respuesta['cuerpo'],
                reintentable: true
            );
        }

        $datos = json_decode($respuesta['cuerpo'], true);

        if (!is_array($datos)) {
            return Resultado::error(
                'Respuesta no reconocida del certificador (HTTP ' . $respuesta['codigo'] . ').',
                'RESPUESTA_INVALIDA',
                $respuesta['cuerpo'],
                reintentable: $respuesta['codigo'] >= 500
            );
        }

        return RespuestaJson::interpretar($datos, $respuesta['cuerpo'], $respuesta['codigo']);
    }
}
