<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/A2Http.php';
T::boot('a2_events');

use App\Core\Clock;
use App\Core\Db;
use App\Core\Str;

A2Http::start('a2_events', 8191);
register_shutdown_function([A2Http::class, 'stop']);

$now = Clock::utc();
$hostIds = [];
foreach (['Ana', 'Beto', 'Carla'] as $i => $n) {
    $hostIds[] = Db::insert('hosts', ['name' => $n, 'slug' => strtolower($n), 'ics_token' => Str::token(16), 'created_at' => $now, 'sort_order' => $i]);
}
$uid = Db::insert('users', ['name' => 'Doc', 'email' => 'doc@test.local', 'password_hash' => password_hash('Doc#Prueba2026', PASSWORD_BCRYPT), 'role' => 'host', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
Db::update('hosts', ['user_id' => $uid], 'id = ?', [$hostIds[0]]);
$roomId = Db::insert('resources', ['name' => 'Sala 1', 'capacity' => 2]);

$admin = new A2Http();
T::ok($admin->login('admin@test.local', 'Prueba#Segura2026'), 'el administrador entra al panel');

$base = ['name' => 'Consulta inicial', 'kind' => 'individual', 'color' => '#C9A050', 'mode' => 'in_person', 'location' => 'Zona 10', 'durations' => ['30', '60'], 'default_duration' => '30',
    'min_notice_value' => '2', 'min_notice_unit' => 'hours', 'max_advance_days' => '60', 'slot_interval' => '30', 'active' => '1',
    'respect_holidays' => '1', 'price' => '150', 'deposit_type' => 'none', 'cancel_hours' => '24', 'visibility' => 'public', 'require_phone' => '1', 'allow_coupon' => '1',
    'hosts' => [$hostIds[0] => ['on' => '1', 'weight' => '1', 'priority' => '1']]];

T::section('Crear eventos de cada tipo');
$r = $admin->post('/admin/eventos/nuevo', $base);
T::eq(302, $r['status'], 'crea evento individual');
$ev1 = (int) Db::val("SELECT id FROM event_types WHERE name = 'Consulta inicial'");
T::ok($ev1 > 0, 'el evento existe en la base');
$row = Db::one('SELECT * FROM event_types WHERE id = ?', [$ev1]);
T::eq('30,60', $row['duration_options'], 'duraciones guardadas');
T::eq('consulta-inicial', $row['slug'], 'slug generado');
T::eq(120, (int) $row['min_notice_minutes'], 'aviso mínimo en minutos');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM event_hosts WHERE event_type_id = ?', [$ev1]), 'anfitrión asignado');

$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'Taller grupal', 'kind' => 'group', 'capacity' => '8', 'mode' => 'video_auto', 'location' => '']));
T::eq(302, $r['status'], 'crea evento grupal');
T::eq(8, (int) Db::val("SELECT capacity FROM event_types WHERE name = 'Taller grupal'"), 'cupos guardados');
T::eq('video_auto', Db::val("SELECT mode FROM event_types WHERE name = 'Taller grupal'"), 'videollamada automática');

$rr = array_merge($base, ['name' => 'Rotativo', 'kind' => 'round_robin', 'rr_mode' => 'weighted', 'hosts' => [
    $hostIds[0] => ['on' => '1', 'weight' => '3', 'priority' => '2'], $hostIds[1] => ['on' => '1', 'weight' => '1', 'priority' => '1']]]);
$r = $admin->post('/admin/eventos/nuevo', $rr);
T::eq(302, $r['status'], 'crea evento round robin');
$rrId = (int) Db::val("SELECT id FROM event_types WHERE name = 'Rotativo'");
T::eq('3', (string) Db::val('SELECT weight FROM event_hosts WHERE event_type_id = ? AND host_id = ?', [$rrId, $hostIds[0]]), 'peso por anfitrión');
T::eq('weighted', Db::val('SELECT rr_mode FROM event_types WHERE id = ?', [$rrId]), 'modo ponderado');

$col = array_merge($base, ['name' => 'Colectivo', 'kind' => 'collective', 'resources' => [$roomId], 'hosts' => [
    $hostIds[0] => ['on' => '1'], $hostIds[1] => ['on' => '1'], $hostIds[2] => ['on' => '1']]]);
$r = $admin->post('/admin/eventos/nuevo', $col);
T::eq(302, $r['status'], 'crea evento colectivo con sala');
$colId = (int) Db::val("SELECT id FROM event_types WHERE name = 'Colectivo'");
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM event_resources WHERE event_type_id = ?', [$colId]), 'sala asignada');

$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'A domicilio', 'mode' => 'home', 'location' => 'Ciudad', 'travel_minutes' => '25']));
T::eq(25, (int) Db::val("SELECT travel_minutes FROM event_types WHERE name = 'A domicilio'"), 'traslado a domicilio');

