<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Settings;
use S5\Provision\Hmac;

/** URLs firmadas de un solo uso para que el agente descargue los archivos del cliente. */
final class AssetUrls
{
    public static function sign(array $asset, string $secret): string
    {
        $exp = time() + 900;
        $nonce = bin2hex(random_bytes(8));
        $tok = (string) ($asset['order_token'] ?? '');
        $id = (string) ($asset['file_key'] ?? '');
        $sig = Hmac::assetSig($secret, $tok, $id, $exp, $nonce);
        return Settings::portalUrl() . '/ag/asset?' . http_build_query(['t' => $tok, 'id' => $id, 'exp' => $exp, 'n' => $nonce, 'sig' => $sig]);
    }

    /** Verifica firma, vigencia y que no se haya usado antes. */
    public static function consume(string $tok, string $id, int $exp, string $nonce, string $sig): bool
    {
        if ($exp < time() || !preg_match('/^[a-f0-9]{16}$/', $nonce)) {
            return false;
        }
        foreach (Hosts::all(true) as $h) {
            if ($h['kind'] !== 'agent') {
                continue;
            }
            $secret = (string) ($h['cfg']['agent_secret'] ?? '');
            if ($secret !== '' && hash_equals(Hmac::assetSig($secret, $tok, $id, $exp, $nonce), $sig)) {
                $key = 'asset-' . $nonce;
                $ins = Db::q('INSERT IGNORE INTO ' . Db::t('meta') . ' (k,v) VALUES (?,?)', [$key, (string) time()]);
                return $ins->rowCount() === 1;
            }
        }
        return false;
    }
}
