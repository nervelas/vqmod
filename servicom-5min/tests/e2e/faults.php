<?php
declare(strict_types=1);
/** Fallos simulados: IA caída, crédito agotado, token inválido, disco lleno, subdominio existente, cierre a medias, análisis interrumpido. */
require __DIR__ . '/lib.php';
$W = '/tmp/s5test/webs'; $V = '/tmp/s5test/vroot'; $FAIL = '/tmp/s5test/sim/fail.json';
$setFail = fn(array $a) => file_put_contents($FAIL, json_encode($a)) && chmod($FAIL, 0666);
$clear = fn() => @unlink($FAIL);
$count = fn(string $q) => (int) mysql_val($q);

function draft(string $nombre): array {
    $c = new Client(); $c->page('/crear'); $tok = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
    $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['negocio' => ['nombre' => $nombre, 'rubro' => 'taller', 'estilo' => 2], 'contacto' => ['whatsapp' => '50233334444'], 'correo_contacto' => 'x@example.com', 'contenido' => ['servicios' => [['nombre' => 'Frenos', 'descripcion' => 'Revisión de frenos'], ['nombre' => 'Aceite', 'descripcion' => '']]]]]);
    $id = (int) mysql_val("SELECT id FROM s5test.s5_orders WHERE token='$tok'");
    return [$c, $tok, $id];
}
function drive(int $id, int $max = 120): string {
    for ($i = 0; $i < $max; $i++) {
        $o = trim((string) as_www('php /home/user/vqmod/servicom-5min/tests/e2e/tick.php ' . $id . ' 25'));
        $j = json_decode(strrchr("\n" . $o, "\n") ?: '', true) ?: json_decode($o, true);
        $st = mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id");
        if ($st !== 'construyendo') { return (string) $st; }
    }
    return 'timeout';
}
function start(Client $c, string $tok): void { $c->api('POST', "/api/borrador/$tok/crear", ['t0' => (time() - 90) * 1000]); }
function orphans(string $slug, int $dbCountBefore, int $usersBefore): array {
    $o = [];
    if (is_dir("/tmp/s5test/webs/$slug")) { $o[] = 'carpeta'; }
    if (is_link("/tmp/s5test/vroot/$slug.servicom.test")) { $o[] = 'vhost'; }
    if (count(sim_dbs()) !== $dbCountBefore) { $o[] = 'bd'; }
    if (count(sim_users()) !== $usersBefore) { $o[] = 'usuario-bd'; }
    return $o;
}
reset_limits();
$dbs0 = count(sim_dbs()); $us0 = count(sim_users());
as_www('chmod -R u+w /tmp/s5test/sim');

echo "== IA caída / sin crédito durante la redacción: la web se crea con textos base\n";
foreach (['error500', 'credit', 'invalid'] as $m) {
    file_put_contents('/tmp/s5test/run/ai-mode', $m);
    [$c, $tok, $id] = draft("Taller IA $m"); start($c, $tok); $st = drive($id);
    t_ok($st === 'vista_lista', "IA $m → vista previa lista", $st);
    t_ok(mysql_val("SELECT texts_source FROM s5test.s5_orders WHERE id=$id") === 'base', "IA $m → textos base");
}
file_put_contents('/tmp/s5test/run/ai-mode', 'ok');
[$c, $tok, $id] = draft('Taller IA ok'); start($c, $tok); drive($id);
t_ok(mysql_val("SELECT texts_source FROM s5test.s5_orders WHERE id=$id") === 'ia', 'IA ok → textos de IA');

echo "== Tope de gasto de IA alcanzado\n";
mysql_val("INSERT INTO s5test.s5_ai_usage (kind,model,tokens_in,tokens_out,cost_usd,ok,created_at) VALUES ('redaccion','claude-sonnet-5-5',1,1,5.0,1,'" . (new DateTime('now', new DateTimeZone('America/Guatemala')))->format('Y-m-d H:i:s') . "')");   // el «día» del tope se mide en hora de Guatemala
$before = $count('SELECT COUNT(*) FROM s5test.s5_ai_usage');
shell_exec('> /tmp/s5test/run/ai-calls.log');
[$c, $tok, $id] = draft('Taller Tope'); start($c, $tok); $st = drive($id);
t_ok($st === 'vista_lista' && mysql_val("SELECT texts_source FROM s5test.s5_orders WHERE id=$id") === 'base', 'tope diario: sigue funcionando con textos base');
t_ok(trim((string) @file_get_contents('/tmp/s5test/run/ai-calls.log')) === '', 'no se llamó a la API al superar el tope');
mysql_val("DELETE FROM s5test.s5_ai_usage WHERE cost_usd=5.0");