T::section('Validaciones');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => '']));
T::eq(422, $r['status'], 'nombre vacío rechazado');
T::ok(str_contains($r['body'], 'Escribe un nombre'), 'mensaje en español del validador');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'Malo', 'kind' => 'collective']));
T::eq(422, $r['status'], 'colectivo con un solo anfitrión rechazado');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'Precio raro', 'price' => 'abc']));
T::eq(422, $r['status'], 'precio inválido rechazado');
T::ok(str_contains($r['body'], 'precio no es válido'), 'mensaje de precio');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'Otro', 'slug' => 'consulta-inicial']));
T::eq(422, $r['status'], 'slug duplicado rechazado');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'Video', 'mode' => 'video_custom', 'video_url' => 'javascript:alert(1)']));
T::eq(422, $r['status'], 'enlace de videollamada con esquema peligroso rechazado');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'Redir', 'redirect_url' => 'javascript:alert(1)']));
T::eq(422, $r['status'], 'redirección peligrosa rechazada');
$r = $admin->post('/admin/eventos/nuevo', $base + ['_dummy' => 1]);
T::eq(302, $r['status'], 'mismo nombre sí se permite con otro slug');

T::section('XSS e inyección SQL');
$xss = '<script>alert(1)</script>"><img src=x onerror=alert(2)>';
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => $xss, 'description' => $xss]));
$xid = (int) Db::val('SELECT MAX(id) FROM event_types');
$page = $admin->get('/admin/eventos/' . $xid . '/editar');
T::ok(!str_contains($page['body'], '<script>alert(1)'), 'el nombre se escapa en el formulario');
T::ok(!str_contains($admin->get('/admin/eventos')['body'], '<script>alert(1)'), 'el nombre se escapa en la lista');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => "x'; DROP TABLE event_types; --", 'slug' => 'sqli-prueba']));
T::ok((int) Db::val('SELECT COUNT(*) FROM event_types') > 3, 'la tabla sigue existiendo tras una inyección');
T::eq("x'; DROP TABLE event_types; --", Db::val("SELECT name FROM event_types WHERE slug = 'sqli-prueba'"), 'el texto se guardó literal');

T::section('Editar, preguntas y orden');
$edit = array_merge($base, ['name' => 'Consulta (editada)', 'price' => '200', 'approval' => '1', 'q' => [
    ['id' => '', 'scope' => 'event', 'label' => 'Motivo de la consulta', 'type' => 'textarea', 'required' => '1', 'active' => '1', 'name' => ''],
    ['id' => '', 'scope' => 'event', 'label' => 'Tipo de seguro', 'type' => 'select', 'options' => "IGSS\nPrivado", 'active' => '1', 'name' => 'seguro'],
    ['id' => '', 'scope' => 'event', 'label' => 'Número de póliza', 'type' => 'text', 'active' => '1', 'name' => 'poliza', 'condition_field' => 'seguro', 'condition_value' => 'Privado'],
    ['id' => '', 'scope' => 'global', 'label' => 'Acepto el tratamiento de datos', 'type' => 'consent', 'required' => '1', 'active' => '1', 'name' => 'consentimiento'],
]]);
$r = $admin->post('/admin/eventos/' . $ev1 . '/editar', $edit);
T::eq(302, $r['status'], 'edita evento con preguntas');
T::eq(200, (int) Db::val('SELECT price FROM event_types WHERE id = ?', [$ev1]), 'precio actualizado');
T::eq(1, (int) Db::val('SELECT approval FROM event_types WHERE id = ?', [$ev1]), 'aprobación manual');
$f = Db::all('SELECT * FROM custom_fields WHERE event_type_id = ? ORDER BY sort_order', [$ev1]);
T::eq(3, count($f), 'tres preguntas propias');
T::eq('motivo_de_la_consulta', $f[0]['name'], 'clave generada desde el texto');
T::eq('seguro', $f[2]['condition_field'], 'lógica condicional guardada');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM custom_fields WHERE event_type_id IS NULL'), 'pregunta global guardada');
$r = $admin->post('/admin/eventos/' . $ev1 . '/editar', array_merge($base, ['q' => [['id' => '', 'scope' => 'event', 'label' => 'Lista rota', 'type' => 'select', 'options' => 'solo una', 'name' => 'rota']]]));
T::eq(422, $r['status'], 'lista con una sola opción rechazada');
$r = $admin->post('/admin/eventos/' . $ev1 . '/editar', array_merge($base, ['q' => [['id' => '', 'scope' => 'event', 'label' => 'A', 'type' => 'text', 'name' => 'a', 'condition_field' => 'inexistente', 'condition_value' => 'x']]]));
T::eq(422, $r['status'], 'condición hacia pregunta inexistente rechazada');
$qid = (int) $f[0]['id'];
$r = $admin->post('/admin/eventos/' . $ev1 . '/editar', array_merge($base, ['q' => [['id' => (string) $qid, 'del' => '1', 'scope' => 'event', 'label' => 'x', 'name' => 'x']]]));
T::eq(302, $r['status'], 'eliminar pregunta');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM custom_fields WHERE id = ?', [$qid]), 'la pregunta se eliminó');
$r = $admin->post('/admin/eventos/orden', ['ids' => [$colId, $rrId, $ev1]], true, true);
T::eq(200, $r['status'], 'orden por arrastre');
T::eq([$colId, $rrId, $ev1], array_map('intval', Db::col('SELECT id FROM event_types WHERE id IN (?,?,?) ORDER BY sort_order', [$colId, $rrId, $ev1])), 'orden persistido');

