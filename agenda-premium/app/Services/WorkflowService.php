<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use Throwable;

/** Flujos de trabajo: correos, WhatsApp, webhooks y acciones automáticas disparadas por eventos de la cita. */
final class WorkflowService
{
    private const IMMEDIATE = ['booking.created', 'booking.approved', 'booking.rescheduled', 'booking.cancelled', 'booking.completed', 'booking.no_show'];
    private const TIMED = ['booking.before_start', 'booking.after_end'];
    /** Intentos totales y espera (min) antes del 2.º y del 3.º. */
    private const MAX_ATTEMPTS = 3;
    private const RETRY_MINUTES = [5, 15];
    private const STATUSES = ['confirmed', 'rejected', 'completed', 'no_show', 'pending', 'cancelled'];

    private static bool $drainRegistered = false;

    private const DEFAULTS = [
        'booking.created' => ['Recibimos tu solicitud', "Hola {nombre},\n\nTu cita de {evento} quedó registrada para el {fecha} a las {hora} ({zona}).\n\nPuedes verla o cambiarla aquí: {enlace}"],
        'booking.approved' => ['Tu cita está confirmada', "Hola {nombre},\n\nTu cita de {evento} con {anfitrion} está confirmada para el {fecha} a las {hora} ({zona}).\n\nDetalles: {enlace}"],
        'booking.rescheduled' => ['Tu cita cambió de horario', "Hola {nombre},\n\nTu cita de {evento} ahora es el {fecha} a las {hora} ({zona}).\n\nDetalles: {enlace}"],
        'booking.cancelled' => ['Tu cita fue cancelada', "Hola {nombre},\n\nLa cita de {evento} del {fecha} a las {hora} fue cancelada. Si quieres volver a agendar, escríbenos al {telefono_negocio}."],
        'booking.completed' => ['Gracias por tu visita', "Hola {nombre},\n\nGracias por acompañarnos en tu cita de {evento}. Esperamos verte pronto."],
        'booking.no_show' => ['Te extrañamos en tu cita', "Hola {nombre},\n\nNo pudimos verte en tu cita de {evento} del {fecha}. Si quieres reprogramar, escríbenos al {telefono_negocio}."],
        'booking.before_start' => ['Recordatorio de tu cita', "Hola {nombre},\n\nTe recordamos tu cita de {evento} con {anfitrion}: {fecha} a las {hora} ({zona}).\n{direccion}\n\nDetalles: {enlace}"],
        'booking.after_end' => ['¿Cómo te fue?', "Hola {nombre},\n\nGracias por tu cita de {evento}. Si necesitas algo más, estamos para ayudarte: {telefono_negocio}."],
    ];

    // ====================================================== variables y plantillas

    /** @return array<string,string> valores de las variables, en la zona horaria del invitado. */
    public static function variables(array $bookingDisplay): array
    {
        $b = $bookingDisplay;
        $tz = Tz::safe((string) ($b['guest_timezone'] ?? ''), Settings::tz());
        $start = (string) $b['starts_at'];
        $hosts = array_values(array_filter(array_map(static fn (array $h): string => (string) ($h['name'] ?? ''), (array) ($b['hosts'] ?? []))));
        if (!$hosts && !empty($b['host']['name'])) {
            $hosts = [(string) $b['host']['name']];
        }
        $total = (float) ($b['total'] ?? 0);
        $price = (float) ($b['price'] ?? 0);
        return [
            'nombre' => (string) ($b['guest_name'] ?? ''),
            'evento' => (string) ($b['event']['name'] ?? ''),
            'fecha' => Fmt::dateLong($start, $tz),
            'hora' => Fmt::time($start, $tz),
            'anfitrion' => implode(', ', $hosts),
            'enlace' => self::bookingLink($b),
            'direccion' => trim((string) ($b['location'] ?? '')) ?: (trim((string) ($b['event']['location'] ?? '')) ?: trim((string) Settings::get('address', ''))),
            'zona' => Fmt::tzLabel($tz, $start),
            'videollamada' => (string) ($b['video_url'] ?? ''),
            'precio' => $total > 0 ? Fmt::money($total) : ($price > 0 ? Fmt::money($price) : 'Sin costo'),
            'telefono_negocio' => Str::phoneDisplay((string) Settings::get('phone', '')),
        ];
    }