echo "== Fallos de aprovisionamiento → rollback completo sin huérfanos\n";
$cases = [
    'token inválido (subdominio)' => ['subdomain' => 'cPanel rechazó el token de API (revise usuario y token).'],
    'disco lleno al crear BD' => ['db' => 'No hay espacio en disco para crear la base de datos.'],
    'disco lleno copiando archivos (a medias)' => ['copy_after' => 400],
    'falla el armado de páginas' => ['provision_pages' => 'Error simulado armando páginas'],
    'falla la instalación de WordPress' => ['provision_install' => 'Error simulado instalando WordPress'],
];
foreach ($cases as $name => $f) {
    $dbs0 = count(sim_dbs()); $us0 = count(sim_users());
    $setFail($f);
    [$c, $tok, $id] = draft('Taller Falla ' . substr(md5($name), 0, 4)); start($c, $tok); $st = drive($id);
    $clear();
    $slug = (string) mysql_val("SELECT slug FROM s5test.s5_orders WHERE id=$id");
    t_ok($st === 'borrador', "$name → estado borrador", $st);
    t_ok(!orphans($slug, $dbs0, $us0), "$name → sin huérfanos", implode(',', orphans($slug, $dbs0, $us0)));
    $g = $c->api('GET', "/api/borrador/$tok")['json'];
    t_ok(!empty($g['construccion']['mensaje']) && !preg_match('/Exception|stack|\/tmp\//i', (string) $g['construccion']['mensaje']), "$name → mensaje amable sin detalles técnicos", (string) ($g['construccion']['mensaje'] ?? ''));
    t_ok($count("SELECT COUNT(*) FROM s5test.s5_alerts WHERE order_id=$id AND resolved=0") >= 1, "$name → alerta en el panel");
    // se puede reintentar tras corregir el problema
    start($c, $tok); $st2 = drive($id);
    t_ok($st2 === 'vista_lista', "$name → reintento exitoso", $st2);
}
t_ok(str_contains(implode('', array_map('file_get_contents', glob('/tmp/s5test/mail/*.json'))), 'Falló la creación de una web'), 'correo de aviso al dueño por fallo');

echo "== Subdominio ya existente\n";
@mkdir("$W/ocupado", 0777); symlink("$W/ocupado", "$V/taller-existe.servicom.test");
[$c, $tok, $id] = draft('Taller Existe'); start($c, $tok); $st = drive($id);
t_ok($st === 'vista_lista' && mysql_val("SELECT slug FROM s5test.s5_orders WHERE id=$id") !== 'taller-existe', 'se evita el subdominio ocupado', (string) mysql_val("SELECT slug FROM s5test.s5_orders WHERE id=$id"));
t_ok(readlink("$V/taller-existe.servicom.test") === "$W/ocupado", 'el subdominio ajeno quedó intacto');

echo "== Cierre a medias de pasos (kill -9) y reanudación\n";
[$c, $tok, $id] = draft('Taller Interrumpido');
mysql_val("UPDATE s5test.s5_orders SET host_id=1 WHERE id=$id");
as_www('php -r ' . escapeshellarg('define("S5_ROOT","/tmp/s5test/portal");require "/tmp/s5test/portal/app/bootstrap.php";S5\Services\Pipeline::start(' . $id . ');'));
$killed = 0; $st = '';
for ($i = 0; $i < 60; $i++) {
    shell_exec('runuser -u www-data -- env S5_CONFIG_FILE=/tmp/s5test/config.php S5_SITE_PORT=8200 S5_QA_RESOLVE=127.0.0.1 S5_AI_URL=http://127.0.0.1:8210/v1/messages S5_PORTAL_URL=http://crear.servicom.test:8201 S5_MAIL_SINK=/tmp/s5test/mail timeout -s KILL 3 php /home/user/vqmod/servicom-5min/tests/e2e/tick.php ' . $id . ' 25 >/dev/null 2>&1');
    $killed++;
    mysql_val("UPDATE s5test.s5_orders SET build_lock=NULL WHERE id=$id");   // el candado vencería a los 90 s
    $st = mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id");
    if ($st !== 'construyendo') { break; }
}
t_ok($st === 'vista_lista', "build completo tras $killed interrupciones violentas", (string) $st);
$slug = mysql_val("SELECT slug FROM s5test.s5_orders WHERE id=$id");
$hp = new Client('http://127.0.0.1:8200', "$slug.servicom.test:8200"); $key = mysql_val("SELECT preview_key FROM s5test.s5_orders WHERE id=$id");
$hp->req('GET', "/?scpk=$key"); $r = $hp->req('GET', '/');
t_ok($r['status'] === 200 && !preg_match('/(Warning|Notice|Fatal error):/', $r['body']), 'el sitio reanudado funciona');
$o = mysql_val("SELECT db_name FROM s5test.s5_orders WHERE id=$id"); $pre = mysql_val("SELECT wp_prefix FROM s5test.s5_orders WHERE id=$id");
t_ok((int) mysql_val("SELECT COUNT(*) FROM `$o`.`{$pre}posts` WHERE post_type='page' AND post_status='publish'") === (int) mysql_val("SELECT COUNT(DISTINCT post_name) FROM `$o`.`{$pre}posts` WHERE post_type='page' AND post_status='publish'"), 'sin páginas duplicadas tras reanudar');

echo "== Rollback explícito y limpieza de borrador vencido a medias\n";
[$c, $tok, $id] = draft('Taller Medias'); start($c, $tok); drive($id);
$slug = mysql_val("SELECT slug FROM s5test.s5_orders WHERE id=$id");
mysql_val("UPDATE s5test.s5_orders SET expires_at='2000-01-01' WHERE id=$id");
as_www('php /tmp/s5test/portal/tools/cron.php');
t_ok(mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id") === 'eliminada' && !is_dir("$W/$slug") && !is_link("$V/$slug.servicom.test"), 'borrado completo');

echo "== Análisis de presentación interrumpido\n";
[$c, $tok, $id] = draft('Taller Analisis');
mysql_val("UPDATE s5test.s5_orders SET analysis_state='procesando', analysis_lock='" . gmdate('Y-m-d H:i:s', time() - 30) . "' WHERE id=$id");
$a = $c->api('GET', "/api/borrador/$tok/analisis")['json'];
t_ok(($a['estado'] ?? '') === 'error' && str_contains((string) ($a['mensaje'] ?? ''), 'manualmente'), 'análisis colgado → error amable', json_encode($a, JSON_UNESCAPED_UNICODE));
t_ok($c->api('GET', "/api/borrador/$tok")['status'] === 200, 'el borrador sigue utilizable');
$clear();
echo "\nFallos simulados: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
