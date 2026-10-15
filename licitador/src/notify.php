<?php
/** Correo (SMTP / mail()), WhatsApp (Cloud API o cola manual), cola y alertas. */

function tpl_render(string $key, array $vars): array {
    $t = row("SELECT subject, body FROM templates WHERE k=?", [$key]) ?: ['subject' => $key, 'body' => ''];
    $vars += ['sitio' => S('site_name', 'Licitador'), 'url' => site_url('')];
    $map = []; foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string) $v;
    return [strtr((string) $t['subject'], $map), strtr((string) $t['body'], $map)];
}
function email_html(string $subject, string $body): string {
    $paras = '';
    foreach (preg_split('/\n{2,}/', trim($body)) as $p) $paras .= '<p style="margin:0 0 14px;line-height:1.55">' . nl2br(preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#C9A24B">$1</a>', e($p))) . '</p>';
    return '<!doctype html><html lang="es"><body style="margin:0;background:#0B0A08;font-family:Arial,Helvetica,sans-serif"><div style="max-width:600px;margin:0 auto;padding:24px"><div style="font-family:Georgia,serif;font-size:26px;color:#C9A24B;letter-spacing:1px;margin-bottom:18px">' . e(S('site_name', 'Licitador')) . '</div><div style="background:#F3EDE0;color:#1a1814;border-radius:10px;padding:24px;border-top:3px solid #C9A24B"><h2 style="margin:0 0 14px;font-family:Georgia,serif">' . e($subject) . '</h2>' . $paras . '</div><p style="color:#8d8573;font-size:12px;margin-top:14px">' . e(S('site_name', 'Licitador')) . ' informa y asiste en la preparación de ofertas; no garantiza adjudicaciones.</p></div></body></html>';
}
function b64h(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

/** Envía un correo. Devuelve true o un mensaje de error. */
function send_mail(string $to, string $subject, string $body)  {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return 'Correo inválido';
    $from = S('mail_from', 'no-responder@' . preg_replace('/^www\./', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]));
    $fromName = S('mail_from_name', S('site_name', 'Licitador'));
    $html = email_html($subject, $body);
    $boundary = 'b' . bin2hex(random_bytes(8));
    $headers = "From: " . b64h($fromName) . " <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@licitador>\r\n";
    $msg = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body)) .
           "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$boundary--\r\n";
    $host = S('smtp_host');
    if ($host) {
        $r = smtp_send($host, (int) S('smtp_port', 587), S('smtp_secure', 'tls'), S('smtp_user'), S('smtp_pass'), $from, $to, "To: $to\r\nSubject: " . b64h($subject) . "\r\n" . $headers, $msg);
        if ($r === true) return true;
        $smtpErr = $r;
    }
    // Respaldo: mail() de PHP
    $ok = @mail($to, b64h($subject), $msg, $headers);
    if ($ok) return true;
    return 'No se pudo enviar' . (isset($smtpErr) ? ' (SMTP: ' . $smtpErr . ')' : ' (mail() no disponible en este servidor)');
}
function smtp_send(string $host, int $port, string $secure, string $user, string $pass, string $from, string $to, string $hdr, string $msg) {
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 12);
    if (!$fp) return "conexión fallida ($errstr)";
    stream_set_timeout($fp, 12);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function (string $c, string $ok) use ($fp, $read) { if ($c !== '') fwrite($fp, $c . "\r\n"); $r = $read(); return strpos($r, $ok) === 0 ? true : trim($r); };
    $helo = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
    try {
        if (($x = $cmd('', '220')) !== true) return $x;
        if (($x = $cmd("EHLO $helo", '250')) !== true) return $x;
        if ($secure === 'tls') {
            if (($x = $cmd('STARTTLS', '220')) !== true) return $x;
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return 'fallo TLS';
            if (($x = $cmd("EHLO $helo", '250')) !== true) return $x;
        }
        if ($user !== '') {
            if (($x = $cmd('AUTH LOGIN', '334')) !== true) return $x;
            if (($x = $cmd(base64_encode($user), '334')) !== true) return $x;
            if (($x = $cmd(base64_encode($pass), '235')) !== true) return 'autenticación rechazada';
        }
        if (($x = $cmd("MAIL FROM:<$from>", '250')) !== true) return $x;
        if (($x = $cmd("RCPT TO:<$to>", '250')) !== true) return $x;
        if (($x = $cmd('DATA', '354')) !== true) return $x;
        $data = $hdr . $msg; $data = preg_replace('/^\./m', '..', $data);
        fwrite($fp, $data . "\r\n.\r\n");
        $r = $read(); $cmd('QUIT', '221'); fclose($fp);
        return strpos($r, '250') === 0 ? true : trim($r);
    } catch (Throwable $e) { return $e->getMessage(); }
}

