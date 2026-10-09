<?php
declare(strict_types=1);
/**
 * Servidor simulado de fotos de stock (Pexels + Openverse) SOLO PARA PRUEBAS.
 *   php -S 127.0.0.1:8211 tests/e2e/mock_stock.php
 * Rutas:
 *   GET /pexels/v1/search?query=..        (cabecera Authorization = TESTKEY; si no, 401)
 *   GET /openverse/v1/images/?q=..
 *   GET /img/g.jpg?q=..&i=..              JPEG 1600x1000 con degradado propio de la consulta
 *   GET /img/{exif|huge|corrupt|fake|small|redirect|png}.jpg    imágenes defectuosas / especiales
 *   GET /ctl?pexels=down|ok&openverse=down|ok&images=normal|exif|huge|corrupt|fake|small|redirect|evil|png
 *   GET /stats   contadores {"search_pexels":n,"search_openverse":n,"img":n}      GET /ctl?reset=1
 * Estado en un archivo temporal (el servidor integrado atiende peticiones en un único proceso, pero se guarda en disco por claridad).
 */
$stateFile = sys_get_temp_dir() . '/s5-mock-stock-' . (getenv('S5_MOCK_STOCK_ID') ?: '8211') . '.json';
function st_load(string $f): array
{
    $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    return is_array($j) ? $j : ['pexels' => 'ok', 'openverse' => 'ok', 'images' => 'normal', 'c' => ['search_pexels' => 0, 'search_openverse' => 0, 'img' => 0]];
}
function st_save(string $f, array $s): void { file_put_contents($f, json_encode($s), LOCK_EX); }
function out_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
}
function gradient(string $seedStr, int $w = 1600, int $h = 1000)
{
    $s = crc32($seedStr);
    $im = imagecreatetruecolor($w, $h);
    $c1 = [($s >> 0) & 255, ($s >> 8) & 255, ($s >> 16) & 255];
    $c2 = [255 - $c1[1], ($c1[2] + 90) % 256, ($c1[0] + 140) % 256];
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $col = imagecolorallocate($im, (int) ($c1[0] + ($c2[0] - $c1[0]) * $t), (int) ($c1[1] + ($c2[1] - $c1[1]) * $t), (int) ($c1[2] + ($c2[2] - $c1[2]) * $t));
        imageline($im, 0, $y, $w, $y, $col);
    }
    $ink = imagecolorallocate($im, 255, 255, 255);
    for ($k = 0; $k < 6; $k++) {
        imagefilledellipse($im, (int) (($s >> ($k * 3)) % $w), (int) (($s >> ($k * 2 + 1)) % $h), 220, 220, $ink ^ ($k * 0x101010));
    }
    return $im;
}
function jpeg_bytes($im, int $q = 88): string
{
    ob_start();
    imagejpeg($im, null, $q);
    return (string) ob_get_clean();
}

$S = st_load($stateFile);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$q = $_GET;
$base = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8211');

if ($path === '/ctl') {
    if (isset($q['reset'])) {
        $S = st_load('/nonexistent');
    }
    foreach (['pexels', 'openverse', 'images'] as $k) {
        if (isset($q[$k])) { $S[$k] = (string) $q[$k]; }
    }
    st_save($stateFile, $S);
    out_json(['ok' => true, 'state' => $S]);
    return;
}
if ($path === '/stats') {
    out_json($S['c']);
    return;
}

/** URL de imagen según el modo. */
$imgUrl = function (string $query, int $i) use ($S, $base): string {
    switch ($S['images']) {
        case 'evil': return 'https://evil.example.com/photos/' . $i . '.jpg';
        case 'exif': case 'huge': case 'corrupt': case 'fake': case 'small': case 'redirect': case 'png':
            return $base . '/img/' . $S['images'] . '.jpg?i=' . $i . '&q=' . rawurlencode($query);
        default: return $base . '/img/g.jpg?q=' . rawurlencode($query) . '&i=' . $i;
    }
};

