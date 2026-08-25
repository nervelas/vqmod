<?php
declare(strict_types=1);

namespace Fel\Certificador;

use DOMDocument;
use Fel\Core\Config;
use Fel\Dte\XmlBuilder;

/**
 * Certificador simulado. NO tiene validez fiscal.
 *
 * Sirve para desarrollar, capacitar al personal y correr las pruebas
 * automatizadas sin gastar folios ni depender de la red. Genera una
 * estructura de respuesta identica a la de un certificador real:
 * numero de autorizacion (UUID), serie, numero y XML certificado.
 */
final class SimuladorCertificador implements CertificadorInterface
{
    public function __construct(
        private string $nitCertificador = '12345679',
        private string $nombreCertificador = 'CERTIFICADOR SIMULADO (SIN VALIDEZ FISCAL)',
    ) {
    }

    public function nombre(): string
    {
        return 'simulador';
    }

    public function firmar(string $xml, bool $esAnulacion = false): string
    {
        // El simulador no aplica firma criptografica real.
        return $xml;
    }

    public function certificar(string $xmlFirmado, string $identificadorInterno): Resultado
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;

        if (!@$doc->loadXML($xmlFirmado)) {
            return Resultado::error('El XML enviado al simulador no es valido.', 'XML_INVALIDO', $xmlFirmado);
        }

        $uuid   = self::uuid();
        $serie  = strtoupper(substr(hash('crc32b', $uuid . '-serie'), 0, 8));
        $numero = (string) $this->siguienteCorrelativo();
        $fecha  = \Fel\Core\Validator::fechaHoraSat();

        $dte = $doc->getElementsByTagNameNS(XmlBuilder::NS_DTE, 'DTE')->item(0);
        if ($dte === null) {
            return Resultado::error('No se encontro el nodo dte:DTE.', 'ESTRUCTURA', $xmlFirmado);
        }

        $certificacion = $doc->createElementNS(XmlBuilder::NS_DTE, 'dte:Certificacion');
        $certificacion->appendChild($this->nodo($doc, 'dte:NITCertificador', $this->nitCertificador));
        $certificacion->appendChild($this->nodo($doc, 'dte:NombreCertificador', $this->nombreCertificador));

        $autorizacion = $doc->createElementNS(XmlBuilder::NS_DTE, 'dte:NumeroAutorizacion');
        $autorizacion->setAttribute('Serie', $serie);
        $autorizacion->setAttribute('Numero', $numero);
        $autorizacion->appendChild($doc->createTextNode($uuid));
        $certificacion->appendChild($autorizacion);

        $certificacion->appendChild($this->nodo($doc, 'dte:FechaHoraCertificacion', $fecha));
        $dte->appendChild($certificacion);

        return new Resultado(
            exito: true,
            uuid: $uuid,
            serie: $serie,
            numero: $numero,
            fechaCertificacion: $fecha,
            xmlCertificado: (string) $doc->saveXML(),
            mensajes: ['Certificado por el SIMULADOR. Este documento no tiene validez fiscal.'],
            respuestaCruda: '{"simulador":true}',
        );
    }

    public function anular(string $xmlAnulacionFirmado, string $identificadorInterno): Resultado
    {
        $uuid = self::uuid();

        return new Resultado(
            exito: true,
            uuid: $uuid,
            fechaCertificacion: \Fel\Core\Validator::fechaHoraSat(),
            xmlCertificado: $xmlAnulacionFirmado,
            mensajes: ['Anulacion simulada. Sin validez fiscal.'],
            respuestaCruda: '{"simulador":true}',
        );
    }

    private function nodo(DOMDocument $doc, string $nombre, string $valor): \DOMElement
    {
        $nodo = $doc->createElementNS(XmlBuilder::NS_DTE, $nombre);
        $nodo->appendChild($doc->createTextNode($valor));

        return $nodo;
    }

    public static function uuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return strtoupper(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    private function siguienteCorrelativo(): int
    {
        $dir     = (string) Config::get('rutas.almacen', dirname(__DIR__, 2) . '/storage');
        $archivo = $dir . '/simulador-correlativo.txt';

        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $puntero = @fopen($archivo, 'c+');
        if ($puntero === false) {
            return random_int(1, 999999);
        }

        flock($puntero, LOCK_EX);
        $actual = (int) stream_get_contents($puntero);
        $nuevo  = $actual + 1;
        ftruncate($puntero, 0);
        rewind($puntero);
        fwrite($puntero, (string) $nuevo);
        fflush($puntero);
        flock($puntero, LOCK_UN);
        fclose($puntero);

        return $nuevo;
    }
}