    /** Sustituye las variables {…} de una plantilla de texto (el resultado es texto plano, sin escapar). */
    public static function render(string $tpl, array $bookingDisplay): string
    {
        return Str::template($tpl, self::variables($bookingDisplay));
    }

    // ================================================================ disparo

    /** Registra las ejecuciones que corresponden a un evento de la cita. */
    public static function fire(string $trigger, int $bookingId): void
    {
        if (!in_array($trigger, self::IMMEDIATE, true)) {
            return;
        }
        $b = BookingService::find($bookingId);
        if (!$b) {
            return;
        }
        if (in_array($trigger, ['booking.cancelled', 'booking.rescheduled'], true)) {
            self::cancelPending($bookingId);
        }
        $created = 0;
        // En series de sesiones, solo la primera cita envía los mensajes inmediatos de confirmación
        $skipImmediate = $trigger === 'booking.created' && (int) ($b['series_index'] ?? 1) > 1;
        if (!$skipImmediate) {
            foreach (self::workflowsFor($trigger, (int) $b['event_type_id']) as $wf) {
                $now = Clock::utc();
                $created += self::insertRun((int) $wf['id'], $bookingId, $now, 'pending', null);
            }
        }
        if (in_array($trigger, ['booking.created', 'booking.approved', 'booking.rescheduled'], true) && $b['status'] === 'confirmed') {
            self::scheduleTimed($b);
        }
        if ($created > 0) {
            self::drainAfterResponse();
        }
    }

    /** Marca como omitidas las ejecuciones programadas pendientes (recordatorios, seguimientos) de una cita. */
    public static function cancelPending(int $bookingId): void
    {
        Db::exec(
            "UPDATE workflow_runs r JOIN workflows w ON w.id = r.workflow_id
                SET r.status = 'skipped', r.last_error = 'La cita cambió o se canceló.'
              WHERE r.booking_id = ? AND r.status = 'pending' AND w.trigger_key IN ('booking.before_start','booking.after_end')",
            [$bookingId]
        );
    }

    /** Ejecuta lo que ya toca. @return array{done:int,failed:int,retry:int,skipped:int} */
    public static function runDue(int $limit = 25): array
    {
        $res = ['done' => 0, 'failed' => 0, 'retry' => 0, 'skipped' => 0];
        $rows = Db::all(
            "SELECT * FROM workflow_runs WHERE status = 'pending' AND COALESCE(next_attempt_at, scheduled_at) <= ? ORDER BY scheduled_at, id LIMIT " . max(1, min(200, $limit)),
            [Clock::utc()]
        );
        foreach ($rows as $run) {
            $n = (int) $run['attempts'] + 1;
            $wait = self::RETRY_MINUTES[$n - 1] ?? self::RETRY_MINUTES[count(self::RETRY_MINUTES) - 1];
            // Reserva la ejecución; si el proceso muriera a medias, se reintenta después
            $claimed = Db::exec(
                "UPDATE workflow_runs SET attempts = ?, next_attempt_at = ? WHERE id = ? AND status = 'pending' AND attempts = ?",
                [$n, Clock::utc(Clock::now() + $wait * 60), $run['id'], $run['attempts']]
            );
            if ($claimed !== 1) {
                continue;
            }
            $out = self::execute($run);
            if ($out['ok'] && empty($out['skip'])) {
                Db::update('workflow_runs', ['status' => 'done', 'executed_at' => Clock::utc(), 'last_error' => null], 'id = ?', [$run['id']]);
                $res['done']++;
            } elseif ($out['ok']) {
                Db::update('workflow_runs', ['status' => 'skipped', 'executed_at' => Clock::utc(), 'last_error' => mb_substr((string) $out['skip'], 0, 500)], 'id = ?', [$run['id']]);
                $res['skipped']++;
            } else {
                $err = mb_substr((string) $out['error'], 0, 500);
                if ($n >= self::MAX_ATTEMPTS) {
                    Db::update('workflow_runs', ['status' => 'failed', 'last_error' => $err], 'id = ?', [$run['id']]);
                    Logger::error('Flujo #' . $run['workflow_id'] . ' falló para la cita ' . $run['booking_id'] . ': ' . $err);
                    $res['failed']++;
                } else {
                    Db::update('workflow_runs', ['last_error' => $err], 'id = ?', [$run['id']]);
                    $res['retry']++;
                }
            }
        }
        return $res;
    }