if ($path === '/pexels/v1/search') {
    $S['c']['search_pexels']++;
    st_save($stateFile, $S);
    if (($S['pexels'] ?? 'ok') === 'down') { out_json(['error' => 'down'], 503); return; }
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($auth !== 'TESTKEY') { out_json(['error' => 'Unauthorized'], 401); return; }
    $query = (string) ($q['query'] ?? '');
    $n = min(15, max(1, (int) ($q['per_page'] ?? 15)));
    $photos = [];
    for ($i = 0; $i < $n; $i++) {
        $u = $imgUrl($query, $i);
        $photos[] = ['id' => crc32($query) % 100000 * 100 + $i, 'url' => 'https://www.pexels.com/photo/' . $i . '/', 'photographer' => 'Fotógrafo ' . $i,
            'src' => ['original' => $u, 'large2x' => $u, 'large' => $u . '&s=l', 'medium' => $u]];
    }
    out_json(['page' => 1, 'per_page' => $n, 'photos' => $photos, 'total_results' => $n]);
    return;
}
if ($path === '/openverse/v1/images/') {
    $S['c']['search_openverse']++;
    st_save($stateFile, $S);
    if (($S['openverse'] ?? 'ok') === 'down') { out_json(['error' => 'down'], 503); return; }
    $query = (string) ($q['q'] ?? '');
    $res = [];
    for ($i = 0; $i < 10; $i++) {
        $res[] = ['id' => 'ov-' . sha1($query . $i), 'url' => $imgUrl('ov ' . $query, $i), 'creator' => 'Autor ' . $i, 'foreign_landing_url' => 'https://example.org/p/' . $i,
            'license' => 'cc0', 'width' => 1600, 'height' => 1000];
    }
    out_json(['result_count' => 10, 'results' => $res]);
    return;
}
if (str_starts_with((string) $path, '/img/')) {
    $S['c']['img']++;
    st_save($stateFile, $S);
    $kind = basename((string) $path, '.jpg');
    $seed = (string) ($q['q'] ?? '') . '|' . (string) ($q['i'] ?? '0');
    switch ($kind) {
        case 'huge':        // >6 MB reales
            header('Content-Type: image/jpeg');
            echo "\xFF\xD8\xFF\xE0" . random_bytes(7 * 1048576);
            return;
        case 'corrupt':     // cabecera JPEG válida seguida de basura
            header('Content-Type: image/jpeg');
            echo "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00" . random_bytes(4000);
            return;
        case 'fake':        // dice ser JPEG pero es HTML
            header('Content-Type: image/jpeg');
            echo '<html><body><h1>no soy una imagen</h1>' . str_repeat('x', 3000) . '</body></html>';
            return;
        case 'small':
            header('Content-Type: image/jpeg');
            echo jpeg_bytes(gradient($seed, 800, 500));
            return;
        case 'redirect':    // a otro host (localhost != 127.0.0.1)
            $other = str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost') ? '127.0.0.1' : 'localhost';
            header('Location: http://' . $other . ':8211/img/g.jpg?q=' . rawurlencode($seed));
            http_response_code(302);
            return;
        case 'png':
            header('Content-Type: image/png');
            ob_start();
            imagepng(gradient($seed));
            echo ob_get_clean();
            return;
        case 'exif':        // JPEG con APP1/Exif que contiene GPS y un texto secreto
            $jpg = jpeg_bytes(gradient($seed));
            $exif = "Exif\x00\x00" . 'II*' . "\x00\x08\x00\x00\x00" . "\x00\x00" . 'SECRETGPS-40.7128N-74.0060W CAMERA-OWNER-JUAN';
            $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
            header('Content-Type: image/jpeg');
            echo substr($jpg, 0, 2) . $app1 . substr($jpg, 2);
            return;
        default:
            header('Content-Type: image/jpeg');
            echo jpeg_bytes(gradient($seed));
            return;
    }
}
http_response_code(404);
echo 'not found';
