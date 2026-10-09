<?php
declare(strict_types=1);
/**
 * Pruebas de StockImages contra el servidor simulado tests/e2e/mock_stock.php (puerto 8211).
 * Sin Apache ni base de datos: Settings/Log son los stubs de tests/ai/stubs y el registro de archivos se sustituye por un callable.
 * Uso: php tests/stock/run.php
 */
define('S5_ROOT', dirname(__DIR__, 2));
spl_autoload_register(function (string $cls): void {
    if (strncmp($cls, 'S5\\', 3) !== 0) { return; }
    $parts = explode('\\', substr($cls, 3));
    $ns = array_shift($parts);
    $name = implode('/', $parts);
    $f = $ns === 'Core' && in_array($name, ['Settings', 'Log'], true) ? S5_ROOT . '/tests/ai/stubs/' . $name . '.php' : S5_ROOT . '/app/' . $ns . '/' . $name . '.php';
    if (is_file($f)) { require_once $f; }
});
use S5\Core\Log;
use S5\Core\Settings;
use S5\Services\StockImages;

error_reporting(E_ALL);
set_error_handler(function (int $no, string $str, string $file, int $line): bool { if (!(error_reporting() & $no)) { return false; } throw new ErrorException($str, 0, $no, $file, $line); });

$T = ['pass' => 0, 'fail' => 0];
function t(string $name, bool $ok, string $detail = ''): void
{
    global $T;
    $T[$ok ? 'pass' : 'fail']++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . (!$ok && $detail !== '' ? "\n         -> " . $detail : '') . "\n";
}
function seccion(string $s): void { echo "\n== $s ==\n"; }

$PORT = 8211;
$mock = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $PORT, S5_ROOT . '/tests/e2e/mock_stock.php'], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($mock) { if (is_resource($mock)) { proc_terminate($mock); } });
$B = 'http://127.0.0.1:' . $PORT;
for ($i = 0; $i < 50; $i++) {
    if (@file_get_contents($B . '/stats')) { break; }
    usleep(100000);
}
if (!@file_get_contents($B . '/stats')) { echo "No arrancó el servidor simulado\n"; exit(2); }

function ctl(string $qs): void { global $B; file_get_contents($B . '/ctl?' . $qs); }
function stats(): array { global $B; return json_decode((string) file_get_contents($B . '/stats'), true); }
function tmpdir(): string { $d = sys_get_temp_dir() . '/s5stock-' . bin2hex(random_bytes(4)); mkdir($d, 0777, true); return $d; }
function rmrf(string $d): void { foreach (glob($d . '/*') ?: [] as $f) { is_dir($f) ? rmrf($f) : @unlink($f); } @rmdir($d); }

$files = [];     // id => ruta
$nextId = 100;
function mkstore(string $dir): callable
{
    return function (string $path, array $meta) use ($dir): int {
        global $files, $nextId;
        $id = $nextId++;
        $dst = $dir . '/f' . $id . '.jpg';
        copy($path, $dst);
        $files[$id] = $dst;
        return $id;
    };
}
function setup(array $env = [], string $key = 'TESTKEY'): array
{
    global $B;
    ctl('reset=1');
    $cache = tmpdir();
    $out = tmpdir();
    putenv('S5_STOCK_CACHE=' . $cache);
    putenv('S5_STOCK_PEXELS_URL=' . ($env['pexels'] ?? $B . '/pexels/v1/search'));
    putenv('S5_STOCK_OPENVERSE_URL=' . ($env['openverse'] ?? $B . '/openverse/v1/images/'));
    Settings::$data = ['pexels_key' => $key, 'stock_online' => '1'];
    Log::$errores = [];
    return [$cache, $out];
}
function brief(array $over = []): array
{
    $b = ['negocio' => ['rubro' => 'abogado'], 'contenido' => [
        'banner' => [], 'galeria' => [11, 12],
        'servicios' => [
            ['nombre' => 'Derecho laboral', 'foto' => null], ['nombre' => 'Divorcios y familia', 'foto' => 77], ['nombre' => 'Contratos mercantiles', 'foto' => null],
        ]]];
    return array_replace_recursive($b, $over);
}
function run(array $b, string $out, float $budget = 30.0): array { return StockImages::fetch($b, 5, $budget, mkstore($out)); }

