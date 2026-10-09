<?php
declare(strict_types=1);
namespace S5\Provision;

/**
 * Cliente de cPanel por API Token (UAPI + API2).
 * Autenticación: cabecera "Authorization: cpanel USUARIO:TOKEN".
 *  - UAPI:  GET https://host:2083/execute/<Modulo>/<funcion>?param=valor
 *  - API2:  GET https://host:2083/json-api/cpanel?cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=…&cpanel_jsonapi_func=…
 * Endpoints usados (ver docs/CPANEL.md; NO verificados contra un cPanel real en este entorno):
 *  UAPI  Mysql::create_database / create_user / set_privileges_on_database / delete_database / delete_user / get_restrictions
 *  API2  SubDomain::addsubdomain / delsubdomain ; AddonDomain::addaddondomain / deladdondomain
 */
final class CpanelHttpApi implements CpanelApi
{
    private string $host;
    private int $port;
    private string $user;
    private string $token;
    private bool $verify;
    private string $home;
    private ?array $restrictions = null;
    /** @var callable|null transporte inyectable para pruebas: fn(string $url, array $headers): array{status:int,body:string,error:string} */
    private $transport;

    public function __construct(array $cfg)
    {
        $this->host = (string) ($cfg['host'] ?? 'localhost');
        $this->port = (int) ($cfg['port'] ?? 2083);
        $this->user = (string) ($cfg['user'] ?? '');
        $this->token = (string) ($cfg['token'] ?? '');
        $this->verify = !array_key_exists('verify_ssl', $cfg) || (bool) $cfg['verify_ssl'];
        // Conexión de bucle local (el mismo servidor): el certificado de cPanel nunca coincide con «localhost» y el tráfico no sale de la máquina.
        if (in_array(strtolower($this->host), ['localhost', '127.0.0.1', '::1'], true)) {
            $this->verify = false;
        }
        $this->home = rtrim((string) ($cfg['home'] ?? ''), '/');
        $this->transport = $cfg['transport'] ?? null;
    }

