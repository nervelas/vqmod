<?php
declare(strict_types=1);

namespace Fel\Dte;

/**
 * Catalogos del regimen FEL.
 *
 * IMPORTANTE: SAT publica los catalogos oficiales en los anexos del
 * "Documento tecnico e informativo del regimen FEL". Antes de salir a
 * produccion verifique cada codigo contra la version vigente del anexo
 * y contra el manual de su certificador. Los valores aqui incluidos son
 * los de uso corriente; puede ampliarlos sin tocar el resto del sistema.
 */
final class Catalogos
{
    /**
     * Tipos de DTE.
     * 'iva' => si el documento desglosa IVA en los items.
     * 'nota' => si requiere referencia a un documento origen.
     *
     * @return array<string,array{nombre:string,iva:bool,nota:bool}>
     */
    public static function tiposDte(): array
    {
        return [
            'FACT' => ['nombre' => 'Factura',                                  'iva' => true,  'nota' => false],
            'FCAM' => ['nombre' => 'Factura cambiaria',                        'iva' => true,  'nota' => false],
            'FPEQ' => ['nombre' => 'Factura pequeño contribuyente',            'iva' => false, 'nota' => false],
            'FCAP' => ['nombre' => 'Factura cambiaria pequeño contribuyente',  'iva' => false, 'nota' => false],
            'FESP' => ['nombre' => 'Factura especial',                         'iva' => true,  'nota' => false],
            'NABN' => ['nombre' => 'Nota de abono',                            'iva' => true,  'nota' => false],
            'RDON' => ['nombre' => 'Recibo por donación',                      'iva' => false, 'nota' => false],
            'RECI' => ['nombre' => 'Recibo',                                   'iva' => true,  'nota' => false],
            'NDEB' => ['nombre' => 'Nota de débito',                           'iva' => true,  'nota' => true],
            'NCRE' => ['nombre' => 'Nota de crédito',                          'iva' => true,  'nota' => true],
            'FACA' => ['nombre' => 'Factura contribuyente agropecuario',       'iva' => false, 'nota' => false],
            'FCCA' => ['nombre' => 'Factura cambiaria contribuyente agropecuario', 'iva' => false, 'nota' => false],
            'FAPE' => ['nombre' => 'Factura pequeño contribuyente régimen electrónico', 'iva' => false, 'nota' => false],
            'FAAE' => ['nombre' => 'Factura contribuyente agropecuario régimen electrónico', 'iva' => false, 'nota' => false],
        ];
    }

    public static function tipoDteValido(string $tipo): bool
    {
        return array_key_exists(strtoupper($tipo), self::tiposDte());
    }

    public static function tipoDteDesglosaIva(string $tipo): bool
    {
        return self::tiposDte()[strtoupper($tipo)]['iva'] ?? true;
    }

    public static function tipoDteEsNota(string $tipo): bool
    {
        return self::tiposDte()[strtoupper($tipo)]['nota'] ?? false;
    }

    /**
     * Frases obligatorias segun el regimen del emisor.
     * La combinacion TipoFrase + CodigoEscenario debe coincidir con el anexo de SAT.
     *
     * @return array<string,array{tipo:int,escenario:int,texto:string}>
     */
    public static function frases(): array
    {
        return [
            'ISR_RETENCION_DEFINITIVA' => [
                'tipo' => 1, 'escenario' => 1,
                'texto' => 'Sujeto a retención definitiva sobre el Impuesto Sobre la Renta',
            ],
            'ISR_PAGOS_TRIMESTRALES' => [
                'tipo' => 1, 'escenario' => 2,
                'texto' => 'Sujeto a pagos trimestrales del Impuesto Sobre la Renta',
            ],
            'IVA_AGENTE_RETENCION' => [
                'tipo' => 2, 'escenario' => 1,
                'texto' => 'Agente de retención del Impuesto al Valor Agregado',
            ],
            'IVA_EXENTO' => [
                'tipo' => 3, 'escenario' => 1,
                'texto' => 'Exento del Impuesto al Valor Agregado',
            ],
            'PEQUENO_CONTRIBUYENTE' => [
                'tipo' => 4, 'escenario' => 1,
                'texto' => 'No genera derecho a crédito fiscal',
            ],
            'PEQUENO_CONTRIBUYENTE_ELECTRONICO' => [
                'tipo' => 4, 'escenario' => 2,
                'texto' => 'Régimen electrónico de pequeño contribuyente. No genera derecho a crédito fiscal',
            ],
            'AGROPECUARIO' => [
                'tipo' => 7, 'escenario' => 1,
                'texto' => 'Régimen especial de contribuyente agropecuario. No genera derecho a crédito fiscal',
            ],
            'AGROPECUARIO_ELECTRONICO' => [
                'tipo' => 7, 'escenario' => 2,
                'texto' => 'Régimen electrónico especial de contribuyente agropecuario. No genera derecho a crédito fiscal',
            ],
        ];
    }

