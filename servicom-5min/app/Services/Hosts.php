<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Crypto;
use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Settings;
use S5\Provision\CpanelHttpApi;
use S5\Provision\HostDriver;
use S5\Provision\LocalDriver;
use S5\Provision\ProvisionException;
use S5\Provision\SimCpanelApi;

/**
 * Hosting de las webs de clientes. HOY solo se usa UNO (el de servicom.gt, configurado en el instalador y editable en Ajustes → cPanel).
 * La tabla `hosts`, `orders.host_id` y la interfaz HostDriver quedan listas para un segundo hosting en el futuro,
 * pero está desactivado y no tiene interfaz. La configuración se guarda cifrada.
 */
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

    /** Datos del hosting único (sin secretos) para el panel. */
    public static function mainConfig(): array
    {
        $h = self::get(self::defaultId());
        if (!$h) {
            return ['kind' => 'cpanel', 'host' => 'localhost', 'port' => 2083, 'user' => '', 'home' => '', 'has_token' => false, 'webs_path' => self::defaultWebsPath()];
        }
        $c = $h['cfg'];
        return ['kind' => $h['kind'], 'host' => $c['host'] ?? 'localhost', 'port' => $c['port'] ?? 2083, 'user' => $c['user'] ?? '', 'home' => $c['home'] ?? '', 'has_token' => !empty($c['token']), 'webs_path' => ($c['webs_path'] ?? '') ?: self::defaultWebsPath()];
    }

    /** Crea o actualiza el hosting único. El token vacío conserva el guardado. */
    public static function saveMain(array $in): int
    {
        $id = self::defaultId() ?: null;
        $old = $id ? self::get($id) : null;
        $cfg = $old['cfg'] ?? [];
        $kind = $old['kind'] ?? 'cpanel';
        if ($kind === 'cpanel') {
            $cfg['host'] = (string) ($in['host'] ?? ($cfg['host'] ?? 'localhost')) ?: 'localhost';
            $cfg['port'] = (int) ($in['port'] ?? ($cfg['port'] ?? 2083)) ?: 2083;
            $cfg['user'] = (string) ($in['user'] ?? ($cfg['user'] ?? ''));
            $tok = trim((string) ($in['token'] ?? ''));
            if ($tok !== '' && $tok !== '__guardado__') {
                $cfg['token'] = $tok;
            }
            $cfg['home'] = rtrim((string) ($in['home'] ?? ($cfg['home'] ?? '')), '/');
            $cfg['verify_ssl'] = $cfg['verify_ssl'] ?? true;
        }
        $cfg['domain_root'] = Settings::baseDomain();
        $cfg['webs_path'] = (string) (($in['webs_path'] ?? '') ?: ($cfg['webs_path'] ?? '') ?: self::defaultWebsPath());
        $hid = self::save($id, $old['name'] ?? 'Hosting principal', $kind, $cfg, true);
        Settings::set('default_host', (string) $hid);
        return $hid;
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

    public static function driver(int $hostId): HostDriver
    {
        $h = self::get($hostId);
        if (!$h || !$h['active']) {
            throw new ProvisionException('El hosting seleccionado no está disponible.', false);
        }
        $c = $h['cfg'];
        $root = (string) ($c['domain_root'] ?? Settings::baseDomain());
        switch ($h['kind']) {
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
