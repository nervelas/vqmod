<?php
declare(strict_types=1);

// Instalación completa por HTTP desde cero (instalador web), bloqueo y migraciones.
require __DIR__ . '/../lib/T.php';

$root = dirname(__DIR__, 2);
$docroot = getenv('AP_TEST_DOCROOT') ?: $root;   // para probar el ZIP descomprimido en una subcarpeta
$prefix = getenv('AP_TEST_PREFIX') ?: '';
$router = getenv('AP_TEST_ROUTER') ?: $root . '/tests/router.php';
$appRoot = getenv('AP_TEST_APPROOT') ?: $root;
$defaultCfg = (bool) getenv('AP_TEST_DEFAULTCFG');
$cfg = $defaultCfg ? $appRoot . '/config/config.php' : '/tmp/ap-inst-web.config.php';
@unlink($cfg);
@unlink('/tmp/installed.lock');
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'ap', 'ap_test_pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS ap_t_instweb');
$pdo->exec('CREATE DATABASE ap_t_instweb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$port = 8194;
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $docroot, $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root, ($defaultCfg ? [] : ['AP_CONFIG' => $cfg]) + ['PATH' => getenv('PATH')]);
register_shutdown_function(static function () use ($proc): void {
    proc_terminate($proc);
});
usleep(700000);
$jar = tempnam(sys_get_temp_dir(), 'jar');
function http(string $path, ?array $post = null, string $jar = ''): array
{
    global $port, $prefix;
    $ch = curl_init("http://127.0.0.1:$port$prefix$path");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $r = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'head' => substr($r, 0, $hs), 'body' => substr($r, $hs)];
}
function csrf(string $html): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

T::section('Antes de instalar');
$r = http('/', null, $jar);
T::ok(in_array($r['code'], [302, 200], true) && strpos($r['head'], $prefix . '/instalar/') !== false, 'la portada redirige al instalador');
$r = http('/instalar/?paso=1', null, $jar);
T::eq(200, $r['code'], 'paso 1 responde');
T::ok(strpos($r['body'], 'Requisito') !== false, 'muestra los requisitos');
T::ok(strpos($r['head'], "script-src") === false || strpos($r['head'], "unsafe-inline") === false, 'sin scripts en línea permitidos');
$t = csrf($r['body']);
T::eq(419, http('/instalar/?paso=1', ['x' => 1], $jar)['code'], 'POST sin CSRF rechazado (419)');
$r = http('/instalar/?paso=1', ['_csrf' => $t], $jar);
T::ok($r['code'] === 302 || strpos($r['head'], 'paso=2') !== false, 'paso 1 → 2');

T::section('Base de datos');
$r = http('/instalar/?paso=2', null, $jar);
$t = csrf($r['body']);
$bad = http('/instalar/?paso=2', ['_csrf' => $t, 'host' => '127.0.0.1', 'port' => '3306', 'name' => 'ap_t_instweb', 'user' => 'ap', 'pass' => 'incorrecta'], $jar);
T::ok(strpos($bad['body'], 'incorrectos') !== false, 'contraseña de BD incorrecta → mensaje amable');
$t = csrf($bad['body']) ?: $t;
$ok = http('/instalar/?paso=2', ['_csrf' => $t, 'host' => '127.0.0.1', 'port' => '3306', 'name' => 'ap_t_instweb', 'user' => 'ap', 'pass' => 'ap_test_pw'], $jar);
T::ok(strpos($ok['head'], 'paso=3') !== false, 'conexión correcta → paso 3');

T::section('Negocio y administrador');
$r = http('/instalar/?paso=3', null, $jar);
$t = csrf($r['body']);
$weak = http('/instalar/?paso=3', ['_csrf' => $t, 'biz_name' => 'Clínica Luz', 'biz_email' => 'hola@example.test', 'biz_phone' => '55551234', 'biz_whatsapp' => '55551234', 'timezone' => 'America/Guatemala', 'adm_name' => 'Dra. Luz', 'adm_email' => 'luz@example.test', 'adm_pass' => 'corta', 'adm_pass2' => 'corta'], $jar);
T::ok(strpos($weak['body'], 'contraseña') !== false && strpos($weak['head'], 'paso=4') === false, 'contraseña débil rechazada');
$t = csrf($weak['body']) ?: $t;
$good = http('/instalar/?paso=3', ['_csrf' => $t, 'biz_name' => 'Clínica Luz', 'biz_email' => 'hola@example.test', 'biz_phone' => '55551234', 'biz_whatsapp' => '55551234', 'timezone' => 'America/Guatemala', 'adm_name' => 'Dra. Luz', 'adm_email' => 'luz@example.test', 'adm_pass' => 'ClaveSegura2026', 'adm_pass2' => 'ClaveSegura2026'], $jar);
T::ok(strpos($good['head'], 'paso=4') !== false, 'datos válidos → paso 4');