    private function get(string $path, array $params): array
    {
        $url = 'https://' . $this->host . ':' . $this->port . $path . '?' . http_build_query($params);
        $headers = ['Authorization: cpanel ' . $this->user . ':' . $this->token];
        if ($this->transport) {
            $r = ($this->transport)($url, $headers);
        } else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => $this->verify,
                CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
            ]);
            $body = curl_exec($ch);
            $r = ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => is_string($body) ? $body : '', 'error' => curl_error($ch)];
            curl_close($ch);
        }
        if ($r['status'] === 0) {
            throw new ProvisionException('No se pudo conectar con cPanel (' . ($r['error'] ?: 'sin respuesta') . ').', true);
        }
        if ($r['status'] === 401 || $r['status'] === 403) {
            throw new ProvisionException('cPanel rechazó el token de API (revise usuario y token).', false);
        }
        $j = json_decode($r['body'], true);
        if (!is_array($j)) {
            throw new ProvisionException('Respuesta inesperada de cPanel (HTTP ' . $r['status'] . ').', true);
        }
        return $j;
    }

    private function uapi(string $module, string $func, array $params = []): array
    {
        $j = $this->get('/execute/' . $module . '/' . $func, $params);
        if (($j['status'] ?? 0) != 1) {
            $err = is_array($j['errors'] ?? null) ? implode(' ', array_map('strval', $j['errors'])) : 'error desconocido';
            throw new ProvisionException('cPanel ' . $module . '::' . $func . ': ' . $err, false);
        }
        return $j;
    }

    private function api2(string $module, string $func, array $params = []): array
    {
        $params = array_merge([
            'cpanel_jsonapi_user' => $this->user,
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => $module,
            'cpanel_jsonapi_func' => $func,
        ], $params);
        $j = $this->get('/json-api/cpanel', $params);
        $res = $j['cpanelresult'] ?? [];
        if (!empty($res['error'])) {
            throw new ProvisionException('cPanel ' . $module . '::' . $func . ': ' . (string) $res['error'], false);
        }
        $d = $res['data'][0] ?? [];
        if (isset($d['result']) && (int) $d['result'] !== 1) {
            throw new ProvisionException('cPanel ' . $module . '::' . $func . ': ' . (string) ($d['reason'] ?? 'falló'), false);
        }
        return $j;
    }

    public function ping(): array
    {
        try {
            $j = $this->uapi('Variables', 'get_user_information', []);
            $u = $j['data']['user'] ?? $this->user;
            return ['ok' => true, 'message' => 'Token válido', 'user' => (string) $u];
        } catch (ProvisionException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function relDir(string $abs): string
    {
        $abs = rtrim($abs, '/');
        if ($this->home !== '' && str_starts_with($abs, $this->home . '/')) {
            return substr($abs, strlen($this->home) + 1);
        }
        return $abs;
    }

    public function addSubdomain(string $sub, string $root, string $dir): void
    {
        if ($this->subdomainExists($sub, $root)) {
            return;
        }
        $this->api2('SubDomain', 'addsubdomain', [
            'domain' => $sub,
            'rootdomain' => $root,
            'dir' => $this->relDir($dir),
            'disallowdot' => 1,
        ]);
    }

    public function delSubdomain(string $sub, string $root): void
    {
        if (!$this->subdomainExists($sub, $root)) {
            return;
        }
        $this->api2('SubDomain', 'delsubdomain', ['domain' => $sub . '.' . $root]);
    }

    public function subdomainExists(string $sub, string $root): bool
    {
        $j = $this->uapi('DomainInfo', 'domains_data', ['format' => 'hash']);
        $fq = strtolower($sub . '.' . $root);
        $subs = $j['data']['sub_domains'] ?? [];
        foreach ($subs as $s) {
            if (strtolower((string) ($s['domain'] ?? '')) === $fq) {
                return true;
            }
        }
        return false;
    }

    private function restrictions(): array
    {
        if ($this->restrictions === null) {
            try {
                $j = $this->uapi('Mysql', 'get_restrictions');
                $this->restrictions = $j['data'] ?? [];
            } catch (ProvisionException $e) {
                $this->restrictions = [];
            }
        }
        return $this->restrictions;
    }

    public function dbRealName(string $short): string
    {
        $pre = (string) ($this->restrictions()['prefix'] ?? ($this->user . '_'));
        return $pre . $short;
    }

    public function maxLengths(): array
    {
        $r = $this->restrictions();
        return ['db' => (int) ($r['max_database_name_length'] ?? 64), 'user' => (int) ($r['max_username_length'] ?? 16)];
    }

    public function createDatabase(string $short): string
    {
        $real = $this->dbRealName($short);
        if ($this->databaseExists($real)) {
            throw new ProvisionException('La base de datos ' . $real . ' ya existe.', true);
        }
        $this->uapi('Mysql', 'create_database', ['name' => $real]);
        return $real;
    }

    public function createDbUser(string $short, string $pass): string
    {
        $real = $this->dbRealName($short);
        $this->uapi('Mysql', 'create_user', ['name' => $real, 'password' => $pass]);
        return $real;
    }

    public function grantAll(string $realUser, string $realDb): void
    {
        $this->uapi('Mysql', 'set_privileges_on_database', ['user' => $realUser, 'database' => $realDb, 'privileges' => 'ALL PRIVILEGES']);
    }

    public function deleteDatabase(string $realDb): void
    {
        if ($this->databaseExists($realDb)) {
            $this->uapi('Mysql', 'delete_database', ['name' => $realDb]);
        }
    }

    public function deleteDbUser(string $realUser): void
    {
        try {
            $this->uapi('Mysql', 'delete_user', ['name' => $realUser]);
        } catch (ProvisionException $e) {
            if (stripos($e->getMessage(), 'not exist') === false && stripos($e->getMessage(), 'does not') === false) {
                throw $e;
            }
        }
    }

    public function databaseExists(string $realDb): bool
    {
        $j = $this->uapi('Mysql', 'list_databases');
        foreach (($j['data'] ?? []) as $d) {
            if (($d['database'] ?? '') === $realDb) {
                return true;
            }
        }
        return false;
    }

    public function addAddonDomain(string $domain, string $sub, string $dir): void
    {
        $this->api2('AddonDomain', 'addaddondomain', ['dir' => $this->relDir($dir), 'newdomain' => $domain, 'subdomain' => $sub]);
    }

    public function delAddonDomain(string $domain, string $sub, string $root): void
    {
        $this->api2('AddonDomain', 'deladdondomain', ['domain' => $domain, 'subdomain' => $sub . '_' . $root]);
    }

    public function dbHost(): string
    {
        return 'localhost';
    }
}
