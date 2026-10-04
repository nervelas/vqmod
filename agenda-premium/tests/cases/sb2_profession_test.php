<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('sb2_prof');

use App\Core\Db;
use App\Core\Settings;
use App\Services\ProfessionPresets;
use App\Services\ProfessionService;

const KEYS = ['medico', 'dentista', 'psicologo', 'nutricionista', 'fisioterapeuta', 'veterinario', 'abogado', 'notario', 'contador', 'arquitecto_ingeniero', 'consultor_coach', 'estetica_spa', 'academia_tutor', 'ventas_reuniones', 'otro'];

T::section('Catálogo de presets');
$all = ProfessionService::all();
T::eq(KEYS, array_keys($all), 'las 15 profesiones están, en orden');
$slugs = [];
$dupes = 0;
foreach (ProfessionPresets::all() as $k => $p) {
    $n = count($p['events']);
    T::ok($n >= 3 && $n <= 5 || $k === 'otro', "$k tiene entre 3 y 5 eventos ($n)");
    foreach ($p['events'] as $e) {
        if (isset($slugs[$e['key']])) {
            $dupes++;
        }
        $slugs[$e['key']] = 1;
    }
    T::ok(isset($all[$k]['icon'], $all[$k]['short'], $all[$k]['events'][0]['name']), "$k expone clave, icono, descripción y vista previa");
    foreach ($p['fields'] as $f) {
        T::ok(!array_filter($f['options'] ?? [], static fn ($o) => str_contains($o, ',')), "$k: las opciones de «{$f['name']}» no llevan comas (el motor separa por coma)");
        if (isset($f['condition'])) {
            $names = array_column($p['fields'], 'name');
            T::ok(in_array($f['condition'][0], $names, true), "$k: la condición de «{$f['name']}» apunta a una pregunta existente");
        }
    }
}
T::eq(0, $dupes, 'las claves de evento son únicas entre profesiones');

T::section('apply() de cada profesión sobre base limpia, dos veces');
$sprite = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/img/icons.svg');
foreach (KEYS as $k) {
    $pdo = Db::pdo();
    foreach (['custom_fields', 'workflows', 'routing_rules', 'routing_forms', 'event_hosts', 'event_resources', 'event_types'] as $tbl) {
        $pdo->exec("DELETE FROM `$tbl`");
    }
    $before = (int) Db::val('SELECT COUNT(*) FROM event_types');
    $r1 = ProfessionService::apply($k, false);
    $nEvents = (int) Db::val('SELECT COUNT(*) FROM event_types');
    T::eq(count(ProfessionPresets::all()[$k]['events']), $nEvents - $before, "$k: eventos creados");
    T::eq($nEvents, $r1['events'], "$k: el resumen coincide con la base");
    T::eq((int) Db::val('SELECT COUNT(*) FROM custom_fields'), $r1['fields'], "$k: preguntas creadas = resumen");
    T::eq(9, $r1['workflows'], "$k: 9 flujos comunes");
    T::eq(0, (int) Db::val('SELECT COUNT(*) FROM event_types WHERE schedule_id IS NULL'), "$k: todos los eventos con horario");
    T::eq($nEvents, (int) Db::val('SELECT COUNT(*) FROM event_hosts'), "$k: anfitrión principal asignado a cada evento");
    $r2 = ProfessionService::apply($k, false);
    T::eq(array_fill_keys(array_keys($r1), 0), $r2, "$k: segunda aplicación no crea nada (idempotente)");
    T::eq($nEvents, (int) Db::val('SELECT COUNT(*) FROM event_types'), "$k: sin eventos duplicados");
    T::eq(9, (int) Db::val('SELECT COUNT(*) FROM workflows'), "$k: sin flujos duplicados");
    T::eq(ProfessionPresets::all()[$k]['terms']['cita'], Settings::get('terms_label'), "$k: terminología aplicada");
    T::eq($k, Settings::get('profession'), "$k: profesión guardada");
    T::ok(str_contains((string) Db::val("SELECT template FROM workflows WHERE trigger_key = 'booking.before_start' AND offset_minutes = 1440"), ProfessionPresets::all()[$k]['terms']['cita']), "$k: plantilla usa el término de la profesión");
    T::ok(!str_contains((string) Db::val('SELECT GROUP_CONCAT(template) FROM workflows'), '[cita]'), "$k: sin marcadores sin resolver");
    T::ok(str_contains($sprite, 'id="i-' . $all[$k]['icon'] . '"'), "$k: icono sugerido existe en el sprite");
    $rf = (int) Db::val('SELECT COUNT(*) FROM routing_forms');
    T::eq(ProfessionPresets::all()[$k]['routing'] ? 1 : 0, $rf, "$k: formulario de enrutamiento según corresponda");
    if ($rf) {
        $badRules = (int) Db::val("SELECT COUNT(*) FROM routing_rules rr WHERE rr.action = 'event' AND NOT EXISTS (SELECT 1 FROM event_types e WHERE e.id = CAST(rr.action_value AS UNSIGNED))");
        T::eq(0, $badRules, "$k: las reglas apuntan a eventos reales");
    }
}

