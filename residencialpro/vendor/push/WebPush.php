<?php
declare(strict_types=1);

namespace Vendor\Push;

/**
 * Notificaciones Web Push (VAPID + cifrado aes128gcm, RFC 8291/8188).
 * Requiere las extensiones openssl y curl. Sin dependencias externas.
 *
 * ResidencialPro — librería local.
 */
final class WebPush
{
    private string $clavePublica;
    private string $clavePrivada;
    private string $sujeto;

    public function __construct(string $clavePublica, string $clavePrivada, string $sujeto)
    {
        $this->clavePublica = $clavePublica;
        $this->clavePrivada = $clavePrivada;
        $this->sujeto = $sujeto !== '' ? $sujeto : 'mailto:admin@localhost';
    }

    public static function disponible(): bool
    {
        return extension_loaded('openssl')
            && extension_loaded('curl')
            && function_exists('openssl_pkey_derive')
            && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    }

    /** Genera un par de claves VAPID en base64url. */
    public static function generarClaves(): array
    {
        $rec = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($rec === false) {
            throw new \RuntimeException('OpenSSL no pudo generar las claves de notificaciones.');
        }
        $d = openssl_pkey_get_details($rec);
        if ($d === false || !isset($d['ec']['x'], $d['ec']['y'], $d['ec']['d'])) {
            throw new \RuntimeException('No se pudieron leer las claves generadas.');
        }
        $publica = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)
                          . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
        return [
            'publica'  => self::b64url($publica),
            'privada'  => self::b64url(str_pad($d['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /**
     * Envía una notificación.
     * $sub = ['endpoint' => ..., 'p256dh' => ..., 'auth' => ...]
     * Devuelve el código HTTP (201 = aceptada; 404/410 = suscripción caduca).
     */
    public function enviar(array $sub, array $carga, int $ttl = 2419200): int
    {
        if (!self::disponible()) {
            return 0;
        }
        $endpoint = (string) ($sub['endpoint'] ?? '');
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return 0;
        }
        $json = json_encode($carga, JSON_UNESCAPED_UNICODE) ?: '{}';
        [$cuerpo, $claveServidor] = $this->cifrar(
            $json,
            self::b64urlDecode((string) ($sub['p256dh'] ?? '')),
            self::b64urlDecode((string) ($sub['auth'] ?? ''))
        );
        $partes = parse_url($endpoint);
        $origen = ($partes['scheme'] ?? 'https') . '://' . ($partes['host'] ?? '');
        $jwt = $this->jwt($origen);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'TTL: ' . $ttl,
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($cuerpo),
                'Urgency: normal',
                'Authorization: vapid t=' . $jwt . ', k=' . $this->clavePublica,
            ],
        ]);
        curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unset($claveServidor);
        return $codigo;
    }

    // ---------------------------------------------------------------- VAPID

    private function jwt(string $audiencia): string
    {
        $cabecera = self::b64url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $cuerpo   = self::b64url((string) json_encode([
            'aud' => $audiencia,
            'exp' => time() + 43200,
            'sub' => $this->sujeto,
        ]));
        $firmar = $cabecera . '.' . $cuerpo;
        $pem    = self::pemPrivada(self::b64urlDecode($this->clavePrivada), self::b64urlDecode($this->clavePublica));
        $clave  = openssl_pkey_get_private($pem);
        if ($clave === false) {
            throw new \RuntimeException('La clave privada de notificaciones no es válida.');
        }
        $der = '';
        if (!openssl_sign($firmar, $der, $clave, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('No se pudo firmar el token de notificaciones.');
        }
        return $firmar . '.' . self::b64url(self::derARaw($der));
    }

    // ------------------------------------------------------------- Cifrado

    /** @return array{0:string,1:string} [cuerpo, clavePublicaEfimera] */
    private function cifrar(string $texto, string $p256dh, string $auth): array
    {
        if (strlen($p256dh) !== 65 || strlen($auth) < 16) {
            throw new \RuntimeException('La suscripción de notificaciones no es válida.');
        }
        $efimera = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($efimera === false) {
            throw new \RuntimeException('OpenSSL no pudo generar la clave efímera.');
        }
        $d = openssl_pkey_get_details($efimera);
        $asPublica = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)
                            . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $uaPem  = self::pemPublica($p256dh);
        $uaKey  = openssl_pkey_get_public($uaPem);
        if ($uaKey === false) {
            throw new \RuntimeException('La clave pública del navegador no es válida.');
        }
        $compartido = openssl_pkey_derive($uaKey, $efimera, 32);
        if ($compartido === false) {
            throw new \RuntimeException('No se pudo derivar el secreto compartido.');
        }

        $sal = random_bytes(16);
        $ikm = hash_hkdf('sha256', $compartido, 32, "WebPush: info\0" . $p256dh . $asPublica, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sal);
        $non = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sal);

        $etiqueta = '';
        $cifrado  = openssl_encrypt($texto . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $non, $etiqueta);
        if ($cifrado === false) {
            throw new \RuntimeException('No se pudo cifrar la notificación.');
        }
        $cabecera = $sal . pack('N', 4096) . chr(strlen($asPublica)) . $asPublica;
        return [$cabecera . $cifrado . $etiqueta, $asPublica];
    }

    // -------------------------------------------------------------- Utiles

    public static function b64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $r = base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $r === false ? '' : $r;
    }

    /** Firma DER (SEQUENCE de dos INTEGER) -> 64 bytes r||s. */
    private static function derARaw(string $der): string
    {
        $pos = 0;
        if (($der[$pos++] ?? '') !== "\x30") {
            return str_pad($der, 64, "\0");
        }
        $len = ord($der[$pos++]);
        if ($len > 0x80) {
            $pos += $len - 0x80;
        }
        $leer = static function (string $d, int &$p): string {
            $p++; // 0x02
            $l = ord($d[$p++]);
            $v = substr($d, $p, $l);
            $p += $l;
            return ltrim($v, "\0");
        };
        $r = $leer($der, $pos);
        $s = $leer($der, $pos);
        return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
    }

    /** Clave pública P-256 sin comprimir -> PEM. */
    private static function pemPublica(string $raw): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Clave privada P-256 -> PEM (estructura SEC1 con parámetros nombrados). */
    private static function pemPrivada(string $d, string $publica): string
    {
        $der = "\x30\x77"                                     // SEQUENCE (119 bytes)
             . "\x02\x01\x01"                                 // version 1
             . "\x04\x20" . str_pad($d, 32, "\0", STR_PAD_LEFT)
             . "\xa0\x0a" . hex2bin('06082a8648ce3d030107')    // [0] OID prime256v1
             . "\xa1\x44" . "\x03\x42\x00" . $publica;         // [1] BIT STRING clave pública
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }
}
