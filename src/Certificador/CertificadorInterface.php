<?php
declare(strict_types=1);

namespace Fel\Certificador;

/**
 * Contrato comun para cualquier certificador autorizado por SAT.
 *
 * En Guatemala NO se transmite directamente a SAT: el emisor entrega el XML
 * a un Certificador de Documentos Tributarios Electronicos, que lo valida,
 * lo certifica (le asigna el numero de autorizacion / UUID) y lo transmite
 * a SAT. Cambiar de certificador solo debe implicar cambiar de adaptador.
 */
interface CertificadorInterface
{
    public function nombre(): string;

    /**
     * Firma electronicamente el XML del DTE con la llave del emisor.
     * Casi todos los certificadores ofrecen este servicio; si usted firma
     * localmente, la implementacion puede devolver el XML sin cambios.
     */
    public function firmar(string $xml, bool $esAnulacion = false): string;

    /**
     * Envia el XML firmado y devuelve el DTE certificado.
     */
    public function certificar(string $xmlFirmado, string $identificadorInterno): Resultado;

    /**
     * Envia el XML de anulacion firmado.
     */
    public function anular(string $xmlAnulacionFirmado, string $identificadorInterno): Resultado;
}
