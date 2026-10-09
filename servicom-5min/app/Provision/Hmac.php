<?php
declare(strict_types=1);
namespace S5\Provision;

/** Firma HMAC de mensajes portal ⇄ agente, con marca de tiempo y nonce anti-repetición. */
final class Hmac
{
    public const SKEW = 120;

    public static function sign(string $secret, int $ts, string $nonce, string $body): string
    {
        return hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . hash('sha256', $body), $secret);
    }

    public static function verify(string $secret, int $ts, string $nonce, string $body, string $sig): bool
    {
        if (abs(time() - $ts) > self::SKEW) {
            return false;
        }
        return $sig !== '' && hash_equals(self::sign($secret, $ts, $nonce, $body), $sig);
    }

    /** URL firmada de un solo uso: ?id=..&exp=..&n=..&sig=.. */
    public static function assetSig(string $secret, string $orderToken, string $fileId, int $exp, string $nonce): string
    {
        return hash_hmac('sha256', "asset|$orderToken|$fileId|$exp|$nonce", $secret);
    }
}
