<?php
declare(strict_types=1);
namespace S5\Provision;

/** Lado servidor del agente: verifica firma/tiempo/nonce/IP y despacha a un LocalDriver. */
final class AgentServer
{
    private array $cfg;
    private LocalDriver $driver;

    public function __construct(array $cfg, LocalDriver $driver)
    {
        $this->cfg = $cfg;
        $this->driver = $driver;
    }

    private function reply(array $data, int $status = 200): void
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $ts = time();
        $nonce = bin2hex(random_bytes(12));
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-S5-Rts: ' . $ts);
        header('X-S5-Rnonce: ' . $nonce);
        header('X-S5-Rsig: ' . Hmac::sign((string) $this->cfg['secret'], $ts, $nonce, (string) $body));
        echo $body;
        exit;
    }

    private function fail(int $status): void
    {
        http_response_code($status);
        header('Content-Type: text/plain');
        echo $status === 403 ? 'Forbidden' : 'Error';
        exit;
    }

    private function nonceUsed(string $nonce): bool
    {
        $dir = (string) $this->cfg['storage'] . '/nonces';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        // limpieza ligera
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (filemtime($f) < time() - 600) {
                @unlink($f);
            }
        }
        $f = $dir . '/' . preg_replace('/[^a-f0-9]/', '', $nonce);
        if (is_file($f)) {
            return true;
        }
        file_put_contents($f, '1');
        return false;
    }

    public function handle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->fail(403);
        }
        $allow = $this->cfg['allowed_ips'] ?? [];
        if ($allow && !in_array($_SERVER['REMOTE_ADDR'] ?? '', $allow, true)) {
            $this->fail(403);
        }
        $body = (string) file_get_contents('php://input');
        $ts = (int) ($_SERVER['HTTP_X_S5_TS'] ?? 0);
        $nonce = (string) ($_SERVER['HTTP_X_S5_NONCE'] ?? '');
        $sig = (string) ($_SERVER['HTTP_X_S5_SIG'] ?? '');
        if (!preg_match('/^[a-f0-9]{24}$/', $nonce) || !Hmac::verify((string) $this->cfg['secret'], $ts, $nonce, $body, $sig) || $this->nonceUsed($nonce)) {
            $this->fail(403);
        }
        $req = json_decode($body, true);
        if (!is_array($req) || !isset($req['op'])) {
            $this->reply(['ok' => false, 'error' => 'Solicitud inválida']);
        }
        $a = is_array($req['args'] ?? null) ? $req['args'] : [];
        @set_time_limit(170);
        try {
            $d = $this->driver;
            switch ($req['op']) {
                case 'ping': $r = $d->ping(); break;
                case 'subdomainCreate': $r = $d->subdomainCreate((string) $a['slug'], (string) $a['docroot']); break;
                case 'subdomainExists': $r = $d->subdomainExists((string) $a['slug']); break;
                case 'subdomainDelete': $d->subdomainDelete((string) $a['slug']); $r = true; break;
                case 'dbCreate': $r = $d->dbCreate((string) $a['shortDb'], (string) $a['shortUser'], (string) $a['pass']); break;
                case 'dbDelete': $d->dbDelete((string) $a['db'], (string) $a['user']); $r = true; break;
                case 'copyBase': $r = $d->copyBase((string) $a['docroot'], (int) $a['cursor'], min(40, (int) ($a['budget'] ?? 12))); break;
                case 'writeFile':
                    $c = base64_decode((string) $a['content_b64'], true);
                    if ($c === false) {
                        throw new ProvisionException('Contenido inválido', false);
                    }
                    $d->writeFile((string) $a['docroot'], (string) $a['rel'], $c, (int) ($a['mode'] ?? 0640));
                    $r = true;
                    break;
                case 'deleteFile': $d->deleteFile((string) $a['docroot'], (string) $a['rel']); $r = true; break;
                case 'stageJob':
                    $m = base64_decode((string) $a['manifest_b64'], true);
                    if ($m === false) {
                        throw new ProvisionException('Manifiesto inválido', false);
                    }
                    $assets = [];
                    foreach ((array) ($a['assets'] ?? []) as $x) {
                        // solo se permiten descargas desde el portal configurado
                        if (!empty($x['url']) && !$this->urlAllowed((string) $x['url'])) {
                            throw new ProvisionException('URL de recurso no permitida', false);
                        }
                        $assets[] = $x;
                    }
                    $d->stageJob((string) $a['docroot'], (string) $a['jobId'], $m, $assets, (string) $a['secret']);
                    $r = true;
                    break;
                case 'provision': $r = $d->provision((string) $a['docroot'], (string) $a['jobId'], (string) $a['step'], (array) ($a['args'] ?? [])); break;
                case 'removeJob': $d->removeJob((string) $a['docroot'], (string) $a['jobId']); $r = true; break;
                case 'removeProvisionScript': $d->removeProvisionScript((string) $a['docroot']); $r = true; break;
                case 'removeSite': $d->removeSite((string) $a['docroot']); $r = true; break;
                case 'attachDomain': $d->attachDomain((string) $a['domain'], (string) $a['slug'], (string) $a['docroot']); $r = true; break;
                case 'detachDomain': $d->detachDomain((string) $a['domain'], (string) $a['slug']); $r = true; break;
                case 'siteExists': $r = $d->siteExists((string) $a['docroot']); break;
                default:
                    $this->reply(['ok' => false, 'error' => 'Operación desconocida']);
            }
            $this->reply(['ok' => true, 'result' => $r]);
        } catch (ProvisionException $e) {
            $this->reply(['ok' => false, 'error' => $e->getMessage(), 'retry' => $e->retry]);
        } catch (\Throwable $e) {
            @file_put_contents((string) $this->cfg['storage'] . '/agent.log', gmdate('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
            $this->reply(['ok' => false, 'error' => 'Error interno del agente', 'retry' => true]);
        }
    }

    private function urlAllowed(string $url): bool
    {
        $portal = rtrim((string) ($this->cfg['portal_url'] ?? ''), '/');
        return $portal !== '' && str_starts_with($url, $portal . '/ag/asset?');
    }
}
