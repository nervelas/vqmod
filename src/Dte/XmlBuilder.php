<?php
declare(strict_types=1);

namespace Fel\Dte;

use DOMDocument;
use DOMElement;
use Fel\Core\Config;
use Fel\Core\Money;

/**
 * Construye el XML del DTE segun el esquema GT_Documento de SAT.
 *
 * Espacios de nombres (definidos por SAT):
 *   dte -> http://www.sat.gob.gt/dte/fel/0.2.0
 *   cfc -> complemento de factura cambiaria (abonos)
 *   cno -> complemento de referencias de nota de credito/debito
 *   cex -> complemento de exportacion
 *
 * El XML generado NO va firmado: la firma electronica la aplica el
 * servicio de firma del certificador con la llave del emisor. Ver
 * docs/03-certificadores.md.
 */
final class XmlBuilder
{
    public const NS_DTE = 'http://www.sat.gob.gt/dte/fel/0.2.0';
    public const NS_CFC = 'http://www.sat.gob.gt/dte/fel/CompCambiaria/0.1.0';
    public const NS_CNO = 'http://www.sat.gob.gt/face2/ComplementoReferenciaNota/0.1.0';
    public const NS_CEX = 'http://www.sat.gob.gt/face2/ComplementoExportaciones/0.1.0';

    private DOMDocument $doc;

    public function construir(Documento $documento): string
    {
        Calculator::calcular($documento);

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput       = (bool) Config::get('xml.formato_legible', false);
        $this->doc->preserveWhiteSpace = false;

        $raiz = $this->doc->createElementNS(self::NS_DTE, 'dte:GTDocumento');
        $raiz->setAttribute('Version', (string) Config::get('xml.version_documento', '0.1'));
        $this->doc->appendChild($raiz);

        $sat = $this->el('dte:SAT');
        $sat->setAttribute('ClaseDocumento', 'dte');
        $raiz->appendChild($sat);

        $dte = $this->el('dte:DTE');
        $dte->setAttribute('ID', 'DatosCertificados');
        $sat->appendChild($dte);

        $emision = $this->el('dte:DatosEmision');
        $emision->setAttribute('ID', 'DatosEmision');
        $dte->appendChild($emision);

        $emision->appendChild($this->datosGenerales($documento));
        $emision->appendChild($this->emisor($documento->emisor));
        $emision->appendChild($this->receptor($documento->receptor));
        $emision->appendChild($this->frases($documento));
        $emision->appendChild($this->items($documento));
        $emision->appendChild($this->totales($documento));

        $complementos = $this->complementos($documento);
        if ($complementos !== null) {
            $emision->appendChild($complementos);
        }

        $adenda = $this->adenda($documento);
        if ($adenda !== null) {
            $sat->appendChild($adenda);
        }

        return (string) $this->doc->saveXML();
    }

    private function datosGenerales(Documento $documento): DOMElement
    {
        $nodo = $this->el('dte:DatosGenerales');
        $nodo->setAttribute('CodigoMoneda', $documento->moneda);
        $nodo->setAttribute('FechaHoraEmision', $documento->fechaEmision ?? '');
        $nodo->setAttribute('Tipo', $documento->tipo);

        return $nodo;
    }

    private function emisor(Emisor $emisor): DOMElement
    {
        $nodo = $this->el('dte:Emisor');
        $nodo->setAttribute('AfiliacionIVA', $emisor->afiliacionIva);
        $nodo->setAttribute('CodigoEstablecimiento', $emisor->codigoEstablecimiento);
        $nodo->setAttribute('CorreoEmisor', $emisor->correo);
        $nodo->setAttribute('NITEmisor', $emisor->nit);
        $nodo->setAttribute('NombreComercial', $emisor->nombreComercial);
        $nodo->setAttribute('NombreEmisor', $emisor->nombre);

        $direccion = $this->el('dte:DireccionEmisor');
        $direccion->appendChild($this->elTexto('dte:Direccion', $emisor->direccion));
        $direccion->appendChild($this->elTexto('dte:CodigoPostal', $emisor->codigoPostal));
        $direccion->appendChild($this->elTexto('dte:Municipio', $emisor->municipio));
        $direccion->appendChild($this->elTexto('dte:Departamento', $emisor->departamento));
        $direccion->appendChild($this->elTexto('dte:Pais', $emisor->pais));
        $nodo->appendChild($direccion);

        return $nodo;
    }

