<?php
declare(strict_types=1);

namespace Fel\Core;

/**
 * Redondeo monetario.
 *
 * Regla practica FEL: los montos del XML se envian con la cantidad de decimales
 * que acepta el esquema de SAT. Precio/Descuento/Total/GranTotal van a 2 decimales;
 * Cantidad y PrecioUnitario admiten hasta 6 (util para combustibles y granel).
 * Todo se redondea "half up" para que la suma de lineas cuadre con el gran total.
 */
final class Money
{
    public const DECIMALES_MONTO   = 2;
    public const DECIMALES_UNITARIO = 6;

    public static function redondear(float $valor, int $decimales = self::DECIMALES_MONTO): float
    {
        // round() de PHP usa half-up para valores positivos, que es lo que exige SAT.
        return round($valor, $decimales);
    }

    public static function formato(float $valor, int $decimales = self::DECIMALES_MONTO): string
    {
        $redondeado = self::redondear($valor, $decimales);

        // -0.00 rompe validaciones de algunos certificadores.
        if (abs($redondeado) < (0.5 / (10 ** $decimales))) {
            $redondeado = 0.0;
        }

        return number_format($redondeado, $decimales, '.', '');
    }

    /**
     * Formatea una cantidad quitando ceros a la derecha innecesarios,
     * conservando al menos un decimal (formato aceptado por el XSD).
     */
    public static function cantidad(float $valor): string
    {
        $texto = number_format(self::redondear($valor, self::DECIMALES_UNITARIO), self::DECIMALES_UNITARIO, '.', '');
        $texto = rtrim(rtrim($texto, '0'), '.');

        return $texto === '' || $texto === '-' ? '0' : $texto;
    }
}
