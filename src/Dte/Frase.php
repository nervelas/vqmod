<?php
declare(strict_types=1);

namespace Fel\Dte;

/**
 * Frase obligatoria del DTE (TipoFrase + CodigoEscenario del anexo de SAT).
 */
final class Frase
{
    public function __construct(
        public int $tipo,
        public int $escenario,
        public string $texto = '',
    ) {
    }

    public static function porClave(string $clave): self
    {
        $frases = Catalogos::frases();
        if (!isset($frases[$clave])) {
            throw new \InvalidArgumentException("Frase desconocida: {$clave}");
        }

        return new self($frases[$clave]['tipo'], $frases[$clave]['escenario'], $frases[$clave]['texto']);
    }

    /**
     * Frases minimas sugeridas segun la afiliacion de IVA del emisor.
     * Revise con su contador si su giro exige frases adicionales.
     *
     * @return list<self>
     */
    public static function sugeridasPara(string $afiliacionIva): array
    {
        return match ($afiliacionIva) {
            'PEQ' => [self::porClave('PEQUENO_CONTRIBUYENTE')],
            'PEE' => [self::porClave('PEQUENO_CONTRIBUYENTE_ELECTRONICO')],
            'AGR' => [self::porClave('AGROPECUARIO')],
            'AGE' => [self::porClave('AGROPECUARIO_ELECTRONICO')],
            'EXE' => [self::porClave('IVA_EXENTO')],
            default => [self::porClave('ISR_PAGOS_TRIMESTRALES')],
        };
    }
}
