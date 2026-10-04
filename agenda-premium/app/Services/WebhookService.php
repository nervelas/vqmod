<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Str;
use App\Core\Tz;

/** Webhooks salientes firmados (HMAC-SHA256), con reintentos de espera creciente. Compatible con Zapier/Make. */
final class WebhookService
{
    /** Espera (min) tras cada intento fallido: 1 min, 5 min, 30 min, 2 h, 12 h. El 6.º fallo es definitivo. */
    private const BACKOFF_MINUTES = [1, 5, 30, 120, 720];
    private const MAX_ATTEMPTS = 6;

    /** Registra una entrega por cada webhook activo suscrito al evento. Nunca lanza. */
    public static function dispatch(string $event, array $payload): void
    {
        try {
            $hooks = Db::all('SELECT id, events FROM webhooks WHERE active = 1');
            $now = Clock::utc();
            $body = null;
            foreach ($hooks as $h) {
                if (!self::matches((string) $h['events'], $event)) {
                    continue;
                }
                $body ??= (string) json_encode([
                    'id' => Str::token(),
                    'event' => $event,
                    'created_at' => Tz::iso($now),
                    'data' => $payload,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
                Db::insert('webhook_deliveries', [
                    'webhook_id' => $h['id'], 'event' => mb_substr($event, 0, 60), 'payload' => $body,
                    'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => $now, 'created_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            Logger::error('Webhooks: no se pudo registrar el evento ' . $event, $e);
        }
    }

    /** Firma HMAC-SHA256 (hex) de "{timestamp}.{body}". */
    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /** Entrega los pendientes cuya hora llegó. @return array{delivered:int,failed:int,retry:int} */
    public static function deliverDue(int $limit = 20): array
    {
        $res = ['delivered' => 0, 'failed' => 0, 'retry' => 0];
        $rows = Db::all(
            "SELECT d.*, w.url, w.secret, w.active FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id
             WHERE d.status = 'pending' AND d.next_attempt_at <= ? ORDER BY d.next_attempt_at, d.id LIMIT " . max(1, min(200, $limit)),
            [Clock::utc()]
        );
        foreach ($rows as $d) {
            $n = (int) $d['attempts'] + 1;
            $delay = self::BACKOFF_MINUTES[min($n, count(self::BACKOFF_MINUTES)) - 1];
            $claimed = Db::exec(
                "UPDATE webhook_deliveries SET attempts = ?, next_attempt_at = ? WHERE id = ? AND status = 'pending' AND attempts = ?",
                [$n, Clock::utc(Clock::now() + $delay * 60), $d['id'], $d['attempts']]
            );
            if ($claimed !== 1) {
                continue;
            }
            if ((int) $d['active'] !== 1) {
                Db::update('webhook_deliveries', ['status' => 'failed', 'response_body' => 'El webhook está desactivado.'], 'id = ?', [$d['id']]);
                $res['failed']++;
                continue;
            }
            $ts = (string) Clock::now();
            $r = SafeHttp::post((string) $d['url'], (string) $d['payload'], [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: AgendaPremium-Webhook/1.0',
                'X-Agenda-Event: ' . $d['event'],
                'X-Agenda-Delivery: ' . $d['id'],
                'X-Agenda-Timestamp: ' . $ts,
                'X-Agenda-Signature: sha256=' . self::sign((string) $d['secret'], $ts, (string) $d['payload']),
            ], ['timeout' => 10, 'max_redirects' => 0]);
            $snippet = $r['status'] > 0 ? mb_substr($r['body'], 0, 500) : mb_substr((string) $r['error'], 0, 500);
            $update = ['response_code' => $r['status'] > 0 ? $r['status'] : null, 'response_body' => $snippet];
            if ($r['ok']) {
                Db::update('webhook_deliveries', $update + ['status' => 'delivered', 'delivered_at' => Clock::utc()], 'id = ?', [$d['id']]);
                $res['delivered']++;
            } elseif ($n >= self::MAX_ATTEMPTS) {
                Db::update('webhook_deliveries', $update + ['status' => 'failed'], 'id = ?', [$d['id']]);
                Logger::error('Webhook #' . $d['webhook_id'] . ': entrega ' . $d['id'] . ' falló definitivamente (' . ($r['error'] ?? '') . ')');
                $res['failed']++;
            } else {
                Db::update('webhook_deliveries', $update, 'id = ?', [$d['id']]);
                $res['retry']++;
            }
        }
        return $res;
    }

    /** Datos de la cita para los webhooks: sin token de gestión, notas internas ni IP. */
    public static function bookingPayload(int $bookingId): array
    {
        $b = Db::one('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
        if (!$b) {
            return [];
        }
        $event = Db::one('SELECT id, name, slug, kind FROM event_types WHERE id = ?', [$b['event_type_id']]) ?: [];
        $hosts = Db::all('SELECT h.id, h.name, h.email FROM booking_hosts bh JOIN hosts h ON h.id = bh.host_id WHERE bh.booking_id = ? ORDER BY h.id', [$bookingId]);
        $host = Db::one('SELECT id, name, email FROM hosts WHERE id = ?', [$b['host_id']]) ?: [];
        $answers = Db::all('SELECT label, value FROM booking_answers WHERE booking_id = ? AND file_id IS NULL ORDER BY id', [$bookingId]);
        return [
            'booking' => [
                'id' => (int) $b['id'],
                'status' => $b['status'],
                'starts_at' => Tz::iso((string) $b['starts_at']),
                'ends_at' => Tz::iso((string) $b['ends_at']),
                'duration_minutes' => (int) $b['duration'],
                'guest_timezone' => $b['guest_timezone'],
                'mode' => $b['mode'],
                'location' => $b['location'],
                'video_url' => $b['video_url'],
                'seats' => (int) $b['seats'],
                'price' => (float) $b['price'],
                'discount' => (float) $b['discount'],
                'total' => (float) $b['total'],
                'paid_amount' => (float) $b['paid_amount'],
                'payment_status' => $b['payment_status'],
                'notes' => $b['notes'],
                'cancel_reason' => $b['cancel_reason'],
                'created_via' => $b['created_via'],
                'created_at' => Tz::iso((string) $b['created_at']),
            ],
            'client' => [
                'id' => $b['client_id'] !== null ? (int) $b['client_id'] : null,
                'name' => $b['guest_name'],
                'email' => $b['guest_email'],
                'phone' => $b['guest_phone'],
            ],
            'event' => $event ? ['id' => (int) $event['id'], 'name' => $event['name'], 'slug' => $event['slug'], 'kind' => $event['kind']] : null,
            'host' => $host ? ['id' => (int) $host['id'], 'name' => $host['name'], 'email' => $host['email']] : null,
            'hosts' => array_map(static fn (array $h): array => ['id' => (int) $h['id'], 'name' => $h['name'], 'email' => $h['email']], $hosts),
            'answers' => $answers,
        ];
    }

    /** '*', una lista separada por comas, o prefijos como "booking.*". */
    private static function matches(string $events, string $event): bool
    {
        foreach (explode(',', $events) as $e) {
            $e = trim($e);
            if ($e === '*' || $e === $event || (substr($e, -2) === '.*' && strncmp($event, substr($e, 0, -1), strlen($e) - 1) === 0)) {
                return true;
            }
        }
        return false;
    }
}
