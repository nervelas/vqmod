<?php
declare(strict_types=1);

namespace Fel\Servicio;

use Fel\Certificador\CertificadorInterface;
use Fel\Certificador\Fabrica;
use Fel\Certificador\Resultado;
use Fel\Certificador\SimuladorCertificador;
use Fel\Core\Logger;
use Fel\Dte\Calculator;
use Fel\Dte\Documento;
use Fel\Dte\XmlBuilder;
use Fel\Plataforma\Empresa;
use Fel\Repositorio\BitacoraRepositorio;
use Fel\Repositorio\DocumentoRepositorio;

/**
 * Orquesta la emision de un DTE de principio a fin:
 *
 *   validar -> generar XML -> guardar -> firmar -> certificar -> registrar
 *
 * Si algo falla por red, el documento queda en estado PENDIENTE y puede
 * reintentarse (contingencia). Si el certificador RECHAZA el contenido,
 * el documento queda RECHAZADO: hay que corregirlo y emitir uno nuevo,
 * nunca reenviar el mismo.
 */
final class FacturacionService
{
    public function __construct(
        private Empresa $empresa,
        private ?CertificadorInterface $certificador = null,
        private ?DocumentoRepositorio $documentos = null,
        private ?BitacoraRepositorio $bitacora = null,
    ) {
        $this->certificador ??= Fabrica::paraEmpresa($empresa);
        $this->documentos   ??= new DocumentoRepositorio($empresa->id());
        $this->bitacora     ??= new BitacoraRepositorio($empresa->id());
    }

    public function emitir(Documento $documento, string $usuario = '', ?int $clienteId = null): ResultadoEmision
    {
        // El emisor siempre es el de la empresa activa: nunca se toma del
        // formulario, para que nadie pueda facturar a nombre de otro NIT.
        $documento->emisor                = $this->empresa->emisor();
        $documento->limiteConsumidorFinal = $this->empresa->limiteConsumidorFinal();

        $problemas = $this->empresa->problemas();
        if ($problemas !== []) {
            return new ResultadoEmision(exito: false, errores: $problemas, estado: 'INVALIDO');
        }

        $documento->completarFrases();
        Calculator::calcular($documento);

        $errores = $documento->validar();
        if ($errores !== []) {
            return new ResultadoEmision(exito: false, errores: $errores, estado: 'INVALIDO');
        }

        $identificador = SimuladorCertificador::uuid();
        $xml           = (new XmlBuilder())->construir($documento);

        $documentoId = $this->documentos->crear(
            documento: $documento,
            identificador: $identificador,
            xmlEnviado: $xml,
            estado: DocumentoRepositorio::PENDIENTE,
            usuario: $usuario,
            clienteId: $clienteId,
        );

        Almacen::guardarXml($xml, $documento->tipo, $identificador, 'enviado');

        return $this->transmitir($documentoId, $xml, $documento->tipo, $identificador);
    }

    /**
     * Reintenta un documento que quedo en contingencia. Reutiliza el mismo
     * XML e identificador para que el certificador lo trate como el mismo
     * documento y no se dupliquen folios.
     */
    public function reintentar(int $documentoId): ResultadoEmision
    {
        $fila = $this->documentos->buscar($documentoId);

        if ($fila === null) {
            return new ResultadoEmision(exito: false, errores: ['Documento no encontrado.'], estado: 'INVALIDO');
        }

        if ($fila['estado'] !== DocumentoRepositorio::PENDIENTE) {
            return new ResultadoEmision(
                exito: $fila['estado'] === DocumentoRepositorio::CERTIFICADO,
                documentoId: $documentoId,
                errores: ['El documento no esta en contingencia (estado actual: ' . $fila['estado'] . ').'],
                estado: (string) $fila['estado'],
            );
        }

        return $this->transmitir(
            $documentoId,
            (string) $fila['xml_enviado'],
            (string) $fila['tipo'],
            (string) $fila['identificador'],
        );
    }

    private function transmitir(int $documentoId, string $xml, string $tipo, string $identificador): ResultadoEmision
    {
        try {
            $xmlFirmado = $this->certificador->firmar($xml);
            $this->bitacora->registrar($documentoId, 'FIRMA', true, 'Documento firmado.');
        } catch (\Throwable $error) {
            $mensaje = 'Error al firmar: ' . $error->getMessage();
            Logger::error($mensaje, ['documento' => $documentoId]);
            $this->bitacora->registrar($documentoId, 'FIRMA', false, $mensaje);
            $this->documentos->marcarFallido($documentoId, $mensaje, reintentable: true);

            return new ResultadoEmision(
                exito: false,
                documentoId: $documentoId,
                errores: [$mensaje],
                estado: DocumentoRepositorio::PENDIENTE,
            );
        }

        try {
            $resultado = $this->certificador->certificar($xmlFirmado, $identificador);
        } catch (\Throwable $error) {
            $resultado = Resultado::error(
                'Error al certificar: ' . $error->getMessage(),
                'EXCEPCION',
                reintentable: true
            );
        }

        $this->bitacora->registrar(
            $documentoId,
            'CERTIFICACION',
            $resultado->exito,
            $resultado->mensaje(),
            $resultado->respuestaCruda
        );

        if (!$resultado->exito) {
            $this->documentos->marcarFallido($documentoId, $resultado->mensaje(), $resultado->reintentable);

            return new ResultadoEmision(
                exito: false,
                documentoId: $documentoId,
                resultado: $resultado,
                errores: $resultado->mensajes,
                estado: $resultado->reintentable
                    ? DocumentoRepositorio::PENDIENTE
                    : DocumentoRepositorio::RECHAZADO,
            );
        }

        $this->documentos->marcarCertificado($documentoId, $resultado, $this->certificador->nombre());

        if ($resultado->xmlCertificado !== '') {
            Almacen::guardarXml($resultado->xmlCertificado, $tipo, $identificador, 'certificado');
        }

        Logger::info('DTE certificado', [
            'documento' => $documentoId,
            'uuid'      => $resultado->uuid,
            'serie'     => $resultado->serie,
            'numero'    => $resultado->numero,
        ]);

        return new ResultadoEmision(
            exito: true,
            documentoId: $documentoId,
            resultado: $resultado,
            estado: DocumentoRepositorio::CERTIFICADO,
        );
    }
}
