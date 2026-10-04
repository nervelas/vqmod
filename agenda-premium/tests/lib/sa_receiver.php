<?php
// Receptor HTTP de prueba para los servicios de comunicación (solo pruebas). Se ejecuta con php -S y varios procesos.
// Directorio de trabajo: variable de entorno SA_DIR.
$dir = (string) getenv('SA_DIR');
$path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
$q = $_GET;

function sa_count(string $dir, string $name): int
{
    $f = $dir . '/count_' . preg_replace('/\W+/', '_', $name);
    $fp = fopen($f, 'c+');
    flock($fp, LOCK_EX);
    $n = (int) stream_get_contents($fp) + 1;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string) $n);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $n;
}

function sa_log(string $dir, string $name, array $entry): void
{
    file_put_contents($dir . '/log_' . $name . '.jsonl', json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
}

$body = (string) file_get_contents('php://input');
$headers = [];
foreach ($_SERVER as $k => $v) {
    if (strncmp($k, 'HTTP_', 5) === 0) {
        $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
    }
}

if (preg_match('#^/rec/(\w+)$#', $path, $m)) {
    // Registra la petición; las primeras ?fail=N respuestas son 500
    $n = sa_count($dir, $m[1]);
    sa_log($dir, $m[1], ['n' => $n, 'headers' => $headers, 'body' => $body, 'method' => $_SERVER['REQUEST_METHOD']]);
    $fail = (int) ($q['fail'] ?? 0);
    http_response_code($n <= $fail ? 500 : (int) ($q['code'] ?? 200));
    echo $n <= $fail ? 'error' : 'ok';
    return;
}
if (preg_match('#^/wa/(\d+)/messages$#', $path, $m)) {
    $n = sa_count($dir, 'wa');
    sa_log($dir, 'wa', ['n' => $n, 'headers' => $headers, 'body' => $body, 'path' => $path]);
    // Comportamiento configurable desde la prueba con los archivos wa_fail (n.º de fallos iniciales) y wa_mode
    $fail = (int) @file_get_contents($dir . '/wa_fail');
    header('Content-Type: application/json');
    if (trim((string) @file_get_contents($dir . '/wa_mode')) === 'badtoken') {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]]);
    } elseif ($n <= $fail) {
        http_response_code(500);
        echo json_encode(['error' => ['message' => 'temporal', 'code' => 2]]);
    } else {
        echo json_encode(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.TEST' . $n]]]);
    }
    return;
}
if ($path === '/redirect') {
    header('Location: ' . ($q['to'] ?? '/'), true, (int) ($q['code'] ?? 302));
    return;
}
if ($path === '/big') {
    header('Content-Type: text/plain');
    for ($i = 0; $i < 4; $i++) {
        echo str_repeat('x', 1048576);
    }
    return;
}
if ($path === '/slow') {
    sleep((int) ($q['s'] ?? 15));
    echo 'tarde';
    return;
}
if ($path === '/gzbomb') {
    header('Content-Encoding: gzip');
    echo gzencode(str_repeat('0', 6 * 1048576));
    return;
}
if (preg_match('#^/feed/(\w+)\.ics$#', $path, $m)) {
    // Sirve $dir/feed_<nombre>.ics con ETag; ?status=500 simula caída
    $n = sa_count($dir, 'feed_' . $m[1]);
    sa_log($dir, 'feed_' . $m[1], ['n' => $n, 'inm' => $headers['if-none-match'] ?? null]);
    if (isset($q['status'])) {
        http_response_code((int) $q['status']);
        return;
    }
    $file = $dir . '/feed_' . $m[1] . '.ics';
    if (!is_file($file)) {
        http_response_code(404);
        return;
    }
    $etag = '"' . md5_file($file) . '"';
    header('ETag: ' . $etag);
    if (($headers['if-none-match'] ?? '') === $etag) {
        http_response_code(304);
        return;
    }
    header('Content-Type: text/calendar; charset=utf-8');
    readfile($file);
    return;
}
if ($path === '/notics') {
    echo '<html>no es un calendario</html>';
    return;
}
http_response_code(404);
echo 'no encontrado';