T::section('Flujos y mensajes');
$wf = Db::all('SELECT * FROM workflows ORDER BY sort_order');
$triggers = array_column($wf, 'trigger_key');
foreach (['booking.created', 'booking.approved', 'booking.before_start', 'booking.cancelled', 'booking.after_end', 'booking.no_show'] as $tr) {
    T::ok(in_array($tr, $triggers, true), "hay flujo para $tr");
}
T::eq([1440, 120], array_map('intval', array_column(array_filter($wf, static fn ($w) => $w['trigger_key'] === 'booking.before_start'), 'offset_minutes')), 'recordatorios a 24 h y 2 h');
T::ok(count(array_filter($wf, static fn ($w) => $w['action'] === 'whatsapp')) === 2, 'recordatorios por WhatsApp de un toque');
T::ok(count(array_filter($wf, static fn ($w) => $w['action'] === 'review_request')) === 1, 'solicitud de reseña');

T::section('Datos de ejemplo');
$r = ProfessionService::apply('medico', true);
T::ok($r['hosts'] === 2 && $r['resources'] === 2 && $r['clients'] === 4 && $r['bookings'] === 7 && $r['coupons'] === 1 && $r['packages'] === 1, 'demo crea anfitriones, salas, clientes, citas, cupón y paquete');
$r = ProfessionService::apply('medico', true);
T::eq(0, array_sum($r), 'demo es idempotente');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM hosts WHERE slug LIKE 'demo-%' AND name NOT LIKE 'Demo%'"), 'anfitriones demo marcados');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM clients WHERE tags = 'demo' AND name NOT LIKE 'Demo%'"), 'clientes demo marcados');
T::eq(7, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE notes LIKE '%Demo%'"), 'citas demo marcadas');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE blocked_start > starts_at OR blocked_end < ends_at"), 'bloques de citas coherentes');
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE status = 'completed' AND payment_status = 'paid'"), 'dos citas pagadas');
T::eq(2, (int) Db::val('SELECT COUNT(*) FROM payments'), 'pagos de ejemplo');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE status IN ('pending','confirmed') AND starts_at < UTC_TIMESTAMP()"), 'las citas activas de ejemplo están en el futuro');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM bookings b JOIN bookings c ON b.host_id = c.host_id AND b.id < c.id AND b.status IN ('pending','confirmed') AND c.status IN ('pending','confirmed') AND b.blocked_start < c.blocked_end AND c.blocked_start < b.blocked_end"), 'sin doble agendado en los datos demo');

T::section('Profesión desconocida');
$r = ProfessionService::apply('astronauta', false);
T::eq('otro', Settings::get('profession'), 'una clave desconocida cae en «otro»');
T::done();
