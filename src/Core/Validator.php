<?php
declare(strict_types=1);

namespace Fel\Core;

/**
 * Validaciones propias de Guatemala. Se ejecutan ANTES de llamar al certificador
 * para no gastar folios ni generar rechazos innecesarios de SAT.
 */
final class Validator
{
    /**
     * Valida el digito verificador del NIT guatemalteco (modulo 11).
     * Acepta formatos con guion y la letra K final.
     */
    public static function nitValido(string $nit): bool
    {
        $nit = self::normalizarNit($nit);

        if ($nit === 'CF') {
            return true;
        }

        if (!preg_match('/^\d+[0-9K]$/', $nit)) {
            return false;
        }

        $numero      = substr($nit, 0, -1);
        $verificador = substr($nit, -1);
        $largo       = strlen($numero);

        if ($largo < 1) {
            return false;
        }

        $suma = 0;
        for ($i = 0; $i < $largo; $i++) {
            $suma += ((int) $numero[$i]) * ($largo + 1 - $i);
        }

        $modulo   = (11 - ($suma % 11)) % 11;
        $esperado = $modulo === 10 ? 'K' : (string) $modulo;

        return $verificador === $esperado;
    }

    /**
     * Quita guiones, espacios y pasa a mayusculas. "1234567-8" -> "12345678".
     */
    public static function normalizarNit(string $nit): string
    {
        $limpio = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $nit) ?? '');

        return $limpio === '' ? 'CF' : $limpio;
    }

    /**
     * Valida el digito verificador del CUI/DPI (13 digitos).
     * Se valida el correlativo y que el codigo de departamento este en rango 1-22.
     */
    public static function cuiValido(string $cui): bool
    {
        $cui = preg_replace('/\D/', '', $cui) ?? '';

        if (strlen($cui) !== 13) {
            return false;
        }

        $correlativo  = substr($cui, 0, 8);
        $verificador  = (int) $cui[8];
        $departamento = (int) substr($cui, 9, 2);
        $municipio    = (int) substr($cui, 11, 2);

        if ($departamento < 1 || $departamento > 22 || $municipio < 1) {
            return false;
        }

        $suma = 0;
        for ($i = 0; $i < 8; $i++) {
            $suma += ((int) $correlativo[$i]) * ($i + 2);
        }

        return ($suma % 11) === $verificador;
    }

    public static function correoValido(string $correo): bool
    {
        return $correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Identificador de receptor valido: NIT, CF, CUI o pasaporte/extranjero.
     */
    public static function identificadorReceptorValido(string $id, string $tipoEspecial = ''): bool
    {
        $id = trim($id);

        if ($id === '' ) {
            return false;
        }

        if (strtoupper($id) === 'CF') {
            return true;
        }

        return match ($tipoEspecial) {
            'CUI'      => self::cuiValido($id),
            'EXT', 'PASAPORTE' => strlen($id) >= 5 && strlen($id) <= 25,
            default    => self::nitValido($id),
        };
    }

    /**
     * Fecha/hora en el formato que exige el XSD de SAT: ISO-8601 con offset.
     * Guatemala no usa horario de verano: siempre -06:00.
     */
    public static function fechaHoraSat(?\DateTimeInterface $fecha = null): string
    {
        $zona  = new \DateTimeZone((string) Config::get('zona_horaria', 'America/Guatemala'));
        $fecha = $fecha === null
            ? new \DateTimeImmutable('now', $zona)
            : \DateTimeImmutable::createFromInterface($fecha)->setTimezone($zona);

        return $fecha->format('Y-m-d\TH:i:sP');
    }
}