    /**
     * Vista previa de un flujo con una cita real, sin efectos. Con $send, un flujo de correo se manda de prueba
     * al correo del administrador (nunca al invitado).
     * @return array{ok:bool,error:?string,action:string,to:string,subject:string,text:string,sent:bool}
     */
    public static function testRun(int $workflowId, int $bookingId, bool $send = false): array
    {
        $out = ['ok' => false, 'error' => null, 'action' => '', 'to' => '', 'subject' => '', 'text' => '', 'sent' => false];
        try {
            $wf = Db::one('SELECT * FROM workflows WHERE id = ?', [$workflowId]);
            $b = BookingService::find($bookingId);
            if (!$wf || !$b) {
                $out['error'] = 'No encontramos el flujo o la cita elegida.';
                return $out;
            }
            $d = BookingService::display($b);
            $out['action'] = (string) $wf['action'];
            [$subject, $text] = self::compose($wf, $d);
            $out['subject'] = $subject;
            $out['text'] = $text;
            $r = self::recipients($wf, $d)[0] ?? null;
            $out['to'] = $r ? (string) ($wf['action'] === 'email' ? $r['email'] : $r['phone']) : '';
            $out['ok'] = true;
            if ($send && $wf['action'] === 'email') {
                $admin = trim((string) Settings::get('admin_notify_email', '')) ?: trim((string) Settings::get('email', ''));
                if ($admin === '') {
                    $out['ok'] = false;
                    $out['error'] = 'Configura el correo del administrador en Ajustes para recibir pruebas.';
                    return $out;
                }
                $sent = Mailer::sendNow($admin, null, '[Prueba] ' . $subject, self::emailHtml($subject, $text, $d, $wf), $text);
                $out['sent'] = $sent['ok'];
                $out['to'] = $admin;
                $out['ok'] = $sent['ok'];
                $out['error'] = $sent['error'];
            }
        } catch (Throwable $e) {
            Logger::error('Flujos: falló la prueba del flujo ' . $workflowId, $e);
            $out['ok'] = false;
            $out['error'] = 'No se pudo preparar la prueba por un problema interno.';
        }
        return $out;
    }

    /** Ejecuta lo pendiente justo después de responder al visitante, para no depender del cron en confirmaciones. */
    public static function drain(): void
    {
        try {
            self::runDue(15);
            Mailer::processQueue(10);
        } catch (Throwable $e) {
            Logger::error('Flujos: error al procesar envíos inmediatos', $e);
        }
    }

    // ================================================================ interno

    private static function drainAfterResponse(): void
    {
        if (self::$drainRegistered || PHP_SAPI === 'cli' || defined('AP_CRON')) {
            return;
        }
        self::$drainRegistered = true;
        register_shutdown_function(static function (): void {
            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            self::drain();
        });
    }

    private static function workflowsFor(string $trigger, int $eventTypeId): array
    {
        return Db::all(
            'SELECT * FROM workflows WHERE active = 1 AND trigger_key = ? AND (event_type_id IS NULL OR event_type_id = ?) ORDER BY sort_order, id',
            [$trigger, $eventTypeId]
        );
    }

    /** Crea las ejecuciones de recordatorios/seguimientos; las que ya pasaron quedan como omitidas. */
    private static function scheduleTimed(array $b): void
    {
        $startTs = Tz::ts((string) $b['starts_at']);
        $endTs = Tz::ts((string) $b['ends_at']);
        foreach (self::TIMED as $trigger) {
            foreach (self::workflowsFor($trigger, (int) $b['event_type_id']) as $wf) {
                $at = $trigger === 'booking.before_start' ? $startTs - (int) $wf['offset_minutes'] * 60 : $endTs + (int) $wf['offset_minutes'] * 60;
                $past = $at <= Clock::now();
                self::insertRun((int) $wf['id'], (int) $b['id'], Clock::utc($at), $past ? 'skipped' : 'pending', $past ? 'La hora ya había pasado.' : null);
            }
        }
    }

    /** INSERT IGNORE: el índice único evita duplicados. Devuelve 1 si creó la ejecución. */
    private static function insertRun(int $workflowId, int $bookingId, string $scheduledAt, string $status, ?string $note): int
    {
        return Db::exec(
            'INSERT IGNORE INTO workflow_runs (workflow_id, booking_id, scheduled_at, status, attempts, last_error, created_at) VALUES (?, ?, ?, ?, 0, ?, ?)',
            [$workflowId, $bookingId, $scheduledAt, $status, $note, Clock::utc()]
        );
    }

