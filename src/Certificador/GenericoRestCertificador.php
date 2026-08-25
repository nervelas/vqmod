<?php
declare(strict_types=1);

namespace Fel\Certificador;

use Fel\Core\Config;
use Fel\Core\Logger;

/**
 * Adaptador REST configurable.
 *
 * Permite conectar cualquier certificador autorizado por SAT (Digifact,
 * Guatefacturas, Megaprint, Certifika, etc.) SIN escribir codigo nuevo:
 * las URL, cabeceras, formato del cuerpo y nombres de campo de respuesta
 * se declaran en config/config.php.
 *
 * Marcadores admitidos dentro de la plantilla del cuerpo:
 *   {XML}            XML tal cual
 *   {XML_BASE64}     XML codificado en base64
 *   {IDENTIFICADOR}  identificador interno del documento
 */
final class GenericoRestCertificador implements CertificadorInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(private array $config, private string $etiqueta = 'generico')
    {
    }

    public function nombre(): string
    {
        return $this->etiqueta;
    }

    public function firmar(string $xml, bool $esAnulacion = false): string
    {
        $firma = $this->seccion('firma');

        if ($firma === [] || ($firma['habilitada'] ?? true) === false) {
            // El emisor firma por su cuenta o el certificador no expone servicio de firma.
            return $xml;
        }

        $respuesta = $this->llamar($firma, $xml, $esAnulacion ? 'ANULACION' : 'DTE');
        $datos     = json_decode($respuesta['cuerpo'], true);

        if (!is_array($datos)) {
            throw new \RuntimeException(
                'El servicio de firma no devolvio JSON valido (HTTP ' . $respuesta['codigo'] . '): '
                . substr($respuesta['cuerpo'] . $respuesta['error'], 0, 500)
            );
        }

        $campo    = (string) ($firma['campo_respuesta_xml'] ?? 'archivo');
        $firmado  = (string) ($datos[$campo] ?? '');

        if ($firmado === '') {
            throw new \RuntimeException(
                'El servicio de firma no devolvio el documento firmado en el campo "' . $campo . '". '
                . 'Respuesta: ' . substr($respuesta['cuerpo'], 0, 500)
            );
        }

        $decodificado = base64_decode($firmado, true);

        return $decodificado !== false && str_contains($decodificado, '<') ? $decodificado : $firmado;
    }

    public function certificar(string $xmlFirmado, string $identificadorInterno): Resultado
    {
        return $this->procesar('certificacion', $xmlFirmado, $identificadorInterno);
    }

    public function anular(string $xmlAnulacionFirmado, string $identificadorInterno): Resultado
    {
        $seccion = $this->seccion('anulacion') === [] ? 'certificacion' : 'anulacion';

        return $this->procesar($seccion, $xmlAnulacionFirmado, $identificadorInterno);
    }

    private function procesar(string $nombreSeccion, string $xml, string $identificador): Resultado
    {
        $seccion = $this->seccion($nombreSeccion);

        if ($seccion === []) {
            return Resultado::error(
                'Falta configurar certificador.' . $this->etiqueta . '.' . $nombreSeccion,
                'CONFIG_INCOMPLETA'
            );
        }

        $respuesta = $this->llamar($seccion, $xml, $identificador);

        Logger::info('Envio a certificador', [
            'certificador' => $this->etiqueta,
            'seccion'      => $nombreSeccion,
            'codigo_http'  => $respuesta['codigo'],
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

    /**
     * @param array<string,mixed> $seccion
     * @return array{codigo:int,cuerpo:string,error:string}
     */
    private function llamar(array $seccion, string $xml, string $identificador): array
    {
        $url = (string) ($seccion['url'] ?? '');

        if ($url === '') {
            throw new \RuntimeException('Falta la URL del certificador para esta operacion.');
        }

        /** @var array<string,string> $cabeceras */
        $cabeceras = (array) ($seccion['cabeceras'] ?? []);
        $cabeceras = $this->sustituirEnArreglo($cabeceras, $xml, $identificador);

        $formato = (string) ($seccion['formato'] ?? 'xml');

        if ($formato === 'json') {
            $plantilla = (array) ($seccion['plantilla'] ?? []);
            $cuerpo    = (string) json_encode(
                $this->sustituirEnArreglo($plantilla, $xml, $identificador),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $cabeceras['Content-Type'] ??= 'application/json';
        } elseif ($formato === 'base64') {
            $cuerpo = base64_encode($xml);
            $cabeceras['Content-Type'] ??= 'text/plain';
        } else {
            $cuerpo = $xml;
            $cabeceras['Content-Type'] ??= 'application/xml; charset=utf-8';
        }

        return HttpClient::post($url, $cuerpo, $cabeceras);
    }

    /**
     * @param array<string,mixed> $valores
     * @return array<string,string>
     */
    private function sustituirEnArreglo(array $valores, string $xml, string $identificador): array
    {
        $reemplazos = [
            '{XML}'           => $xml,
            '{XML_BASE64}'    => base64_encode($xml),
            '{IDENTIFICADOR}' => $identificador,
        ];

        $resultado = [];
        foreach ($valores as $clave => $valor) {
            $resultado[(string) $clave] = is_string($valor)
                ? strtr($valor, $reemplazos)
                : (string) $valor;
        }

        return $resultado;
    }

    /** @return array<string,mixed> */
    private function seccion(string $nombre): array
    {
        $valor = $this->config[$nombre] ?? [];

        return is_array($valor) ? $valor : [];
    }
}
