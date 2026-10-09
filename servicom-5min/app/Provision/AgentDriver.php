<?php
declare(strict_types=1);
namespace S5\Provision;

/** Cliente del agente instalado en el segundo hosting (agent.php). Mensajes firmados con HMAC. */
final class AgentDriver implements HostDriver
{
    private string $url;
    private string $secret;
    private string $webs;
    /** @var callable|null fn(array $asset): string  -> URL firmada de un solo uso */
    private $signer;
    /** @var callable|null transporte inyectable para pruebas */
    private $transport;
    private bool $verify;

    public function __construct(array $cfg)
    {
        $this->url = (string) $cfg['agent_url'];
        $this->secret = (string) $cfg['agent_secret'];
        $this->webs = rtrim((string) ($cfg['webs_path'] ?? ''), '/');
        $this->signer = $cfg['asset_signer'] ?? null;
        $this->transport = $cfg['transport'] ?? null;
        $this->verify = !array_key_exists('verify_ssl', $cfg) || (bool) $cfg['verify_ssl'];
    }

    private function call(string $op, array $args = [], int $timeout = 150)
    {
        $body = json_encode(['op' => $op, 'args' => $args], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ts = time();
        $nonce = bin2hex(random_bytes(12));
        $sig = Hmac::sign($this->secret, $ts, $nonce, $body);
        $headers = ['Content-Type: application/json', 'X-S5-Ts: ' . $ts, 'X-S5-Nonce: ' . $nonce, 'X-S5-Sig: ' . $sig];
        if ($this->transport) {
            $r = ($this->transport)($this->url, $headers, $body);
        } else {
            $rh = [];
            $ch = curl_init($this->url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => $this->verify,
                CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
                CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$rh) {
                    $p = explode(':', $h, 2);
                    if (count($p) === 2) {
                        $rh[strtolower(trim($p[0]))] = trim($p[1]);
                    }
                    return strlen($h);
                },
            ]);
            $resp = curl_exec($ch);
            $r = ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => is_string($resp) ? $resp : '', 'headers' => $rh, 'error' => curl_error($ch)];
            curl_close($ch);
        }
        if ($r['status'] === 0) {
            throw new ProvisionException('No se pudo contactar el agente del segundo hosting (' . ($r['error'] ?? '') . ').', true);
        }
        $h = $r['headers'] ?? [];
        if (!Hmac::verify($this->secret, (int) ($h['x-s5-rts'] ?? 0), (string) ($h['x-s5-rnonce'] ?? ''), $r['body'], (string) ($h['x-s5-rsig'] ?? ''))) {
            throw new ProvisionException('La respuesta del agente no está firmada correctamente.', false);
        }
        $j = json_decode($r['body'], true);
        if (!is_array($j)) {
            throw new ProvisionException('Respuesta inválida del agente.', true);
        }
        if (empty($j['ok'])) {
            throw new ProvisionException((string) ($j['error'] ?? 'Error del agente'), !empty($j['retry']));
        }
        return $j['result'] ?? null;
    }

    public function ping(): array
    {
        return (array) $this->call('ping', [], 40);
    }

    public function docroot(string $slug): string
    {
        return $this->webs . '/' . $slug;
    }

    public function subdomainCreate(string $slug, string $docroot): array
    {
        return (array) $this->call('subdomainCreate', ['slug' => $slug, 'docroot' => $docroot]);
    }

    public function subdomainExists(string $slug): bool
    {
        return (bool) $this->call('subdomainExists', ['slug' => $slug]);
    }

    public function subdomainDelete(string $slug): void
    {
        $this->call('subdomainDelete', ['slug' => $slug]);
    }

    public function dbCreate(string $shortDb, string $shortUser, string $pass): array
    {
        return (array) $this->call('dbCreate', ['shortDb' => $shortDb, 'shortUser' => $shortUser, 'pass' => $pass]);
    }

    public function dbDelete(string $db, string $user): void
    {
        $this->call('dbDelete', ['db' => $db, 'user' => $user]);
    }

    public function copyBase(string $docroot, int $cursor, int $budgetSec = 12): array
    {
        return (array) $this->call('copyBase', ['docroot' => $docroot, 'cursor' => $cursor, 'budget' => $budgetSec], 90);
    }

    public function writeFile(string $docroot, string $rel, string $content, int $mode = 0640): void
    {
        $this->call('writeFile', ['docroot' => $docroot, 'rel' => $rel, 'content_b64' => base64_encode($content), 'mode' => $mode]);
    }

    public function deleteFile(string $docroot, string $rel): void
    {
        $this->call('deleteFile', ['docroot' => $docroot, 'rel' => $rel]);
    }

    public function stageJob(string $docroot, string $jobId, string $manifestJson, array $assets, string $secret): void
    {
        $out = [];
        foreach ($assets as $a) {
            $x = $a;
            if (!empty($a['local']) && $this->signer) {
                $x['url'] = ($this->signer)($a);
            }
            unset($x['local']);
            $out[] = $x;
        }
        $this->call('stageJob', ['docroot' => $docroot, 'jobId' => $jobId, 'manifest_b64' => base64_encode($manifestJson), 'assets' => $out, 'secret' => $secret], 300);
    }

    public function provision(string $docroot, string $jobId, string $step, array $args = []): array
    {
        return (array) $this->call('provision', ['docroot' => $docroot, 'jobId' => $jobId, 'step' => $step, 'args' => $args], 170);
    }

    public function removeJob(string $docroot, string $jobId): void
    {
        $this->call('removeJob', ['docroot' => $docroot, 'jobId' => $jobId]);
    }

    public function removeProvisionScript(string $docroot): void
    {
        $this->call('removeProvisionScript', ['docroot' => $docroot]);
    }

    public function removeSite(string $docroot): void
    {
        $this->call('removeSite', ['docroot' => $docroot]);
    }

    public function attachDomain(string $domain, string $slug, string $docroot): void
    {
        $this->call('attachDomain', ['domain' => $domain, 'slug' => $slug, 'docroot' => $docroot]);
    }

    public function detachDomain(string $domain, string $slug): void
    {
        $this->call('detachDomain', ['domain' => $domain, 'slug' => $slug]);
    }

    public function siteExists(string $docroot): bool
    {
        return (bool) $this->call('siteExists', ['docroot' => $docroot]);
    }
}
