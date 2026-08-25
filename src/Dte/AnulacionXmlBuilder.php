<?php
declare(strict_types=1);

namespace Fel\Dte;

use DOMDocument;
use Fel\Core\Config;
use Fel\Core\Validator;

/**
 * XML de anulacion de un DTE ya certificado.
 *
 * Reglas de SAT que conviene recordar:
 *  - La anulacion se hace contra el numero de autorizacion (UUID) del DTE.
 *  - El plazo para anular esta limitado por el periodo de la declaracion;
 *    pasado ese plazo el camino correcto es una nota de credito, no una anulacion.
 *  - Una anulacion tambien es un documento certificado: queda registrada en SAT.
 */
final class AnulacionXmlBuilder
{
    public const NS_DTE = 'http://www.sat.gob.gt/dte/fel/0.1.0';

    public function construir(
        string $uuid,
        string $nitEmisor,
        string $idReceptor,
        string $fechaEmisionOriginal,
        string $motivo,
        ?string $fechaAnulacion = null,
    ): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput       = (bool) Config::get('xml.formato_legible', false);
        $doc->preserveWhiteSpace = false;

        $raiz = $doc->createElementNS(self::NS_DTE, 'dte:GTAnulacionDocumento');
        $raiz->setAttribute('Version', (string) Config::get('xml.version_anulacion', '0.1'));
        $doc->appendChild($raiz);

        $sat = $doc->createElementNS(self::NS_DTE, 'dte:SAT');
        $raiz->appendChild($sat);

        $anulacion = $doc->createElementNS(self::NS_DTE, 'dte:AnulacionDTE');
        $anulacion->setAttribute('ID', 'DatosCertificados');
        $sat->appendChild($anulacion);

        $generales = $doc->createElementNS(self::NS_DTE, 'dte:DatosGenerales');
        $generales->setAttribute('ID', 'DatosAnulacion');
        $generales->setAttribute('NumeroDocumentoAAnular', $uuid);
        $generales->setAttribute('NITEmisor', Validator::normalizarNit($nitEmisor));
        $generales->setAttribute('IDReceptor', $idReceptor);
        $generales->setAttribute('FechaEmisionDocumentoAnular', $fechaEmisionOriginal);
        $generales->setAttribute('FechaHoraAnulacion', $fechaAnulacion ?? Validator::fechaHoraSat());
        $generales->setAttribute('MotivoAnulacion', $motivo);
        $anulacion->appendChild($generales);

        return (string) $doc->saveXML();
    }
}
