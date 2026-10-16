<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

/* =====================================================================
 *  Monitor de integridad + modo bloqueo
 *  - Línea base (SHA-256) de todos los archivos de código del sitio.
 *  - Las páginas generadas ("Publicar") se comprueban contra su manifiesto.
 *  - Cualquier cambio en código, PHP en carpetas públicas o archivos de script
 *    activa el modo bloqueo: panel y formulario se desactivan y se avisa por correo.
 *    El sitio público estático sigue funcionando.
 * ===================================================================== */

const GX_SCRIPT_EXT = '/\.(php\d?|phtml|pht|phar|phps|pgif|shtml|cgi|pl|py|sh|bash|asp|aspx|jsp|exe|dll|so)$/i';

/** Carpetas que no entran en la línea base (contenido dinámico controlado). */
function gx_skip_dir(string $rel): bool
{
    return $rel === 'uploads' || $rel === 'media' || $rel === '_core/data' || $rel === '_docs' || $rel === '.git';
}

/** Archivos que cambian por diseño y se comprueban aparte. */
function gx_is_volatile(string $rel): bool
{
    return in_array($rel, ['README.txt', 'LEEME.txt', 'favicon.ico', 'favicon.png', 'favicon-192.png', 'apple-touch-icon.png'], true)
        || $rel === '_core/release.json'
        || (bool)preg_match('/^[^\/]+\.md$/', $rel);   // documentación en la raíz (bloqueada por .htaccess; se puede borrar)
}

