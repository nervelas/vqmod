<?php
declare(strict_types=1);

namespace Fel\Servicio;

use Fel\Certificador\CertificadorInterface;
use Fel\Certificador\Fabrica;
use Fel\Certificador\Resultado;
use Fel\Certificador\SimuladorCertificador;
use Fel\Core\Config;
use Fel\Dte\AnulacionXmlBuilder;
use Fel\Repositorio\AnulacionRepositorio;
use Fel\Repositorio\BitacoraRepositorio;
use Fel\Repositorio\DocumentoRepositorio;

/**
 * Anulacion de un DTE ya certificado.
 *
 * Antes de anular, verifique el plazo: SAT permite anular dentro de la
 * ventana definida en la normativa vigente (habitualmente el mismo periodo
 * de declaracion). Fuera de ese plazo el instrumento correcto es una
 * NOTA DE CREDITO, que este mismo sistema emite como tipo NCRE.
 */
final class AnulacionService
{
    public function __construct(
        private ?CertificadorInterface $certificador = null,
        private ?DocumentoRepositorio $documentos = null,
        private ?AnulacionRepositorio $anulaciones = null,
        private ?BitacoraRepositorio $bitacora = null,
    ) {
        $this->certificador ??= Fabrica::crear();
        $this->documentos   ??= new DocumentoRepositorio();
        $this->anulaciones  ??= new AnulacionRepositorio();
        $this->bitacora     ??= new BitacoraRepositorio();
    }

    /** @return array{exito:bool,mensaje:string,uuid:string} */
    public function anular(int $documentoId, string $motivo, string $usuario = ''): array
    {
        $documento = $this->documentos->buscar($documentoId);

        if ($documento === null) {
            return ['exito' => false, 'mensaje' => 'Documento no encontrado.', 'uuid' => ''];
        }

        if ($documento['estado'] !== DocumentoRepositorio::CERTIFICADO) {
            return [
                'exito'   => false,
                'mensaje' => 'Solo se pueden anular documentos certificados. Estado actual: ' . $documento['estado'],
                'uuid'    => '',
            ];
        }

        if (trim($motivo) === '') {
            return ['exito' => false, 'mensaje' => 'Debe indicar el motivo de la anulacion.', 'uuid' => ''];
        }

        $aviso = $this->avisoDePlazo((string) $documento['fecha_emision']);

        $xml = (new AnulacionXmlBuilder())->construir(
            uuid: (string) $documento['uuid'],
            nitEmisor: (string) $documento['emisor_nit'],
            idReceptor: (string) $documento['receptor_id'],
            fechaEmisionOriginal: (string) $documento['fecha_emision'],
            motivo: $motivo,
        );

        $anulacionId   = $this->anulaciones->crear($documentoId, $motivo, $xml, $usuario);
        $identificador = SimuladorCertificador::uuid();

        Almacen::guardarXml($xml, 'ANULACION', $identificador, 'enviado');

        try {
            $xmlFirmado = $this->certificador->firmar($xml, esAnulacion: true);
            $resultado  = $this->certificador->anular($xmlFirmado, $identificador);
        } catch (\Throwable $error) {
            $resultado = Resultado::error('Error al anular: ' . $error->getMessage(), 'EXCEPCION');
        }

        $this->bitacora->registrar(
            $documentoId,
            'ANULACION',
            $resultado->exito,
            $resultado->mensaje(),
            $resultado->respuestaCruda
        );

        if (!$resultado->exito) {
            $this->anulaciones->fallar($anulacionId, $resultado->mensaje());

            return ['exito' => false, 'mensaje' => $resultado->mensaje(), 'uuid' => ''];
        }

        $this->anulaciones->completar(
            $anulacionId,
            $resultado->uuid,
            $resultado->fechaCertificacion,
            $resultado->xmlCertificado
        );
        $this->documentos->marcarAnulado($documentoId);

        return [
            'exito'   => true,
            'mensaje' => trim('Documento anulado. ' . $aviso . ' ' . $resultado->mensaje()),
            'uuid'    => $resultado->uuid,
        ];
    }

    /**
     * Advierte cuando la anulacion sale del plazo configurado. No bloquea:
     * la palabra final la tiene SAT a traves del certificador.
     */
    private function avisoDePlazo(string $fechaEmision): string
    {
        $dias = (int) Config::get('reglas.dias_maximos_anulacion', 0);

        if ($dias <= 0) {
            return '';
        }

        try {
            $emision = new \DateTimeImmutable($fechaEmision);
        } catch (\Exception) {
            return '';
        }

        $transcurridos = (new \DateTimeImmutable())->diff($emision)->days ?? 0;

        return $transcurridos > $dias
            ? sprintf(
                'AVISO: han pasado %d dias desde la emision (limite configurado: %d). '
                . 'Si el certificador la rechaza, emita una nota de credito (NCRE).',
                $transcurridos,
                $dias
            )
            : '';
    }
}
