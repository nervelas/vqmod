<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/A2Http.php';
T::boot('a2_sched');

use App\Core\Clock;
use App\Core\Db;
use App\Core\Str;

A2Http::start('a2_sched', 8193);
register_shutdown_function([A2Http::class, 'stop']);
$now = Clock::utc();

$schedA = Db::insert('schedules', ['name' => 'Horario A', 'timezone' => 'America/Guatemala', 'created_at' => $now]);
$schedB = Db::insert('schedules', ['name' => 'Horario B', 'timezone' => 'America/Guatemala', 'created_at' => $now]);
$default = (int) Db::val('SELECT id FROM schedules WHERE is_default = 1');
$hA = Db::insert('hosts', ['name' => 'Ana', 'slug' => 'ana', 'ics_token' => Str::token(16), 'schedule_id' => $schedA, 'created_at' => $now]);
$hB = Db::insert('hosts', ['name' => 'Beto', 'slug' => 'beto', 'ics_token' => Str::token(16), 'schedule_id' => $schedB, 'created_at' => $now]);
$uA = Db::insert('users', ['name' => 'Ana', 'email' => 'ana@test.local', 'password_hash' => password_hash('Ana#Prueba2026', PASSWORD_BCRYPT), 'role' => 'host', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
Db::update('hosts', ['user_id' => $uA], 'id = ?', [$hA]);

$admin = new A2Http();
T::ok($admin->login('admin@test.local', 'Prueba#Segura2026'), 'admin entra');
$ana = new A2Http();
T::ok($ana->login('ana@test.local', 'Ana#Prueba2026'), 'anfitrión entra');

T::section('Horarios con varios bloques y excepciones');
$post = [
    'name' => 'Consultorio', 'timezone' => 'America/Guatemala',
    'rules' => [
        1 => [['start' => '08:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '18:00']],
        2 => [['start' => '08:00', 'end' => '12:00']],
    ],
    'ex' => [
        ['date' => '2026-12-24', 'open' => '0', 'note' => 'Nochebuena'],
        ['date' => '2026-12-26', 'open' => '1', 'blocks' => [['start' => '09:00', 'end' => '11:00'], ['start' => '15:00', 'end' => '16:00']]],
    ],
];
$r = $admin->post('/admin/horarios/nuevo', $post);
T::eq(302, $r['status'], 'crea horario');
$sid = (int) Db::val("SELECT id FROM schedules WHERE name = 'Consultorio'");
T::eq(3, (int) Db::val('SELECT COUNT(*) FROM schedule_rules WHERE schedule_id = ?', [$sid]), 'tres bloques semanales');
T::eq(2, (int) Db::val('SELECT COUNT(*) FROM schedule_rules WHERE schedule_id = ? AND weekday = 1', [$sid]), 'dos bloques el lunes');
T::eq(3, (int) Db::val('SELECT COUNT(*) FROM schedule_overrides WHERE schedule_id = ?', [$sid]), 'excepciones: 1 cierre + 2 bloques');
T::eq(0, (int) Db::val("SELECT is_open FROM schedule_overrides WHERE schedule_id = ? AND date = '2026-12-24'", [$sid]), 'día cerrado');
T::eq('09:00:00', Db::val("SELECT MIN(start_time) FROM schedule_overrides WHERE schedule_id = ? AND date = '2026-12-26'", [$sid]), 'día abierto con horas propias');
$form = $admin->get('/admin/horarios/' . $sid . '/editar');
T::ok(str_contains($form['body'], 'name="rules[1][1][start]"') && str_contains($form['body'], 'value="14:00"'), 'el formulario recarga los bloques');

$bad = static fn (array $over) => $admin->post('/admin/horarios/nuevo', array_merge($post, ['name' => 'Malo'], $over));
T::eq(422, $bad(['rules' => [1 => [['start' => '10:00', 'end' => '09:00']]]])['status'], 'fin antes del inicio rechazado');
T::eq(422, $bad(['rules' => [1 => [['start' => '08:00', 'end' => '12:00'], ['start' => '11:00', 'end' => '13:00']]]])['status'], 'bloques traslapados rechazados');
T::eq(422, $bad(['rules' => [1 => [['start' => '25:00', 'end' => '26:00']]]])['status'], 'hora inválida rechazada');
T::eq(422, $bad(['timezone' => 'Nada/Nada'])['status'], 'zona inválida rechazada');
T::eq(422, $bad(['ex' => [['date' => '2026-02-30', 'open' => '0']]])['status'], 'fecha de excepción inválida');
T::eq(422, $bad(['ex' => [['date' => '2026-12-24', 'open' => '0'], ['date' => '2026-12-24', 'open' => '0']]])['status'], 'excepción repetida');
T::eq(422, $bad(['ex' => [['date' => '2026-12-24', 'open' => '1']]])['status'], 'día abierto sin horas rechazado');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM schedules WHERE name = 'Malo'"), 'ningún horario inválido se guardó');
$r = $admin->post('/admin/horarios/' . $sid . '/editar', array_merge($post, ['rules' => [3 => [['start' => '09:00', 'end' => '10:00']]], 'ex' => []]));
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM schedule_rules WHERE schedule_id = ?', [$sid]), 'editar reemplaza los bloques');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM schedule_overrides WHERE schedule_id = ?', [$sid]), 'editar reemplaza las excepciones');
$admin->post('/admin/horarios/' . $sid . '/predeterminado');
T::eq(1, (int) Db::val('SELECT is_default FROM schedules WHERE id = ?', [$sid]), 'nuevo predeterminado');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM schedules WHERE is_default = 1'), 'solo hay un predeterminado');
$admin->post('/admin/horarios/' . $sid . '/eliminar');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM schedules WHERE id = ?', [$sid]), 'el predeterminado no se elimina');
$admin->post('/admin/horarios/' . $default . '/predeterminado');
$admin->post('/admin/horarios/' . $sid . '/eliminar');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM schedules WHERE id = ?', [$sid]), 'elimina un horario no predeterminado');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM schedule_rules WHERE schedule_id = ?', [$sid]), 'sus bloques se borran en cascada');