seccion('plan');
[$cache, $out] = setup();
$p = StockImages::plan(brief(), 5);
$keys = array_column($p, 'key');
t('plan: hero 2 + about 1 + 2 servicios sin foto + 4 de galería', count($p) === 9, implode(',', $keys));
t('plan: servicio con foto del cliente no se pide', !in_array('svc:1', $keys, true));
t('plan: con banner del cliente no hay hero', !in_array('hero:0', array_column(StockImages::plan(brief(['contenido' => ['banner' => [9]]]), 5), 'key'), true));
t('plan: galería completa del cliente no pide galería', count(array_filter(array_column(StockImages::plan(brief(['contenido' => ['galeria' => [1, 2, 3, 4, 5, 6]]]), 5), 'key'), fn($k) => str_starts_with($k, 'gal:'))) === 0);
$ro = StockImages::plan(['negocio' => ['rubro' => 'xyz'], 'contenido' => ['servicios' => [['nombre' => 'Contabilidad mensual']]]], 5);
t('plan: rubro desconocido usa "otro"; servicio traducido por diccionario', str_contains($ro[0]['query'], 'office') || str_contains($ro[0]['query'], 'business') , $ro[0]['query']);
$sv = array_values(array_filter(StockImages::plan(brief(), 5), fn($i) => $i['key'] === 'svc:0'))[0];
t('plan: "Derecho laboral" -> términos en inglés', str_contains($sv['query'], 'labor'), $sv['query']);

seccion('éxito con Pexels');
$r = run(brief(), $out);
$st = $r['stock'];
t('termina (done) y completa los 9 espacios', $r['done'] && count($st['assets']) === 9, 'assets=' . count($st['assets']));
t('slots: hero 2, about 1, galería 4, servicios alineados por índice', count($st['slots']['hero']) === 2 && count($st['slots']['about']) === 1 && count($st['slots']['galeria']) === 4
    && ($st['slots']['servicios'][0] ?? 0) > 0 && ($st['slots']['servicios'][1] ?? -1) === 0 && ($st['slots']['servicios'][2] ?? 0) > 0, json_encode($st['slots']));
t('cada asset tiene role=stock y crédito', !array_filter($st['assets'], fn($a) => $a['role'] !== 'stock' || $a['credit'] === ''));
t('crédito de Pexels con fotógrafo', str_contains($st['assets'][0]['credit'], 'Pexels') && str_contains($st['assets'][0]['credit'], 'Fotógrafo'));
$hashes = array_map(fn($f) => sha1_file($f), $files);
$mine = array_filter($files, fn($f) => str_starts_with($f, $out));
$hs = array_map('sha1_file', $mine);
t('ninguna foto se repite en el pedido', count($hs) === count(array_unique($hs)) && count($hs) === 9);
$dim = getimagesize(reset($mine));
t('salida JPEG, ≤1920 px', $dim[2] === IMAGETYPE_JPEG && max($dim[0], $dim[1]) <= 1920, json_encode($dim));
t('sin errores registrados', !Log::$errores, implode(';', Log::$errores));
t('el estado persistente cabe en JSON y es idempotente (segunda llamada: 0 nuevas)', StockImages::fetch(['_stock' => $st] + brief(), 5, 30.0, mkstore($out))['added'] === 0);
rmrf($cache); rmrf($out);

seccion('PNG y WebP se aceptan y se recodifican a JPEG');
[$cache, $out] = setup();
ctl('images=png');
$r = run(brief(), $out);
$f = reset($files);
t('PNG aceptado y convertido a JPEG', $r['done'] && count($r['stock']['assets']) === 9 && getimagesize(end($files))[2] === IMAGETYPE_JPEG);
rmrf($cache); rmrf($out);

