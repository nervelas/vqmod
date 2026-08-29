<?php
declare(strict_types=1);
/**
 * Sustituye la fotografía del tema por imágenes reales.
 *
 *   php tools/fetch-photos.php                  usa tools/fotos.txt
 *   php tools/fetch-photos.php CLAVE_UNSPLASH   busca en Unsplash con su API
 *
 * De cada imagen genera JPG + WebP en 2200, 1200 y 640 px, más la miniatura
 * borrosa que usa el efecto blur-up. Requiere salida a internet en el hosting.
 * Si algo falla, las imágenes generadas por tools/generate-images.php siguen
 * en su lugar: el sitio nunca se queda sin fotos.
 */

require __DIR__ . '/../app/bootstrap.php';
@set_time_limit(0);

$OUT = BASE_PATH . '/assets/img/industry';
@mkdir($OUT, 0755, true);

/** Consultas por escena cuando se usa la API de Unsplash. */
const QUERIES = [
    'hero-planta'     => 'industrial warehouse racking',
    'hero-taller'     => 'spare parts warehouse shelves',
    'acero-cepillado' => 'brushed steel texture',
    'pieza-torneada'  => 'machined metal part macro',
    'placa-remachada' => 'riveted steel plate',
    'plano-tecnico'   => 'engineering blueprint',
    'concreto'        => 'polished concrete floor',
];

function http(string $url, array $headers = []): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'CotizaPro/1.0',
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? (string) $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 60, 'header' => implode("\r\n", $headers) . "\r\nUser-Agent: CotizaPro/1.0"]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

/** Descarga, valida y exporta una escena en todos sus tamaños. */
function procesar(string $name, string $url, string $out): bool
{
    $raw = http($url);
    if ($raw === null || strlen($raw) < 20000) {
        echo "  ✕ {$name}: no se pudo descargar\n";
        return false;
    }
    $tmp = STORAGE_PATH . '/tmp/' . bin2hex(random_bytes(6));
    @mkdir(dirname($tmp), 0700, true);
    file_put_contents($tmp, $raw);
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        @unlink($tmp);
        echo "  ✕ {$name}: el archivo no es una imagen válida\n";
        return false;
    }
    $src = \App\Core\Img::load($tmp);
    @unlink($tmp);
    if (!$src) {
        echo "  ✕ {$name}: no se pudo abrir la imagen\n";
        return false;
    }
    foreach ([['', 2200, 1400], ['-md', 1200, 764], ['-sm', 640, 408]] as [$suf, $w, $h]) {
        $im = \App\Core\Img::resize($src, $w, $h, true);
        imagejpeg($im, "{$out}/{$name}{$suf}.jpg", $w > 1500 ? 78 : 82);
        if (function_exists('imagewebp')) {
            imagewebp($im, "{$out}/{$name}{$suf}.webp", $w > 1500 ? 74 : 80);
        }
        imagedestroy($im);
    }
    $blurFile = $out . '/blur.json';
    $blur = is_file($blurFile) ? (json_decode((string) file_get_contents($blurFile), true) ?: []) : [];
    $blur[$name] = \App\Core\Img::blurData($src);
    file_put_contents($blurFile, json_encode($blur, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    imagedestroy($src);
    echo "  ✓ {$name}\n";
    return true;
}

$key = $argv[1] ?? '';
$ok = 0;

if ($key !== '') {
    echo "Buscando en Unsplash con su clave de API…\n";
    foreach (QUERIES as $name => $q) {
        $api = 'https://api.unsplash.com/photos/random?orientation=landscape&content_filter=high&query=' . rawurlencode($q);
        $json = http($api, ['Authorization: Client-ID ' . $key, 'Accept-Version: v1']);
        $d = $json ? json_decode($json, true) : null;
        $url = $d['urls']['raw'] ?? null;
        if (!$url) {
            echo "  ✕ {$name}: la API no devolvió resultados\n";
            continue;
        }
        $ok += procesar($name, $url . '&w=2200&q=80&fm=jpg&fit=max', $OUT) ? 1 : 0;
        // Crédito requerido por la licencia de Unsplash.
        file_put_contents($OUT . '/CREDITOS.txt',
            sprintf("%s — foto de %s (%s) en Unsplash\n", $name, $d['user']['name'] ?? '?', $d['user']['links']['html'] ?? ''),
            FILE_APPEND);
        usleep(400000);
    }
} else {
    $list = BASE_PATH . '/tools/fotos.txt';
    if (!is_file($list)) {
        exit("No existe tools/fotos.txt\n");
    }
    $lines = array_filter(array_map('trim', file($list) ?: []), static fn ($l) => $l !== '' && $l[0] !== '#');
    if (!$lines) {
        exit("tools/fotos.txt no tiene ninguna línea activa.\n"
           . "Agregue líneas con el formato:  nombre | URL   (o pase su clave de Unsplash como argumento)\n");
    }
    foreach ($lines as $l) {
        [$name, $url] = array_pad(array_map('trim', explode('|', $l, 2)), 2, '');
        if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo "  ✕ línea ignorada: {$l}\n";
            continue;
        }
        $ok += procesar(preg_replace('/[^a-z0-9\-]/i', '', $name) ?: 'imagen', $url, $OUT) ? 1 : 0;
    }
}

echo "\nListo: {$ok} imagen(es) actualizadas en /assets/img/industry.\n";
echo "Si usó Unsplash, revise assets/img/industry/CREDITOS.txt y dé el crédito que pide su licencia.\n";