T::section('Duplicar, pausar, enlace único, eliminar');
$before = (int) Db::val('SELECT COUNT(*) FROM event_types');
$r = $admin->post('/admin/eventos/' . $ev1 . '/duplicar');
T::eq(302, $r['status'], 'duplica');
T::eq($before + 1, (int) Db::val('SELECT COUNT(*) FROM event_types'), 'hay una fila más');
$r = $admin->post('/admin/eventos/' . $ev1 . '/pausar');
T::eq(0, (int) Db::val('SELECT active FROM event_types WHERE id = ?', [$ev1]), 'pausa');
$admin->post('/admin/eventos/' . $ev1 . '/pausar');
T::eq(1, (int) Db::val('SELECT active FROM event_types WHERE id = ?', [$ev1]), 'reactiva');
$r = $admin->post('/admin/eventos/' . $ev1 . '/enlace-unico', ['expires' => gmdate('Y-m-d', time() + 5 * 86400)]);
T::eq(302, $r['status'], 'crea enlace de un solo uso');
T::ok(str_contains($r['loc'], 'enlace='), 'redirige mostrando el enlace');
$su = (int) Db::val('SELECT id FROM event_types WHERE single_use = 1');
T::eq('secret', Db::val('SELECT visibility FROM event_types WHERE id = ?', [$su]), 'el enlace único es secreto');
T::ok(Db::val('SELECT expires_at FROM event_types WHERE id = ?', [$su]) !== null, 'con fecha límite');
$list = $admin->get($r['loc']);
T::ok(str_contains($list['body'], '/e/u-'), 'la lista muestra el enlace copiable');
$r = $admin->post('/admin/eventos/' . $ev1 . '/enlace-unico', ['expires' => '2000-01-01']);
T::eq(302, $r['status'], 'fecha pasada: vuelve con error');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM event_types WHERE single_use = 1'), 'no se creó otro enlace con fecha pasada');

$del = (int) Db::val("SELECT id FROM event_types WHERE name = 'A domicilio'");
$r = $admin->post('/admin/eventos/' . $del . '/eliminar');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM event_types WHERE id = ?', [$del]), 'elimina evento sin citas');
Db::insert('bookings', ['token' => Str::token(16), 'event_type_id' => $ev1, 'host_id' => $hostIds[0], 'starts_at' => '2030-01-01 15:00:00', 'ends_at' => '2030-01-01 15:30:00', 'blocked_start' => '2030-01-01 15:00:00', 'blocked_end' => '2030-01-01 15:30:00', 'duration' => 30, 'guest_name' => 'X', 'created_at' => $now, 'updated_at' => $now]);
$admin->post('/admin/eventos/' . $ev1 . '/eliminar');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM event_types WHERE id = ?', [$ev1]), 'con citas no se elimina');
T::eq(404, $admin->get('/admin/eventos/999999/editar')['status'], 'evento inexistente: 404');

T::section('Permisos y CSRF');
$r = $admin->post('/admin/eventos/nuevo', array_merge($base, ['name' => 'Sin token']), false);
T::eq(419, $r['status'], 'POST sin CSRF rechazado');
$host = new A2Http();
T::ok($host->login('doc@test.local', 'Doc#Prueba2026'), 'el anfitrión entra');
T::eq(403, $host->get('/admin/eventos')['status'], 'anfitrión: 403 en eventos');
T::eq(403, $host->post('/admin/eventos/nuevo', $base)['status'], 'anfitrión: 403 al crear evento');
T::eq(403, $host->get('/admin/usuarios')['status'], 'anfitrión: 403 en usuarios');
T::eq(403, $host->get('/admin/insertar')['status'], 'anfitrión: 403 en insertar');
$anon = new A2Http();
T::eq(302, $anon->get('/admin/eventos')['status'], 'sin sesión: redirige al acceso');
T::done();