seccion('caída de Pexels -> Openverse');
[$cache, $out] = setup();
ctl('pexels=down');
$r = run(brief(), $out);
t('rellena con Openverse', $r['done'] && count($r['stock']['assets']) === 9, 'assets=' . count($r['stock']['assets']));
t('crédito indica Openverse', str_contains($r['stock']['assets'][0]['credit'], 'Openverse'));
t('Pexels solo se consulta una vez (se marca caído)', stats()['search_pexels'] === 1, json_encode(stats()));
t('el fallo de Pexels queda en el log', (bool) array_filter(Log::$errores, fn($m) => str_contains($m, 'pexels')));
rmrf($cache); rmrf($out);

seccion('clave inválida (401) -> Openverse');
[$cache, $out] = setup([], 'CLAVE-MALA');
$r = run(brief(), $out);
t('401 no rompe: se usa Openverse', $r['done'] && count($r['stock']['assets']) === 9 && str_contains($r['stock']['assets'][0]['credit'], 'Openverse'));
t('Pexels consultado una sola vez', stats()['search_pexels'] === 1);
rmrf($cache); rmrf($out);

seccion('sin clave de Pexels');
[$cache, $out] = setup([], '');
$r = run(brief(), $out);
t('no consulta Pexels y usa Openverse', stats()['search_pexels'] === 0 && count($r['stock']['assets']) === 9);
rmrf($cache); rmrf($out);

seccion('ambos caídos');
[$cache, $out] = setup();
ctl('pexels=down&openverse=down');
$ex = null;
try { $r = run(brief(), $out); } catch (Throwable $e) { $ex = $e; }
t('no lanza excepción', $ex === null, (string) $ex);
t('devuelve vacío (done=true, sin assets ni slots)', $r['done'] && !$r['stock']['assets'] && !array_filter($r['stock']['slots']));
t('rápido: cada proveedor se consulta una sola vez', stats()['search_pexels'] === 1 && stats()['search_openverse'] === 1, json_encode(stats()));
rmrf($cache); rmrf($out);

seccion('imágenes defectuosas / hosts no permitidos');
foreach (['huge' => 'enorme (>6 MB)', 'corrupt' => 'corrupta', 'fake' => 'mime falso (HTML)', 'small' => 'pequeña (<1000x600)', 'evil' => 'host no permitido'] as $mode => $label) {
    [$cache, $out] = setup();
    ctl('images=' . $mode);
    $files = [];
    $ex = null;
    try { $r = run(brief(), $out); } catch (Throwable $e) { $ex = $e; }
    t("rechaza imagen $label", $ex === null && !$r['stock']['assets'] && !$files, $ex ? (string) $ex : 'assets=' . count($r['stock']['assets']));
    $reasons = implode(' | ', array_unique(StockImages::$log));
    t("  motivo registrado ($label)", $reasons !== '', $reasons);
    rmrf($cache); rmrf($out);
}
[$cache, $out] = setup(['pexels' => 'http://localhost:' . $PORT . '/pexels/v1/search']);
ctl('images=redirect&pexels=down');
$files = [];
$r = run(brief(), $out);
t('rechaza redirección a otro host', !$r['stock']['assets'] && !$files && str_contains(implode('|', StockImages::$log), 'otro host'), implode('|', array_unique(StockImages::$log)));
rmrf($cache); rmrf($out);

seccion('lista de hosts y esquemas (sin red)');
putenv('S5_STOCK_PEXELS_URL'); putenv('S5_STOCK_OPENVERSE_URL');
$m = new ReflectionMethod(StockImages::class, 'urlAllowed');
$m->setAccessible(true);
t('https images.pexels.com permitido', $m->invoke(null, 'https://images.pexels.com/photos/1/x.jpeg') === null);
t('https upload.wikimedia.org permitido', $m->invoke(null, 'https://upload.wikimedia.org/a.jpg') === null);
t('http rechazado', $m->invoke(null, 'http://images.pexels.com/x.jpg') !== null);
t('host ajeno rechazado', $m->invoke(null, 'https://evil.example.com/x.jpg') !== null);
t('loopback rechazado sin variable de pruebas', $m->invoke(null, 'http://127.0.0.1/x.jpg') !== null && $m->invoke(null, 'https://localhost/x.jpg') !== null);
t('credenciales en la URL rechazadas', $m->invoke(null, 'https://u:p@images.pexels.com/x.jpg') !== null);
t('puerto raro rechazado', $m->invoke(null, 'https://images.pexels.com:8443/x.jpg') !== null);
t('truco de subdominio rechazado', $m->invoke(null, 'https://images.pexels.com.evil.io/x.jpg') !== null);

