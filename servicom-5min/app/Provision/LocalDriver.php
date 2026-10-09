<?php
declare(strict_types=1);
namespace S5\Provision;

use S5\Core\Fs;

/** Driver que opera en la MISMA máquina donde corre este código (portal o agente). */
final class LocalDriver implements HostDriver
{
    private string $webs;
    private string $base;
    private string $root;
    private CpanelApi $api;
    private array $cfg;

    /** @param array $cfg webs_path, base_path?, domain_root, api(CpanelApi), php_cli?, loopback_url?, fail_file?, provision_script? */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->webs = rtrim((string) $cfg['webs_path'], '/');
        $this->base = rtrim((string) ($cfg['base_path'] ?? ($this->webs . '/_base')), '/');
        $this->root = strtolower((string) $cfg['domain_root']);
        $this->api = $cfg['api'];
    }

    private function inject(string $op): void
    {
        $f = $this->cfg['fail_file'] ?? '';
        if ($f && is_file($f)) {
            $m = json_decode((string) file_get_contents($f), true) ?: [];
            if (isset($m[$op])) {
                throw new ProvisionException((string) $m[$op], false);
            }
        }
    }

    /** Gancho de pruebas: valor de una clave del archivo de fallos simulados (solo existe en entornos de prueba). */
    private function failValue(string $op)
    {
        $f = $this->cfg['fail_file'] ?? '';
        if ($f && is_file($f)) {
            $m = json_decode((string) file_get_contents($f), true) ?: [];
            return $m[$op] ?? null;
        }
        return null;
    }

    private function checkSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $slug) || $slug === '_base') {
            throw new ProvisionException('Nombre de sitio no válido.', false);
        }
    }

    public function docroot(string $slug): string
    {
        $this->checkSlug($slug);
        return $this->webs . '/' . $slug;
    }

    private function safeDocroot(string $docroot): string
    {
        $docroot = rtrim($docroot, '/');
        if (dirname($docroot) !== $this->webs || basename($docroot) === '_base' || !Fs::inside($docroot, $this->webs)) {
            throw new ProvisionException('Ruta fuera de la zona permitida.', false);
        }
        $this->checkSlug(basename($docroot));
        return $docroot;
    }

    public function ping(): array
    {
        $exts = ['zip', 'dom', 'xml', 'mbstring', 'fileinfo', 'curl', 'gd', 'imagick', 'mysqli', 'json', 'openssl', 'sodium'];
        $have = [];
        foreach ($exts as $x) {
            $have[$x] = extension_loaded($x);
        }
        $api = $this->api->ping();
        $baseOk = is_file($this->base . '/wp-includes/version.php');
        $wpv = '';
        if ($baseOk && preg_match("/\\\$wp_version\s*=\s*'([^']+)'/", (string) file_get_contents($this->base . '/wp-includes/version.php'), $m)) {
            $wpv = $m[1];
        }
        $plugins = [];
        foreach (['elementor', 'woocommerce', 'fluentform', 'contact-form-7'] as $p) {
            $plugins[$p] = is_dir($this->base . '/wp-content/plugins/' . $p);
        }
        return [
            'ok' => $api['ok'] && $baseOk && is_dir($this->webs) && is_writable($this->webs),
            'php' => PHP_VERSION,
            'extensions' => $have,
            'disk_free' => Fs::freeSpace(is_dir($this->webs) ? $this->webs : dirname($this->webs)),
            'webs_path' => $this->webs,
            'webs_writable' => is_dir($this->webs) && is_writable($this->webs),
            'base_ok' => $baseOk,
            'wp_version' => $wpv,
            'plugins' => $plugins,
            'theme' => is_dir($this->base . '/wp-content/themes/servicom'),
            'mu_plugin' => is_file($this->base . '/wp-content/mu-plugins/servicom-core.php'),
            'provision_script' => is_file($this->base . '/.provision/sc-provision.php'),
            'cpanel' => $api,
            'php_cli' => $this->phpCli(),
            'proc_open' => $this->canProc(),
        ];
    }

    public function subdomainCreate(string $slug, string $docroot): array
    {
        $this->inject('subdomain');
        $docroot = $this->safeDocroot($docroot);
        if (!is_dir($docroot) && !@mkdir($docroot, 0755, true) && !is_dir($docroot)) {
            throw new ProvisionException('No se pudo crear la carpeta del sitio.', false);
        }
        $this->api->addSubdomain($slug, $this->root, $docroot);
        return ['fqdn' => $slug . '.' . $this->root];
    }

    public function subdomainExists(string $slug): bool
    {
        $this->checkSlug($slug);
        return $this->api->subdomainExists($slug, $this->root);
    }

    public function subdomainDelete(string $slug): void
    {
        $this->checkSlug($slug);
        $this->api->delSubdomain($slug, $this->root);
    }

    public function dbCreate(string $shortDb, string $shortUser, string $pass): array
    {
        $this->inject('db');
        $db = $this->api->createDatabase($shortDb);
        $user = $this->api->createDbUser($shortUser, $pass);
        $this->api->grantAll($user, $db);
        return ['db' => $db, 'user' => $user, 'host' => $this->api->dbHost()];
    }

    public function dbDelete(string $db, string $user): void
    {
        if ($db !== '') {
            $this->api->deleteDatabase($db);
        }
        if ($user !== '') {
            $this->api->deleteDbUser($user);
        }
    }

    private function fileList(): array
    {
        $cache = $this->base . '/.filelist.json';
        $stamp = (string) @filemtime($this->base . '/wp-includes/version.php') . '|' . (string) @filemtime($this->base . '/wp-content');
        if (is_file($cache)) {
            $j = json_decode((string) file_get_contents($cache), true);
            if (is_array($j) && ($j['stamp'] ?? '') === $stamp) {
                return $j['files'];
            }
        }
        if (!is_dir($this->base)) {
            throw new ProvisionException('Falta el paquete base (webs-clientes/_base). Ejecute tools/build_base.php.', false);
        }
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        $len = strlen($this->base) + 1;
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), $len);
            if ($rel === '.filelist.json' || str_starts_with($rel, '.provision') || str_starts_with($rel, '.git') || $f->isLink()) {
                continue;
            }
            if ($f->isDir()) {
                $files[] = [$rel, 'd', 0];
            } else {
                $files[] = [$rel, 'f', $f->getSize()];
            }
        }
        usort($files, fn($a, $b) => strcmp($a[0], $b[0]));
        @file_put_contents($cache, json_encode(['stamp' => $stamp, 'files' => $files]), LOCK_EX);
        return $files;
    }

    public function copyBase(string $docroot, int $cursor, int $budgetSec = 12): array
    {
        $this->inject('copy');
        $docroot = $this->safeDocroot($docroot);
        $files = $this->fileList();
        $total = count($files);
        if ($cursor === 0) {
            $need = 0;
            foreach ($files as $f) {
                $need += $f[2];
            }
            $free = Fs::freeSpace($this->webs);
            if ($free >= 0 && $free < $need * 1.3 + 30 * 1048576) {
                throw new ProvisionException('No hay espacio suficiente en el hosting para crear la web.', false);
            }
        }
        $t0 = microtime(true);
        $n = 0;
        $i = $cursor;
        for (; $i < $total; $i++) {
            [$rel, $type, $size] = $files[$i];
            $dst = $docroot . '/' . $rel;
            if ($type === 'd') {
                if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
                    throw new ProvisionException('No se pudo crear una carpeta (disco lleno o permisos).', false);
                }
            } else {
                if (!is_file($dst) || filesize($dst) !== $size) {
                    $dir = dirname($dst);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $tmp = $dst . '.part';
                    if (!@copy($this->base . '/' . $rel, $tmp) || !@rename($tmp, $dst)) {
                        @unlink($tmp);
                        throw new ProvisionException('No se pudo copiar un archivo (disco lleno o permisos).', false);
                    }
                    @chmod($dst, 0644);
                }
            }
            $n++;
            $ca = $this->failValue('copy_after');
            if ($ca !== null && $i + 1 >= (int) $ca) {
                throw new ProvisionException('No hay espacio suficiente en el disco del hosting (simulado).', false);
            }
            if ($n % 50 === 0 && (microtime(true) - $t0) > $budgetSec) {
                $i++;
                break;
            }
        }
        return ['done' => $i >= $total, 'cursor' => $i, 'total' => $total];
    }

    public function writeFile(string $docroot, string $rel, string $content, int $mode = 0640): void
    {
        $docroot = $this->safeDocroot($docroot);
        if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/') || str_contains($rel, "\0")) {
            throw new ProvisionException('Ruta de archivo no válida.', false);
        }
        $dst = $docroot . '/' . $rel;
        if (!Fs::inside($dst, $docroot)) {
            throw new ProvisionException('Ruta de archivo fuera del sitio.', false);
        }
        $dir = dirname($dst);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ProvisionException('No se pudo crear la carpeta.', false);
        }
        if (@file_put_contents($dst, $content, LOCK_EX) === false) {
            throw new ProvisionException('No se pudo escribir un archivo (disco lleno o permisos).', false);
        }
        @chmod($dst, $mode);
    }

    public function deleteFile(string $docroot, string $rel): void
    {
        $docroot = $this->safeDocroot($docroot);
        if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/') || str_contains($rel, "\0")) {
            throw new ProvisionException('Ruta de archivo no válida.', false);
        }
        $dst = $docroot . '/' . $rel;
        if (is_file($dst) && Fs::inside($dst, $docroot)) {
            @unlink($dst);
        }
    }

    public function stageJob(string $docroot, string $jobId, string $manifestJson, array $assets, string $secret): void
    {
        $docroot = $this->safeDocroot($docroot);
        if (!preg_match('/^[a-z0-9]{6,40}$/', $jobId)) {
            throw new ProvisionException('Identificador de trabajo no válido.', false);
        }
        $jobs = $docroot . '/wp-content/sc-jobs';
        $job = $jobs . '/' . $jobId;
        @mkdir($job . '/assets', 0750, true);
        file_put_contents($jobs . '/.htaccess', "Require all denied\nDeny from all\n");
        file_put_contents($jobs . '/index.html', '');
        file_put_contents($jobs . '/secret.php', "<?php\nreturn " . var_export($secret, true) . ";\n");
        @chmod($jobs . '/secret.php', 0640);
        file_put_contents($job . '/manifest.json', $manifestJson, LOCK_EX);
        foreach ($assets as $a) {
            $rel = (string) ($a['path'] ?? '');
            if (!preg_match('#^assets/[A-Za-z0-9._-]+$#', $rel)) {
                throw new ProvisionException('Ruta de recurso no válida.', false);
            }
            $dst = $job . '/' . $rel;
            if (is_file($dst) && (empty($a['sha256']) || hash_file('sha256', $dst) === $a['sha256'])) {
                continue;
            }
            if (!empty($a['local'])) {
                if (!@copy((string) $a['local'], $dst)) {
                    throw new ProvisionException('No se pudo copiar un recurso del cliente.', false);
                }
            } elseif (!empty($a['url'])) {
                $this->download((string) $a['url'], $dst, (int) ($a['size'] ?? 0));
            } else {
                throw new ProvisionException('Recurso sin origen.', false);
            }
            if (!empty($a['sha256']) && hash_file('sha256', $dst) !== $a['sha256']) {
                @unlink($dst);
                throw new ProvisionException('Un recurso del cliente llegó dañado.', true);
            }
        }
    }

    private function download(string $url, string $dst, int $expectSize): void
    {
        $fh = fopen($dst . '.part', 'wb');
        if (!$fh) {
            throw new ProvisionException('No se pudo escribir un recurso.', false);
        }
        $max = max(1048576, $expectSize + 1024);
        $got = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->cfg['verify_ssl'] ?? true,
            CURLOPT_SSL_VERIFYHOST => ($this->cfg['verify_ssl'] ?? true) ? 2 : 0,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($fh, &$got, $max) {
                $got += strlen($data);
                if ($got > $max + 20971520) {
                    return 0;
                }
                return fwrite($fh, $data);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fh);
        if (!$ok || $code !== 200) {
            @unlink($dst . '.part');
            throw new ProvisionException('No se pudo descargar un recurso del cliente (HTTP ' . $code . ').', true);
        }
        rename($dst . '.part', $dst);
    }

    public function phpCli(): string
    {
        if (!empty($this->cfg['php_cli'])) {
            return (string) $this->cfg['php_cli'];
        }
        $c = [];
        if (defined('PHP_BINARY') && PHP_BINARY && !preg_match('/fpm|cgi|lsphp|litespeed/i', PHP_BINARY)) {
            $c[] = PHP_BINARY;
        }
        foreach (['/opt/cpanel/ea-php84', '/opt/cpanel/ea-php83', '/opt/cpanel/ea-php82', '/opt/cpanel/ea-php81', '/opt/cpanel/ea-php80'] as $d) {
            $c[] = $d . '/root/usr/bin/php';
        }
        array_push($c, '/usr/local/bin/php', '/usr/bin/php');
        foreach ($c as $p) {
            if (is_file($p) && is_executable($p)) {
                return $p;
            }
        }
        return '';
    }

    private function canProc(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $dis = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $dis, true);
    }

    private function scriptSource(): string
    {
        return $this->base . '/.provision/sc-provision.php';
    }

    private function placeScript(string $docroot): void
    {
        $src = $this->scriptSource();
        if (!is_file($src)) {
            throw new ProvisionException('Falta sc-provision.php en el paquete base. Ejecute tools/build_base.php.', false);
        }
        $dst = $docroot . '/sc-provision.php';
        if (!is_file($dst) || md5_file($dst) !== md5_file($src)) {
            if (!@copy($src, $dst)) {
                throw new ProvisionException('No se pudo preparar el script de construcción.', false);
            }
        }
    }

    public function provision(string $docroot, string $jobId, string $step, array $args = []): array
    {
        $this->inject('provision_' . $step);
        $docroot = $this->safeDocroot($docroot);
        if (!preg_match('/^[a-z_]{2,20}$/', $step) || !preg_match('/^[a-z0-9]{6,40}$/', $jobId)) {
            throw new ProvisionException('Paso no válido.', false);
        }
        $this->placeScript($docroot);
        $jobDir = $docroot . '/wp-content/sc-jobs/' . $jobId;
        $timeout = (int) ($this->cfg['step_timeout'] ?? 110);
        $php = $this->phpCli();
        $mode = (string) ($this->cfg['runner'] ?? 'auto');
        $res = null;
        if ($php !== '' && $this->canProc() && $mode !== 'http') {
            $res = $this->runCli($php, $docroot, $jobDir, $step, $args, $timeout);
        }
        if ($res === null) {
            $res = $this->runHttp($docroot, $jobId, $step, $args, $timeout);
        }
        return $res;
    }

    private function runCli(string $php, string $docroot, string $jobDir, string $step, array $args, int $timeout): ?array
    {
        $cmd = [$php, '-d', 'display_errors=0', '-d', 'memory_limit=512M', $docroot . '/sc-provision.php', $jobDir, $step];
        foreach ($args as $k => $v) {
            $cmd[] = '--' . preg_replace('/[^a-z_]/', '', (string) $k) . '=' . (string) $v;
        }
        $env = ['PATH' => '/usr/local/bin:/usr/bin:/bin', 'HOME' => sys_get_temp_dir()];
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $docroot, $env);
        if (!is_resource($p)) {
            return null;
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        $t0 = time();
        while (true) {
            $st = proc_get_status($p);
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            if (!$st['running']) {
                break;
            }
            if (time() - $t0 > $timeout) {
                proc_terminate($p, 9);
                proc_close($p);
                return ['ok' => false, 'error' => 'El paso tardó demasiado y se canceló.', 'retry' => true];
            }
            usleep(50000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        proc_close($p);
        return $this->parseLine($out, $err);
    }

    private function parseLine(string $out, string $err = ''): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $out)), 'strlen'));
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $j = json_decode($lines[$i], true);
            if (is_array($j) && array_key_exists('ok', $j)) {
                return $j;
            }
        }
        return ['ok' => false, 'error' => 'Respuesta inválida del constructor' . ($err !== '' ? ': ' . mb_substr(trim($err), 0, 200) : ''), 'retry' => true];
    }

    private function runHttp(string $docroot, string $jobId, string $step, array $args, int $timeout): array
    {
        $secretFile = $docroot . '/wp-content/sc-jobs/secret.php';
        $secret = is_file($secretFile) ? (string) include $secretFile : '';
        $ts = time();
        $sig = hash_hmac('sha256', $step . '|' . $jobId . '|' . $ts, $secret);
        $q = http_build_query(array_merge($args, ['job' => $jobId, 'step' => $step, 'ts' => $ts, 'sig' => $sig]));
        $slug = basename($docroot);
        $host = $slug . '.' . $this->root;
        $loop = rtrim((string) ($this->cfg['loopback_url'] ?? 'http://127.0.0.1'), '/');
        $ch = curl_init($loop . '/sc-provision.php?' . $q);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Host: ' . $host],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if (!is_string($body)) {
            return ['ok' => false, 'error' => 'No se pudo contactar el constructor (' . $err . ').', 'retry' => true];
        }
        return $this->parseLine($body);
    }

    public function removeJob(string $docroot, string $jobId): void
    {
        $docroot = $this->safeDocroot($docroot);
        if (!preg_match('/^[a-z0-9]{6,40}$/', $jobId)) {
            return;
        }
        $job = $docroot . '/wp-content/sc-jobs/' . $jobId;
        if (is_dir($job)) {
            Fs::rmTreeSafe($job, [$docroot]);
        }
        @unlink($docroot . '/wp-content/sc-jobs/secret.php');
    }

    public function removeProvisionScript(string $docroot): void
    {
        $docroot = $this->safeDocroot($docroot);
        @unlink($docroot . '/sc-provision.php');
        @unlink($docroot . '/wp-content/sc-jobs/secret.php');
    }

    public function removeSite(string $docroot): void
    {
        $docroot = $this->safeDocroot(rtrim($docroot, '/'));
        if (!is_dir($docroot) && !is_link($docroot)) {
            return;
        }
        Fs::rmTreeSafe($docroot, [$this->webs]);
    }

    public function attachDomain(string $domain, string $slug, string $docroot): void
    {
        $this->inject('attach');
        $docroot = $this->safeDocroot($docroot);
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $domain)) {
            throw new ProvisionException('Dominio no válido.', false);
        }
        $this->api->addAddonDomain($domain, $slug . '-dom', $docroot);
    }

    public function detachDomain(string $domain, string $slug): void
    {
        $this->api->delAddonDomain($domain, $slug . '-dom', $this->root);
    }

    public function siteExists(string $docroot): bool
    {
        return is_dir($docroot) && is_file($docroot . '/wp-config.php');
    }
}
