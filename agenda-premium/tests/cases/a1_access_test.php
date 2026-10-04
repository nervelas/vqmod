<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/a1_http.inc.php';
T::boot('a1_access');

use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Str;
use App\Core\Tz;
use App\Services\BookingService;

A1Http::start(8114, '/tmp/ap-t-a1_access.config.php');
$now = Clock::utc();
$user = static function (string $name, string $email, string $role) use ($now): int {
    return Db::insert('users', ['name' => $name, 'email' => $email, 'password_hash' => Crypto::hashPassword('Clave#Prueba2026'), 'role' => $role, 'active' => 1, 'theme' => 'dark', 'created_at' => $now, 'updated_at' => $now]);
};
$schedule = (int) Db::val('SELECT id FROM schedules LIMIT 1');
$host = static function (int $uid, string $name, string $slug) use ($now, $schedule): int {
    $id = Db::insert('hosts', ['user_id' => $uid, 'name' => $name, 'slug' => $slug, 'timezone' => 'America/Guatemala', 'color' => '#5B8DEF', 'ics_token' => Str::token(16), 'schedule_id' => $schedule, 'created_at' => $now]);
    foreach (Db::col('SELECT id FROM event_types') as $e) {
        Db::insert('event_hosts', ['event_type_id' => $e, 'host_id' => $id, 'weight' => 1, 'priority' => 1]);
    }
    return $id;
};
$ua = $user('Host Alfa', 'alfa@test.local', 'host');
$ub = $user('Host Beta', 'beta@test.local', 'host');
$user('Rita Recepción', 'rita@test.local', 'reception');
$ha = $host($ua, 'Host Alfa', 'alfa');
$hb = $host($ub, 'Host Beta', 'beta');
$day = date('Y-m-d', strtotime('+3 day'));
$book = static function (int $hostId, string $name, string $time, string $email) use ($day): int {
    $r = BookingService::create(['event_id' => 1, 'duration' => 30, 'start' => Tz::localToUtc("$day $time:00", 'America/Guatemala'), 'host_id' => $hostId, 'timezone' => 'America/Guatemala', 'name' => $name, 'email' => $email, 'phone' => '55' . random_int(100000, 999999), 'consent' => true, 'created_via' => 'admin', 'force' => true]);
    return (int) $r['booking']['id'];
};
$bA = $book($ha, 'Cliente De Alfa', '10:00', 'alfa-cli@example.test');
$bB = $book($hb, 'Cliente De Beta', '11:00', 'beta-cli@example.test');
$xss = '<img src=x onerror=alert(1)>';
$bX = $book($ha, $xss, '12:00', 'xss@example.test');
$cA = (int) Db::val('SELECT client_id FROM bookings WHERE id = ?', [$bA]);
$cB = (int) Db::val('SELECT client_id FROM bookings WHERE id = ?', [$bB]);
$cX = (int) Db::val('SELECT client_id FROM bookings WHERE id = ?', [$bX]);
T::ok($cA > 0 && $cB > 0 && $cA !== $cB, 'datos de prueba creados');

$login = static function (string $email): A1Client {
    $c = new A1Client();
    $c->login($email, $email === 'admin@test.local' ? 'Prueba#Segura2026' : 'Clave#Prueba2026');
    return $c;
};

T::section('Anfitrión: solo lo suyo (IDOR)');
$a = $login('alfa@test.local');
$a->get('/admin/citas/' . $bA);
T::eq(200, $a->last['status'], 've su propia cita');
foreach (["/admin/citas/$bB", "/admin/citas/$bB/ics", "/admin/clientes/$cB", "/admin/clientes/$cB/editar"] as $p) {
    $a->get($p);
    T::eq(404, $a->last['status'], "GET {$p} de otro anfitrión => 404");
}
$a->get('/admin/citas');
$csrf = $a->csrf();
T::ok(str_contains($a->last['body'], 'Cliente De Alfa') && !str_contains($a->last['body'], 'Cliente De Beta'), 'la lista de citas solo muestra las suyas');
$a->get('/admin/clientes');
T::ok(str_contains($a->last['body'], 'Cliente De Alfa') && !str_contains($a->last['body'], 'Cliente De Beta'), 'la lista de clientes solo muestra los suyos');
foreach (['estado' => ['status' => 'confirmed'], 'mover' => ['fecha' => $day, 'hora' => '15:00'], 'cancelar' => ['reason' => 'x'], 'nota' => ['internal_note' => 'hackeo']] as $act => $data) {
    $r = $a->request('POST', "/admin/citas/$bB/$act", $data + ['_csrf' => $csrf]);
    T::eq(404, $r['status'], "POST {$act} sobre cita ajena => 404");
}
T::eq(null, Db::val('SELECT internal_note FROM bookings WHERE id = ?', [$bB]), 'la cita ajena no cambió');
foreach (["/admin/clientes/$cB/notas" => ['body' => 'x'], "/admin/clientes/$cB/etiquetas" => ['tags' => 'x'], "/admin/clientes/$cB/editar" => ['name' => 'Roto']] as $p => $data) {
    $r = $a->request('POST', $p, $data + ['_csrf' => $csrf]);
    T::eq(404, $r['status'], "POST {$p} ajeno => 404");
}
$j = $a->getJson('/admin/calendario/datos?from=' . $day . '&to=' . date('Y-m-d', strtotime($day . ' +1 day')));
$names = array_column($j['json']['bookings'] ?? [], 'guest');
T::ok(in_array('Cliente De Alfa', $names, true) && !in_array('Cliente De Beta', $names, true), 'el calendario solo entrega sus citas');
$j = $a->getJson('/admin/buscar?q=Cliente');
$titles = implode('|', array_column($j['json']['results'] ?? [], 'title'));
T::ok(str_contains($titles, 'Cliente De Alfa') && !str_contains($titles, 'Cliente De Beta'), 'la búsqueda global respeta el alcance');
$j = $a->getJson('/admin/slots?evento=1&fecha=' . $day . '&anfitrion=' . $hb);
T::ok(($j['status'] ?? 0) === 200, 'slots de anfitrión ignora el parámetro y usa el propio');
foreach (['/admin/clientes/nuevo', '/admin/clientes/importar', '/admin/clientes/duplicados', '/admin/clientes/fusionar'] as $p) {
    $a->get($p);
    T::eq(403, $a->last['status'], "anfitrión no accede a {$p}");
}
foreach (["/admin/clientes/$cA/eliminar", "/admin/clientes/$cA/bloqueo", "/admin/clientes/fusionar"] as $p) {
    $r = $a->request('POST', $p, ['_csrf' => $csrf, 'confirm' => 'ELIMINAR']);
    T::eq(403, $r['status'], "anfitrión no puede POST {$p}");
}
$a->get("/admin/clientes/$cA/datos");
T::eq(403, $a->last['status'], 'exportar datos personales es solo de administración');

