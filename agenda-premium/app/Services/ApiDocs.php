<?php
declare(strict_types=1);

namespace App\Services;

use App\Controllers\Api\BookingsController;
use App\Controllers\Api\ClientsController;
use App\Controllers\Api\EventsController;
use App\Core\Router;

/**
 * Registro central de la API v1: de aquí salen las rutas (app/routes/api.php) y la documentación pública (/api-docs).
 * Cada endpoint: id, grupo, método, ruta del router, alcance, controlador, resumen, descripción, parámetros, cuerpo y respuesta de ejemplo.
 */
final class ApiDocs
{
    public const BASE = '/api/v1';

    /** Registra en el router todos los endpoints documentados. */
    public static function routes(Router $r): void
    {
        foreach (self::endpoints() as $e) {
            $r->add($e['method'], $e['route'], $e['handler'], ['api' => $e['scope']]);
        }
    }

    /** ¿La ruta existe para algún método? (distingue 405 de 404). */
    public static function knownPath(string $path): bool
    {
        foreach (self::endpoints() as $e) {
            if (preg_match('#^' . preg_replace_callback('/\{([a-z_]+)(?::([^}]+))?\}/i', static fn (array $m): string => '(?:' . ($m[2] ?? '[^/]+') . ')', $e['route']) . '$#', $path)) {
                return true;
            }
        }
        return false;
    }

    /** Ruta tal como se muestra al usuario: /api/v1/events/{id}. */
    public static function displayPath(array $e): string
    {
        return (string) preg_replace('/\{([a-z_]+):[^}]+\}/i', '{$1}', $e['route']);
    }

    /** Ejemplo curl de un endpoint (con la URL base real del sitio). */
    public static function curl(array $e, string $baseUrl): string
    {
        $path = (string) preg_replace('/\{id\}/', '12', self::displayPath($e));
        $url = rtrim($baseUrl, '/') . $path;
        $query = array_filter($e['params'], static fn (array $p): bool => $p['in'] === 'query' && !empty($p['example']));
        if ($query && $e['method'] === 'GET') {
            $pairs = [];
            foreach (array_slice($query, 0, 4) as $p) {
                $pairs[] = $p['name'] . '=' . rawurlencode((string) $p['example']);
            }
            $url .= '?' . implode('&', $pairs);
        }
        $lines = ['curl ' . ($e['method'] === 'GET' ? '' : '-X ' . $e['method'] . ' ') . "'" . $url . "'", "  -H 'Authorization: Bearer TU_CLAVE_DE_API'"];
        if (isset($e['body'])) {
            $lines[] = "  -H 'Content-Type: application/json'";
            $lines[] = "  -d '" . self::json($e['body'], false) . "'";
        }
        return implode(" \\\n", $lines);
    }