    /** @return array{ok:bool,error?:string,skip?:string} */
    private static function execute(array $run): array
    {
        try {
            $wf = Db::one('SELECT * FROM workflows WHERE id = ?', [$run['workflow_id']]);
            if (!$wf || (int) $wf['active'] !== 1) {
                return ['ok' => true, 'skip' => 'El flujo está desactivado.'];
            }
            $b = BookingService::find((int) $run['booking_id']);
            if (!$b) {
                return ['ok' => true, 'skip' => 'La cita ya no existe.'];
            }
            $status = (string) $b['status'];
            if ($wf['trigger_key'] === 'booking.before_start' && $status !== 'confirmed') {
                return ['ok' => true, 'skip' => 'La cita ya no está confirmada.'];
            }
            if ($wf['trigger_key'] === 'booking.after_end' && !in_array($status, ['confirmed', 'completed'], true)) {
                return ['ok' => true, 'skip' => 'La cita no se llevó a cabo.'];
            }
            return self::act($wf, BookingService::display($b), $run);
        } catch (Throwable $e) {
            Logger::error('Flujos: error al ejecutar la ejecución ' . $run['id'], $e);
            return ['ok' => false, 'error' => $e instanceof \InvalidArgumentException || $e instanceof BookingException ? $e->getMessage() : 'Error inesperado al ejecutar el flujo.'];
        }
    }

    private static function act(array $wf, array $d, array $run): array
    {
        switch ($wf['action']) {
            case 'email':
                return self::actEmail($wf, $d);
            case 'whatsapp':
            case 'whatsapp_api':
                return self::actWhatsapp($wf, $d, $run);
            case 'webhook':
                $event = preg_replace('/[^a-z0-9._\-]/', '', strtolower(trim((string) $wf['action_value'])));
                WebhookService::dispatch($event !== '' ? $event : 'workflow.custom', WebhookService::bookingPayload((int) $d['id']));
                return ['ok' => true];
            case 'set_status':
                return self::actSetStatus($wf, $d);
            case 'review_request':
                return self::actReview($wf, $d, $run);
            case 'add_tag':
                return self::actTag($wf, $d);
        }
        return ['ok' => false, 'error' => 'La acción del flujo no es válida.'];
    }

    /** @return array{0:string,1:string} [asunto, texto] ya con variables. */
    private static function compose(array $wf, array $d, ?array $vars = null): array
    {
        $def = self::DEFAULTS[$wf['trigger_key']] ?? ['Aviso de tu cita', "Hola {nombre},\n\nTe escribimos sobre tu cita de {evento}: {enlace}"];
        $subjectTpl = trim((string) $wf['subject']) !== '' ? (string) $wf['subject'] : $def[0];
        $textTpl = trim((string) $wf['template']) !== '' ? (string) $wf['template'] : $def[1];
        $vars ??= self::variables($d);
        return [
            trim((string) preg_replace('/\s+/', ' ', Str::template($subjectTpl, $vars))),
            Str::template($textTpl, $vars),
        ];
    }

    private static function emailHtml(string $subject, string $text, array $d, array $wf): string
    {
        $link = self::bookingLink($d);
        $showButton = $wf['recipient'] === 'guest' && $wf['trigger_key'] !== 'booking.cancelled' && $wf['action'] === 'email' && preg_match('~^https?://~', $link);
        return Mailer::layout($subject, Mailer::textToHtml($text), [
            'preheader' => mb_substr((string) preg_replace('/\s+/', ' ', $text), 0, 110),
            'button' => $showButton ? ['label' => 'Ver mi cita', 'url' => $link] : null,
        ]);
    }