T::section('IDOR de anfitrión en horarios');
T::eq(200, $ana->get('/admin/horarios/' . $schedA . '/editar')['status'], 'edita su propio horario');
T::eq(403, $ana->get('/admin/horarios/' . $schedB . '/editar')['status'], 'no ve el horario de otro');
T::eq(403, $ana->post('/admin/horarios/' . $schedB . '/editar', $post)['status'], 'no edita el horario de otro');
T::eq('Horario B', Db::val('SELECT name FROM schedules WHERE id = ?', [$schedB]), 'el horario ajeno no cambió');
T::eq(403, $ana->get('/admin/horarios/' . $default . '/editar')['status'], 'no edita el horario predeterminado compartido');
T::eq(403, $ana->post('/admin/horarios/' . $schedB . '/eliminar')['status'], 'no elimina horarios');
T::eq(403, $ana->post('/admin/horarios/' . $schedB . '/predeterminado')['status'], 'no cambia el predeterminado');
$list = $ana->get('/admin/horarios')['body'];
T::ok(str_contains($list, 'Horario A') && !str_contains($list, 'Horario B'), 'la lista solo muestra el suyo');
T::eq(403, $ana->get('/admin/horarios/nuevo')['status'], 'con horario propio no crea otro');
T::eq(302, $ana->post('/admin/horarios/' . $schedA . '/editar', array_merge($post, ['name' => 'Mi horario', 'is_default' => '1']))['status'], 'guarda su horario');
T::eq(0, (int) Db::val('SELECT is_default FROM schedules WHERE id = ?', [$schedA]), 'un anfitrión no puede marcarlo predeterminado');

