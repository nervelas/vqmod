<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

const GX_BACKUP_KEEP = 14;

function gx_backup_dir(): string
{
    $d = GX_DATA . '/backups';
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
    return $d;
}

/** Copia consistente de la base de datos con rotación. @return string|null nombre del archivo */
function gx_backup_db(): ?string
{
    $name = 'db-' . date('Ymd-His') . '-' . gx_rand_hex(3) . '.sqlite';
    $path = gx_backup_dir() . '/' . $name;
    try {
        gx_db()->exec("VACUUM INTO '" . str_replace("'", "''", $path) . "'");
    } catch (Throwable $t) {
        $cfg = gx_config();
        if (!@copy(GX_DATA . '/' . (string)($cfg['db_file'] ?? ''), $path)) {
            return null;
        }
    }
    @chmod($path, 0600);
    $all = gx_backup_list();
    foreach (array_slice($all, GX_BACKUP_KEEP) as $old) {
        @unlink(gx_backup_dir() . '/' . $old['name']);
    }
    gx_set('last_backup', gx_now());
    return $name;
}

/** @return array<int,array{name:string,size:int,time:int}> más recientes primero */
function gx_backup_list(): array
{
    $out = [];
    foreach ((array)@scandir(gx_backup_dir()) as $f) {
        if (is_string($f) && preg_match('/^db-\d{8}-\d{6}-[a-f0-9]{6}\.sqlite$/', $f)) {
            $p = gx_backup_dir() . '/' . $f;
            $out[] = ['name' => $f, 'size' => (int)filesize($p), 'time' => (int)filemtime($p)];
        }
    }
    usort($out, static function ($a, $b) {
        return $b['time'] <=> $a['time'];
    });
    return $out;
}

/** Respaldo diario automático (se evalúa al iniciar sesión o publicar). */
function gx_backup_if_due(): void
{
    if (gx_now() - (int)gx_setting('last_backup', 0) > 86400) {
        gx_backup_db();
    }
}

/** Envía un respaldo de la base de datos como descarga. */
function gx_backup_download(string $name): void
{
    if (!preg_match('/^db-\d{8}-\d{6}-[a-f0-9]{6}\.sqlite$/', $name) || !is_file(gx_backup_dir() . '/' . $name)) {
        gx_fail(404, 'No encontrado');
    }
    $p = gx_backup_dir() . '/' . $name;
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($p));
    header('X-Content-Type-Options: nosniff');
    readfile($p);
    exit;
}

/** Respaldo completo (.zip): base de datos + imágenes subidas + videos. */
function gx_backup_zip_download(): void
{
    if (!class_exists('ZipArchive')) {
        gx_fail(501, 'ZipArchive no está disponible en este hosting');
    }
    $db = gx_backup_db();
    if ($db === null) {
        gx_fail(500, 'No se pudo crear el respaldo');
    }
    $tmpDir = GX_DATA . '/tmp';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0755, true);
    }
    $tmp = $tmpDir . '/bk-' . gx_rand_hex(6) . '.zip';
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::CREATE) !== true) {
        gx_fail(500, 'No se pudo crear el ZIP');
    }
    $z->addFile(gx_backup_dir() . '/' . $db, 'base-de-datos.sqlite');
    foreach (['uploads' => '/^[a-z0-9][a-z0-9_-]*(-t)?\.webp$/', 'media' => '/^[a-z0-9][a-z0-9_-]*\.mp4$/'] as $d => $rx) {
        foreach ((array)@scandir(GX_ROOT . '/' . $d) as $f) {
            if (is_string($f) && preg_match($rx, $f)) {
                $z->addFile(GX_ROOT . '/' . $d . '/' . $f, $d . '/' . $f);
            }
        }
    }
    $z->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="respaldo-' . date('Ymd-His') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}