    /**
     * Impuestos del catalogo FEL. IVA es el unico con desglose automatico aqui;
     * los demas se agregan por linea si su giro los requiere.
     *
     * @return array<string,array{nombre:string,unidad_gravable:int,tasa:float|null}>
     */
    public static function impuestos(): array
    {
        return [
            'IVA'                    => ['nombre' => 'Impuesto al Valor Agregado', 'unidad_gravable' => 1, 'tasa' => 0.12],
            'PETROLEO'               => ['nombre' => 'Impuesto a la distribución de petróleo', 'unidad_gravable' => 1, 'tasa' => null],
            'TURISMO HOSPEDAJE'      => ['nombre' => 'Impuesto de turismo hospedaje', 'unidad_gravable' => 1, 'tasa' => null],
            'TURISMO PASAJES'        => ['nombre' => 'Impuesto de turismo pasajes', 'unidad_gravable' => 1, 'tasa' => null],
            'TIMBRE DE PRENSA'       => ['nombre' => 'Timbre de prensa', 'unidad_gravable' => 1, 'tasa' => null],
            'BOMBEROS'               => ['nombre' => 'Aporte para bomberos', 'unidad_gravable' => 1, 'tasa' => null],
            'TASA MUNICIPAL'         => ['nombre' => 'Tasa municipal', 'unidad_gravable' => 1, 'tasa' => null],
            'BEBIDAS ALCOHOLICAS'    => ['nombre' => 'Impuesto a bebidas alcohólicas', 'unidad_gravable' => 1, 'tasa' => null],
            'TABACO'                 => ['nombre' => 'Impuesto al tabaco', 'unidad_gravable' => 1, 'tasa' => null],
            'CEMENTO'                => ['nombre' => 'Impuesto al cemento', 'unidad_gravable' => 1, 'tasa' => null],
            'BEBIDAS NO ALCOHOLICAS' => ['nombre' => 'Impuesto a bebidas no alcohólicas', 'unidad_gravable' => 1, 'tasa' => null],
            'TARIFA PORTUARIA'       => ['nombre' => 'Tarifa portuaria', 'unidad_gravable' => 1, 'tasa' => null],
        ];
    }

    public static function tasaIva(): float
    {
        return self::impuestos()['IVA']['tasa'] ?? 0.12;
    }

    /** @return array<string,string> */
    public static function unidadesMedida(): array
    {
        return [
            'UNI' => 'Unidad',
            'LB'  => 'Libra',
            'KG'  => 'Kilogramo',
            'QQ'  => 'Quintal',
            'TON' => 'Tonelada',
            'GAL' => 'Galón',
            'LT'  => 'Litro',
            'MT'  => 'Metro',
            'M2'  => 'Metro cuadrado',
            'M3'  => 'Metro cúbico',
            'CAJ' => 'Caja',
            'DOC' => 'Docena',
            'HRS' => 'Hora',
            'DIA' => 'Día',
            'MES' => 'Mes',
            'SER' => 'Servicio',
        ];
    }

    /** @return array<string,string> */
    public static function monedas(): array
    {
        return [
            'GTQ' => 'Quetzal',
            'USD' => 'Dólar de los Estados Unidos',
            'EUR' => 'Euro',
            'MXN' => 'Peso mexicano',
        ];
    }

    /** @return array<string,string> */
    public static function afiliacionesIva(): array
    {
        return [
            'GEN' => 'Régimen general (contribuyente normal)',
            'PEQ' => 'Régimen de pequeño contribuyente',
            'PEE' => 'Régimen electrónico de pequeño contribuyente',
            'AGR' => 'Régimen especial de contribuyente agropecuario',
            'AGE' => 'Régimen electrónico especial de contribuyente agropecuario',
            'EXE' => 'Exento de IVA',
        ];
    }

    /** @return list<string> */
    public static function departamentos(): array
    {
        return [
            'Guatemala', 'El Progreso', 'Sacatepéquez', 'Chimaltenango', 'Escuintla',
            'Santa Rosa', 'Sololá', 'Totonicapán', 'Quetzaltenango', 'Suchitepéquez',
            'Retalhuleu', 'San Marcos', 'Huehuetenango', 'Quiché', 'Baja Verapaz',
            'Alta Verapaz', 'Petén', 'Izabal', 'Zacapa', 'Chiquimula', 'Jalapa', 'Jutiapa',
        ];
    }

    /**
     * Tipos de bien o servicio del item.
     * B = Bien, S = Servicio.
     *
     * @return array<string,string>
     */
    public static function tiposItem(): array
    {
        return ['B' => 'Bien', 'S' => 'Servicio'];
    }
}