    private function receptor(Receptor $receptor): DOMElement
    {
        $nodo = $this->el('dte:Receptor');
        $nodo->setAttribute('CorreoReceptor', $receptor->correo);
        $nodo->setAttribute('IDReceptor', $receptor->id);
        $nodo->setAttribute('NombreReceptor', $receptor->nombre);

        if ($receptor->tipoEspecial !== '') {
            $nodo->setAttribute('TipoEspecial', $receptor->tipoEspecial);
        }

        $direccion = $this->el('dte:DireccionReceptor');
        $direccion->appendChild($this->elTexto('dte:Direccion', $receptor->direccion));
        $direccion->appendChild($this->elTexto('dte:CodigoPostal', $receptor->codigoPostal));
        $direccion->appendChild($this->elTexto('dte:Municipio', $receptor->municipio));
        $direccion->appendChild($this->elTexto('dte:Departamento', $receptor->departamento));
        $direccion->appendChild($this->elTexto('dte:Pais', $receptor->pais));
        $nodo->appendChild($direccion);

        return $nodo;
    }

    private function frases(Documento $documento): DOMElement
    {
        $nodo = $this->el('dte:Frases');

        foreach ($documento->frases as $frase) {
            $hijo = $this->el('dte:Frase');
            $hijo->setAttribute('CodigoEscenario', (string) $frase->escenario);
            $hijo->setAttribute('TipoFrase', (string) $frase->tipo);
            $nodo->appendChild($hijo);
        }

        return $nodo;
    }

    private function items(Documento $documento): DOMElement
    {
        $nodo = $this->el('dte:Items');

        foreach ($documento->items as $indice => $item) {
            $linea = $this->el('dte:Item');
            $linea->setAttribute('BienOServicio', $item->tipo);
            $linea->setAttribute('NumeroLinea', (string) ($indice + 1));

            $linea->appendChild($this->elTexto('dte:Cantidad', Money::cantidad($item->cantidad)));
            $linea->appendChild($this->elTexto('dte:UnidadMedida', $item->unidadMedida));
            $linea->appendChild($this->elTexto('dte:Descripcion', $item->descripcion));
            $linea->appendChild($this->elTexto('dte:PrecioUnitario', Money::formato($item->precioUnitario)));
            $linea->appendChild($this->elTexto('dte:Precio', Money::formato($item->precio)));
            $linea->appendChild($this->elTexto('dte:Descuento', Money::formato($item->descuento)));

            if ($item->impuestos !== []) {
                $impuestos = $this->el('dte:Impuestos');
                foreach ($item->impuestos as $impuesto) {
                    $impuestos->appendChild($this->impuesto($impuesto));
                }
                $linea->appendChild($impuestos);
            }

            $linea->appendChild($this->elTexto('dte:Total', Money::formato($item->total)));
            $nodo->appendChild($linea);
        }

        return $nodo;
    }

    private function impuesto(Impuesto $impuesto): DOMElement
    {
        $nodo = $this->el('dte:Impuesto');
        $nodo->appendChild($this->elTexto('dte:NombreCorto', $impuesto->nombreCorto));
        $nodo->appendChild($this->elTexto('dte:CodigoUnidadGravable', (string) $impuesto->codigoUnidadGravable));

        if ($impuesto->cantidadUnidadesGravables !== null) {
            $nodo->appendChild($this->elTexto(
                'dte:CantidadUnidadesGravables',
                Money::cantidad($impuesto->cantidadUnidadesGravables)
            ));
        }

        $nodo->appendChild($this->elTexto('dte:MontoGravable', Money::formato($impuesto->montoGravable)));
        $nodo->appendChild($this->elTexto('dte:MontoImpuesto', Money::formato($impuesto->montoImpuesto)));

        return $nodo;
    }

    private function totales(Documento $documento): DOMElement
    {
        $nodo     = $this->el('dte:Totales');
        $porTipo  = Calculator::totalImpuestos($documento);

        if ($porTipo !== []) {
            $totalImpuestos = $this->el('dte:TotalImpuestos');
            foreach ($porTipo as $nombre => $monto) {
                $hijo = $this->el('dte:TotalImpuesto');
                $hijo->setAttribute('NombreCorto', $nombre);
                $hijo->setAttribute('TotalMontoImpuesto', Money::formato($monto));
                $totalImpuestos->appendChild($hijo);
            }
            $nodo->appendChild($totalImpuestos);
        }

        $nodo->appendChild($this->elTexto('dte:GranTotal', Money::formato(Calculator::granTotal($documento))));

        return $nodo;
    }

    private function complementos(Documento $documento): ?DOMElement
    {
        $hijos = [];

        if ($documento->referencia !== null) {
            $hijos[] = $this->complementoReferenciaNota($documento->referencia);
        }

        if ($documento->abonos !== []) {
            $hijos[] = $this->complementoCambiaria($documento);
        }

        if ($hijos === []) {
            return null;
        }

        $nodo = $this->el('dte:Complementos');
        foreach ($hijos as $hijo) {
            $nodo->appendChild($hijo);
        }

        return $nodo;
    }

