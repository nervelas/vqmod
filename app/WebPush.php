<?php
/**
 * Self-contained Web Push (VAPID + aes128gcm) implementation using only PHP's
 * openssl + hash_hkdf. No external dependencies. Works on cPanel.
 *
 * Implements:
 *  - VAPID JWT (ES256) for the Authorization header (RFC 8292).
 *  - Message body encryption "aes128gcm" (RFC 8188 + RFC 8291).
 */
class WebPush
{
    /** base64url encode (no padding). */
    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /** base64url decode. */
    public static function b64uDecode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) { $s .= str_repeat('=', 4 - $pad); }
        return base64_decode($s) ?: '';
    }

    /** Left-pad a big-endian integer to a fixed length. */
    private static function pad(string $bin, int $len): string
    {
        $bin = ltrim($bin, "\x00");
        if (strlen($bin) > $len) { $bin = substr($bin, -$len); }
        return str_pad($bin, $len, "\x00", STR_PAD_LEFT);
    }

    /**
     * Generate a VAPID key pair.
     * @return array{public:string, private_pem:string}  public = base64url 65-byte point.
     */
    public static function generateVapidKeys(): array
    {
        $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$res) { throw new RuntimeException('No se pudo generar la clave (openssl EC).'); }
        openssl_pkey_export($res, $pem);
        $d = openssl_pkey_get_details($res);
        $point = "\x04" . self::pad($d['ec']['x'], 32) . self::pad($d['ec']['y'], 32);
        return ['public' => self::b64u($point), 'private_pem' => $pem];
    }

    /** Build an EC public key resource from a raw uncompressed 65-byte point. */
    private static function publicKeyFromPoint(string $point)
    {
        // ASN.1 SPKI prefix for prime256v1 uncompressed point.
        $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der = $prefix . $point;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($pem);
    }

    /** Convert a DER ECDSA signature to raw R||S (64 bytes). */
    private static function derToRaw(string $der): string
    {
        $off = 0;
        if (ord($der[$off++]) !== 0x30) { throw new RuntimeException('Firma DER inválida.'); }
        if (ord($der[$off]) & 0x80) { $off += 1 + (ord($der[$off]) & 0x7f); } else { $off++; }
        $readInt = function () use ($der, &$off) {
            if (ord($der[$off++]) !== 0x02) { throw new RuntimeException('DER int inválido.'); }
            $len = ord($der[$off++]);
            $v = substr($der, $off, $len); $off += $len;
            return ltrim($v, "\x00");
        };
        $r = $readInt(); $s = $readInt();
        return self::pad($r, 32) . self::pad($s, 32);
    }

    /** Create the VAPID Authorization + Crypto-Key values for an endpoint origin. */
    private static function vapidHeaders(string $audience, string $publicKeyB64u, string $privatePem, string $subject): array
    {
        $header = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::b64u(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $subject,
        ]));
        $signingInput = $header . '.' . $payload;
        $key = openssl_pkey_get_private($privatePem);
        if (!$key) { throw new RuntimeException('Clave privada VAPID inválida.'); }
        $der = '';
        openssl_sign($signingInput, $der, $key, OPENSSL_ALGO_SHA256);
        $jwt = $signingInput . '.' . self::b64u(self::derToRaw($der));
        return [
            'Authorization: vapid t=' . $jwt . ', k=' . $publicKeyB64u,
        ];
    }

    /**
     * Encrypt a payload for a subscription using aes128gcm (RFC 8188/8291).
     * @return string  the encrypted content-coding body.
     */
    public static function encrypt(string $payload, string $uaPublicRaw, string $authSecret): string
    {
        $as = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asDet = openssl_pkey_get_details($as);
        $asPublic = "\x04" . self::pad($asDet['ec']['x'], 32) . self::pad($asDet['ec']['y'], 32);

        $uaKey = self::publicKeyFromPoint($uaPublicRaw);
        if (!$uaKey) { throw new RuntimeException('Clave pública del cliente inválida.'); }
        $secret = openssl_pkey_derive($uaKey, $as, 32);
        if ($secret === false) { throw new RuntimeException('ECDH falló.'); }

        // IKM (RFC 8291).
        $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublic;
        $ikm = hash_hkdf('sha256', $secret, 32, $keyInfo, $authSecret);

        $salt = random_bytes(16);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // Single last record: payload + 0x02 delimiter.
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        $cipher .= $tag;

        // aes128gcm header: salt(16) | rs(4) | idlen(1) | keyid(as_public 65).
        $header = $salt . pack('N', 4096) . chr(65) . $asPublic;
        return $header . $cipher;
    }

    /**
     * Send a push message to one subscription.
     * @param array $sub ['endpoint','p256dh','auth']
     * @return array{status:int, ok:bool}
     */
    public static function send(array $sub, string $payload, string $vapidPublic, string $vapidPrivatePem, string $subject): array
    {
        $endpoint = $sub['endpoint'];
        $parts = parse_url($endpoint);
        $audience = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $body = self::encrypt(
            $payload,
            self::b64uDecode($sub['p256dh']),
            self::b64uDecode($sub['auth'])
        );

        $headers = array_merge(self::vapidHeaders($audience, $vapidPublic, $vapidPrivatePem, $subject), [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'ok' => in_array($status, [200, 201, 202], true)];
    }
}
