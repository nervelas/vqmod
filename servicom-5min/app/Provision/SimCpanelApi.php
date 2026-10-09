<?php
declare(strict_types=1);
namespace S5\Provision;

/**
 * "Provisionador local" que SIMULA cPanel: registra subdominios en un mapa (vhosts.json) que usa el
 * router de pruebas, y crea bases de datos / usuarios reales en MariaDB local. SOLO PARA PRUEBAS.
 */
final class SimCpanelApi implements CpanelApi
{
    private string $dir;
    private array $dbCfg;
    private string $prefix;
    private string $vroot;
    /** @var array<string,string> fallos inyectados: operación => mensaje */
    private array $fail;

    public function __construct(array $cfg)
    {
        $this->dir = rtrim((string) $cfg['sim_dir'], '/');
        $this->dbCfg = $cfg['sim_db'];
        $this->prefix = (string) ($cfg['sim_prefix'] ?? 'sim_');
        $this->vroot = rtrim((string) ($cfg['sim_vroot'] ?? ''), '/');
        $this->fail = [];
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    private function inject(string $op): void
    {
        $f = $this->dir . '/fail.json';
        if (is_file($f)) {
            $m = json_decode((string) file_get_contents($f), true) ?: [];
            if (isset($m[$op])) {
                throw new ProvisionException((string) $m[$op], false);
            }
        }
    }

    private function vh(): array
    {
        $f = $this->dir . '/vhosts.json';
        return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }

    private function saveVh(array $v): void
    {
        file_put_contents($this->dir . '/vhosts.json', json_encode($v, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** "Host virtual" simulado: enlace simbólico host -> docroot que usa VirtualDocumentRoot de Apache. */
    private function link(string $host, string $dir): void
    {
        if ($this->vroot === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            return;
        }
        $l = $this->vroot . '/' . $host;
        if (is_link($l)) {
            @unlink($l);
        }
        @symlink($dir, $l);
    }

    private function unlink(string $host): void
    {
        $l = $this->vroot . '/' . $host;
        if ($this->vroot !== '' && preg_match('/^[a-z0-9.-]+$/', $host) && is_link($l)) {
            @unlink($l);
        }
    }

    private function pdo(): \PDO
    {
        return new \PDO($this->dbCfg['dsn'], $this->dbCfg['user'], $this->dbCfg['pass'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    public function ping(): array
    {
        $this->inject('ping');
        return ['ok' => true, 'message' => 'Simulador local activo', 'user' => 'sim'];
    }

    public function addSubdomain(string $sub, string $root, string $dir): void
    {
        $this->inject('addSubdomain');
        $v = $this->vh();
        $fq = strtolower($sub . '.' . $root);
        $v[$fq] = $dir;
        $this->saveVh($v);
        $this->link($fq, $dir);
    }

    public function delSubdomain(string $sub, string $root): void
    {
        $v = $this->vh();
        unset($v[strtolower($sub . '.' . $root)]);
        $this->saveVh($v);
        $this->unlink(strtolower($sub . '.' . $root));
    }

    public function subdomainExists(string $sub, string $root): bool
    {
        $fq = strtolower($sub . '.' . $root);
        return isset($this->vh()[$fq]) || ($this->vroot !== '' && is_link($this->vroot . '/' . $fq));
    }

    public function dbRealName(string $short): string
    {
        return $this->prefix . $short;
    }

    public function maxLengths(): array
    {
        return ['db' => 64, 'user' => (int) ($this->dbCfg['max_user'] ?? 16)];
    }

    public function createDatabase(string $short): string
    {
        $this->inject('createDatabase');
        $n = $this->dbRealName($short);
        if ($this->databaseExists($n)) {
            throw new ProvisionException('La base de datos ' . $n . ' ya existe.', true);
        }
        $this->pdo()->exec('CREATE DATABASE `' . $n . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        return $n;
    }

    public function createDbUser(string $short, string $pass): string
    {
        $this->inject('createDbUser');
        $n = $this->dbRealName($short);
        $p = $this->pdo();
        $st = $p->prepare("SELECT COUNT(*) FROM mysql.user WHERE user=? AND host='localhost'");
        $st->execute([$n]);
        if ((int) $st->fetchColumn() > 0) {
            throw new ProvisionException('El usuario ' . $n . ' ya existe.', true);
        }
        $p->exec("CREATE USER '$n'@'localhost' IDENTIFIED BY " . $p->quote($pass));
        return $n;
    }

    public function grantAll(string $realUser, string $realDb): void
    {
        $this->pdo()->exec("GRANT ALL PRIVILEGES ON `$realDb`.* TO '$realUser'@'localhost'");
    }

    public function deleteDatabase(string $realDb): void
    {
        $this->pdo()->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '', $realDb) . '`');
    }

    public function deleteDbUser(string $realUser): void
    {
        $this->pdo()->exec("DROP USER IF EXISTS '" . str_replace("'", '', $realUser) . "'@'localhost'");
    }

    public function databaseExists(string $realDb): bool
    {
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
        $st->execute([$realDb]);
        return (int) $st->fetchColumn() > 0;
    }

    public function addAddonDomain(string $domain, string $sub, string $dir): void
    {
        $v = $this->vh();
        $v[strtolower($domain)] = $dir;
        $v['www.' . strtolower($domain)] = $dir;
        $this->saveVh($v);
        $this->link(strtolower($domain), $dir);
        $this->link('www.' . strtolower($domain), $dir);
    }

    public function delAddonDomain(string $domain, string $sub, string $root): void
    {
        $v = $this->vh();
        unset($v[strtolower($domain)], $v['www.' . strtolower($domain)]);
        $this->saveVh($v);
        $this->unlink(strtolower($domain));
        $this->unlink('www.' . strtolower($domain));
    }

    public function dbHost(): string
    {
        return (string) ($this->dbCfg['host'] ?? 'localhost');
    }
}