    private function complementoReferenciaNota(Referencia $referencia): DOMElement
    {
        $envoltura = $this->el('dte:Complemento');
        $envoltura->setAttribute('IDComplemento', 'ReferenciasNota');
        $envoltura->setAttribute('NombreComplemento', 'ReferenciasNota');
        $envoltura->setAttribute('URIComplemento', self::NS_CNO);

        $nodo = $this->doc->createElementNS(self::NS_CNO, 'cno:ReferenciasNota');
        $nodo->setAttribute('FechaEmisionDocumentoOrigen', substr($referencia->fechaEmisionDocumentoOrigen, 0, 10));
        $nodo->setAttribute('MotivoAjuste', $referencia->motivoAjuste);
        $nodo->setAttribute('NumeroAutorizacionDocumentoOrigen', $referencia->numeroAutorizacionDocumentoOrigen);
        $nodo->setAttribute('NumeroDocumentoOrigen', $referencia->numeroDocumentoOrigen);
        $nodo->setAttribute('SerieDocumentoOrigen', $referencia->serieDocumentoOrigen);
        $nodo->setAttribute('Version', (string) Config::get('xml.version_referencias_nota', '0.0'));
        $envoltura->appendChild($nodo);

        return $envoltura;
    }

    private function complementoCambiaria(Documento $documento): DOMElement
    {
        $envoltura = $this->el('dte:Complemento');
        $envoltura->setAttribute('IDComplemento', 'Cambiaria');
        $envoltura->setAttribute('NombreComplemento', 'Cambiaria');
        $envoltura->setAttribute('URIComplemento', self::NS_CFC);

        $abonos = $this->doc->createElementNS(self::NS_CFC, 'cfc:AbonosFacturaCambiaria');
        $abonos->setAttribute('Version', (string) Config::get('xml.version_cambiaria', '1'));

        foreach ($documento->abonos as $abono) {
            $nodo = $this->doc->createElementNS(self::NS_CFC, 'cfc:Abono');
            $nodo->appendChild($this->elNs(self::NS_CFC, 'cfc:NumeroAbono', (string) $abono['numero']));
            $nodo->appendChild($this->elNs(self::NS_CFC, 'cfc:FechaVencimiento', substr($abono['fecha'], 0, 10)));
            $nodo->appendChild($this->elNs(self::NS_CFC, 'cfc:MontoAbono', Money::formato((float) $abono['monto'])));
            $abonos->appendChild($nodo);
        }

        $envoltura->appendChild($abonos);

        return $envoltura;
    }

    /**
     * La Adenda es informacion NO fiscal que SAT conserva pero no valida.
     * Se usa para el numero de pedido, vendedor, tipo de cambio, etc.
     */
    private function adenda(Documento $documento): ?DOMElement
    {
        $campos = $documento->adenda;

        if ($documento->observaciones !== '') {
            $campos['Observaciones'] = $documento->observaciones;
        }
        if ($documento->referenciaInterna !== '') {
            $campos['ReferenciaInterna'] = $documento->referenciaInterna;
        }
        if ($documento->moneda !== 'GTQ') {
            $campos['TipoCambio'] = Money::formato($documento->tipoCambio, 6);
        }

        if ($campos === []) {
            return null;
        }

        $nodo = $this->el('dte:Adenda');
        foreach ($campos as $clave => $valor) {
            $etiqueta = preg_replace('/[^A-Za-z0-9_]/', '', (string) $clave) ?: 'Campo';
            if (!preg_match('/^[A-Za-z_]/', $etiqueta)) {
                $etiqueta = 'C' . $etiqueta;
            }
            // Los hijos de la Adenda van SIN espacio de nombres: el esquema de SAT
            // los procesa como contenido libre y algunos certificadores rechazan
            // elementos no declarados dentro del namespace dte.
            $hijo = $this->doc->createElement($etiqueta);
            $hijo->appendChild($this->doc->createTextNode((string) $valor));
            $nodo->appendChild($hijo);
        }

        return $nodo;
    }

    private function el(string $nombre): DOMElement
    {
        return $this->doc->createElementNS(self::NS_DTE, $nombre);
    }

    private function elTexto(string $nombre, string $valor): DOMElement
    {
        return $this->elNs(self::NS_DTE, $nombre, $valor);
    }

    private function elNs(string $ns, string $nombre, string $valor): DOMElement
    {
        $nodo = $this->doc->createElementNS($ns, $nombre);
        $nodo->appendChild($this->doc->createTextNode($valor));

        return $nodo;
    }
}