// ---- WhatsApp ----
function wa_phone(string $p): string {
    $d = preg_replace('/\D+/', '', $p);
    if (strlen($d) === 8) $d = '502' . $d;
    return $d;
}
function wa_link(string $phone, string $text): string {
    return 'https://wa.me/' . wa_phone($phone) . '?text=' . rawurlencode($text);
}
function wa_cloud_send(string $phone, string $text) {
    $tok = S('wa_token'); $pid = S('wa_phone_id');
    if (!$tok || !$pid) return 'WhatsApp Cloud API sin configurar';
    $tplName = S('wa_template_name');
    $payload = ['messaging_product' => 'whatsapp', 'to' => wa_phone($phone)];
    if ($tplName) {
        $payload['type'] = 'template';
        $payload['template'] = ['name' => $tplName, 'language' => ['code' => S('wa_template_lang', 'es')], 'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr(preg_replace('/\s+/', ' ', $text), 0, 1000)]]]]];
    } else { $payload['type'] = 'text'; $payload['text'] = ['body' => $text]; }
    $ch = curl_init('https://graph.facebook.com/v19.0/' . rawurlencode($pid) . '/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tok, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload)]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($r === false) return 'Error de red: ' . $err;
    if ($code >= 200 && $code < 300) return true;
    $j = json_decode($r, true);
    return 'Meta respondió ' . $code . ': ' . ($j['error']['message'] ?? substr($r, 0, 150));
}

// ---- Cola ----
function enqueue(string $channel, ?int $uid, string $to, ?string $subject, string $body, ?string $dedupe = null): ?int {
    if ($dedupe && val("SELECT COUNT(*) FROM queue WHERE dedupe_key=?", [$dedupe])) return null;
    $link = $channel === 'whatsapp' ? wa_link($to, $body) : null;
    return insert('queue', ['user_id' => $uid, 'channel' => $channel, 'to_addr' => $to, 'subject' => $subject, 'body' => $body, 'wa_link' => $link, 'status' => 'pendiente', 'dedupe_key' => $dedupe, 'created_at' => now()]);
}
/** Envía un correo transaccional de plantilla (verificación, recuperación...). */
function send_template_mail(?int $uid, string $to, string $key, array $vars, ?string $dedupe = null): void {
    [$s, $b] = tpl_render($key, $vars);
    $id = enqueue('email', $uid, $to, $s, $b, $dedupe);
    if ($id) process_queue(5, $id);
}
function process_queue(int $limit = 50, ?int $only = null): array {
    $sent = 0; $err = 0; $manual = 0;
    $where = $only ? "id=" . (int) $only : "status='pendiente'";
    foreach (rows("SELECT * FROM queue WHERE $where ORDER BY id LIMIT " . (int) $limit) as $m) {
        if ($m['channel'] === 'email') {
            $r = send_mail($m['to_addr'], (string) $m['subject'], $m['body']);
            if ($r === true) { update('queue', ['status' => 'enviado', 'sent_at' => now(), 'error' => null], 'id=?', [$m['id']]); $sent++; }
            else { update('queue', ['status' => 'error', 'error' => mb_substr($r, 0, 390)], 'id=?', [$m['id']]); $err++; }
        } else {
            if (S('wa_provider', 'manual') === 'cloud') {
                $r = wa_cloud_send($m['to_addr'], $m['body']);
                if ($r === true) { update('queue', ['status' => 'enviado', 'sent_at' => now(), 'error' => null], 'id=?', [$m['id']]); $sent++; }
                else { update('queue', ['status' => 'error', 'error' => mb_substr($r, 0, 390)], 'id=?', [$m['id']]); $err++; }
            } else { update('queue', ['status' => 'manual'], 'id=?', [$m['id']]); $manual++; }
        }
    }
    return compact('sent', 'err', 'manual');
}

// ---- Alertas de coincidencias ----
function build_alert_list(array $matches): string {
    $l = '';
    foreach ($matches as $m) {
        $d = days_left($m['deadline']);
        $l .= '• ' . $m['title'] . "\n  " . $m['entity'] . ' · ' . money($m['amount']) . ' · vence ' . fdate($m['deadline']) . ($d !== null ? " ($d días)" : '') . "\n  Por qué: " . $m['reasons'] . "\n  " . site_url('app/oportunidad/' . $m['tender_id']) . "\n\n";
    }
    return trim($l);
}
function plan_alert_room(array $u): int {
    $limit = $u['plan_id'] ? (int) val("SELECT alert_limit FROM plans WHERE id=?", [$u['plan_id']]) : (int) S('trial_alert_limit', 50);
    if ($limit <= 0) $limit = 100000;
    $used = (int) val("SELECT COUNT(*) FROM matches WHERE user_id=? AND notified_at>=?", [$u['id'], date('Y-m-01 00:00:00')]);
    return max(0, $limit - $used);
}
function create_alerts(): int {
    $count = 0;
    $hour = (int) S('digest_hour', 7);
    foreach (rows("SELECT u.*, p.alert_email, p.alert_wa, p.wa_phone, p.freq FROM users u JOIN profiles p ON p.user_id=u.id WHERE u.role='client' AND u.status='activo' AND u.sub_ends>=?", [now()]) as $u) {
        if ($u['freq'] === 'diaria' && ((int) date('G') < $hour || $u['last_digest'] === date('Y-m-d'))) continue;
        $room = plan_alert_room($u); if ($room <= 0) continue;
        $ms = rows("SELECT m.*, t.title, t.entity, t.amount, t.deadline FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? AND m.notified_at IS NULL AND m.status='nuevo' AND t.status='abierto' AND (t.deadline IS NULL OR t.deadline>=?) ORDER BY m.score DESC, t.deadline LIMIT " . min(15, $room), [$u['id'], now()]);
        if (!$ms) { if ($u['freq'] === 'diaria') update('users', ['last_digest' => date('Y-m-d')], 'id=?', [$u['id']]); continue; }
        $list = build_alert_list($ms);
        $key = 'al:' . $u['id'] . ':' . implode('-', array_column($ms, 'tender_id'));
        $vars = ['nombre' => $u['name'], 'cantidad' => count($ms), 'lista' => $list, 'panel' => site_url('app')];
        if ($u['alert_email']) { [$s, $b] = tpl_render('alert_email', $vars); enqueue('email', (int) $u['id'], $u['email'], $s, $b, $key . ':e'); }
        if ($u['alert_wa'] && $u['wa_phone']) {
            $short = '';
            foreach (array_slice($ms, 0, 5) as $m) $short .= '• ' . mb_substr($m['title'], 0, 80) . ' (vence ' . fdate($m['deadline']) . ") " . site_url('app/oportunidad/' . $m['tender_id']) . "\n";
            [, $b] = tpl_render('alert_wa', $vars + ['lista' => trim($short)]);
            enqueue('whatsapp', (int) $u['id'], $u['wa_phone'], null, $b, $key . ':w');
        }
        q("UPDATE matches SET notified_at=? WHERE id IN (" . implode(',', array_map('intval', array_column($ms, 'id'))) . ")", [now()]);
        if ($u['freq'] === 'diaria') update('users', ['last_digest' => date('Y-m-d')], 'id=?', [$u['id']]);
        $count += count($ms);
    }
    return $count;
}
/** Recordatorios de vencimiento de suscripción (3 días, 1 día, vencida). */
function subscription_reminders(): int {
    $n = 0;
    foreach (rows("SELECT * FROM users WHERE role='client' AND status='activo' AND sub_ends IS NOT NULL AND sub_ends BETWEEN ? AND ?", [date('Y-m-d H:i:s', time() - 2 * 86400), date('Y-m-d H:i:s', time() + 4 * 86400)]) as $u) {
        $d = (strtotime($u['sub_ends']) - time()) / 86400;
        $key = $d < 0 ? 'expired' : ($d <= 1 ? 'renewal_1d' : ($d <= 3 ? 'renewal_3d' : null));
        if (!$key) continue;
        $plan = $u['plan_id'] ? val("SELECT name FROM plans WHERE id=?", [$u['plan_id']]) : 'Prueba gratis';
        if (send_reminder($u, $key, $plan)) $n++;
    }
    return $n;
}
function send_reminder(array $u, string $key, string $plan): bool {
    $vars = ['nombre' => $u['name'], 'fecha' => fdate($u['sub_ends']), 'plan' => $plan, 'enlace' => site_url('app/suscripcion')];
    $dk = 'sub:' . $u['id'] . ':' . $key . ':' . date('Ymd', strtotime($u['sub_ends']));
    [$s, $b] = tpl_render($key, $vars);
    $id = enqueue('email', (int) $u['id'], $u['email'], $s, $b, $dk);
    $p = row("SELECT wa_phone, alert_wa FROM profiles WHERE user_id=?", [$u['id']]);
    if ($p && $p['alert_wa'] && $p['wa_phone']) enqueue('whatsapp', (int) $u['id'], $p['wa_phone'], null, $b, $dk . ':w');
    return (bool) $id;
}
