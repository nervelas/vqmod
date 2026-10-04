<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('sb1_routing', ['profession' => 'medico', 'demo' => true]);

use App\Core\Db;
use App\Services\RoutingService;

$questions = json_encode([
    ['id' => 'servicio', 'label' => '¿Qué necesitas?', 'type' => 'select', 'options' => ['Consulta', 'Control', 'Urgencia'], 'required' => true],
    ['id' => 'edad', 'label' => 'Edad', 'type' => 'number', 'required' => false],
    ['id' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'required' => false],
    ['id' => 'sintomas', 'label' => 'Síntomas', 'type' => 'checkbox', 'options' => ['Fiebre', 'Tos', 'Dolor'], 'required' => false],
], JSON_UNESCAPED_UNICODE);
$fid = Db::insert('routing_forms', ['slug' => 'triaje', 'name' => 'Triaje', 'questions' => $questions, 'default_action' => 'message', 'default_value' => null, 'default_message' => 'Escríbenos y te orientamos.', 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
$slug = (string) Db::val('SELECT slug FROM event_types WHERE id = 1');
$host = (string) Db::val('SELECT slug FROM hosts ORDER BY id LIMIT 1');
$rule = static fn (int $prio, string $mode, array $conds, string $action, string $val, ?string $msg = null, int $active = 1): int =>
    Db::insert('routing_rules', ['form_id' => $GLOBALS['fid'], 'priority' => $prio, 'match_mode' => $mode, 'conditions' => json_encode($conds, JSON_UNESCAPED_UNICODE), 'action' => $action, 'action_value' => $val, 'message' => $msg, 'active' => $active]);
$GLOBALS['fid'] = $fid;
$form = static fn (): array => (array) Db::one('SELECT * FROM routing_forms WHERE id = ?', [$GLOBALS['fid']]);

// Prioridad 5 (después en el orden de inserción) debe evaluarse antes que la de prioridad 10
$r10 = $rule(10, 'all', [['question' => 'servicio', 'op' => 'eq', 'value' => 'Consulta']], 'event', $slug);
$r5 = $rule(5, 'all', [['question' => 'servicio', 'op' => 'eq', 'value' => 'Consulta'], ['question' => 'edad', 'op' => 'gt', 'value' => '64']], 'host', $host);
$r20 = $rule(20, 'any', [['question' => 'servicio', 'op' => 'eq', 'value' => 'Urgencia'], ['question' => 'sintomas', 'op' => 'contains', 'value' => 'dolor']], 'message', '', 'Si es urgente, llama al 1234-5678.');
$r30 = $rule(30, 'all', [['question' => 'ciudad', 'op' => 'in', 'value' => 'Antigua, Escuintla']], 'url', 'https://example.test/sucursal');
$r40 = $rule(40, 'all', [['question' => 'ciudad', 'op' => 'neq', 'value' => 'Guatemala'], ['question' => 'edad', 'op' => 'lt', 'value' => '18']], 'url', 'javascript:alert(1)');
$rOff = $rule(1, 'all', [['question' => 'servicio', 'op' => 'eq', 'value' => 'Control']], 'event', $slug, null, 0);
$rMiss = $rule(2, 'all', [['question' => 'servicio', 'op' => 'eq', 'value' => 'Control']], 'event', 'no-existe');

T::section('Reglas por prioridad');
$r = RoutingService::run($form(), ['servicio' => 'Consulta', 'edad' => '70'], '203.0.113.77');
T::ok($r['rule_id'] === $r5 && $r['action'] === 'host' && $r['redirect'] === url('/h/' . $host), 'gana la regla de menor prioridad numérica');
T::ok(str_contains($r['reason'], 'Respondió «Consulta» a «¿Qué necesitas?»') && str_contains($r['reason'], 'regla ' . $r5), 'razón en español: ' . $r['reason']);
$r = RoutingService::run($form(), ['servicio' => 'Consulta', 'edad' => '30'], '203.0.113.77');
T::ok($r['rule_id'] === $r10 && $r['action'] === 'event' && $r['target'] === '/e/' . $slug && $r['redirect'] === url('/e/' . $slug), 'cae a la siguiente regla → /e/{slug}');
$r = RoutingService::run($form(), ['servicio' => 'Control'], '203.0.113.77');
T::ok($r['rule_id'] === null && $r['action'] === 'message' && $r['message'] === 'Escríbenos y te orientamos.' && str_contains($r['reason'], 'por defecto'), 'regla inactiva y regla con destino inexistente se omiten → acción por defecto');
$r = RoutingService::run($form(), ['servicio' => 'Control', 'sintomas' => ['Fiebre', 'Dolor']], '203.0.113.77');
T::ok($r['rule_id'] === $r20 && $r['action'] === 'message' && $r['redirect'] === null && str_contains((string) $r['message'], 'llama'), 'modo any con "contiene" sobre casillas múltiples → mensaje');
$r = RoutingService::run($form(), ['servicio' => 'Control', 'ciudad' => ' antigua '], '203.0.113.77');
T::ok($r['rule_id'] === $r30 && $r['redirect'] === 'https://example.test/sucursal', 'operador "en lista" sin importar mayúsculas ni espacios; acción url');
$r = RoutingService::run($form(), ['servicio' => 'Control', 'ciudad' => 'Xela', 'edad' => '15'], '203.0.113.77');
T::ok($r['rule_id'] === null && $r['redirect'] === null, 'una regla con url javascript: nunca redirige (se omite)');
T::throws(fn () => RoutingService::run($form(), ['servicio' => 'Consulta', 'edad' => 'abc'], '1.2.3.4'), 'edad no numérica rechazada', \InvalidArgumentException::class, 'número');
$r = RoutingService::run($form(), ['servicio' => 'Consulta', 'edad' => '30'], '2001:db8:abcd:12::1');

T::section('Validación de respuestas');
T::throws(fn () => RoutingService::run($form(), [], '1.2.3.4'), 'respuesta obligatoria', \InvalidArgumentException::class, 'Falta responder');
T::throws(fn () => RoutingService::run($form(), ['servicio' => 'Otra'], '1.2.3.4'), 'opción fuera de la lista', \InvalidArgumentException::class, 'no es válida');
T::throws(fn () => RoutingService::run($form(), ['servicio' => ['Consulta', 'Control']], '1.2.3.4'), 'varias respuestas en pregunta simple', \InvalidArgumentException::class);
T::throws(fn () => RoutingService::run($form(), ['servicio' => 'Consulta', 'sintomas' => ['Hambre']], '1.2.3.4'), 'casilla con opción inventada', \InvalidArgumentException::class);
T::throws(fn () => RoutingService::run($form(), ['servicio' => 'Consulta', 'ciudad' => str_repeat('x', 600)], '1.2.3.4'), 'respuesta demasiado larga', \InvalidArgumentException::class, 'larga');
$before = (int) Db::val('SELECT COUNT(*) FROM routing_logs');
RoutingService::run($form(), ['servicio' => 'Urgencia', 'campo_inventado' => '<script>'], '1.2.3.4');
$last = Db::one('SELECT * FROM routing_logs ORDER BY id DESC LIMIT 1');
T::ok(!str_contains($last['answers'], 'inventado'), 'las claves desconocidas se descartan');
T::eq($before + 1, (int) Db::val('SELECT COUNT(*) FROM routing_logs'), 'las respuestas inválidas no se registran, las válidas sí');

T::section('Bitácora y estadísticas');
$logs = RoutingService::logs($fid, 3);
T::ok(count($logs) === 3 && is_array($logs[0]['answers']) && $logs[0]['id'] > $logs[1]['id'], 'logs más recientes primero con respuestas decodificadas');
T::eq('1.2.3.0', Db::val("SELECT ip_trunc FROM routing_logs WHERE ip_trunc LIKE '1.2.3%' LIMIT 1"), 'la IP se guarda truncada');
T::ok(Db::val("SELECT ip_trunc FROM routing_logs WHERE ip_trunc LIKE '2001%' LIMIT 1") === '2001:db8:abcd::', 'IPv6 truncada a /48');
$s = RoutingService::stats($fid);
$by = array_column($s['rules'], null, 'rule_id');
T::eq((int) Db::val('SELECT COUNT(*) FROM routing_logs WHERE form_id = ?', [$fid]), $s['total'], 'total de enrutamientos');
T::ok($by[$r5]['count'] === 1 && $by[$r10]['count'] === 2 && $by[$r20]['count'] === 2 && $by[$r30]['count'] === 1 && $by[$r40]['count'] === 0, 'conteo por regla');
T::ok(end($s['rules'])['rule_id'] === null && end($s['rules'])['action'] === 'default' && end($s['rules'])['count'] === 2, 'la acción por defecto aparece al final con su conteo');
$sum = array_sum(array_column($s['rules'], 'pct'));
T::ok(abs($sum - 100.0) < 0.5, 'los porcentajes suman ~100 (' . $sum . ')');
T::eq(0, RoutingService::stats(99999)['total'], 'formulario sin registros → total 0');

T::section('Equipo');
$tid = Db::insert('teams', ['name' => 'Equipo Pediatría', 'slug' => 'pediatria', 'active' => 1]);
Db::exec('UPDATE event_types SET team_id = ? WHERE id IN (1, 2)', [$tid]);
$rt = $rule(3, 'all', [['question' => 'servicio', 'op' => 'eq', 'value' => 'Urgencia']], 'team', 'pediatria');
$r = RoutingService::run($form(), ['servicio' => 'Urgencia'], '1.2.3.4');
T::ok($r['rule_id'] === $rt && $r['action'] === 'team' && count($r['events']) === 2 && $r['redirect'] === null && str_contains($r['events'][0]['url'], '/e/'), 'acción equipo devuelve los eventos del equipo');
T::done();
