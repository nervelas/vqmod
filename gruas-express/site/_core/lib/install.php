<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/render.php';
require_once __DIR__ . '/integrity.php';
require_once __DIR__ . '/backup.php';

const GX_DENY_HTACCESS = "# Acceso web bloqueado\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\nOptions -Indexes\n";

function gx_installed(): bool
{
    $c = gx_config();
    return !empty($c['installed_at']) && !empty($c['db_file']) && is_file(GX_DATA . '/' . $c['db_file']);
}

/** Sustituye recursivamente las referencias "@m:clave" por el id del medio importado. */
function gx_resolve_media($v, array $map)
{
    if (is_array($v)) {
        foreach ($v as $k => $x) {
            $v[$k] = gx_resolve_media($x, $map);
        }
        return $v;
    }
    if (is_string($v) && strncmp($v, '@m:', 3) === 0) {
        return $map[substr($v, 3)] ?? 0;
    }
    return $v;
}

function gx_find_admin_dir(): ?string
{
    foreach ((array)@scandir(GX_ROOT) as $d) {
        if (is_string($d) && $d !== '.' && $d !== '..' && is_dir(GX_ROOT . '/' . $d) && is_file(GX_ROOT . '/' . $d . '/.htaccess')) {
            $c = (string)file_get_contents(GX_ROOT . '/' . $d . '/.htaccess');
            if (strpos($c, '# GX-AUTH-BEGIN') !== false) {
                return $d;
            }
        }
    }
    return null;
}

/** Escribe .htpasswd y activa HTTP Basic Auth en la carpeta del panel (una sola vez). */
function gx_apply_basic_auth(string $user, string $hash): bool
{
    $dir = gx_find_admin_dir();
    if ($dir === null || !preg_match('/^[a-zA-Z0-9._-]{1,40}$/', $user) || !preg_match('/^\$apr1\$[.\/0-9A-Za-z]{8}\$[.\/0-9A-Za-z]{22}$/', $hash)) {
        return false;
    }
    $ht = GX_DATA . '/.htpasswd';
    if (file_put_contents($ht, $user . ':' . $hash . "\n", LOCK_EX) === false) {
        return false;
    }
    @chmod($ht, 0644);
    $path = GX_ROOT . '/' . $dir . '/.htaccess';
    $c = (string)file_get_contents($path);
    $auth = "# GX-AUTH-BEGIN\n  AuthType Basic\n  AuthName \"Acceso restringido\"\n  AuthUserFile " . $ht . "\n  Require valid-user\n  # GX-AUTH-END";
    $new = preg_replace_callback('/# GX-AUTH-BEGIN.*?# GX-AUTH-END/s', static function () use ($auth): string {
        return $auth;
    }, $c, 1, $n);
    if ($new === null || $n !== 1 || file_put_contents($path, $new, LOCK_EX) === false) {
        return false;
    }
    $cfg = gx_config();
    $cfg['basic_auth'] = true;
    $cfg['admin_dir'] = $dir;
    gx_config_save($cfg);
    gx_baseline_update([$dir . '/.htaccess']);
    return true;
}

function gx_baseline_update(array $rels): void
{
    $b = gx_json_read(gx_baseline_path(), null);
    if (!is_array($b)) {
        return;
    }
    foreach ($rels as $rel) {
        $abs = GX_ROOT . '/' . $rel;
        if (is_file($abs)) {
            $b['files'][$rel] = hash_file('sha256', $abs);
        }
    }
    gx_json_write(gx_baseline_path(), $b);
}