seccion('EXIF eliminado');
[$cache, $out] = setup();
ctl('images=exif');
$files = [];
$r = run(brief(), $out);
$raw = file_get_contents($B . '/img/exif.jpg');
t('el original del simulador contiene el EXIF secreto', str_contains($raw, 'SECRETGPS'));
$bad = array_filter($files, fn($f) => str_contains((string) file_get_contents($f), 'SECRETGPS') || str_contains((string) file_get_contents($f), 'Exif'));
t('JPEG aceptado y guardado sin EXIF', count($r['stock']['assets']) === 9 && !$bad, 'assets=' . count($r['stock']['assets']) . ' log=' . implode('|', StockImages::$log));
rmrf($cache); rmrf($out);

seccion('caché');
[$cache, $out] = setup();
$files = [];
run(brief(), $out);
$s1 = stats();
$r2 = run(brief(), $out);   // pedido nuevo (sin _stock) con la misma caché
$s2 = stats();
t('segunda vez no repite búsquedas ni descargas', $s1 === $s2 && count($r2['stock']['assets']) === 9, json_encode([$s1, $s2]));
t('archivos de caché en <hash>.jpg', count(glob($cache . '/*.jpg')) >= 9);
rmrf($cache); rmrf($out);

seccion('límite por pedido (24)');
[$cache, $out] = setup();
$muchos = [];
for ($i = 1; $i <= 30; $i++) { $muchos[] = ['nombre' => 'Servicio número ' . $i, 'foto' => null]; }
$r = run(brief(['contenido' => ['servicios' => $muchos, 'galeria' => []]]), $out);
// array_replace_recursive mezcla listas: reconstruir sin mezcla
$b = brief(); $b['contenido']['servicios'] = $muchos; $b['contenido']['galeria'] = [];
$r = run($b, $out);
$fs = array_filter($files, fn($f) => str_starts_with($f, $out));
t('nunca más de 24 descargas por pedido', count($r['stock']['assets']) === 24 && $r['done'], 'assets=' . count($r['stock']['assets']));
$hs = array_map('sha1_file', array_filter($r['stock']['assets'] ? $files : [], fn($f) => str_starts_with($f, $out)));
rmrf($cache); rmrf($out);

seccion('reanudable por presupuesto de tiempo');
[$cache, $out] = setup();
$files = [];
$r = StockImages::fetch(brief(), 5, 0.0, mkstore($out));
t('presupuesto agotado -> done=false sin perder estado', !$r['done'] && !$r['stock']['assets']);
$st = $r['stock'];
$guard = 0;
while (!$r['done'] && $guard++ < 30) {
    $r = StockImages::fetch(['_stock' => $st] + brief(), 5, 0.4, mkstore($out));
    $st = $r['stock'];
}
t('reanudando termina con los 9 espacios y sin repetidos', $r['done'] && count($st['assets']) === 9 && count(array_unique(array_map('sha1_file', $files))) === 9, 'assets=' . count($st['assets']));
rmrf($cache); rmrf($out);

seccion('prune');
$pr = StockImages::prune($st, fn(int $id) => $id !== $st['assets'][0]['file']);
t('prune quita el archivo ausente y lo vuelve a pedir', count($pr['assets']) === 8 && !in_array($st['assets'][0]['file'], $pr['done'], true));

echo "\nResultado: {$T['pass']} PASS, {$T['fail']} FAIL\n";
exit($T['fail'] ? 1 : 0);