    /** @return array<int,array{name:string,email:string,phone:string}> */
    private static function recipients(array $wf, array $d): array
    {
        $out = [];
        switch ($wf['recipient']) {
            case 'host':
                $hosts = (array) ($d['hosts'] ?? []);
                if (!$hosts && !empty($d['host'])) {
                    $hosts = [$d['host']];
                }
                foreach ($hosts as $h) {
                    $email = trim((string) ($h['email'] ?? ''));
                    if ($email === '' && !empty($h['user_id'])) {
                        $email = (string) Db::val('SELECT email FROM users WHERE id = ?', [$h['user_id']]);
                    }
                    $out[] = ['name' => (string) ($h['name'] ?? ''), 'email' => $email, 'phone' => (string) Str::phone((string) (($h['whatsapp'] ?? '') ?: ($h['phone'] ?? '')))];
                }
                break;
            case 'admin':
                $out[] = [
                    'name' => (string) Settings::get('business_name', ''),
                    'email' => trim((string) Settings::get('admin_notify_email', '')) ?: trim((string) Settings::get('email', '')),
                    'phone' => (string) Str::phone((string) (Settings::get('whatsapp', '') ?: Settings::get('phone', ''))),
                ];
                break;
            default:
                $out[] = ['name' => (string) $d['guest_name'], 'email' => trim((string) ($d['guest_email'] ?? '')), 'phone' => (string) Str::phone((string) ($d['guest_phone'] ?? ''))];
        }
        return $out;
    }

    private static function bookingLink(array $b): string
    {
        return !empty($b['token']) ? Mailer::absUrl('/reserva/' . $b['token']) : '';
    }

    private static function actEmail(array $wf, array $d): array
    {
        [$subject, $text] = self::compose($wf, $d);
        $to = array_filter(self::recipients($wf, $d), static fn (array $r): bool => $r['email'] !== '');
        if (!$to) {
            return ['ok' => true, 'skip' => 'No hay un correo al que enviar.'];
        }
        $attachments = [];
        $trigger = (string) $wf['trigger_key'];
        $withIcs = ($d['status'] === 'confirmed' && in_array($trigger, ['booking.created', 'booking.approved', 'booking.rescheduled'], true))
            || ($d['status'] === 'cancelled' && $trigger === 'booking.cancelled');
        if ($withIcs) {
            $attachments[] = ['name' => 'cita.ics', 'mime' => 'text/calendar', 'content' => IcsService::generate($d)];
        }
        $html = self::emailHtml($subject, $text, $d, $wf);
        foreach ($to as $r) {
            if (Mailer::queue($r['email'], $r['name'], $subject, $html, $text, $attachments) === 0) {
                return ['ok' => false, 'error' => 'La dirección de correo no es válida.'];
            }
        }
        return ['ok' => true];
    }

    private static function actWhatsapp(array $wf, array $d, array $run): array
    {
        [, $text] = self::compose($wf, $d);
        return self::sendWhatsapp((string) $wf['action'], self::recipients($wf, $d), $text, $d, $run);
    }

    /** Cola de un toque, o API si está activada. Un solo registro por ejecución (idempotente en los reintentos). */
    private static function sendWhatsapp(string $action, array $recipients, string $text, array $d, array $run): array
    {
        $to = array_values(array_filter($recipients, static fn (array $r): bool => $r['phone'] !== ''));
        if (!$to) {
            return ['ok' => true, 'skip' => 'No hay un teléfono al que enviar.'];
        }
        $useApi = $action === 'whatsapp_api' && Settings::bool('wa_api_enabled');
        $due = Clock::utc(max(Clock::now(), Tz::ts((string) $run['scheduled_at'])));
        foreach ($to as $r) {
            $row = ['booking_id' => $d['id'], 'client_id' => $d['client_id'] ?? null, 'workflow_run_id' => $run['id'], 'phone' => $r['phone'], 'body' => $text, 'due_at' => $due];
            if (!$useApi) {
                self::saveMessage($row + ['channel' => 'whatsapp', 'status' => 'pending', 'last_error' => null, 'sent_at' => null], $r['phone']);
                continue;
            }
            $sent = WhatsAppService::send($r['phone'], $text);
            self::saveMessage($row + [
                'channel' => 'whatsapp_api', 'status' => $sent['ok'] ? 'sent' : 'failed',
                'last_error' => $sent['ok'] ? null : mb_substr((string) $sent['error'], 0, 500), 'sent_at' => $sent['ok'] ? Clock::utc() : null,
            ], $r['phone']);
            if (!$sent['ok']) {
                return ['ok' => false, 'error' => (string) $sent['error']];
            }
        }
        return ['ok' => true];
    }

    private static function saveMessage(array $row, string $phone): void
    {
        $id = Db::val('SELECT id FROM message_queue WHERE workflow_run_id = ? AND phone = ?', [$row['workflow_run_id'], $phone]);
        if ($id) {
            Db::update('message_queue', ['channel' => $row['channel'], 'status' => $row['status'], 'last_error' => $row['last_error'], 'sent_at' => $row['sent_at'], 'body' => $row['body']], 'id = ?', [$id]);
            return;
        }
        Db::insert('message_queue', $row + ['created_at' => Clock::utc()]);
    }