T::section('Ausencias');
$r = $admin->post('/admin/ausencias/guardar', ['host_id' => (string) $hB, 'start_date' => '2026-11-02', 'end_date' => '2026-11-04', 'all_day' => '1', 'reason' => 'Cirugía de Beto']);
T::eq(302, $r['status'], 'ausencia de un anfitrión');
$to = Db::one('SELECT * FROM time_off WHERE host_id = ?', [$hB]);
T::eq('2026-11-02 06:00:00', $to['starts_at'], 'inicio en UTC (Guatemala = UTC-6)');
T::eq('2026-11-05 05:59:59', $to['ends_at'], 'fin en UTC al terminar el último día');
$admin->post('/admin/ausencias/guardar', ['host_id' => 'business', 'start_date' => '2026-12-31', 'end_date' => '2026-12-31', 'start_time' => '14:00', 'end_time' => '18:00']);
$biz = Db::one('SELECT * FROM time_off WHERE host_id IS NULL');
T::eq('2026-12-31 20:00:00', $biz['starts_at'], 'cierre del negocio por horas en UTC');
T::eq(302, $admin->post('/admin/ausencias/guardar', ['host_id' => (string) $hB, 'start_date' => '2026-11-10', 'end_date' => '2026-11-09', 'all_day' => '1'])['status'], 'rango invertido vuelve con error');
T::eq(2, (int) Db::val('SELECT COUNT(*) FROM time_off'), 'rango inválido no se guardó');
$admin->post('/admin/ausencias/guardar', ['host_id' => '', 'start_date' => '2026-11-10', 'all_day' => '1']);
T::eq(2, (int) Db::val('SELECT COUNT(*) FROM time_off'), 'sin responsable no se guarda');
$ana->post('/admin/ausencias/guardar', ['host_id' => (string) $hB, 'start_date' => '2026-11-20', 'end_date' => '2026-11-20', 'all_day' => '1']);
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM time_off WHERE host_id = ? AND starts_at >= ?', [$hB, '2026-11-20 00:00:00']), 'el anfitrión no crea ausencias a nombre de otro');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM time_off WHERE host_id = ?', [$hA]), 'se guardó a nombre del propio anfitrión');
T::eq(403, $ana->post('/admin/ausencias/' . $to['id'] . '/eliminar')['status'], 'no elimina la ausencia de otro');
T::eq(403, $ana->post('/admin/ausencias/' . $biz['id'] . '/eliminar')['status'], 'no elimina el cierre del negocio');
$mine = (int) Db::val('SELECT id FROM time_off WHERE host_id = ?', [$hA]);
$ana->post('/admin/ausencias/' . $mine . '/eliminar');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM time_off WHERE host_id = ?', [$hA]), 'elimina la propia');
T::ok(str_contains($ana->get('/admin/ausencias')['body'], 'Todo el negocio') && !str_contains($ana->get('/admin/ausencias')['body'], 'Cirugía de Beto'), 've cierres del negocio pero no ausencias ajenas');