    public static function json($value, bool $pretty = true): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0));
    }

    /** @return array<int,array{code:string,status:int,meaning:string}> */
    public static function errorCodes(): array
    {
        return [
            ['code' => 'unauthorized', 'status' => 401, 'meaning' => 'Falta la clave de API, no es válida o fue revocada.'],
            ['code' => 'forbidden', 'status' => 403, 'meaning' => 'La clave solo tiene permiso de lectura y la operación escribe datos.'],
            ['code' => 'rate_limited', 'status' => 429, 'meaning' => 'Superaste el límite de solicitudes por minuto.'],
            ['code' => 'bad_request', 'status' => 400, 'meaning' => 'Un parámetro de la URL no es válido (fecha, número, filtro).'],
            ['code' => 'invalid_json', 'status' => 400, 'meaning' => 'El cuerpo no es un objeto JSON válido.'],
            ['code' => 'unsupported_media_type', 'status' => 415, 'meaning' => 'Falta la cabecera Content-Type: application/json.'],
            ['code' => 'not_found', 'status' => 404, 'meaning' => 'El recurso no existe.'],
            ['code' => 'method_not_allowed', 'status' => 405, 'meaning' => 'La ruta existe, pero no con ese método HTTP.'],
            ['code' => 'validation', 'status' => 422, 'meaning' => 'Falta un dato o alguno no es válido (nombre, correo, teléfono, duración, respuestas).'],
            ['code' => 'slot_unavailable', 'status' => 409, 'meaning' => 'Ese horario ya no está libre. Consulta /availability y elige otro.'],
            ['code' => 'closed', 'status' => 409, 'meaning' => 'El evento no está disponible para reservar (pausado, caducado o ya usado).'],
            ['code' => 'policy', 'status' => 409, 'meaning' => 'La política del evento no permite la operación (por ejemplo, cancelar una cita que ya no está activa).'],
            ['code' => 'blocked', 'status' => 403, 'meaning' => 'La persona tiene bloqueadas las reservas en línea.'],
            ['code' => 'payment', 'status' => 402, 'meaning' => 'La reserva requiere un pago que no se pudo validar.'],
            ['code' => 'server_error', 'status' => 500, 'meaning' => 'Fallo interno. Quedó registrado; reintenta en unos minutos.'],
        ];
    }

    /** Eventos de webhook (disparadores de las citas). */
    public static function webhookEvents(): array
    {
        return [
            'booking.created' => 'Se creó una cita (desde la página pública, el panel o la API).',
            'booking.approved' => 'Se aprobó una cita que esperaba aprobación.',
            'booking.rescheduled' => 'Se cambió la fecha u hora de una cita.',
            'booking.cancelled' => 'Se canceló una cita.',
            'booking.completed' => 'Se marcó una cita como realizada.',
            'booking.no_show' => 'Se marcó que la persona no asistió.',
        ];
    }

    public static function webhookExample(): array
    {
        return [
            'id' => '9f2c41d0b7a84e3c8c1d5e6f70a1b2c3',
            'event' => 'booking.created',
            'created_at' => '2026-10-05T15:02:11Z',
            'data' => [
                'booking' => ['id' => 318, 'status' => 'confirmed', 'starts_at' => '2026-10-07T15:00:00Z', 'ends_at' => '2026-10-07T15:30:00Z', 'duration_minutes' => 30, 'guest_timezone' => 'America/Guatemala', 'mode' => 'in_person', 'total' => 300.0, 'payment_status' => 'none', 'created_via' => 'public'],
                'client' => ['id' => 87, 'name' => 'María López', 'email' => 'maria@example.com', 'phone' => '50255551234'],
                'event' => ['id' => 4, 'name' => 'Consulta médica general', 'slug' => 'consulta-medica-general', 'kind' => 'individual'],
                'host' => ['id' => 1, 'name' => 'Dra. Ana Morales', 'email' => 'ana@example.com'],
                'answers' => [['label' => '¿Es su primera visita?', 'value' => 'Sí']],
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function endpoints(): array
    {
        $page = [
            ['name' => 'limit', 'in' => 'query', 'type' => 'entero', 'required' => false, 'description' => 'Cuántos resultados devolver (1 a 100). Por defecto 25.', 'example' => '25'],
            ['name' => 'offset', 'in' => 'query', 'type' => 'entero', 'required' => false, 'description' => 'Cuántos resultados saltar (para pasar a la página siguiente). Por defecto 0.', 'example' => '0'],
        ];
        $meta = ['request_id' => 'a1b2c3d4e5f6'];
        $paged = $meta + ['total' => 1, 'count' => 1, 'limit' => 25, 'offset' => 0, 'has_more' => false];
        $event = [
            'id' => 4, 'slug' => 'consulta-medica-general', 'name' => 'Consulta médica general', 'description' => 'Evaluación de síntomas, diagnóstico y tratamiento.',
            'kind' => 'individual', 'mode' => 'in_person', 'location' => null, 'color' => '#C9A050', 'active' => true, 'visibility' => 'public',
            'durations' => [30, 45], 'default_duration' => 30, 'price' => 300.0, 'currency' => 'GTQ', 'deposit' => ['type' => 'none', 'value' => 0.0],
            'requires_approval' => false, 'capacity' => 1, 'min_notice_minutes' => 120, 'max_advance_days' => 60, 'cancel_hours' => 12,
            'cancel_policy' => 'Puedes cancelar o reprogramar tu consulta sin costo hasta 12 horas antes.', 'allow_guests' => false, 'max_guests' => 0,
            'booking_url' => 'https://tusitio.com/e/consulta-medica-general',
            'hosts' => [['id' => 1, 'name' => 'Dra. Ana Morales', 'slug' => 'ana-morales', 'title' => 'Medicina general']],
            'questions' => [['id' => 21, 'name' => 'primera_visita', 'label' => '¿Es su primera visita?', 'type' => 'radio', 'options' => ['Sí', 'No'], 'required' => true, 'help' => null, 'show_if' => null]],
        ];
        $booking = [
            'id' => 318, 'status' => 'confirmed', 'event' => ['id' => 4, 'name' => 'Consulta médica general', 'slug' => 'consulta-medica-general'], 'host' => ['id' => 1, 'name' => 'Dra. Ana Morales'],
            'starts_at' => '2026-10-07T15:00:00Z', 'ends_at' => '2026-10-07T15:30:00Z', 'duration_minutes' => 30, 'mode' => 'in_person', 'location' => null, 'video_url' => null, 'seats' => 1,
            'guest' => ['name' => 'María López', 'email' => 'maria@example.com', 'phone' => '50255551234', 'timezone' => 'America/Guatemala', 'client_id' => 87],
            'notes' => 'Prefiero la tarde.',
            'payment' => ['currency' => 'GTQ', 'price' => 300.0, 'discount' => 0.0, 'total' => 300.0, 'deposit_due' => 0.0, 'paid_amount' => 0.0, 'status' => 'none'],
            'cancellation' => null, 'created_via' => 'api', 'created_at' => '2026-10-05T15:02:11Z', 'updated_at' => '2026-10-05T15:02:11Z',
            'hosts' => [['id' => 1, 'name' => 'Dra. Ana Morales']],
            'answers' => [['question' => '¿Es su primera visita?', 'answer' => 'Sí']],
            'attendees' => [],
        ];
        $client = [
            'id' => 87, 'name' => 'María López', 'email' => 'maria@example.com', 'phone' => '50255551234', 'nit' => '1234567-8', 'tags' => ['vip'], 'source' => 'API',
            'timezone' => 'America/Guatemala', 'no_show_count' => 0, 'blocked' => false, 'bookings_count' => 3, 'last_booking_at' => '2026-10-07T15:00:00Z', 'created_at' => '2026-07-12T14:20:00Z',
        ];
        $slot = ['start' => '2026-10-07T09:00:00-06:00', 'end' => '2026-10-07T09:30:00-06:00', 'start_utc' => '2026-10-07T15:00:00Z', 'end_utc' => '2026-10-07T15:30:00Z', 'host_ids' => [1], 'seats_left' => null];
        $range = [
            ['name' => 'from', 'in' => 'query', 'type' => 'fecha u hora ISO 8601', 'required' => false, 'description' => 'Desde cuándo. Una fecha sola (2026-10-05) vale desde las 00:00 de la zona «tz».', 'example' => '2026-10-05'],
            ['name' => 'to', 'in' => 'query', 'type' => 'fecha u hora ISO 8601', 'required' => false, 'description' => 'Hasta cuándo (no incluido).', 'example' => '2026-10-12'],
            ['name' => 'tz', 'in' => 'query', 'type' => 'zona horaria', 'required' => false, 'description' => 'Zona para interpretar fechas sin zona y para mostrar horas. Por defecto, la del negocio (America/Guatemala).', 'example' => 'America/Guatemala'],
        ];

        return [
            [
                'id' => 'events.list', 'group' => 'Eventos', 'method' => 'GET', 'route' => self::BASE . '/events', 'scope' => 'read', 'handler' => [EventsController::class, 'index'],
                'summary' => 'Listar eventos',
                'description' => 'Devuelve los tipos de cita activos con su duración, precio, política de cancelación, anfitriones y preguntas del formulario. Los enlaces de un solo uso no aparecen.',
                'params' => array_merge([
                    ['name' => 'kind', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'Filtra por tipo: individual, group, round_robin o collective.', 'example' => ''],
                    ['name' => 'mode', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'Filtra por modalidad: in_person, video_auto, video_custom, phone o home.', 'example' => ''],
                    ['name' => 'include_inactive', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'Con el valor 1 también incluye los eventos pausados.', 'example' => ''],
                ], $page),
                'response' => ['data' => [$event], 'meta' => $paged],
            ],
            [
                'id' => 'events.show', 'group' => 'Eventos', 'method' => 'GET', 'route' => self::BASE . '/events/{id:\d+}', 'scope' => 'read', 'handler' => [EventsController::class, 'show'],
                'summary' => 'Ver un evento', 'description' => 'Devuelve un evento con todos sus datos públicos.',
                'params' => [['name' => 'id', 'in' => 'ruta', 'type' => 'entero', 'required' => true, 'description' => 'Identificador del evento.', 'example' => '4']],
                'response' => ['data' => $event, 'meta' => $meta],
            ],
            [
                'id' => 'availability', 'group' => 'Disponibilidad', 'method' => 'GET', 'route' => self::BASE . '/availability', 'scope' => 'read', 'handler' => [EventsController::class, 'availability'],
                'summary' => 'Consultar horarios libres',
                'description' => 'Horarios en los que se puede reservar un evento, ya descontando citas, ausencias, feriados, recursos y el aviso mínimo. Rango máximo: 31 días por consulta. Cada horario trae la hora local en «tz» y la hora UTC.',
                'params' => array_merge([
                    ['name' => 'event_id', 'in' => 'query', 'type' => 'entero', 'required' => true, 'description' => 'Evento a consultar.', 'example' => '4'],
                    ['name' => 'duration', 'in' => 'query', 'type' => 'entero', 'required' => false, 'description' => 'Duración en minutos; debe ser una de las del evento. Por defecto, la duración predeterminada.', 'example' => '30'],
                    ['name' => 'host_id', 'in' => 'query', 'type' => 'entero', 'required' => false, 'description' => 'Limita los horarios a un anfitrión.', 'example' => ''],
                ], $range),
                'response' => ['data' => [$slot], 'meta' => $meta + ['event_id' => 4, 'duration' => 30, 'timezone' => 'America/Guatemala', 'from' => '2026-10-05T06:00:00Z', 'to' => '2026-10-12T06:00:00Z', 'count' => 1]],
            ],
            [
                'id' => 'bookings.list', 'group' => 'Citas', 'method' => 'GET', 'route' => self::BASE . '/bookings', 'scope' => 'read', 'handler' => [BookingsController::class, 'index'],
                'summary' => 'Listar citas',
                'description' => 'Citas ordenadas por fecha de inicio (las más recientes primero). Nunca incluye notas internas ni el enlace de gestión de la persona.',
                'params' => array_merge([
                    ['name' => 'status', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'Uno o varios estados separados por coma: pending, confirmed, cancelled, completed, no_show, rejected.', 'example' => 'confirmed,pending'],
                    ['name' => 'host_id', 'in' => 'query', 'type' => 'entero', 'required' => false, 'description' => 'Solo citas de ese anfitrión.', 'example' => ''],
                    ['name' => 'event_id', 'in' => 'query', 'type' => 'entero', 'required' => false, 'description' => 'Solo citas de ese evento.', 'example' => ''],
                    ['name' => 'client_id', 'in' => 'query', 'type' => 'entero', 'required' => false, 'description' => 'Solo citas de ese cliente.', 'example' => ''],
                    ['name' => 'order', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'asc para las más antiguas primero. Por defecto desc.', 'example' => ''],
                ], $range, $page),
                'response' => ['data' => [array_diff_key($booking, ['hosts' => 1, 'answers' => 1, 'attendees' => 1])], 'meta' => $paged],
            ],
            [
                'id' => 'bookings.show', 'group' => 'Citas', 'method' => 'GET', 'route' => self::BASE . '/bookings/{id:\d+}', 'scope' => 'read', 'handler' => [BookingsController::class, 'show'],
                'summary' => 'Ver una cita', 'description' => 'Devuelve una cita con anfitriones, respuestas al formulario y acompañantes.',
                'params' => [['name' => 'id', 'in' => 'ruta', 'type' => 'entero', 'required' => true, 'description' => 'Identificador de la cita.', 'example' => '318']],
                'response' => ['data' => $booking, 'meta' => $meta],
            ],
            [
                'id' => 'bookings.create', 'group' => 'Citas', 'method' => 'POST', 'route' => self::BASE . '/bookings', 'scope' => 'write', 'handler' => [BookingsController::class, 'create'],
                'summary' => 'Crear una cita',
                'description' => 'Reserva un horario con las mismas validaciones que la página pública: anti doble reserva, aviso mínimo, cupos, recursos y respuestas obligatorias. Queda con origen «api». Si el evento requiere aprobación, la cita nace pendiente. Código 201 al crear.',
                'params' => [
                    ['name' => 'event_id', 'in' => 'cuerpo', 'type' => 'entero', 'required' => true, 'description' => 'Evento a reservar.'],
                    ['name' => 'start', 'in' => 'cuerpo', 'type' => 'hora ISO 8601 con zona', 'required' => true, 'description' => 'Inicio de la cita. Debe ser uno de los horarios de /availability.'],
                    ['name' => 'name', 'in' => 'cuerpo', 'type' => 'texto', 'required' => true, 'description' => 'Nombre completo de la persona.'],
                    ['name' => 'email', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'Correo. Indica al menos correo o teléfono.'],
                    ['name' => 'phone', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => '8 dígitos (+502) o número internacional completo.'],
                    ['name' => 'duration', 'in' => 'cuerpo', 'type' => 'entero', 'required' => false, 'description' => 'Minutos; por defecto la duración predeterminada del evento.'],
                    ['name' => 'host_id', 'in' => 'cuerpo', 'type' => 'entero', 'required' => false, 'description' => 'Anfitrión preferido (si el evento tiene varios).'],
                    ['name' => 'timezone', 'in' => 'cuerpo', 'type' => 'zona horaria', 'required' => false, 'description' => 'Zona de la persona, para sus correos y recordatorios.'],
                    ['name' => 'nit', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'NIT para el comprobante.'],
                    ['name' => 'notes', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'Comentario de la persona.'],
                    ['name' => 'answers', 'in' => 'cuerpo', 'type' => 'objeto', 'required' => false, 'description' => 'Respuestas del formulario: {"id de la pregunta": "respuesta"}. Los ids salen de «questions» en /events.'],
                    ['name' => 'guests', 'in' => 'cuerpo', 'type' => 'lista', 'required' => false, 'description' => 'Acompañantes: [{"name": "...", "email": "..."}].'],
                    ['name' => 'coupon', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'Código de cupón.'],
                    ['name' => 'gift_code', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'Código de certificado de regalo.'],
                ],
                'body' => ['event_id' => 4, 'start' => '2026-10-07T09:00:00-06:00', 'name' => 'María López', 'email' => 'maria@example.com', 'phone' => '5555 1234', 'answers' => ['21' => 'Sí']],
                'status' => 201,
                'response' => ['data' => $booking, 'meta' => $meta + ['needs_payment' => false, 'bookings_created' => 1]],
            ],
            [
                'id' => 'bookings.cancel', 'group' => 'Citas', 'method' => 'POST', 'route' => self::BASE . '/bookings/{id:\d+}/cancel', 'scope' => 'write', 'handler' => [BookingsController::class, 'cancel'],
                'summary' => 'Cancelar una cita',
                'description' => 'Cancela una cita activa (pendiente o confirmada), libera el horario y dispara los avisos y webhooks de cancelación. La cancelación por API no está sujeta al plazo de cancelación de la persona.',
                'params' => [
                    ['name' => 'id', 'in' => 'ruta', 'type' => 'entero', 'required' => true, 'description' => 'Identificador de la cita.', 'example' => '318'],
                    ['name' => 'reason', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'Motivo (hasta 500 caracteres).'],
                ],
                'body' => ['reason' => 'La persona avisó que no puede asistir.'],
                'response' => ['data' => array_replace($booking, ['status' => 'cancelled', 'cancellation' => ['reason' => 'La persona avisó que no puede asistir.', 'by' => 'host', 'at' => '2026-10-06T13:10:00Z']]), 'meta' => $meta],
            ],
            [
                'id' => 'clients.list', 'group' => 'Clientes', 'method' => 'GET', 'route' => self::BASE . '/clients', 'scope' => 'read', 'handler' => [ClientsController::class, 'index'],
                'summary' => 'Listar clientes',
                'description' => 'Clientes del negocio, los más recientes primero. No incluye notas internas ni a las personas cuyos datos fueron eliminados.',
                'params' => array_merge([
                    ['name' => 'q', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'Busca en nombre, correo y teléfono.', 'example' => 'maría'],
                    ['name' => 'email', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'Correo exacto.', 'example' => ''],
                    ['name' => 'phone', 'in' => 'query', 'type' => 'texto', 'required' => false, 'description' => 'Teléfono exacto (8 dígitos o completo).', 'example' => ''],
                ], $page),
                'response' => ['data' => [$client], 'meta' => $paged],
            ],
            [
                'id' => 'clients.show', 'group' => 'Clientes', 'method' => 'GET', 'route' => self::BASE . '/clients/{id:\d+}', 'scope' => 'read', 'handler' => [ClientsController::class, 'show'],
                'summary' => 'Ver un cliente', 'description' => 'Devuelve la ficha de un cliente con su número de citas y la fecha de la última.',
                'params' => [['name' => 'id', 'in' => 'ruta', 'type' => 'entero', 'required' => true, 'description' => 'Identificador del cliente.', 'example' => '87']],
                'response' => ['data' => $client, 'meta' => $meta],
            ],
            [
                'id' => 'clients.create', 'group' => 'Clientes', 'method' => 'POST', 'route' => self::BASE . '/clients', 'scope' => 'write', 'handler' => [ClientsController::class, 'create'],
                'summary' => 'Crear o encontrar un cliente',
                'description' => 'Crea al cliente, o devuelve el que ya existe con ese correo o teléfono (los datos de un cliente existente no se sobrescriben). Código 201 si se creó y 200 si ya existía; «meta.created» lo indica.',
                'params' => [
                    ['name' => 'name', 'in' => 'cuerpo', 'type' => 'texto', 'required' => true, 'description' => 'Nombre completo.'],
                    ['name' => 'email', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'Correo. Indica al menos correo o teléfono.'],
                    ['name' => 'phone', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => '8 dígitos (+502) o número internacional completo.'],
                    ['name' => 'nit', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'NIT.'],
                    ['name' => 'timezone', 'in' => 'cuerpo', 'type' => 'zona horaria', 'required' => false, 'description' => 'Zona horaria de la persona.'],
                    ['name' => 'source', 'in' => 'cuerpo', 'type' => 'texto', 'required' => false, 'description' => 'De dónde viene el contacto. Por defecto «API».'],
                ],
                'body' => ['name' => 'María López', 'email' => 'maria@example.com', 'phone' => '5555 1234', 'source' => 'Formulario del sitio web'],
                'status' => 201,
                'response' => ['data' => $client, 'meta' => $meta + ['created' => true]],
            ],
        ];
    }
}