/** @return array<string,string> ruta relativa => ruta absoluta */
function gx_list_files(): array
{
    $out = [];
    $root = GX_ROOT;
    $stack = [''];
    while ($stack) {
        $relDir = array_pop($stack);
        $dir = $relDir === '' ? $root : $root . '/' . $relDir;
        $h = @opendir($dir);
        if (!$h) {
            continue;
        }
        while (($f = readdir($h)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $rel = $relDir === '' ? $f : $relDir . '/' . $f;
            $abs = $root . '/' . $rel;
            if (is_link($abs)) {
                $out[$rel] = $abs;   // un enlace simbólico es sospechoso: se reporta
                continue;
            }
            if (is_dir($abs)) {
                if (!gx_skip_dir($rel)) {
                    $stack[] = $rel;
                }
            } elseif (is_file($abs)) {
                $out[$rel] = $abs;
            }
        }
        closedir($h);
    }
    ksort($out);
    return $out;
}

/** Archivos generados por "Publicar": se excluyen de la línea base y se validan con su manifiesto. */
function gx_generated_set(): array
{
    $m = gx_setting('gen_manifest', []);
    return is_array($m) ? $m : [];
}

function gx_baseline_path(): string
{
    return GX_DATA . '/baseline.json';
}

function gx_baseline_build(): int
{
    $gen = gx_generated_set();
    $files = [];
    foreach (gx_list_files() as $rel => $abs) {
        if (isset($gen[$rel]) || gx_is_volatile($rel) || is_link($abs)) {
            continue;
        }
        $files[$rel] = hash_file('sha256', $abs);
    }
    gx_json_write(gx_baseline_path(), ['created' => gx_now(), 'files' => $files]);
    @chmod(gx_baseline_path(), 0600);
    return count($files);
}

/**
 * Compara el estado actual con la línea base.
 * @return array{critical:array<int,string>,generated:array<int,string>,forbidden:array<int,string>,modified:array<int,string>,added:array<int,string>,deleted:array<int,string>,ok:bool,checked:int}
 */
function gx_integrity_scan(): array
{
    $base = gx_json_read(gx_baseline_path(), null);
    $r = ['critical' => [], 'generated' => [], 'forbidden' => [], 'modified' => [], 'added' => [], 'deleted' => [], 'ok' => true, 'checked' => 0];
    if (!is_array($base) || !isset($base['files'])) {
        $r['ok'] = false;
        $r['critical'][] = 'Falta la línea base de integridad';
        return $r;
    }
    $gen = gx_generated_set();
    $now = gx_list_files();
    foreach ($now as $rel => $abs) {
        if (is_link($abs)) {
            $r['forbidden'][] = $rel . ' (enlace simbólico)';
            continue;
        }
        if (isset($gen[$rel])) {
            $r['checked']++;
            if (!hash_equals((string)$gen[$rel], (string)hash_file('sha256', $abs))) {
                $r['generated'][] = $rel;
            }
            continue;
        }
        if (gx_is_volatile($rel)) {
            continue;
        }
        $r['checked']++;
        if (!isset($base['files'][$rel])) {
            $r['added'][] = $rel;
        } elseif (!hash_equals((string)$base['files'][$rel], (string)hash_file('sha256', $abs))) {
            $r['modified'][] = $rel;
        }
    }
    foreach ($base['files'] as $rel => $_h) {
        if (!isset($now[$rel])) {
            $r['deleted'][] = $rel;
        }
    }
    foreach ($gen as $rel => $_h) {
        if (!isset($now[$rel])) {
            $r['deleted'][] = $rel . ' (página generada)';
        }
    }
    // archivos de script / extraños en carpetas de contenido
    foreach (['uploads', 'media', '_core/data'] as $d) {
        $dir = GX_ROOT . '/' . $d;
        $it = @scandir($dir);
        if ($it === false) {
            continue;
        }
        foreach ($it as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            if (preg_match(GX_SCRIPT_EXT, $f)) {
                $r['forbidden'][] = $d . '/' . $f;
            } elseif ($d === 'uploads' && is_file($p)) {
                if ($f !== 'index.html' && $f !== '.htaccess' && !preg_match('/^[a-z0-9][a-z0-9_-]*(-t)?\.webp$/', $f)) {
                    $r['forbidden'][] = $d . '/' . $f . ' (no es una imagen del panel)';
                } elseif (gx_has_php_tag($p)) {
                    $r['forbidden'][] = $d . '/' . $f . ' (contiene código PHP)';
                }
            } elseif ($d === 'media' && is_file($p)) {
                if ($f !== 'index.html' && $f !== '.htaccess' && !preg_match('/^[a-z0-9][a-z0-9_-]*\.mp4$/', $f)) {
                    $r['forbidden'][] = $d . '/' . $f . ' (archivo no permitido)';
                }
            }
        }
    }
    // PHP no autorizado en cualquier carpeta pública
    foreach ($r['added'] as $rel) {
        if (preg_match(GX_SCRIPT_EXT, $rel)) {
            $r['forbidden'][] = $rel;
        }
    }
    $r['critical'] = array_values(array_unique(array_merge($r['modified'], $r['added'], $r['deleted'], $r['forbidden'])));
    $r['ok'] = !$r['critical'] && !$r['generated'];
    return $r;
}

/** ¿El archivo contiene una etiqueta de apertura de PHP en sus primeros o últimos bytes? */
function gx_has_php_tag(string $path): bool
{
    $size = (int)@filesize($path);
    if ($size <= 0) {
        return false;
    }
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $c = (string)fread($fh, 8192);
    if ($size > 16384) {
        fseek($fh, -8192, SEEK_END);
        $c .= (string)fread($fh, 8192);
    }
    fclose($fh);
    return (bool)preg_match('/<\?php|<\?=|<script\s+language\s*=\s*["\']?php/i', $c);
}

/* ---------------- modo bloqueo ---------------- */

function gx_lockdown_path(): string
{
    return GX_DATA . '/lockdown.flag';
}

function gx_lockdown_active(): bool
{
    return is_file(gx_lockdown_path());
}

function gx_lockdown_info(): array
{
    $d = gx_json_read(gx_lockdown_path(), []);
    return is_array($d) ? $d : [];
}

function gx_lockdown_trigger(array $report): void
{
    if (gx_lockdown_active()) {
        return;
    }
    $list = array_slice($report['critical'], 0, 40);
    gx_json_write(gx_lockdown_path(), ['since' => gx_now(), 'files' => $list, 'ip' => gx_ip()]);
    @chmod(gx_lockdown_path(), 0600);
    try {
        gx_audit('LOCKDOWN', 'Modo bloqueo activado: ' . implode(', ', array_slice($list, 0, 8)), 'sistema');
    } catch (Throwable $t) {
        error_log('lockdown audit: ' . $t->getMessage());
    }
    gx_integrity_alert('ALERTA DE SEGURIDAD: modo bloqueo activado',
        "Se detectaron archivos modificados, agregados o eliminados sin autorización.\n"
        . "El panel y el formulario fueron desactivados. El sitio público sigue funcionando.\n\n"
        . implode("\n", $list) . "\n\n"
        . "Qué hacer: 1) Cambie las contraseñas de cPanel/FTP. 2) Restaure los archivos limpios desde el ZIP original.\n"
        . "3) Borre _core/data/lockdown.flag y _core/data/baseline.json por FTP para reactivar el panel.");
}

function gx_integrity_alert(string $subject, string $body): void
{
    require_once __DIR__ . '/mailer.php';
    $to = (string)gx_setting('alert_email', '');
    if ($to === '') {
        $to = (string)gx_setting('form_to', '');
    }
    if ($to !== '') {
        gx_send_mail($to, $subject . ' - ' . gx_mail_host(), $body);
    }
}

/**
 * Se ejecuta al inicio de cada punto de entrada PHP. Si hay bloqueo, devuelve true.
 * Revisa la integridad como máximo cada 60 s (o siempre si $force).
 */
function gx_guard(bool $force = false): bool
{
    if (gx_lockdown_active()) {
        return true;
    }
    if (gx_installed() && gx_release_apply_if_new()) {
        return false;
    }
    if (!is_file(gx_baseline_path())) {
        // el propietario restauró archivos limpios y borró la línea base: se recrea con el estado actual
        $n = gx_baseline_build();
        gx_audit('LINEA_BASE_RECREADA', $n . ' archivos', 'sistema');
        gx_integrity_alert('Aviso: línea base de integridad recreada', 'Se volvió a calcular la huella de ' . $n . " archivos.\nSi usted no borró baseline.json, revise el servidor.");
    }
    $state = GX_DATA . '/integrity.state';
    $st = gx_json_read($state, []);
    if (!$force && is_array($st) && isset($st['t']) && gx_now() - (int)$st['t'] < 60) {
        return false;
    }
    $rep = gx_integrity_scan();
    gx_json_write($state, ['t' => gx_now(), 'ok' => $rep['ok'], 'critical' => count($rep['critical']), 'generated' => count($rep['generated'])]);
    if ($rep['critical']) {
        gx_lockdown_trigger($rep);
        return true;
    }
    if ($rep['generated'] && (int)gx_setting('alert_gen_ts', 0) < gx_now() - 3600) {
        gx_set('alert_gen_ts', gx_now());
        gx_audit('INTEGRIDAD', 'Páginas generadas modificadas: ' . implode(', ', array_slice($rep['generated'], 0, 6)), 'sistema');
        gx_integrity_alert('ALERTA: páginas públicas modificadas fuera del panel',
            "Estas páginas estáticas no coinciden con la última publicación:\n" . implode("\n", $rep['generated'])
            . "\n\nEntre al panel > Seguridad y pulse Publicar para restaurarlas, y cambie sus contraseñas de FTP/cPanel.");
    }
    return false;
}


/** Huella de un archivo ignorando el bloque de contraseña HTTP (que el instalador modifica por diseño). */
function gx_norm_hash(string $abs): string
{
    $c = (string)@file_get_contents($abs);
    if (strpos($c, '# GX-AUTH-BEGIN') !== false) {
        $c = (string)preg_replace('/# GX-AUTH-BEGIN.*?# GX-AUTH-END/s', '# GX-AUTH', $c);
    }
    return hash('sha256', $c);
}

/** Vuelve a activar la contraseña HTTP del panel si el ZIP nuevo restauró el .htaccess sin ella. */
function gx_reapply_auth(): void
{
    $ht = GX_DATA . '/.htpasswd';
    $dir = gx_find_admin_dir();
    if (!is_file($ht) || $dir === null) {
        return;
    }
    if (strpos((string)@file_get_contents(GX_ROOT . '/' . $dir . '/.htaccess'), 'AuthUserFile') !== false) {
        return;
    }
    $parts = explode(':', trim((string)file_get_contents($ht)), 2);
    if (count($parts) === 2) {
        gx_apply_basic_auth($parts[0], $parts[1]);
    }
}

/**
 * Actualización por ZIP: si _core/release.json trae una versión nueva y TODOS los archivos de código
 * coinciden con ella, se acepta sola: se regeneran las páginas, se rehace la línea base y se reactiva la contraseña HTTP.
 * Si algún archivo no coincide (código ajeno o modificado), no se acepta y el monitor normal activa el bloqueo.
 */
function gx_release_apply_if_new(): bool
{
    $rel = gx_json_read(GX_CORE . '/release.json', null);
    if (!is_array($rel) || empty($rel['build']) || !is_array($rel['files'] ?? null)) {
        return false;
    }
    if ((string)gx_setting('release_build', '') === (string)$rel['build']) {
        return false;
    }
    $gen = array_flip((array)($rel['generated'] ?? [])) + gx_generated_set();
    $now = gx_list_files();
    foreach ($now as $r => $abs) {
        if (isset($gen[$r]) || gx_is_volatile($r)) {
            continue;
        }
        if (is_link($abs)) {
            return false;
        }
        if (isset($rel['files'][$r])) {
            if (!hash_equals((string)$rel['files'][$r], gx_norm_hash($abs))) {
                return false;
            }
        } elseif (preg_match(GX_SCRIPT_EXT, $r) || gx_has_php_tag($abs)) {
            return false;   // script ajeno al paquete
        }
    }
    foreach ($rel['files'] as $r => $_h) {
        if (!isset($now[$r])) {
            return false;
        }
    }
    $note = 'OK';
    try {
        $res = gx_publish();
        if ($res['errors']) {
            $note = 'publicación con avisos: ' . implode(' ', $res['errors']);
        }
    } catch (Throwable $t) {
        $note = 'error al publicar: ' . $t->getMessage();
    }
    gx_reapply_auth();
    $n = gx_baseline_build();
    gx_set('release_build', (string)$rel['build']);
    gx_audit('ACTUALIZACION', 'Versión ' . $rel['build'] . ' aceptada (' . $n . ' archivos; ' . $note . ')', 'sistema');
    return true;
}