function gx_ensure_installed(): void
{
    if (gx_installed()) {
        return;
    }
    if (!is_dir(GX_DATA) && !@mkdir(GX_DATA, 0755, true)) {
        gx_fail(500, 'Error interno.');
    }
    $lock = @fopen(GX_DATA . '/install.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        gx_fail(500, 'Error interno.');
    }
    gx_config([]);
    if (gx_installed()) {
        flock($lock, LOCK_UN);
        return;
    }
    try {
        gx_install_run();
    } catch (Throwable $t) {
        error_log('[INSTALL] ' . $t->getMessage() . ' @ ' . basename($t->getFile()) . ':' . $t->getLine());
        foreach (['config.json', 'baseline.json'] as $f) {
            @unlink(GX_DATA . '/' . $f);
        }
        foreach ((array)@glob(GX_DATA . '/*.sqlite') as $f) {
            @unlink($f);
        }
        gx_config([]);
        flock($lock, LOCK_UN);
        gx_fail(500, 'No se pudo completar la instalación automática. Verifique que la carpeta _core/data tenga permiso de escritura.');
    }
    flock($lock, LOCK_UN);
}

function gx_install_run(): void
{
    $seed = gx_json_read(GX_CORE . '/seed/content.json', null);
    $inst = gx_json_read(GX_CORE . '/seed/install.json', null);
    if (!is_array($seed) || !is_array($inst)) {
        throw new RuntimeException('seed missing');
    }
    foreach (['', '/sessions', '/backups', '/tmp'] as $sub) {
        $d = GX_DATA . $sub;
        if (!is_dir($d) && !@mkdir($d, 0755, true)) {
            throw new RuntimeException('mkdir ' . $sub);
        }
        file_put_contents($d . '/index.html', '');
        file_put_contents($d . '/.htaccess', GX_DENY_HTACCESS);
    }
    $cfg = [
        'db_file' => gx_rand_hex(12) . '.sqlite',
        'secret' => gx_rand_hex(32),
        'proxy_header' => '',
    ];
    gx_config($cfg);
    file_put_contents(GX_DATA . '/config.json', json_encode($cfg), LOCK_EX);
    @chmod(GX_DATA . '/config.json', 0600);

    $db = gx_db();
    gx_schema($db);
    $db->beginTransaction();

    $map = [];
    $ins = $db->prepare('INSERT INTO media (file, thumb, w, h, tw, th, alt, name, created) VALUES (:f,:t,:w,:h,:tw,:th,:a,:n,:c)');
    foreach ((array)$seed['media'] as $m) {
        $ins->execute([':f' => $m['file'], ':t' => $m['thumb'], ':w' => $m['w'], ':h' => $m['h'], ':tw' => $m['tw'], ':th' => $m['th'],
            ':a' => $m['alt'], ':n' => $m['name'], ':c' => gx_now()]);
        $map[$m['key']] = (int)$db->lastInsertId();
    }
    $sset = $db->prepare('INSERT INTO settings (k, v) VALUES (:k, :v)');
    foreach ((array)$seed['settings'] as $k => $v) {
        $sset->execute([':k' => $k, ':v' => json_encode(gx_resolve_media($v, $map), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
    $pins = $db->prepare('INSERT INTO pages (slug, sort, data) VALUES (:s, :o, :d)');
    foreach ((array)$seed['pages'] as $p) {
        $pins->execute([':s' => $p['slug'], ':o' => $p['sort'], ':d' => json_encode(gx_resolve_media($p['data'], $map), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
    $u = $db->prepare('INSERT INTO users (username, pass_hash, must_change, created) VALUES (:u, :h, 1, :c)');
    $u->execute([':u' => (string)$inst['admin_user'], ':h' => (string)$inst['admin_hash'], ':c' => gx_now()]);
    $db->commit();
    gx_settings_all(true);

    $res = gx_publish();
    if ($res['errors']) {
        throw new RuntimeException('publish: ' . implode('; ', $res['errors']));
    }
    if (!empty($inst['ht_user']) && !empty($inst['ht_hash'])) {
        gx_apply_basic_auth((string)$inst['ht_user'], (string)$inst['ht_hash']);
    }
    gx_baseline_build();
    $rl = gx_json_read(GX_CORE . '/release.json', []);
    if (!empty($rl['build'])) {
        gx_set('release_build', (string)$rl['build']);
    }
    gx_backup_db();
    $cfg = gx_config();
    $cfg['installed_at'] = gx_now();
    gx_config_save($cfg);
    gx_audit('INSTALL', 'Instalación automática completada', 'sistema');
}
