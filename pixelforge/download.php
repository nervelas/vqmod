<?php
/** Entrega imágenes y ZIP. Todo pasa por aquí para que storage/ siga cerrado al público. */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

Security::headers();
Security::requireAuth();

$store = pf_store();
$id = Security::id('id', 'get');
$zip = Security::str('zip', '', 16, 'get');

if ($zip !== '') {
    entregarZip($store, $zip);
    exit;
}

if ($id === '') {
    http_response_code(404);
    exit;
}
$row = $store->imageGet($id);
if ($row === null) {
    http_response_code(404);
    exit;
}

$mini = Security::str('mini', '', 4, 'get') === '1';
$guardar = Security::str('guardar', '', 4, 'get') === '1';

$name = $mini && (string) $row['thumb'] !== '' ? (string) $row['thumb'] : (string) $row['file'];
$dir = $mini && (string) $row['thumb'] !== '' ? '/thumbs/' : '/images/';
$path = PF_STORAGE . $dir . basename($name);

if ($name === '' || !is_file($path)) {
    Logger::write('descarga', 'Archivo ausente para la imagen ' . $id);
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
header('Content-Type: ' . Imaging::mimeFor($ext));
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
if ($guardar) {
    header('Content-Disposition: attachment; filename="' . nombreArchivo($row) . '"');
} else {
    header('Content-Disposition: inline');
    header('Cache-Control: private, max-age=86400');
}
readfile($path);
exit;

// --------------------------------------------------------------------------

function entregarZip(Store $store, string $modo): void
{
    Zipper::cleanup();
    $rows = [];
    if ($modo === 'lote') {
        $batch = Security::str('lote', '', 64, 'get');
        if ($batch !== '') {
            $rows = $store->imagesByBatch($batch);
        }
    } elseif ($modo === 'sesion') {
        $desde = (int) ($_SESSION['session_start'] ?? time());
        $rows = $store->imagesSince($desde);
    } elseif ($modo === 'todo') {
        $rows = $store->imageList(500, 0);
    }

    if (!$rows) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No hay imágenes que descargar todavía en esta sesión.';
        return;
    }

    $files = [];
    $usados = [];
    foreach ($rows as $row) {
        $path = PF_STORAGE . '/images/' . basename((string) $row['file']);
        if (!is_file($path)) {
            continue;
        }
        $name = nombreArchivo($row);
        $i = 2;
        while (isset($usados[$name])) {
            $name = preg_replace('/(\.[a-z0-9]+)$/i', '-' . $i . '$1', $name) ?? $name;
            $i++;
        }
        $usados[$name] = true;
        $files[] = ['path' => $path, 'name' => $name];
    }

    try {
        $zipPath = Zipper::build($files, 'pixelforge-' . $modo);
    } catch (Throwable $e) {
        Logger::exception($e);
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No se pudo crear el ZIP. El detalle quedó en el log.';
        return;
    }

    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) filesize($zipPath));
    header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
    readfile($zipPath);
    @unlink($zipPath);
}

/** Nombre legible: prompt recortado + tamaño + seed. */
function nombreArchivo(array $row): string
{
    $prompt = (string) ($row['prompt'] ?? 'imagen');
    $slug = strtolower(trim($prompt));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
        if (is_string($converted) && $converted !== '') {
            $slug = $converted;
        }
    }
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'imagen';
    $slug = trim((string) $slug, '-');
    if ($slug === '') {
        $slug = 'imagen';
    }
    $slug = substr($slug, 0, 48);
    $ext = (string) ($row['format'] ?? 'png');
    return sprintf('%s-%dx%d-seed%d.%s', $slug, (int) $row['width'], (int) $row['height'], (int) $row['seed'], $ext);
}