    private static function actSetStatus(array $wf, array $d): array
    {
        $status = trim((string) $wf['action_value']);
        if (!in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'El estado elegido en el flujo no es válido.'];
        }
        if ($d['status'] === $status) {
            return ['ok' => true, 'skip' => 'La cita ya tenía ese estado.'];
        }
        $actor = ['type' => 'system', 'label' => 'Flujo: ' . $wf['name']];
        if ($status === 'cancelled') {
            BookingService::cancel((int) $d['id'], 'Cancelada por un flujo automático.', $actor);
        } else {
            BookingService::setStatus((int) $d['id'], $status, $actor);
        }
        return ['ok' => true];
    }

    private static function actReview(array $wf, array $d, array $run): array
    {
        $row = Db::one('SELECT * FROM reviews WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$d['id']]);
        if ($row && $row['status'] !== 'requested') {
            return ['ok' => true, 'skip' => 'Esta cita ya tiene una reseña.'];
        }
        $token = $row ? (string) $row['token'] : Str::token();
        if (!$row) {
            Db::insert('reviews', [
                'token' => $token, 'booking_id' => $d['id'], 'host_id' => $d['host_id'],
                'client_name' => mb_substr((string) $d['guest_name'], 0, 160), 'status' => 'requested', 'created_at' => Clock::utc(),
            ]);
        }
        $vars = self::variables($d);
        $vars['enlace'] = Mailer::absUrl('/resena/' . $token);
        $wfForText = $wf;
        if (trim((string) $wf['template']) === '') {
            $wfForText['template'] = "Hola {nombre},\n\nGracias por tu cita de {evento}. ¿Nos cuentas cómo te fue? Solo toma un minuto: {enlace}";
        }
        if (trim((string) $wf['subject']) === '') {
            $wfForText['subject'] = 'Cuéntanos cómo te fue';
        }
        [$subject, $text] = self::compose($wfForText, $d, $vars);
        $guest = self::recipients(['recipient' => 'guest'], $d)[0];
        $channel = trim((string) $wf['action_value']) === 'whatsapp' ? 'whatsapp' : 'email';
        if ($channel === 'email' && $guest['email'] === '' && $guest['phone'] !== '') {
            $channel = 'whatsapp';
        } elseif ($channel === 'whatsapp' && $guest['phone'] === '' && $guest['email'] !== '') {
            $channel = 'email';
        }
        if ($channel === 'whatsapp') {
            return self::sendWhatsapp('whatsapp', [$guest], $text, $d, $run);
        }
        if ($guest['email'] === '') {
            return ['ok' => true, 'skip' => 'La persona no dejó correo ni teléfono.'];
        }
        $html = Mailer::layout($subject, Mailer::textToHtml($text), ['button' => ['label' => 'Dejar mi reseña', 'url' => $vars['enlace']]]);
        Mailer::queue($guest['email'], $guest['name'], $subject, $html, $text);
        return ['ok' => true];
    }

    private static function actTag(array $wf, array $d): array
    {
        $tag = trim((string) preg_replace('/[\s,]+/u', ' ', (string) $wf['action_value']));
        if ($tag === '') {
            return ['ok' => false, 'error' => 'El flujo no tiene una etiqueta definida.'];
        }
        if (empty($d['client_id'])) {
            return ['ok' => true, 'skip' => 'La cita no está ligada a un cliente.'];
        }
        $msg = Db::tx(static function () use ($d, $tag): ?string {
            $cur = (string) Db::val('SELECT tags FROM clients WHERE id = ? FOR UPDATE', [$d['client_id']]);
            $tags = array_values(array_filter(array_map('trim', explode(',', $cur)), 'strlen'));
            foreach ($tags as $t) {
                if (mb_strtolower($t) === mb_strtolower($tag)) {
                    return null;
                }
            }
            $tags[] = $tag;
            $joined = implode(',', $tags);
            if (mb_strlen($joined) > 255) {
                return 'El cliente ya tiene demasiadas etiquetas.';
            }
            Db::update('clients', ['tags' => $joined, 'updated_at' => Clock::utc()], 'id = ?', [$d['client_id']]);
            return null;
        });
        return $msg === null ? ['ok' => true] : ['ok' => false, 'error' => $msg];
    }
}
