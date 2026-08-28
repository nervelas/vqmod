<?php
declare(strict_types=1);

namespace Fel\Core;

/**
 * Cifrado simetrico para los datos sensibles que se guardan en la base:
 * las llaves de firma y las credenciales de API de cada empresa.
 *
 * Se usa AES-256-GCM, que ademas de cifrar autentica: si alguien altera
 * un registro en la base, el descifrado falla en lugar de devolver basura.
 *
 * La clave se deriva de app.clave_aplicacion. Si esa clave se pierde, las
 * credenciales guardadas no se pueden recuperar y hay que volver a
 * capturarlas: por eso debe respaldarse junto con la base de datos.
 */
final class Cifrado
{
    private const METODO   = 'aes-256-gcm';
    private const PREFIJO  = 'fel1:';
    private const LARGO_IV = 12;
    private const LARGO_ETIQUETA = 16;

    public static function cifrar(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        $iv        = random_bytes(self::LARGO_IV);
        $etiqueta  = '';
        $cifrado   = openssl_encrypt($texto, self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $etiqueta);

        if ($cifrado === false) {
            throw new \RuntimeException('No se pudo cifrar el dato.');
        }

        return self::PREFIJO . base64_encode($iv . $etiqueta . $cifrado);
    }

    public static function descifrar(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        if (!str_starts_with($texto, self::PREFIJO)) {
            // Valor guardado antes de activar el cifrado: se devuelve tal cual
            // para no romper instalaciones existentes.
            return $texto;
        }

        $crudo = base64_decode(substr($texto, strlen(self::PREFIJO)), true);

        if ($crudo === false || strlen($crudo) <= self::LARGO_IV + self::LARGO_ETIQUETA) {
            throw new \RuntimeException('El dato cifrado esta corrupto.');
        }

        $iv       = substr($crudo, 0, self::LARGO_IV);
        $etiqueta = substr($crudo, self::LARGO_IV, self::LARGO_ETIQUETA);
        $cifrado  = substr($crudo, self::LARGO_IV + self::LARGO_ETIQUETA);

        $claro = openssl_decrypt($cifrado, self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $etiqueta);

        if ($claro === false) {
            throw new \RuntimeException(
                'No se pudo descifrar. Revise que app.clave_aplicacion sea la misma con la que se guardo el dato.'
            );
        }

        return $claro;
    }

    /** Cifra un arreglo completo (por ejemplo la configuracion del certificador). */
    public static function cifrarArreglo(array $datos): string
    {
        return self::cifrar((string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string,mixed> */
    public static function descifrarArreglo(string $texto): array
    {
        if (trim($texto) === '') {
            return [];
        }

        $json = json_decode(self::descifrar($texto), true);

        return is_array($json) ? $json : [];
    }

    private static function clave(): string
    {
        $clave = (string) Config::get('app.clave_aplicacion', '');

        if ($clave === '' || $clave === 'CAMBIE_ESTA_CLAVE_ALEATORIA') {
            throw new \RuntimeException(
                'Falta app.clave_aplicacion en config/config.php. '
                . 'Genere una con: php -r "echo bin2hex(random_bytes(32));"'
            );
        }

        return hash('sha256', $clave, true);
    }
}
