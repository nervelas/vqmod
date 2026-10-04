<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;
use Aurea\Core\Logger;
use Aurea\Core\Mailer;
use Aurea\Core\Secret;
use Aurea\Core\Settings;
use Aurea\Core\Util;

/** Cola de mensajes: correo (automático) y WhatsApp (manual por wa.me o automático con Cloud API). */
final class NotificationService
{
    public static function appointment(int $id): ?array
    {
        return Db::one('SELECT a.*, c.name client_name, c.phone_cc, c.phone client_phone, c.email client_email,
            s.name service_name, p.name prof_name, p.email prof_email, l.name loc_name, l.address loc_address, l.city loc_city
            FROM appointments a JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id
            JOIN professionals p ON p.id=a.professional_id LEFT JOIN locations l ON l.id=a.location_id WHERE a.id=?', [$id]);
    }

    public static function whereText(array $a): string
    {
        if ($a['modality'] === 'virtual') {
            return 'Virtual' . ($a['meeting_url'] !== '' ? ' (' . $a['meeting_url'] . ')' : ' — te enviaremos el enlace');
        }
        if ($a['modality'] === 'domicilio') {
            return 'A domicilio: ' . $a['home_address'];
        }
        if (!empty($a['loc_address'])) {
            return trim(($a['loc_name'] ? $a['loc_name'] . ', ' : '') . $a['loc_address'] . ($a['loc_city'] ? ', ' . $a['loc_city'] : ''));
        }
        return (string)(Settings::get('business_address', '') ?: Settings::get('business_name', ''));
    }

    public static function vars(array $a, ?string $link = null): array
    {
        return [
            'nombre' => $a['client_name'],
            'servicio' => $a['service_name'],
            'fecha' => Util::dateLong($a['start_at']),
            'hora' => date('H:i', strtotime($a['start_at'])),
            'profesional' => $a['prof_name'],
            'direccion' => self::whereText($a),
            'enlace' => $link ?? abs_url('/cita/' . $a['token']),
            'negocio' => (string)Settings::get('business_name', ''),
            'telefono' => '+' . $a['phone_cc'] . ' ' . $a['client_phone'],
            'precio' => money($a['total']),
        ];
    }

    /**
     * Encola mensajes de un tipo para una cita. $sendAfter = timestamp (null = ahora).
     * El dedupe_key evita duplicados (cron + reserva).
     */
    public static function queue(int $apptId, string $type, ?int $sendAfter = null, ?string $dedupe = null, ?string $link = null): void
    {
        $a = self::appointment($apptId);
        $tpl = TemplateService::find($type);
        if (!$a || !$tpl || !(int)$tpl['active']) { return; }
        $vars = self::vars($a, $link);
        $when = date('Y-m-d H:i:s', $sendAfter ?? time());
        $subject = TemplateService::fill((string)$tpl['subject'], $vars);
        $now = date('Y-m-d H:i:s');
        if ($type === 'staff_new') {
            $vars['enlace'] = abs_url('/admin/citas/' . $a['id']);
            $to = array_unique(array_filter([(string)Settings::get('business_email', ''), (string)$a['prof_email']], [Util::class, 'isEmail']));
            foreach ($to as $addr) {
                Db::exec('INSERT IGNORE INTO notifications_queue (appointment_id,channel,type,recipient,subject,body,status,dedupe_key,send_after,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$apptId, 'email', $type, $addr, $subject, TemplateService::fill((string)$tpl['email_body'], $vars), 'pending', $dedupe ? $dedupe . ':' . md5($addr) : null, $when, $now]);
            }
            return;
        }
        if (Util::isEmail((string)$a['client_email']) && (string)$tpl['email_body'] !== '') {
            Db::exec('INSERT IGNORE INTO notifications_queue (appointment_id,channel,type,recipient,subject,body,status,dedupe_key,send_after,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$apptId, 'email', $type, $a['client_email'], $subject, TemplateService::fill((string)$tpl['email_body'], $vars), 'pending', $dedupe ? $dedupe . ':email' : null, $when, $now]);
        }
        if ((string)$a['client_phone'] !== '' && (string)$tpl['wa_body'] !== '') {
            Db::exec('INSERT IGNORE INTO notifications_queue (appointment_id,channel,type,recipient,subject,body,status,dedupe_key,send_after,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$apptId, 'whatsapp', $type, $a['phone_cc'] . $a['client_phone'], '', TemplateService::fill((string)$tpl['wa_body'], $vars), 'pending', $dedupe ? $dedupe . ':wa' : null, $when, $now]);
        }
    }

    /** Encola correo + WhatsApp con variables propias (sin cita, p. ej. lista de espera). */
    public static function enqueueRaw(string $type, array $vars, string $email, string $waRecipient, ?string $dedupe = null): void
    {
        $tpl = TemplateService::find($type);
        if (!$tpl || !(int)$tpl['active']) { return; }
        $now = date('Y-m-d H:i:s');
        if (Util::isEmail($email) && (string)$tpl['email_body'] !== '') {
            Db::exec('INSERT IGNORE INTO notifications_queue (appointment_id,channel,type,recipient,subject,body,status,dedupe_key,send_after,created_at) VALUES (NULL,?,?,?,?,?,?,?,?,?)',
                ['email', $type, $email, TemplateService::fill((string)$tpl['subject'], $vars), TemplateService::fill((string)$tpl['email_body'], $vars), 'pending', $dedupe ? $dedupe . ':email' : null, $now, $now]);
        }
        if ($waRecipient !== '' && (string)$tpl['wa_body'] !== '') {
            Db::exec('INSERT IGNORE INTO notifications_queue (appointment_id,channel,type,recipient,subject,body,status,dedupe_key,send_after,created_at) VALUES (NULL,?,?,?,?,?,?,?,?,?)',
                ['whatsapp', $type, $waRecipient, '', TemplateService::fill((string)$tpl['wa_body'], $vars), 'pending', $dedupe ? $dedupe . ':wa' : null, $now, $now]);
        }
    }

    public static function onBooked(int $apptId): void
    {
        $a = self::appointment($apptId);
        if (!$a) { return; }
        self::queue($apptId, $a['status'] === 'pending' ? 'pending' : 'confirmation', null, 'conf:' . $apptId . ':' . $a['status']);
        self::queue($apptId, 'staff_new', null, 'staff:' . $apptId);
        if ($a['status'] === 'confirmed') { self::scheduleReminders($apptId); }
    }

    public static function onConfirmed(int $apptId): void
    {
        self::queue($apptId, 'confirmation', null, 'conf:' . $apptId . ':confirmed');
        self::scheduleReminders($apptId);
    }

    public static function scheduleReminders(int $apptId): void
    {
        $a = self::appointment($apptId);
        if (!$a || $a['status'] !== 'confirmed') { return; }
        $start = strtotime($a['start_at']);
        $now = time();
        if (Settings::bool('reminder_24h', true) && $start - 86400 > $now - 1800) {
            self::queue($apptId, 'reminder_24h', max($now, $start - 86400), 'r24:' . $apptId);
        }
        if (Settings::bool('reminder_2h', true) && $start - 7200 > $now) {
            self::queue($apptId, 'reminder_2h', $start - 7200, 'r2:' . $apptId);
        }
    }

    public static function onCancelled(int $apptId): void
    {
        self::queue($apptId, 'cancellation', null, 'cancel:' . $apptId);
        Db::exec("UPDATE notifications_queue SET status='skipped' WHERE appointment_id=? AND status='pending' AND type IN ('reminder_24h','reminder_2h','confirmation','pending')", [$apptId]);
    }

    public static function onRescheduled(int $oldId, int $newId): void
    {
        Db::exec("UPDATE notifications_queue SET status='skipped' WHERE appointment_id=? AND status='pending'", [$oldId]);
        self::queue($newId, 'reschedule', null, 'resch:' . $newId);
        self::scheduleReminders($newId);
    }

    public static function onCompleted(int $apptId): void
    {
        $a = self::appointment($apptId);
        if (!$a) { return; }
        $end = strtotime($a['end_at']);
        $delay = max(0, Settings::int('review_delay_hours', 2)) * 3600;
        if (Settings::bool('review_requests', true)) {
            self::queue($apptId, 'review_request', max(time(), $end + $delay), 'review:' . $apptId, abs_url('/resena/' . $a['token']));
        }
        if (Settings::bool('followup_enabled', false)) {
            self::queue($apptId, 'followup', max(time(), $end + 86400), 'follow:' . $apptId);
        }
    }

    /** Envía correos vencidos (y WhatsApp si la API está activa). Devuelve estadísticas. */
    public static function process(int $limit = 40): array
    {
        $stats = ['email_sent' => 0, 'email_failed' => 0, 'wa_sent' => 0, 'wa_failed' => 0, 'skipped' => 0];
        $now = date('Y-m-d H:i:s');
        $rows = Db::all("SELECT * FROM notifications_queue WHERE status='pending' AND channel='email' AND send_after<=? AND attempts<5 ORDER BY send_after LIMIT " . (int)$limit, [$now]);
        foreach ($rows as $r) {
            if (self::stale($r)) { Db::exec("UPDATE notifications_queue SET status='skipped' WHERE id=?", [$r['id']]); $stats['skipped']++; continue; }
            [$ok, $err] = Mailer::send((string)$r['recipient'], (string)$r['subject'], self::htmlWrap((string)$r['subject'], (string)$r['body']), (string)$r['body']);
            if ($ok) {
                Db::exec("UPDATE notifications_queue SET status='sent', sent_at=?, attempts=attempts+1, last_error='' WHERE id=?", [$now, $r['id']]);
                $stats['email_sent']++;
            } else {
                $att = (int)$r['attempts'] + 1;
                Db::exec('UPDATE notifications_queue SET attempts=?, last_error=?, status=?, send_after=? WHERE id=?',
                    [$att, mb_substr($err, 0, 480), $att >= 5 ? 'failed' : 'pending', date('Y-m-d H:i:s', time() + $att * 600), $r['id']]);
                Logger::error('Correo fallido #' . $r['id'] . ': ' . $err);
                $stats['email_failed']++;
            }
        }
        if (Settings::bool('wa_api_enabled', false)) {
            $rows = Db::all("SELECT * FROM notifications_queue WHERE status='pending' AND channel='whatsapp' AND send_after<=? AND attempts<3 ORDER BY send_after LIMIT " . (int)$limit, [$now]);
            foreach ($rows as $r) {
                if (self::stale($r)) { Db::exec("UPDATE notifications_queue SET status='skipped' WHERE id=?", [$r['id']]); $stats['skipped']++; continue; }
                [$ok, $err] = WhatsAppCloud::send($r);
                if ($ok) {
                    Db::exec("UPDATE notifications_queue SET status='sent', sent_at=?, attempts=attempts+1, last_error='' WHERE id=?", [$now, $r['id']]);
                    $stats['wa_sent']++;
                } else {
                    $att = (int)$r['attempts'] + 1;
                    Db::exec('UPDATE notifications_queue SET attempts=?, last_error=?, status=?, send_after=? WHERE id=?',
                        [$att, mb_substr($err, 0, 480), $att >= 3 ? 'failed' : 'pending', date('Y-m-d H:i:s', time() + $att * 900), $r['id']]);
                    Logger::error('WhatsApp API fallido #' . $r['id'] . ': ' . $err);
                    $stats['wa_failed']++;
                }
            }
        }
        return $stats;
    }

    /** Un recordatorio/confirmación de una cita que ya no está activa no debe enviarse. */
    private static function stale(array $q): bool
    {
        if (!$q['appointment_id'] || in_array($q['type'], ['cancellation', 'review_request', 'followup', 'staff_new', 'waitlist_offer'], true)) {
            return false;
        }
        $st = Db::val('SELECT status FROM appointments WHERE id=?', [$q['appointment_id']]);
        return !in_array($st, ['pending', 'confirmed'], true);
    }

    public static function htmlWrap(string $subject, string $text): string
    {
        $biz = e(Settings::get('business_name', ''));
        $body = nl2br(e($text), false);
        $body = preg_replace_callback('#(https?://[^\s<]+)#', static function ($m) {
            $u = rtrim($m[1], '.,);');
            $tail = substr($m[1], strlen($u));
            return '<a href="' . $u . '" style="color:#8C6A2B;font-weight:600">' . $u . '</a>' . $tail;
        }, $body) ?? $body;
        return '<!doctype html><html lang="es"><body style="margin:0;background:#F6F0E4;padding:24px 12px;font-family:Helvetica,Arial,sans-serif;color:#2a2620">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fffdf8;border:1px solid #e6d9b8">'
            . '<tr><td style="background:#0B0A08;padding:22px 28px;color:#E9D29A;font-family:Georgia,serif;font-size:22px;letter-spacing:1px">' . $biz . '</td></tr>'
            . '<tr><td style="height:2px;background:linear-gradient(90deg,#8C6A2B,#B8924A,#E9D29A);font-size:0">&nbsp;</td></tr>'
            . '<tr><td style="padding:28px;font-size:15px;line-height:1.65">' . $body . '</td></tr>'
            . '<tr><td style="padding:16px 28px;border-top:1px solid #eee3c6;font-size:12px;color:#7a7061">' . $biz . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Mensajes de WhatsApp pendientes de enviar manualmente (centro "Mensajes por enviar hoy"). */
    public static function dueWhatsApp(?int $professionalId = null): array
    {
        $end = date('Y-m-d 23:59:59');
        $sql = "SELECT q.*, a.start_at, a.professional_id, c.name client_name FROM notifications_queue q
            LEFT JOIN appointments a ON a.id=q.appointment_id LEFT JOIN clients c ON c.id=a.client_id
            WHERE q.channel='whatsapp' AND q.status='pending' AND q.send_after<=?";
        $params = [$end];
        if ($professionalId) { $sql .= ' AND a.professional_id=?'; $params[] = $professionalId; }
        $rows = Db::all($sql . ' ORDER BY q.send_after LIMIT 300', $params);
        return array_values(array_filter($rows, static fn($r) => !self::stale($r)));
    }

    public static function waUrl(array $q): string
    {
        $digits = preg_replace('/\D+/', '', (string)$q['recipient']) ?? '';
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode((string)$q['body']);
    }
}
