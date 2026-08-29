<?php
namespace MenuGold\Core;

final class Money
{
    /** @var string */
    private static $currency = 'Q';

    public static function setCurrency($c)
    {
        self::$currency = $c !== '' ? $c : 'Q';
    }

    public static function currency()
    {
        return self::$currency;
    }

    public static function format($amount, $currency = null)
    {
        $c = $currency !== null ? $currency : self::$currency;
        return $c . number_format(self::round($amount), 2, '.', ',');
    }

    /** Redondeo monetario consistente en todo el sistema. */
    public static function round($amount)
    {
        return round((float)$amount + 0.0000001, 2);
    }

    /** Convierte a centavos enteros para sumar sin errores de coma flotante. */
    public static function cents($amount)
    {
        return (int)round(((float)$amount) * 100);
    }

    public static function fromCents($cents)
    {
        return ((int)$cents) / 100;
    }
}
