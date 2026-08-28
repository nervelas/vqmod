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
    /**
     * @param array<string,mixed> $config Credenciales y URL de esta empresa.
     *                                    Si viene vacio se leen de config/config.php
     *                                    (instalacion de un solo emisor).
     */
    public function __construct(private array $config = [])
    {
    }

    /** Lee un ajuste de la empresa, o del config global si no lo trae. */
    private function ajuste(string $clave, mixed $porDefecto = null): mixed
    {
        if (array_key_exists($clave, $this->config) && $this->config[$clave] !== '') {
            return $this->config[$clave];
        }

        return Config::get('certificador.infile.' . $clave, $porDefecto);
    }

    private function ajusteRequerido(string $clave): string
    {
        $valor = $this->ajuste($clave);

        if ($valor === null || $valor === '') {
            throw new \RuntimeException(
                "Falta la credencial '{$clave}' del certificador INFILE. "
                . 'Complete los datos del certificador de la empresa.'
            );
        }

        return (string) $valor;
    }

    public function nombre(): string
    {
        return 'infile';
    }

    public function firmar(string $xml, bool $esAnulacion = false): string
    {
        $url = $this->ajusteRequerido('url_firma');

        $peticion = [
            'llave'        => $this->ajusteRequerido('llave_firma'),
            'archivo'      => base64_encode($xml),
            'codigo'       => (string) $this->ajuste('codigo_firma', 'DTE'),
            'alias'        => $this->ajusteRequerido('alias_firma'),
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
            $this->ajusteRequerido('url_certificacion'),
            $xmlFirmado,
            $identificadorInterno
        );
    }

    public function anular(string $xmlAnulacionFirmado, string $identificadorInterno): Resultado
    {
        $url = (string) $this->ajuste('url_anulacion', '');

        return $this->enviar(
            $url !== '' ? $url : $this->ajusteRequerido('url_certificacion'),
            $xmlAnulacionFirmado,
            $identificadorInterno
        );
    }

    private function enviar(string $url, string $xml, string $identificador): Resultado
    {
        /** @var array<string,string> $mapa */
        $mapa = (array) $this->ajuste('cabeceras', []);
        $mapa += ['usuario' => 'UsuarioApi', 'llave' => 'LlaveApi', 'identificador' => 'Identificador'];

        $cabeceras = [
            'Content-Type'         => 'application/xml; charset=utf-8',
            $mapa['usuario']       => $this->ajusteRequerido('usuario_api'),
            $mapa['llave']         => $this->ajusteRequerido('llave_api'),
            $mapa['identificador'] => $identificador,
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
