<?php
/**
 * Kaptor - Cifrado de datos sensibles (las contraseñas SMTP).
 *
 * Usa AES-256-GCM con la clave única que genera el instalador en
 * config/config.php. Si alguien consigue una copia de la base de datos sin el
 * archivo de configuración, las contraseñas de correo siguen siendo ilegibles.
 */
declare(strict_types=1);

final class Cripto
{
    private const METODO = 'aes-256-gcm';

    /** Deriva una clave binaria de 32 bytes a partir de la clave de la aplicación. */
    private static function clave(): string
    {
        $base = defined('CR_CLAVE_APP') ? (string) CR_CLAVE_APP : '';
        if ($base === '') {
            throw new RuntimeException('Falta CR_CLAVE_APP en config/config.php.');
        }
        return hash('sha256', 'correoradar|' . $base, true);
    }

    /** Cifra un texto. Devuelve un binario listo para guardar en VARBINARY. */
    public static function cifrar(string $texto): string
    {
        if ($texto === '') { return ''; }
        if (!in_array(self::METODO, openssl_get_cipher_methods(), true)) {
            // Respaldo para servidores muy antiguos sin GCM.
            return self::cifrarCbc($texto);
        }

        $iv  = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($texto, self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cifrado === false) {
            throw new RuntimeException('No se pudo cifrar el dato.');
        }
        return 'G1' . $iv . $tag . $cifrado;
    }

    /** Descifra un texto cifrado con cifrar(). Devuelve '' si no se puede. */
    public static function descifrar(string $dato): string
    {
        if ($dato === '') { return ''; }

        if (str_starts_with($dato, 'G1')) {
            $iv  = substr($dato, 2, 12);
            $tag = substr($dato, 14, 16);
            $cifrado = substr($dato, 30);
            $plano = openssl_decrypt($cifrado, self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $tag);
            return $plano === false ? '' : $plano;
        }
        if (str_starts_with($dato, 'C1')) {
            return self::descifrarCbc($dato);
        }
        return '';
    }

    // ------------------------------------------------------ respaldo sin GCM

    private static function cifrarCbc(string $texto): string
    {
        $iv = random_bytes(16);
        $cifrado = openssl_encrypt($texto, 'aes-256-cbc', self::clave(), OPENSSL_RAW_DATA, $iv);
        if ($cifrado === false) {
            throw new RuntimeException('No se pudo cifrar el dato.');
        }
        $firma = hash_hmac('sha256', $iv . $cifrado, self::clave(), true);
        return 'C1' . $iv . $firma . $cifrado;
    }

    private static function descifrarCbc(string $dato): string
    {
        $iv      = substr($dato, 2, 16);
        $firma   = substr($dato, 18, 32);
        $cifrado = substr($dato, 50);
        if (!hash_equals(hash_hmac('sha256', $iv . $cifrado, self::clave(), true), $firma)) {
            return '';
        }
        $plano = openssl_decrypt($cifrado, 'aes-256-cbc', self::clave(), OPENSSL_RAW_DATA, $iv);
        return $plano === false ? '' : $plano;
    }
}
