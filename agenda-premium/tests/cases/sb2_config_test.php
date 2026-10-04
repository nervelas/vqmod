<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('sb2_config', ['profession' => 'medico']);

use App\Core\Db;
use App\Core\Settings;
use App\Services\ConfigPortabilityService as C;
use App\Services\ProfessionService;

// Estado rico de origen
ProfessionService::apply('dentista', true);
Settings::setMany(['business_name' => 'Clínica Ñandú', 'tagline' => 'Sonrisas con calma', 'smtp_host' => 'smtp.example.test', 'smtp_pass' => 'SECRETO-SMTP', 'wa_api_token' => 'SECRETO-WA', 'captcha_secret' => 'SECRETO-CAPTCHA', 'currency_symbol' => 'Q', 'retention_months' => '18']);
Db::insert('webhooks', ['name' => 'Zapier', 'url' => 'https://hooks.example.test/abc', 'secret' => 'SECRETO-WEBHOOK-123', 'events' => '*', 'created_at' => gmdate('Y-m-d H:i:s')]);
Db::insert('schedule_overrides', ['schedule_id' => (int) Db::val('SELECT id FROM schedules LIMIT 1'), 'date' => '2026-12-24', 'is_open' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'note' => 'Nochebuena']);
Db::insert('teams', ['slug' => 'equipo-demo', 'name' => 'Equipo demo', 'description' => 'x']);
Db::insert('team_hosts', ['team_id' => (int) Db::val("SELECT id FROM teams WHERE slug = 'equipo-demo'"), 'host_id' => (int) Db::val("SELECT id FROM hosts WHERE slug = 'demo-lucia-morales'")]);
Db::exec("UPDATE event_types SET team_id = (SELECT id FROM teams WHERE slug = 'equipo-demo') WHERE slug = 'limpieza-dental'");
Db::exec("INSERT INTO event_types (slug,name,single_use,visibility,created_at,updated_at) VALUES ('enlace-unico','Enlace único',1,'secret',NOW(),NOW())");
$exp = C::export();
$json = (string) json_encode($exp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

T::section('Exportación');
T::eq('agenda-premium', $exp['app'], 'identifica la aplicación');
T::eq(1, $exp['version'], 'versión de formato');
foreach (['settings', 'schedules', 'holidays', 'resources', 'teams', 'hosts', 'events', 'custom_fields', 'workflows', 'routing_forms', 'packages', 'coupons', 'webhooks', 'legal'] as $k) {
    T::ok(isset($exp[$k]) && is_array($exp[$k]), "sección $k presente");
}
foreach (['SECRETO-SMTP', 'SECRETO-WA', 'SECRETO-CAPTCHA', 'SECRETO-WEBHOOK-123', (string) Settings::get('cron_token'), (string) \App\Core\Config::get('app_key')] as $secret) {
    T::ok($secret !== '' && !str_contains($json, $secret), 'el JSON no contiene el secreto «' . substr($secret, 0, 12) . '…»');
}
foreach (['smtp_pass', 'wa_api_token', 'captcha_secret', 'cron_token', 'app_key', 'cron_last_run'] as $k) {
    T::ok(!array_key_exists($k, $exp['settings']), "ajuste $k fuera del archivo");
}
T::ok(!str_contains($json, 'ics_token') && !str_contains($json, '"user_id"') && !str_contains($json, 'password_hash'), 'sin tokens de calendario, usuarios ni contraseñas');
T::ok(!str_contains($json, 'enlace-unico'), 'los enlaces de un solo uso no se exportan');
T::ok(!str_contains($json, 'example.test') || !str_contains($json, 'demo.cliente'), 'sin clientes ni citas');
T::eq('Clínica Ñandú', $exp['settings']['business_name'], 'ajustes con acentos intactos');
T::eq('dentista', $exp['settings']['profession'], 'profesión incluida');
$ev = array_values(array_filter($exp['events'], static fn ($e) => $e['slug'] === 'limpieza-dental'))[0];
T::ok($ev['hosts'] && $ev['fields'] && $ev['schedule'] === 'Horario laboral' && $ev['team'] === 'equipo-demo', 'evento con anfitriones, preguntas, horario y equipo');
T::ok(isset($exp['routing_forms'][0]['rules'][0]) && !is_numeric($exp['routing_forms'][0]['rules'][0]['action_value']), 'reglas de enrutamiento con referencias por slug');
T::eq('Nochebuena', $exp['schedules'][0]['overrides'][0]['note'], 'excepciones de horario');
T::ok(count($exp['workflows']) === 9, 'flujos');
T::ok(str_contains($exp['legal']['privacy'], 'Clínica Ñandú') || str_contains($exp['legal']['privacy'], 'Negocio de Prueba'), 'textos legales');

T::section('Importar en otra instalación (ida y vuelta)');
$snapshot = static function (): array {
    $out = [];
    foreach (['event_types', 'event_hosts', 'event_resources', 'custom_fields', 'workflows', 'routing_forms', 'routing_rules', 'packages', 'coupons', 'webhooks', 'schedules', 'schedule_rules', 'schedule_overrides', 'holidays', 'resources', 'teams', 'team_hosts', 'hosts'] as $t) {
        $out[$t] = (int) Db::val("SELECT COUNT(*) FROM `$t`");
    }
    return $out;
};
$src = $snapshot();
$srcJson = $json;
// Reinstalar una base limpia con la misma app y cargar el archivo
T::boot('sb2_config_dest', ['profession' => 'otro']);
$destBefore = $snapshot();
$bookingsBefore = (int) Db::val('SELECT COUNT(*) FROM bookings');
$usersBefore = (int) Db::val('SELECT COUNT(*) FROM users');
$sum = C::import(C::decode($srcJson), false);
T::eq(count($exp['events']), $sum['created']['events'] ?? 0, 'resumen: eventos creados');
T::eq('Clínica Ñandú', Settings::get('business_name'), 'ajuste importado');
T::eq('18', Settings::get('retention_months'), 'otro ajuste importado');
T::eq('', (string) Settings::get('smtp_pass'), 'sin secretos en destino');
T::ok(Settings::get('cron_token') !== '', 'el token de cron propio del destino se conserva');
$again = C::export();
// La vuelta: lo exportado desde el destino coincide con el origen en las secciones de configuración
foreach (['schedules', 'holidays', 'resources', 'teams', 'events', 'custom_fields', 'packages', 'coupons', 'webhooks'] as $k) {
    $norm = static function (array $rows): array {
        usort($rows, static fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        return $rows;
    };
    // El destino ya traía los eventos del preset «otro»: se compara solo lo que viene del origen
    if ($k === 'events') {
        $slugs = array_column($exp['events'], 'slug');
        $got = array_values(array_filter($again['events'], static fn ($e) => in_array($e['slug'], $slugs, true)));
        $want = $exp['events'];
        $strip = static function (array $rows): array {
            foreach ($rows as &$r) {
                usort($r['hosts'], static fn ($a, $b) => strcmp($a['host'], $b['host']));
                $r['hosts'] = array_map(static fn ($h) => $h['host'], $r['hosts']);
                sort($r['resources']);
                unset($r['sort_order']);
            }
            return $rows;
        };
        T::eq($norm($strip($want)), $norm($strip($got)), 'ida y vuelta: eventos idénticos (con anfitriones, preguntas, recursos)');
        continue;
    }
    T::eq($norm($exp[$k]), $norm($again[$k]), "ida y vuelta: $k idénticos");
}
T::eq($bookingsBefore, (int) Db::val('SELECT COUNT(*) FROM bookings'), 'no toca citas');
T::eq($usersBefore, (int) Db::val('SELECT COUNT(*) FROM users'), 'no toca usuarios');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM hosts WHERE user_id IS NOT NULL AND slug = ?', ['demo-lucia-morales']), 'anfitriones importados sin vínculo a usuarios');
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM hosts WHERE slug LIKE 'demo-%'"), 'anfitriones importados');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM webhooks'), 'webhook importado');
T::ok(strlen((string) Db::val('SELECT secret FROM webhooks')) >= 20 && Db::val('SELECT secret FROM webhooks') !== 'SECRETO-WEBHOOK-123', 'webhook con secreto nuevo');
T::ok(count(array_filter($sum['notes'], static fn ($n) => str_contains($n, 'secreta'))) === 1, 'avisa del secreto nuevo del webhook');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM schedules WHERE is_default = 1'), 'un único horario predeterminado');
T::ok((int) Db::val("SELECT COUNT(*) FROM event_types e JOIN event_hosts h ON h.event_type_id = e.id WHERE e.slug = 'limpieza-dental'") === 3, 'anfitriones del evento enlazados por slug');

T::section('Modo fusionar: idempotente y actualiza por slug');
$counts = $snapshot();
$sum2 = C::import(C::decode($srcJson), false);
T::eq($counts, $snapshot(), 'importar dos veces no duplica nada');
T::eq(0, array_sum($sum2['created']) - ($sum2['created']['routing_rules'] ?? 0), 'segunda importación: solo se recrean reglas de enrutamiento');
Db::exec("UPDATE event_types SET name = 'Nombre cambiado' WHERE slug = 'limpieza-dental'");
Db::exec("UPDATE hosts SET name = 'Otro nombre' WHERE slug = 'demo-lucia-morales'");
$sum3 = C::import(C::decode($srcJson), false);
T::eq('Limpieza dental profunda', Db::val("SELECT name FROM event_types WHERE slug = 'limpieza-dental'"), 'fusionar actualiza el evento por slug');
T::eq('Demo · Lucía Morales', Db::val("SELECT name FROM hosts WHERE slug = 'demo-lucia-morales'"), 'fusionar actualiza el anfitrión por slug');
T::ok(($sum3['updated']['events'] ?? 0) >= 5, 'resumen: eventos actualizados');
Db::insert('event_types', ['slug' => 'evento-propio', 'name' => 'Evento propio', 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
C::import(C::decode($srcJson), false);
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM event_types WHERE slug = 'evento-propio'"), 'fusionar conserva lo propio');

T::section('Modo reemplazar');
$ev2 = (int) Db::val("SELECT id FROM event_types WHERE slug = 'evento-propio'");
$evUsed = (int) Db::val("SELECT id FROM event_types WHERE slug = 'valoracion-dental'");
$hostId = (int) Db::val('SELECT id FROM hosts WHERE user_id IS NOT NULL LIMIT 1');
$now = gmdate('Y-m-d H:i:s');
Db::insert('bookings', ['token' => bin2hex(random_bytes(16)), 'event_type_id' => $evUsed, 'host_id' => $hostId, 'starts_at' => '2026-11-02 15:00:00', 'ends_at' => '2026-11-02 15:30:00', 'blocked_start' => '2026-11-02 15:00:00', 'blocked_end' => '2026-11-02 15:30:00', 'duration' => 30, 'guest_name' => 'Cita existente', 'created_at' => $now, 'updated_at' => $now]);
Db::insert('workflows', ['name' => 'Flujo propio', 'trigger_key' => 'booking.created', 'action' => 'email', 'recipient' => 'guest', 'template' => 'x', 'created_at' => $now]);
$small = $exp;
$small['events'] = array_values(array_filter($exp['events'], static fn ($e) => $e['slug'] === 'limpieza-dental'));
$small['workflows'] = [];
$small['routing_forms'] = [];
$small['settings']['business_name'] = 'Nombre tras reemplazar';
$sumR = C::import($small, true);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM event_types WHERE slug = 'evento-propio'"), 'reemplazar elimina eventos propios sin citas');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM event_types WHERE slug = 'valoracion-dental'"), 'conserva el evento que tiene citas');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE guest_name = 'Cita existente'"), 'las citas siguen ahí');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM workflows'), 'reemplazar vacía los flujos');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM routing_forms'), 'reemplazar vacía el enrutamiento');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM coupons WHERE code = ?', ['DEMO10']) - (int) count(array_filter($small['coupons'], static fn ($c) => $c['code'] === 'DEMO10')), 'cupones = los del archivo');
T::eq($usersBefore, (int) Db::val('SELECT COUNT(*) FROM users'), 'usuarios intactos');
T::ok(Db::val('SELECT COUNT(*) FROM hosts WHERE user_id IS NOT NULL') >= 1, 'anfitrión ligado a usuario conservado');
T::eq('Nombre tras reemplazar', Settings::get('business_name'), 'ajustes aplicados');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM schedules WHERE is_default = 1'), 'sigue habiendo horario predeterminado');

T::section('Archivos inválidos: mensajes amables y sin cambios');
$before = $snapshot();
$bn = Settings::get('business_name');
T::throws(static fn () => C::decode('{esto no es json'), 'JSON corrupto', InvalidArgumentException::class, 'no es un JSON válido');
T::throws(static fn () => C::decode(''), 'archivo vacío', InvalidArgumentException::class, 'JSON válido');
T::throws(static fn () => C::decode(str_repeat('a', 5242881)), 'archivo enorme', InvalidArgumentException::class, 'demasiado grande');
T::throws(static fn () => C::import(['hola' => 1], false), 'JSON válido pero ajeno', InvalidArgumentException::class, 'no parece una configuración');
T::throws(static fn () => C::import(['app' => 'otra-app', 'version' => 1, 'settings' => []], false), 'otra aplicación', InvalidArgumentException::class, 'no parece');
T::throws(static fn () => C::import(['app' => 'agenda-premium', 'version' => 99, 'settings' => []], false), 'versión futura', InvalidArgumentException::class, 'versión más nueva');
T::throws(static fn () => C::import(['app' => 'agenda-premium', 'version' => 0, 'settings' => []], true), 'versión cero', InvalidArgumentException::class, 'versión');
T::throws(static fn () => C::import(['app' => 'agenda-premium', 'version' => 1, 'settings' => [], 'events' => 'no-es-lista'], false), 'sección con formato incorrecto', InvalidArgumentException::class, 'formato esperado');
$badEvent = $small;
$badEvent['settings'] = ['business_name' => 'No debe aplicarse'];
$badEvent['events'][] = ['slug' => 'Slug Inválido!', 'name' => 'x'];
T::throws(static fn () => C::import($badEvent, false), 'evento con slug inválido', InvalidArgumentException::class, 'slug');
T::eq($bn, Settings::get('business_name'), 'el error revierte todo (transaccional)');
$badMode = $small;
$badMode['events'][0]['mode'] = 'teletransporte';
T::throws(static fn () => C::import($badMode, true), 'valor fuera de la lista', InvalidArgumentException::class, 'mode');
T::eq($before, $snapshot(), 'ningún error dejó cambios a medias, ni siquiera en modo reemplazar');
$inject = $small;
$inject['settings'] = ['smtp_pass' => 'hack', 'app_key' => 'hack', 'cron_token' => 'hack', 'clave_inexistente' => 'x', 'tagline' => "<script>alert(1)</script>"];
$s = C::import($inject, false);
T::ok(Settings::get('smtp_pass') !== 'hack' && Settings::get('cron_token') !== 'hack' && Settings::get('clave_inexistente') === null, 'secretos y ajustes desconocidos del archivo se ignoran');
T::eq(4, $s['skipped']['settings'], 'y se informan como omitidos');
T::eq("<script>alert(1)</script>", Settings::get('tagline'), 'los textos se guardan tal cual (la salida se escapa con e())');
T::done();