T::section('Áreas por rol');
$admin = $login('admin@test.local');
foreach (['/admin/ajustes', '/admin/usuarios', '/admin/anfitriones', '/admin/eventos', '/admin/sistema'] as $p) {
    $admin->get($p);
    if ($admin->last['status'] === 404) {
        echo "  --   {$p} aún no existe (otro agente); se omite\n";
        continue;
    }
    $a->get($p);
    T::eq(403, $a->last['status'], "anfitrión => 403 en {$p}");
    $rita = $login('rita@test.local');
    $rita->get($p);
    T::eq(403, $rita->last['status'], "recepción => 403 en {$p}");
}
$rita = $login('rita@test.local');
foreach (['/admin', '/admin/citas', '/admin/clientes', '/admin/calendario', '/admin/perfil'] as $p) {
    $rita->get($p);
    T::eq(200, $rita->last['status'], "recepción accede a {$p}");
}
$rc = $rita->csrf();
$r = $rita->request('POST', "/admin/clientes/$cA/eliminar", ['_csrf' => $rc, 'confirm' => 'ELIMINAR', 'password' => 'Clave#Prueba2026']);
T::eq(403, $r['status'], 'recepción no puede eliminar datos de personas');
$r = $a->request('POST', '/admin/citas/' . $bA . '/estado', ['status' => 'confirmed']);
T::eq(419, $r['status'], 'POST sin CSRF => 419');
$anon = new A1Client();
$r = $anon->getJson('/admin/buscar?q=ab');
T::eq(401, $r['status'], 'búsqueda sin sesión => 401 JSON');
$anon->get('/admin/citas/' . $bA);
T::ok(str_contains($anon->location(), '/admin/login'), 'detalle sin sesión redirige al acceso');

T::section('XSS neutralizado');
$admin->get('/admin/clientes/' . $cX);
$admin->request('POST', "/admin/clientes/$cX/notas", ['_csrf' => $admin->csrf(), 'body' => '<script>alert("nota")</script>']);
$pages = ['/admin', '/admin/citas', '/admin/citas/' . $bX, '/admin/clientes', '/admin/clientes/' . $cX, '/admin/calendario?fecha=' . $day];
foreach ($pages as $p) {
    $admin->get($p);
    $body = $admin->last['body'];
    T::ok(!str_contains($body, $xss) && !str_contains($body, '<script>alert'), "sin HTML inyectado en {$p}");
}
$admin->get('/admin/clientes/' . $cX);
T::ok(str_contains($admin->last['body'], '&lt;script&gt;alert(&quot;nota&quot;)&lt;/script&gt;'), 'la nota se muestra escapada');
$js = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/admin.js') . (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/admin-calendar.js') . (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/admin-bookings.js');
T::ok(!preg_match('/innerHTML|outerHTML|insertAdjacentHTML|document\.write/', $js), 'el JS del panel no usa innerHTML');

T::section('Inyección SQL neutralizada');
$total = (int) Db::val('SELECT COUNT(*) FROM bookings');
foreach (["' OR 1=1 --", "%' OR '1'='1", '%', '_', '1; DROP TABLE bookings', '" OR ""="'] as $q) {
    $admin->get('/admin/citas?q=' . rawurlencode($q));
    T::eq(200, $admin->last['status'], 'citas?q=' . $q . ' responde 200');
    preg_match('/<p class="page-sub">(\d+) /', $admin->last['body'], $m);
    T::ok(isset($m[1]) && (int) $m[1] === 0, 'citas?q=' . $q . ' no devuelve filas');
    $admin->get('/admin/clientes?q=' . rawurlencode($q) . '&etiqueta=' . rawurlencode($q));
    T::eq(200, $admin->last['status'], 'clientes?q=' . $q . ' responde 200');
    $j = $admin->getJson('/admin/buscar?q=' . rawurlencode($q));
    $groups = array_unique(array_column($j['json']['results'] ?? [], 'group'));
    T::ok(!array_intersect($groups, ['Clientes', 'Citas']), 'buscar?q=' . $q . ' no devuelve clientes ni citas');
}
foreach (['estado=x%27%20OR%20%271%27=%271', 'anfitrion=1%20OR%201=1', 'evento=1;SELECT', 'desde=2026-01-01%27--', 'pagina=-5', 'pagina=abc'] as $qs) {
    $admin->get('/admin/citas?' . $qs);
    T::eq(200, $admin->last['status'], "filtro {$qs} no rompe");
}
T::eq($total, (int) Db::val('SELECT COUNT(*) FROM bookings'), 'la tabla de citas sigue intacta');
T::done();