T::section('Instalación');
$r = http('/instalar/?paso=4', null, $jar);
T::ok(strpos($r['body'], 'Dentista') !== false || strpos($r['body'], 'dentista') !== false, 'el paso 4 lista las profesiones');
$t = csrf($r['body']);
$done = http('/instalar/?paso=4', ['_csrf' => $t, 'profession' => 'dentista', 'demo' => '1'], $jar);
T::eq(200, $done['code'], 'instalación termina');
T::ok(strpos($done['body'], 'borra ahora la carpeta') !== false, 'avisa que se borre /instalar');
T::ok(strpos($done['body'], 'cron.php?token=') !== false, 'muestra la URL del cron');
T::ok(is_file($cfg) && substr(sprintf('%o', fileperms($cfg)), -3) === '640', 'configuración escrita con permisos 640');

T::section('Bloqueo y sistema instalado');
$again = http('/instalar/?paso=1', null, $jar);
T::ok(strpos($again['body'], 'ya está instalado') !== false, 'el instalador queda bloqueado');
$post = http('/instalar/?paso=4', ['_csrf' => 'x', 'profession' => 'otro'], $jar);
T::ok(strpos($post['body'], 'ya está instalado') !== false, 'tampoco acepta POST tras instalar');
$home = http('/', null, $jar);
T::eq(200, $home['code'], 'la página pública funciona');
T::ok(strpos($home['body'], 'Clínica Luz') !== false, 'muestra el nombre del negocio');
$adm = http('/admin/login', null, $jar);
T::eq(200, $adm['code'], 'el acceso al panel carga');
$login = http('/admin/login', ['_csrf' => csrf($adm['body']), 'email' => 'luz@example.test', 'password' => 'ClaveSegura2026'], $jar);
T::ok(strpos($login['head'], 'Location') !== false, 'el administrador puede entrar');
$dash = http('/admin', null, $jar);
T::eq(200, $dash['code'], 'el panel abre');
$pdo2 = new PDO('mysql:host=127.0.0.1;dbname=ap_t_instweb;charset=utf8mb4', 'ap', 'ap_test_pw');
T::ok((int) $pdo2->query('SELECT COUNT(*) FROM event_types')->fetchColumn() >= 3, 'eventos de la profesión creados');
T::ok((int) $pdo2->query("SELECT COUNT(*) FROM workflows")->fetchColumn() >= 5, 'flujos de recordatorio creados');
$nFiles = count(glob($appRoot . '/database/migrations/*.{sql,php}', GLOB_BRACE));
T::eq($nFiles, (int) $pdo2->query('SELECT COUNT(*) FROM migrations')->fetchColumn(), 'todas las migraciones numeradas quedaron registradas');
// Actualización futura: una migración nueva se aplica sola al abrir el sitio
$mig = $appRoot . '/database/migrations/999_prueba_actualizacion.sql';
file_put_contents($mig, "CREATE TABLE IF NOT EXISTS zz_prueba_upd (id INT PRIMARY KEY);\n");
$r2 = http('/', null, $jar);
@unlink($mig);
T::ok((bool) $pdo2->query("SHOW TABLES LIKE 'zz_prueba_upd'")->fetchColumn(), 'una migración nueva se aplica automáticamente al actualizar');
$pdo2->exec('DROP TABLE IF EXISTS zz_prueba_upd');
$pdo2->exec("DELETE FROM migrations WHERE name = '999_prueba_actualizacion.sql'");
T::ok(strpos((string) $pdo2->query("SELECT password_hash FROM users LIMIT 1")->fetchColumn(), '$') === 0, 'contraseña guardada con hash');
@unlink($jar);
T::done();
