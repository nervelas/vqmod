<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Crypto;
use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Settings;
use S5\Provision\AgentDriver;
use S5\Provision\CpanelHttpApi;
use S5\Provision\HostDriver;
use S5\Provision\LocalDriver;
use S5\Provision\ProvisionException;
use S5\Provision\SimCpanelApi;

/** Hostings configurados y fábrica de drivers. La configuración se guarda cifrada. */
final class Hosts
{
    public static function all(bool $onlyActive = false): array
    {
        $rows = Db::all('SELECT * FROM ' . Db::t('hosts') . ($onlyActive ? ' WHERE active=1' : '') . ' ORDER BY id');
        foreach ($rows as &$r) {
            $r['cfg'] = self::cfg($r);
        }
        return $rows;
    }

    public static function get(int $id): ?array
    {
        $r = Db::one('SELECT * FROM ' . Db::t('hosts') . ' WHERE id=?', [$id]);
        if ($r) {
            $r['cfg'] = self::cfg($r);
        }
        return $r;
    }

    private static function cfg(array $r): array
    {
        $j = json_decode(Crypto::decrypt((string) $r['config']), true);
        return is_array($j) ? $j : [];
    }

    public static function save(?int $id, string $name, string $kind, array $cfg, bool $active = true): int
    {
        $enc = Crypto::encrypt(json_encode($cfg, JSON_UNESCAPED_UNICODE));
        if ($id) {
            Db::update('hosts', ['name' => $name, 'kind' => $kind, 'config' => $enc, 'active' => $active ? 1 : 0], 'id=?', [$id]);
            return $id;
        }
        return Db::insert('hosts', ['name' => $name, 'kind' => $kind, 'config' => $enc, 'active' => $active ? 1 : 0, 'created_at' => Db::now()]);
    }

    public static function defaultId(): int
    {
        $d = Settings::int('default_host', 0);
        if ($d && self::get($d)) {
            return $d;
        }
        $first = Db::val('SELECT id FROM ' . Db::t('hosts') . ' WHERE active=1 ORDER BY id LIMIT 1');
        return $first ? (int) $first : 0;
    }

    /** Número de webs por hosting (excluye eliminadas). */
    public static function counts(): array
    {
        $out = [];
        foreach (Db::all('SELECT host_id, COUNT(*) c FROM ' . Db::t('orders') . ' WHERE host_id IS NOT NULL AND status NOT IN (?,?) GROUP BY host_id', [Orders::ST_ELIMINADA, Orders::ST_BORRADOR]) as $r) {
            $out[(int) $r['host_id']] = (int) $r['c'];
        }
        return $out;
    }

    public static function driver(int $hostId): HostDriver
    {
        $h = self::get($hostId);
        if (!$h || !$h['active']) {
            throw new ProvisionException('El hosting seleccionado no está disponible.', false);
        }
        $c = $h['cfg'];
        $root = (string) ($c['domain_root'] ?? Settings::baseDomain());
        switch ($h['kind']) {
            case 'agent':
                return new AgentDriver([
                    'agent_url' => $c['agent_url'], 'agent_secret' => $c['agent_secret'], 'webs_path' => $c['webs_path'] ?? '',
                    'verify_ssl' => $c['verify_ssl'] ?? true,
                    'asset_signer' => fn(array $a) => AssetUrls::sign($a, (string) $c['agent_secret']),
                ]);
            case 'sim':
                $api = new SimCpanelApi($c);
                return new LocalDriver([
                    'webs_path' => $c['webs_path'], 'base_path' => $c['base_path'] ?? null, 'domain_root' => $root, 'api' => $api,
                    'php_cli' => $c['php_cli'] ?? '', 'loopback_url' => $c['loopback_url'] ?? 'http://127.0.0.1',
                    'fail_file' => ($c['sim_dir'] ?? '') . '/fail.json', 'runner' => $c['runner'] ?? 'auto',
                ]);
            default:
                $api = new CpanelHttpApi($c);
                return new LocalDriver([
                    'webs_path' => $c['webs_path'] ?: self::defaultWebsPath(), 'base_path' => $c['base_path'] ?? null, 'domain_root' => $root, 'api' => $api,
                    'php_cli' => $c['php_cli'] ?? '', 'loopback_url' => $c['loopback_url'] ?? 'http://127.0.0.1', 'verify_ssl' => $c['verify_ssl'] ?? true,
                ]);
        }
    }

    /** Ruta por defecto de las webs: hermana del docroot, NUNCA dentro de public_html del sitio principal. */
    public static function defaultWebsPath(): string
    {
        $s = (string) Settings::get('webs_path', '');
        if ($s !== '') {
            return rtrim($s, '/');
        }
        return dirname(S5_ROOT) . '/webs-clientes';
    }
}