T::section('Feriados');
$admin->get('/admin/feriados?anio=2027');
T::ok((int) Db::val("SELECT COUNT(*) FROM holidays WHERE date LIKE '2027-%'") >= 12, 'se precargan los feriados de 2027');
$hol = (int) Db::val("SELECT id FROM holidays WHERE date = '2027-09-15'");
$admin->post('/admin/feriados/' . $hol . '/activo');
T::eq(0, (int) Db::val('SELECT active FROM holidays WHERE id = ?', [$hol]), 'desactiva un feriado');
$aug = (int) Db::val("SELECT id FROM holidays WHERE date = '2027-08-15'");
T::eq(0, (int) Db::val('SELECT active FROM holidays WHERE id = ?', [$aug]), '15 de agosto viene desactivado');
$admin->post('/admin/feriados/' . $aug . '/activo');
T::eq(1, (int) Db::val('SELECT active FROM holidays WHERE id = ?', [$aug]), 'se puede activar');
$admin->post('/admin/feriados/guardar', ['date' => '2027-03-10', 'name' => 'Aniversario del negocio', 'kind' => 'half', 'half_day_end' => '12:00', 'scope' => 'national', 'active' => '1']);
$m = Db::one("SELECT * FROM holidays WHERE name = 'Aniversario del negocio'");
T::eq('manual', $m['source'], 'feriado manual');
T::eq('half', $m['kind'], 'medio día');
T::eq('12:00:00', $m['half_day_end'], 'hora de fin del medio día');
$admin->post('/admin/feriados/guardar', ['id' => (string) $m['id'], 'date' => '2027-03-11', 'name' => 'Aniversario del negocio', 'kind' => 'full', 'active' => '1']);
T::eq('2027-03-11', Db::val('SELECT date FROM holidays WHERE id = ?', [$m['id']]), 'edita un feriado');
$admin->post('/admin/feriados/guardar', ['date' => '2027-03-12', 'name' => 'Medio', 'kind' => 'half', 'half_day_end' => 'xx']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM holidays WHERE name = 'Medio'"), 'medio día sin hora válida rechazado');
$admin->post('/admin/feriados/guardar', ['date' => 'no-fecha', 'name' => 'Roto']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM holidays WHERE name = 'Roto'"), 'fecha inválida rechazada');
$admin->post('/admin/feriados/restablecer', ['anio' => '2027']);
T::eq(0, (int) Db::val("SELECT active FROM holidays WHERE date = '2027-08-15'"), 'restablecer devuelve el 15 de agosto a desactivado');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM holidays WHERE name = 'Aniversario del negocio'"), 'restablecer conserva los manuales');
T::eq(1, (int) Db::val("SELECT active FROM holidays WHERE date = '2027-09-15'"), 'restablecer reactiva el 15 de septiembre');
$admin->post('/admin/feriados/interruptor', ['anio' => '2027']);
T::eq('0', (string) Db::val("SELECT v FROM settings WHERE k = 'holidays_enabled'"), 'interruptor global apagado');
$admin->post('/admin/feriados/interruptor', ['holidays_enabled' => '1', 'anio' => '2027']);
T::eq('1', (string) Db::val("SELECT v FROM settings WHERE k = 'holidays_enabled'"), 'interruptor global encendido');
T::eq(403, $ana->get('/admin/feriados')['status'], 'anfitrión: 403 en feriados');
T::eq(403, $ana->post('/admin/feriados/' . $hol . '/activo')['status'], 'anfitrión: 403 al editar feriados');

T::section('Recursos');
$admin->post('/admin/recursos/guardar', ['name' => 'Consultorio 2', 'capacity' => '2', 'active' => '1', 'description' => 'Con camilla']);
$rid = (int) Db::val("SELECT id FROM resources WHERE name = 'Consultorio 2'");
T::eq(2, (int) Db::val('SELECT capacity FROM resources WHERE id = ?', [$rid]), 'crea recurso');
$admin->post('/admin/recursos/guardar', ['id' => (string) $rid, 'name' => 'Consultorio 2B', 'capacity' => '3']);
T::eq('Consultorio 2B', Db::val('SELECT name FROM resources WHERE id = ?', [$rid]), 'edita recurso');
T::eq(0, (int) Db::val('SELECT active FROM resources WHERE id = ?', [$rid]), 'se puede marcar inactivo');
$admin->post('/admin/recursos/guardar', ['name' => '']);
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM resources'), 'nombre vacío rechazado');
T::eq(403, $ana->get('/admin/recursos')['status'], 'anfitrión: 403 en recursos');
$admin->post('/admin/recursos/' . $rid . '/eliminar');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM resources'), 'elimina recurso');

T::section('Calendarios externos');
$calB = Db::insert('external_calendars', ['host_id' => $hB, 'name' => 'Calendario de Beto', 'url' => 'https://example.test/secreto-de-beto.ics', 'active' => 1]);
$r = $ana->post('/admin/calendarios/guardar', ['host_id' => (string) $hB, 'name' => 'Mío', 'url' => 'http://127.0.0.1:1/x.ics']);
T::eq(302, $r['status'], 'guarda (con aviso porque el enlace local está bloqueado)');
$mineCal = Db::one("SELECT * FROM external_calendars WHERE name = 'Mío'");
T::eq($hA, (int) $mineCal['host_id'], 'el calendario queda a nombre del propio anfitrión, aunque pida otro');
T::ok($mineCal['last_status'] === 'error' && !empty($mineCal['last_error']), 'queda registrado el último error');
$page = $ana->get('/admin/calendarios')['body'];
T::ok(str_contains($page, 'No pudimos leer este calendario'), 'el error es visible en el panel');
T::ok(!str_contains($page, 'secreto-de-beto') && !str_contains($page, 'Calendario de Beto'), 'no ve calendarios de otro anfitrión');
T::eq(403, $ana->post('/admin/calendarios/' . $calB . '/sincronizar')['status'], 'no sincroniza el de otro');
T::eq(403, $ana->post('/admin/calendarios/' . $calB . '/activo')['status'], 'no pausa el de otro');
T::eq(403, $ana->post('/admin/calendarios/' . $calB . '/eliminar')['status'], 'no elimina el de otro');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM external_calendars WHERE id = ?', [$calB]), 'el calendario ajeno sigue ahí');
$admin->post('/admin/calendarios/' . $mineCal['id'] . '/activo');
T::eq(0, (int) Db::val('SELECT active FROM external_calendars WHERE id = ?', [$mineCal['id']]), 'admin pausa');
$admin->post('/admin/calendarios/guardar', ['host_id' => (string) $hA, 'name' => 'Sin protocolo', 'url' => 'ftp://x/y.ics']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM external_calendars WHERE name = 'Sin protocolo'"), 'esquema no http rechazado');
$admin->post('/admin/calendarios/guardar', ['host_id' => (string) $hA, 'name' => 'XSS', 'url' => 'javascript:alert(1)']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM external_calendars WHERE name = 'XSS'"), 'javascript: rechazado');
$t = $admin->post('/admin/calendarios/probar', ['url' => 'http://127.0.0.1:1/x.ics'], true, true);
T::eq(422, $t['status'], 'probar un enlace local da error amable');
T::ok(str_contains($t['body'], '"ok":false'), 'respuesta JSON de error');
$admin->post('/admin/calendarios/' . $mineCal['id'] . '/eliminar');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM external_calendars WHERE id = ?', [$mineCal['id']]), 'elimina calendario');
T::done();
