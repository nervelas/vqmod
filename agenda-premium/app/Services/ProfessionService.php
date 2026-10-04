<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;

/**
 * Presets por profesión: terminología, eventos, preguntas, enrutamiento y flujos de mensajes.
 * Los datos viven en ProfessionPresets; aquí se aplican de forma idempotente y transaccional.
 */
final class ProfessionService
{
    private const FALLBACK = 'otro';

    /** @return array<string,array{key:string,name:string,short:string,icon:string,terms:array,events:array,counts:array}> */
    public static function all(): array
    {
        $out = [];
        foreach (ProfessionPresets::all() as $key => $p) {
            $preview = [];
            foreach ($p['events'] as $e) {
                $preview[] = ['name' => $e['name'], 'duration' => (int) ($e['default'] ?? $e['durations'][0]), 'price' => (float) $e['price'], 'mode' => $e['mode']];
            }
            $out[$key] = [
                'key' => $key,
                'name' => $p['name'],
                'short' => $p['short'],
                'icon' => $p['icon'],
                'terms' => $p['terms'],
                'events' => $preview,
                'counts' => ['events' => count($p['events']), 'fields' => count($p['fields']), 'routing' => $p['routing'] ? 1 : 0],
            ];
        }
        return $out;
    }

    /**
     * Aplica un preset. Idempotente: lo que ya existe (por slug, nombre o correo) no se duplica.
     * @return array<string,int> cantidades CREADAS en esta llamada
     */
    public static function apply(string $profession, bool $demo): array
    {
        $presets = ProfessionPresets::all();
        $key = isset($presets[$profession]) ? $profession : self::FALLBACK;
        $preset = $presets[$key];

        return Db::tx(static function () use ($key, $preset, $demo): array {
            $now = Clock::utc();
            $sum = ['events' => 0, 'fields' => 0, 'workflows' => 0, 'routing_forms' => 0, 'routing_rules' => 0,
                'hosts' => 0, 'resources' => 0, 'clients' => 0, 'bookings' => 0, 'coupons' => 0, 'packages' => 0];

            $t = $preset['terms'];
            Settings::setMany([
                'profession' => $key,
                'terms_label' => $t['cita'],
                'terms_label_plural' => $t['plural'],
                'host_label' => $t['host'],
                'client_label' => $t['client'],
            ]);

            $mainHost = Db::val("SELECT id FROM hosts WHERE active = 1 AND slug NOT LIKE 'demo-%' ORDER BY (user_id IS NULL), sort_order, id LIMIT 1");
            $schedule = Db::val('SELECT id FROM schedules ORDER BY is_default DESC, id LIMIT 1');
            $mainHost = $mainHost === null ? null : (int) $mainHost;
            $scheduleId = $schedule === null ? null : (int) $schedule;

            // --- Eventos y preguntas (solo para eventos nuevos: no pisa lo que el negocio ya editó) ---
            $eventIds = [];
            $created = [];
            $sort = (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM event_types');
            foreach ($preset['events'] as $e) {
                $existing = Db::val('SELECT id FROM event_types WHERE slug = ?', [$e['key']]);
                if ($existing !== null) {
                    $eventIds[$e['key']] = (int) $existing;
                    continue;
                }
                $id = Db::insert('event_types', self::eventRow($e, $t, $scheduleId, ++$sort, $now));
                if ($mainHost !== null) {
                    Db::insert('event_hosts', ['event_type_id' => $id, 'host_id' => $mainHost, 'weight' => 1, 'priority' => 1]);
                }
                $eventIds[$e['key']] = $id;
                $created[] = $e['key'];
                $sum['events']++;
            }
            foreach ($preset['fields'] as $i => $f) {
                $targets = $f['events'] ?? array_column($preset['events'], 'key');
                foreach ($targets as $evKey) {
                    if (!in_array($evKey, $created, true)) {
                        continue;
                    }
                    Db::insert('custom_fields', [
                        'event_type_id' => $eventIds[$evKey],
                        'name' => $f['name'],
                        'label' => $f['label'],
                        'type' => $f['type'],
                        'options' => isset($f['options']) ? implode("\n", $f['options']) : null,
                        'help' => $f['help'] ?? null,
                        'required' => (int) ($f['required'] ?? 0),
                        'condition_field' => $f['condition'][0] ?? null,
                        'condition_value' => $f['condition'][1] ?? null,
                        'sort_order' => ($i + 1) * 10,
                        'active' => 1,
                    ]);
                    $sum['fields']++;
                }
            }

            // --- Enrutamiento ---
            if ($preset['routing'] !== null) {
                $r = self::applyRouting($key, $preset['routing'], $eventIds, $now);
                $sum['routing_forms'] += $r[0];
                $sum['routing_rules'] += $r[1];
            }

            // --- Flujos de mensajes comunes ---
            $sum['workflows'] = self::applyWorkflows($t, $now);

            if ($demo) {
                foreach (self::applyDemo($eventIds, $mainHost, $scheduleId, $now) as $k => $n) {
                    $sum[$k] += $n;
                }
            }
            return $sum;
        });
    }

    private static function eventRow(array $e, array $t, ?int $scheduleId, int $sort, string $now): array
    {
        $default = (int) ($e['default'] ?? $e['durations'][0]);
        $hours = (int) ($e['cancel_hours'] ?? 24);
        $approval = (int) ($e['approval'] ?? 0);
        $confirm = $e['confirm'] ?? ($approval
            ? 'Recibimos tu solicitud de [cita]. Te avisaremos por correo en cuanto sea aprobada.'
            : 'Tu [cita] quedó registrada. Te enviamos los detalles por correo; si necesitas cambiarla, usa el enlace que incluye.');
        if ($e['mode'] === 'video_auto' && !isset($e['confirm'])) {
            $confirm .= ' El enlace de la videollamada va en ese mismo correo.';
        }
        $policy = $e['cancel'] ?? ($hours === 0
            ? 'Puedes cancelar o reprogramar tu [cita] en cualquier momento.'
            : 'Puedes cancelar o reprogramar tu [cita] sin costo hasta ' . $hours . ($hours === 1 ? ' hora' : ' horas') . ' antes. Después de ese plazo, la [cita] se considera realizada.');
        $deposit = $e['deposit'] ?? ['none', 0];
        $guests = (int) ($e['guests'] ?? 0);
        return [
            'slug' => $e['key'],
            'name' => $e['name'],
            'description' => $e['description'],
            'kind' => $e['kind'] ?? 'individual',
            'color' => $e['color'],
            'duration_options' => implode(',', $e['durations']),
            'default_duration' => $default,
            'mode' => $e['mode'],
            'min_notice_minutes' => (int) ($e['notice'] ?? 120),
            'slot_interval' => (int) ($e['interval'] ?? ($default <= 20 ? 15 : 30)),
            'buffer_before' => (int) ($e['buffer_before'] ?? 0),
            'buffer_after' => (int) ($e['buffer_after'] ?? 0),
            'travel_minutes' => (int) ($e['travel'] ?? 0),
            'approval' => $approval,
            'price' => $e['price'],
            'deposit_type' => $deposit[0],
            'deposit_value' => $deposit[1],
            'cancel_hours' => $hours,
            'cancel_policy_text' => self::terms($policy, $t),
            'confirm_message' => self::terms($confirm, $t),
            'schedule_id' => $scheduleId,
            'capacity' => (int) ($e['capacity'] ?? 1),
            'allow_guests' => $guests > 0 ? 1 : 0,
            'max_guests' => $guests,
            'sort_order' => $sort,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** Sustituye [cita] / [Cita] por el término de la profesión. */
    private static function terms(string $text, array $t): string
    {
        return str_replace(['[cita]', '[Cita]'], [$t['cita'], mb_strtoupper(mb_substr($t['cita'], 0, 1)) . mb_substr($t['cita'], 1)], $text);
    }

    /** @return array{0:int,1:int} formularios y reglas creados */
    private static function applyRouting(string $profKey, array $r, array $eventIds, string $now): array
    {
        $slug = 'orientacion-' . str_replace('_', '-', $profKey);
        if (Db::val('SELECT id FROM routing_forms WHERE slug = ?', [$slug]) !== null) {
            return [0, 0];
        }
        $names = [
            'medico' => 'Orientación médica', 'dentista' => 'Orientación dental', 'psicologo' => 'Orientación terapéutica',
            'nutricionista' => 'Orientación nutricional', 'fisioterapeuta' => 'Orientación de fisioterapia', 'veterinario' => 'Orientación veterinaria',
            'abogado' => 'Orientación legal', 'notario' => 'Orientación notarial', 'contador' => 'Orientación contable',
            'arquitecto_ingeniero' => 'Orientación de proyectos', 'consultor_coach' => 'Orientación de servicios', 'estetica_spa' => 'Orientación de servicios',
            'academia_tutor' => 'Orientación de clases', 'ventas_reuniones' => 'Orientación comercial',
        ];
        $formId = Db::insert('routing_forms', [
            'slug' => $slug,
            'name' => $names[$profKey] ?? 'Orientación',
            'description' => 'Responde una pregunta y te llevamos al tipo de cita que más te conviene.',
            'questions' => json_encode([[
                'id' => $r['key'], 'label' => $r['label'], 'type' => 'select', 'required' => true, 'options' => array_keys($r['options']),
            ]], JSON_UNESCAPED_UNICODE),
            'default_action' => 'event',
            'default_value' => (string) $eventIds[$r['default']],
            'default_message' => null,
            'active' => 1,
            'created_at' => $now,
        ]);
        $rules = 0;
        $priority = 0;
        foreach ($r['options'] as $option => $evKey) {
            Db::insert('routing_rules', [
                'form_id' => $formId,
                'priority' => ($priority += 10),
                'match_mode' => 'all',
                'conditions' => json_encode([['question' => $r['key'], 'op' => 'eq', 'value' => $option]], JSON_UNESCAPED_UNICODE),
                'action' => 'event',
                'action_value' => (string) $eventIds[$evKey],
                'message' => null,
                'active' => 1,
            ]);
            $rules++;
        }
        return [1, $rules];
    }

    /**
     * Flujos comunes a todas las profesiones. Un flujo se reconoce por disparador, acción, destinatario y desfase:
     * si ya existe y sigue con el texto de fábrica de otra profesión, se actualiza con la terminología nueva; si el negocio lo editó, no se toca.
     * Devuelve cuántos se crearon.
     */
    private static function applyWorkflows(array $t, string $now): int
    {
        $n = 0;
        foreach (self::workflowDefs() as $i => $w) {
            $row = [
                'name' => self::terms($w['name'], $t),
                'subject' => isset($w['subject']) ? self::terms($w['subject'], $t) : null,
                'template' => self::terms($w['text'], $t),
            ];
            $found = Db::one(
                'SELECT id, name, subject, template FROM workflows WHERE trigger_key = ? AND offset_minutes = ? AND action = ? AND recipient = ? AND event_type_id IS NULL ORDER BY id LIMIT 1',
                [$w['trigger'], $w['offset'] ?? 0, $w['action'], $w['to']]
            );
            if ($found === null) {
                Db::insert('workflows', $row + [
                    'trigger_key' => $w['trigger'], 'offset_minutes' => $w['offset'] ?? 0, 'event_type_id' => null, 'action' => $w['action'],
                    'recipient' => $w['to'], 'action_value' => null, 'active' => 1, 'sort_order' => ($i + 1) * 10, 'created_at' => $now,
                ]);
                $n++;
            } elseif (self::isFactoryText($w, $found)) {
                Db::update('workflows', $row, 'id = ?', [$found['id']]);
            }
        }
        return $n;
    }

    /** ¿El flujo conserva el texto de fábrica (con la terminología de alguna profesión)? */
    private static function isFactoryText(array $def, array $row): bool
    {
        foreach (ProfessionPresets::all() as $p) {
            if ($row['template'] === self::terms($def['text'], $p['terms']) && $row['name'] === self::terms($def['name'], $p['terms'])
                && $row['subject'] === (isset($def['subject']) ? self::terms($def['subject'], $p['terms']) : null)) {
                return true;
            }
        }
        return false;
    }

    /** Plantillas: variables {nombre} {evento} {fecha} {hora} {anfitrion} {enlace} {direccion} {zona}. */
    private static function workflowDefs(): array
    {
        return [
            ['name' => 'Confirmación de [cita] al invitado', 'trigger' => 'booking.created', 'action' => 'email', 'to' => 'guest',
                'subject' => 'Registramos tu [cita]: {evento}',
                'text' => "Hola {nombre},\n\nregistramos tu [cita] de {evento} con {anfitrion}.\n\nFecha: {fecha}\nHora: {hora} ({zona})\nLugar: {direccion}\n\nSi necesitas cambiarla o cancelarla, hazlo desde este enlace: {enlace}\n\nSi tu [cita] requiere aprobación, te avisaremos en cuanto sea confirmada."],
            ['name' => 'Aviso al anfitrión de nueva [cita]', 'trigger' => 'booking.created', 'action' => 'email', 'to' => 'host',
                'subject' => 'Nueva [cita]: {nombre} · {fecha}, {hora}',
                'text' => "Hola {anfitrion},\n\n{nombre} agendó {evento} para el {fecha} a las {hora} ({zona}).\n\nPuedes ver el detalle aquí: {enlace}"],
            ['name' => 'Aprobación de [cita]', 'trigger' => 'booking.approved', 'action' => 'email', 'to' => 'guest',
                'subject' => 'Tu [cita] fue aprobada: {evento}',
                'text' => "Hola {nombre},\n\ntu [cita] de {evento} fue aprobada.\n\nFecha: {fecha}\nHora: {hora} ({zona})\nLugar: {direccion}\n\nAdministra tu [cita] aquí: {enlace}"],
            ['name' => 'Recordatorio por WhatsApp 24 horas antes', 'trigger' => 'booking.before_start', 'offset' => 1440, 'action' => 'whatsapp', 'to' => 'guest',
                'text' => "Hola {nombre}, te recordamos tu [cita] de {evento} mañana, {fecha}, a las {hora} ({zona}) con {anfitrion}.\nLugar: {direccion}\nSi no puedes asistir, avísanos desde aquí: {enlace}"],
            ['name' => 'Recordatorio por WhatsApp 2 horas antes', 'trigger' => 'booking.before_start', 'offset' => 120, 'action' => 'whatsapp', 'to' => 'guest',
                'text' => "Hola {nombre}, tu [cita] de {evento} es hoy a las {hora} ({zona}). Te esperamos en {direccion}.\nDetalles: {enlace}"],
            ['name' => 'Aviso de cancelación al invitado', 'trigger' => 'booking.cancelled', 'action' => 'email', 'to' => 'guest',
                'subject' => 'Tu [cita] fue cancelada: {evento}',
                'text' => "Hola {nombre},\n\nconfirmamos que tu [cita] de {evento} del {fecha} a las {hora} fue cancelada.\n\nCuando quieras volver a reservar, entra aquí: {enlace}"],
            ['name' => 'Aviso de cancelación al anfitrión', 'trigger' => 'booking.cancelled', 'action' => 'email', 'to' => 'host',
                'subject' => 'Se canceló una [cita]: {nombre} · {fecha}, {hora}',
                'text' => "Hola {anfitrion},\n\n{nombre} canceló {evento} del {fecha} a las {hora} ({zona}). Ese espacio quedó libre en tu agenda."],
            ['name' => 'Solicitud de reseña tras la [cita]', 'trigger' => 'booking.after_end', 'offset' => 120, 'action' => 'review_request', 'to' => 'guest',
                'subject' => '¿Cómo te fue en tu [cita]?',
                'text' => "Hola {nombre},\n\ngracias por confiar en nosotros. Tu opinión nos ayuda a mejorar y a que otras personas nos conozcan.\n\nCuéntanos cómo te fue (toma menos de un minuto): {enlace}"],
            ['name' => 'Mensaje tras inasistencia', 'trigger' => 'booking.no_show', 'action' => 'email', 'to' => 'guest',
                'subject' => 'Te extrañamos en tu [cita]',
                'text' => "Hola {nombre},\n\nnotamos que no pudiste asistir a tu [cita] de {evento} del {fecha}. Sabemos que pasan imprevistos.\n\nSi quieres retomarla, reserva un nuevo horario aquí: {enlace}"],
        ];
    }

    /** Datos de ejemplo claramente marcados «Demo». @return array<string,int> */
    private static function applyDemo(array $eventIds, ?int $mainHost, ?int $scheduleId, string $now): array
    {
        $sum = ['hosts' => 0, 'resources' => 0, 'clients' => 0, 'bookings' => 0, 'coupons' => 0, 'packages' => 0];
        $tz = Settings::tz();

        // Anfitriones
        $demoHosts = [];
        foreach ([['demo-lucia-morales', 'Demo · Lucía Morales', '#A0646E'], ['demo-carlos-pineda', 'Demo · Carlos Pineda', '#5B7C99']] as $i => [$slug, $name, $color]) {
            $id = Db::val('SELECT id FROM hosts WHERE slug = ?', [$slug]);
            if ($id === null) {
                $id = Db::insert('hosts', [
                    'name' => $name, 'slug' => $slug, 'title' => 'Anfitrión de ejemplo (Demo)',
                    'bio' => 'Perfil de ejemplo para que veas cómo luce tu equipo. Puedes editarlo o eliminarlo.',
                    'timezone' => $tz, 'color' => $color, 'ics_token' => Str::token(16), 'schedule_id' => $scheduleId,
                    'public_profile' => 0, 'active' => 1, 'sort_order' => 900 + $i, 'created_at' => $now,
                ]);
                $sum['hosts']++;
            }
            $demoHosts[] = (int) $id;
        }

        // Recursos
        $resourceIds = [];
        foreach (['Demo · Sala 1', 'Demo · Sala 2'] as $name) {
            $id = Db::val('SELECT id FROM resources WHERE name = ?', [$name]);
            if ($id === null) {
                $id = Db::insert('resources', ['name' => $name, 'description' => 'Espacio de ejemplo (Demo)', 'capacity' => 1, 'active' => 1]);
                $sum['resources']++;
            }
            $resourceIds[] = (int) $id;
        }

        // Los anfitriones y salas de ejemplo atienden los eventos presentes en este preset
        foreach ($eventIds as $evId) {
            foreach ($demoHosts as $h) {
                Db::q('INSERT IGNORE INTO event_hosts (event_type_id, host_id, weight, priority) VALUES (?, ?, 1, 2)', [$evId, $h]);
            }
            if ((string) Db::val('SELECT mode FROM event_types WHERE id = ?', [$evId]) === 'in_person') {
                foreach ($resourceIds as $rid) {
                    Db::q('INSERT IGNORE INTO event_resources (event_type_id, resource_id) VALUES (?, ?)', [$evId, $rid]);
                }
            }
        }

        // Clientes
        $people = [['María López', '55501001'], ['José Ramírez', '55501002'], ['Ana Sofía Castillo', '55501003'], ['Luis Fernando Girón', '55501004']];
        $clientIds = [];
        foreach ($people as $i => [$name, $phone]) {
            $email = 'demo.cliente' . ($i + 1) . '@example.test';
            $id = Db::val('SELECT id FROM clients WHERE email = ?', [$email]);
            if ($id === null) {
                $id = Db::insert('clients', [
                    'name' => 'Demo · ' . $name, 'email' => $email, 'phone' => '502' . $phone, 'tags' => 'demo',
                    'source' => 'Datos de ejemplo', 'timezone' => $tz, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $sum['clients']++;
            }
            $clientIds[] = (int) $id;
        }

        // Citas de ejemplo: [días desde hoy, estado, evento (índice), anfitrión (índice 0=principal), cliente]
        $keys = array_keys($eventIds);
        $hostPool = array_values(array_filter([$mainHost, $demoHosts[0], $demoHosts[1]], static fn ($h) => $h !== null));
        $plan = [[-14, 'completed', 0, 0, 0], [-9, 'completed', 1, 1, 1], [-4, 'no_show', 0, 2, 2], [-2, 'cancelled', 1, 1, 3], [2, 'confirmed', 0, 0, 0], [4, 'confirmed', 1, 1, 1], [6, 'pending', 0, 2, 3]];
        foreach ($plan as $n => [$days, $status, $evIdx, $hostIdx, $cliIdx]) {
            $token = substr(hash('sha256', 'demo-booking-' . $n), 0, 32);
            if (Db::val('SELECT id FROM bookings WHERE token = ?', [$token]) !== null) {
                continue;
            }
            $ev = Db::one('SELECT * FROM event_types WHERE id = ?', [$eventIds[$keys[min($evIdx, count($keys) - 1)]]]);
            $hostId = $hostPool[min($hostIdx, count($hostPool) - 1)] ?? null;
            if ($ev === null || $hostId === null) {
                continue;
            }
            $sum['bookings'] += self::demoBooking($n, $token, $days, $status, $ev, $hostId, $clientIds[$cliIdx], $people[$cliIdx][1], $tz, $now);
        }

        // Cupón y paquete
        if (Db::val("SELECT id FROM coupons WHERE code = 'DEMO10'") === null) {
            Db::insert('coupons', ['code' => 'DEMO10', 'type' => 'percent', 'value' => 10, 'max_uses' => 20, 'active' => 1]);
            $sum['coupons']++;
        }
        $pkgName = 'Demo · Paquete de 5 sesiones';
        if (Db::val('SELECT id FROM packages WHERE name = ?', [$pkgName]) === null) {
            $firstEvent = Db::one('SELECT id, price FROM event_types WHERE id = ?', [$eventIds[$keys[0]]]);
            $base = $firstEvent && (float) $firstEvent['price'] > 0 ? (float) $firstEvent['price'] : 100.0;
            Db::insert('packages', [
                'name' => $pkgName, 'description' => 'Paquete de ejemplo con 10 % de descuento (Demo)', 'sessions' => 5,
                'price' => round($base * 5 * 0.9, 2), 'validity_days' => 180, 'event_type_id' => $firstEvent ? (int) $firstEvent['id'] : null, 'active' => 1,
            ]);
            $sum['packages']++;
        }
        return $sum;
    }

    private static function demoBooking(int $n, string $token, int $days, string $status, array $ev, int $hostId, int $clientId, string $phone, string $tz, string $now): int
    {
        // Día laborable más cercano en la dirección indicada, a las 10:00 hora local del negocio
        $ts = Clock::now() + $days * 86400;
        $dow = (int) (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone($tz))->format('N');
        if ($dow >= 6) {
            $ts += ($days >= 0 ? (8 - $dow) : -($dow - 5)) * 86400;
        }
        $local = (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone($tz))->format('Y-m-d') . ' 10:00:00';
        $start = Tz::localToTs($local, $tz);
        $dur = (int) $ev['default_duration'];
        $end = $start + $dur * 60;
        $before = (int) $ev['buffer_before'] + (int) $ev['travel_minutes'];
        $after = (int) $ev['buffer_after'] + (int) $ev['travel_minutes'];
        $client = (array) Db::one('SELECT name, email FROM clients WHERE id = ?', [$clientId]);
        $price = (float) $ev['price'];
        $paid = $status === 'completed' && $price > 0;
        $bookingId = Db::insert('bookings', [
            'token' => $token, 'event_type_id' => (int) $ev['id'], 'host_id' => $hostId, 'client_id' => $clientId,
            'starts_at' => Tz::fromTs($start), 'ends_at' => Tz::fromTs($end),
            'blocked_start' => Tz::fromTs($start - $before * 60), 'blocked_end' => Tz::fromTs($end + $after * 60),
            'duration' => $dur, 'status' => $status,
            'guest_name' => $client['name'], 'guest_email' => $client['email'], 'guest_phone' => '502' . $phone, 'guest_timezone' => $tz,
            'mode' => $ev['mode'], 'location' => $ev['location'],
            'price' => $price, 'total' => $price, 'paid_amount' => $paid ? $price : 0, 'payment_status' => $paid ? 'paid' : 'none',
            'notes' => 'Cita de ejemplo (Demo).',
            'cancel_reason' => $status === 'cancelled' ? 'Cancelada por la persona (Demo)' : null,
            'cancelled_by' => $status === 'cancelled' ? 'guest' : null,
            'cancelled_at' => $status === 'cancelled' ? Tz::fromTs($start - 86400) : null,
            'created_via' => 'admin',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        Db::insert('booking_hosts', ['booking_id' => $bookingId, 'host_id' => $hostId]);
        Db::insert('booking_history', ['booking_id' => $bookingId, 'action' => 'created', 'detail' => 'Cita de ejemplo (Demo)', 'actor' => 'sistema', 'created_at' => $now]);
        if ($paid) {
            Db::insert('payments', ['booking_id' => $bookingId, 'client_id' => $clientId, 'amount' => $price, 'method' => 'cash', 'status' => 'verified', 'note' => 'Pago de ejemplo (Demo)', 'created_at' => $now]);
        }
        if ($status === 'no_show') {
            Db::exec('UPDATE clients SET noshow_count = noshow_count + 1 WHERE id = ?', [$clientId]);
        }
        return 1;
    }
}
