<?php
/** Panel del cliente. */

function client_profile(int $uid): array {
    $p = row("SELECT * FROM profiles WHERE user_id=?", [$uid]);
    if (!$p) { insert('profiles', ['user_id' => $uid]); $p = row("SELECT * FROM profiles WHERE user_id=?", [$uid]); }
    return $p;
}
function client_plan(array $u): array {
    $pl = $u['plan_id'] ? row("SELECT * FROM plans WHERE id=?", [$u['plan_id']]) : null;
    return $pl ?: ['id' => null, 'name' => 'Prueba gratis', 'kw_limit' => (int) S('trial_kw_limit', 10), 'alert_limit' => (int) S('trial_alert_limit', 50), 'price' => 0, 'features' => ''];
}
/** Exige sesión de cliente con suscripción vigente. */
function client_gate(bool $needAccess = true): array {
    $u = require_client();
    if ($needAccess && !client_has_access($u)) { flash('Tu suscripción venció. Renueva para volver a ver tus oportunidades.', 'err'); redirect('app/suscripcion'); }
    return $u;
}
function my_match(int $uid, int $tid): ?array { return row("SELECT * FROM matches WHERE user_id=? AND tender_id=?", [$uid, $tid]); }
function set_match_status(int $uid, int $tid, string $st): void {
    q("INSERT INTO matches (user_id,tender_id,score,reasons,status,notified_at,created_at) VALUES (?,?,0,'Agregado por ti',?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)", [$uid, $tid, $st, now(), now()]);
}

route('GET', '/app', function () {
    $u = client_gate(); if (!$u['onboarded']) redirect('app/asistente');
    $tab = get('tab', 'activas'); $ord = get('orden', 'relevancia'); $page = max(1, (int) get('p', '1')); $per = 15;
    $tabs = ['activas' => "m.status IN ('nuevo','guardado','participando') AND t.status='abierto'", 'nuevo' => "m.status='nuevo' AND t.status='abierto'", 'guardado' => "m.status='guardado'", 'participando' => "m.status IN ('participando','ganado','perdido')", 'descartado' => "m.status='descartado'"];
    if (!isset($tabs[$tab])) $tab = 'activas';
    $counts = []; foreach ($tabs as $k => $w) $counts[$k] = (int) val("SELECT COUNT(*) FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? AND $w", [$u['id']]);
    $orderBy = $ord === 'fecha' ? "t.deadline IS NULL, t.deadline ASC, m.score DESC" : "m.score DESC, t.deadline IS NULL, t.deadline ASC";
    $items = rows("SELECT t.*, m.score, m.reasons, m.status AS mstatus FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? AND {$tabs[$tab]} ORDER BY $orderBy LIMIT $per OFFSET " . (($page - 1) * $per), [$u['id']]);
    $prof = client_profile((int) $u['id']);
    render('client/dashboard', compact('u', 'items', 'tab', 'ord', 'counts', 'page', 'per', 'prof') + ['title' => 'Mis oportunidades'], 'client');
});

route('GET', '/app/asistente', function () {
    $u = require_client(); $p = client_profile((int) $u['id']);
    render('client/wizard', ['u' => $u, 'p' => $p, 'plan' => client_plan($u), 'title' => 'Asistente inicial'], 'client');
});
route('POST', '/app/asistente', function () {
    $u = require_client(); $plan = client_plan($u);
    $kws = array_slice(array_values(array_unique(array_filter(array_map(fn($k) => mb_substr(trim($k), 0, 60), explode(',', post('keywords')))))), 0, 200);
    if (!$kws) { flash('Escribe al menos una palabra clave de lo que vendes.', 'err'); redirect('app/asistente'); }
    if (count($kws) > $plan['kw_limit']) { $kws = array_slice($kws, 0, (int) $plan['kw_limit']); flash('Tu plan permite ' . $plan['kw_limit'] . ' palabras clave; guardamos las primeras. Puedes mejorar tu plan.', 'warn'); }
    $deps = array_values(array_intersect(postarr('departments'), deptos()));
    $mods = array_values(array_intersect(postarr('modalities'), array_keys(modalidades())));
    $min = to_amount(post('min_amount')); $max = to_amount(post('max_amount'));
    update('profiles', ['keywords' => implode(', ', $kws), 'departments' => implode(',', $deps), 'modalities' => implode(',', $mods), 'min_amount' => $min, 'max_amount' => $max], 'user_id=?', [$u['id']]);
    update('users', ['onboarded' => 1], 'id=?', [$u['id']]);
    q("DELETE FROM matches WHERE user_id=? AND status='nuevo'", [$u['id']]);
    $n = match_for_user((int) $u['id']);
    flash($n ? "¡Listo! Encontramos $n oportunidades para ti." : 'Preferencias guardadas. Aún no hay concursos que coincidan; te avisaremos cuando aparezcan.');
    redirect('app');
});
route('GET', '/app/api/sugerir', function () {
    require_client(); $term = norm(get('q')); $out = [];
    if (strlen($term) >= 2) {
        foreach (rows("SELECT term, synonyms FROM synonyms") as $s) foreach (array_merge([$s['term']], explode(',', $s['synonyms'])) as $w) { $w = trim($w); if ($w !== '' && strpos(norm($w), $term) !== false) $out[$w] = 1; }
        foreach (rows("SELECT keywords FROM sectors") as $s) foreach (explode(',', $s['keywords']) as $w) { $w = trim($w); if ($w !== '' && strpos(norm($w), $term) !== false) $out[$w] = 1; }
        foreach (rows("SELECT title FROM tenders WHERE norm_text LIKE ? ORDER BY id DESC LIMIT 80", ['%' . $term . '%']) as $t)
            foreach (explode(' ', norm($t['title'])) as $w) if (strlen($w) > 3 && strpos($w, $term) === 0) $out[$w] = 1;
    }
    json_out(array_slice(array_keys($out), 0, 8));
});

route('GET', '/app/oportunidad/{id}', function ($id) {
    $u = client_gate(); $t = row("SELECT * FROM tenders WHERE id=?", [(int) $id]); if (!$t) not_found();
    $m = my_match((int) $u['id'], (int) $t['id']);
    $items = array_values(array_filter(array_map('trim', explode("\n", (string) $t['requirements']))));
    $done = array_column(rows("SELECT item_idx FROM checks WHERE user_id=? AND tender_id=?", [$u['id'], $t['id']]), 'item_idx');
    $drafts = rows("SELECT id, source, created_at FROM drafts WHERE user_id=? AND tender_id=? ORDER BY id DESC LIMIT 5", [$u['id'], $t['id']]);
    render('client/detail', ['u' => $u, 't' => $t, 'm' => $m, 'items' => $items, 'done' => $done, 'price' => price_stats($t), 'drafts' => $drafts, 'title' => $t['title']], 'client');
});
route('POST', '/app/accion/{id}', function ($id) {
    $u = client_gate(); $t = row("SELECT id FROM tenders WHERE id=?", [(int) $id]); if (!$t) not_found();
    $map = ['guardar' => 'guardado', 'descartar' => 'descartado', 'participar' => 'participando', 'ganado' => 'ganado', 'perdido' => 'perdido', 'restaurar' => 'nuevo'];
    $a = post('accion');
    if (!isset($map[$a])) { flash('Acción no válida.', 'err'); redirect('app'); }
    set_match_status((int) $u['id'], (int) $t['id'], $map[$a]);
    if (post('ajax')) json_out(['ok' => true, 'status' => $map[$a]]);
    $msgs = ['guardado' => 'Guardado en tu lista.', 'descartado' => 'Descartado. Puedes recuperarlo en la pestaña "Descartadas".', 'participando' => '¡Éxito! Marcado como "Voy a participar". Revisa los requisitos.', 'ganado' => '¡Felicidades por ganar!', 'perdido' => 'Anotado. Cada oferta suma experiencia.', 'nuevo' => 'Restaurado.'];
    flash($msgs[$map[$a]]);
    $back = post('volver'); redirect($back === 'detalle' ? 'app/oportunidad/' . (int) $id : 'app');
});
route('POST', '/app/check/{id}', function ($id) {
    $u = client_gate(); $i = (int) post('idx'); $tid = (int) $id;
    if (post('on') === '1') q("INSERT IGNORE INTO checks (user_id,tender_id,item_idx) VALUES (?,?,?)", [$u['id'], $tid, $i]);
    else q("DELETE FROM checks WHERE user_id=? AND tender_id=? AND item_idx=?", [$u['id'], $tid, $i]);
    json_out(['ok' => true]);
});
route('POST', '/app/generar/{id}', function ($id) {
    $u = client_gate(); $t = row("SELECT * FROM tenders WHERE id=?", [(int) $id]); if (!$t) not_found();
    $last = val("SELECT COUNT(*) FROM drafts WHERE user_id=? AND created_at>=?", [$u['id'], date('Y-m-d H:i:s', time() - 60)]);
    if ($last >= 5) { flash('Espera un momento antes de generar otro borrador.', 'warn'); redirect('app/oportunidad/' . $t['id']); }
    $d = make_draft($t, $u);
    redirect('app/borrador/' . $d['id']);
});
function load_draft(int $id, array $u): array {
    $d = row("SELECT * FROM drafts WHERE id=? AND user_id=?", [$id, $u['id']]); if (!$d) not_found();
    $t = row("SELECT * FROM tenders WHERE id=?", [$d['tender_id']]); if (!$t) not_found();
    return [$d, $t, json_decode($d['content'], true)];
}
route('GET', '/app/borrador/{id}', function ($id) {
    $u = client_gate(); [$d, $t, $c] = load_draft((int) $id, $u);
    render('client/draft', ['u' => $u, 'd' => $d, 't' => $t, 'c' => $c, 'title' => 'Borrador de oferta'], 'client');
});
route('GET', '/app/borrador/{id}/{fmt}', function ($id, $fmt) {
    $u = client_gate(); [$d, $t, $c] = load_draft((int) $id, $u);
    $fn = 'borrador-oferta-' . slugify(mb_substr($t['title'], 0, 40));
    if ($fmt === 'pdf') { header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $fn . '.pdf"'); echo build_pdf($c, $t); exit; }
    if ($fmt === 'docx') {
        $tmp = APP_ROOT . '/storage/tmp/' . bin2hex(random_bytes(6)) . '.docx';
        if (!build_docx($c, $t, $tmp)) { flash('Este servidor no tiene la extensión ZIP; descarga el PDF.', 'err'); redirect('app/borrador/' . (int) $id); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document'); header('Content-Disposition: attachment; filename="' . $fn . '.docx"'); header('Content-Length: ' . filesize($tmp));
        readfile($tmp); @unlink($tmp); exit;
    }
    not_found();
});

route('GET', '/app/calendario', function () {
    $u = client_gate();
    $items = rows("SELECT t.id,t.title,t.entity,t.deadline,t.amount,m.status AS mstatus,m.score FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? AND m.status IN ('nuevo','guardado','participando') AND t.status='abierto' AND t.deadline>=? ORDER BY t.deadline LIMIT 200", [$u['id'], now()]);
    render('client/calendar', ['u' => $u, 'items' => $items, 'title' => 'Vencimientos'], 'client');
});
route('GET', '/app/alertas', function () { $u = client_gate(false); render('client/alerts', ['u' => $u, 'p' => client_profile((int) $u['id']), 'title' => 'Alertas'], 'client'); });
route('POST', '/app/alertas', function () {
    $u = client_gate(false);
    $wa = preg_replace('/[^0-9+ \-]/', '', post('wa_phone'));
    update('profiles', ['alert_email' => post('alert_email') ? 1 : 0, 'alert_wa' => post('alert_wa') ? 1 : 0, 'wa_phone' => $wa, 'freq' => post('freq') === 'inmediata' ? 'inmediata' : 'diaria'], 'user_id=?', [$u['id']]);
    flash('Alertas actualizadas.'); redirect('app/alertas');
});
route('GET', '/app/mensajes', function () {
    $u = client_gate(false);
    $msgs = rows("SELECT * FROM queue WHERE user_id=? AND channel='whatsapp' AND status IN ('manual','pendiente') ORDER BY id DESC LIMIT 30", [$u['id']]);
    render('client/messages', ['u' => $u, 'msgs' => $msgs, 'title' => 'Mensajes de WhatsApp'], 'client');
});
route('POST', '/app/mensajes/{id}', function ($id) {
    $u = client_gate(false);
    q("UPDATE queue SET status='enviado', sent_at=? WHERE id=? AND user_id=? AND channel='whatsapp'", [now(), (int) $id, $u['id']]);
    flash('Marcado como enviado.'); redirect('app/mensajes');
});
route('GET', '/app/perfil', function () { $u = client_gate(false); render('client/profile', ['u' => $u, 'p' => client_profile((int) $u['id']), 'title' => 'Perfil de empresa'], 'client'); });
route('POST', '/app/perfil', function () {
    $u = client_gate(false);
    update('profiles', ['company' => mb_substr(post('company'), 0, 190), 'nit' => mb_substr(post('nit'), 0, 30), 'address' => mb_substr(post('address'), 0, 250), 'legal_rep' => mb_substr(post('legal_rep'), 0, 150)], 'user_id=?', [$u['id']]);
    $name = mb_substr(post('name'), 0, 150) ?: $u['name'];
    update('users', ['name' => $name, 'phone' => preg_replace('/[^0-9+ \-]/', '', post('phone'))], 'id=?', [$u['id']]);
    if (post('new_pass') !== '') {
        if (!password_verify((string) ($_POST['cur_pass'] ?? ''), $u['pass'])) { flash('La contraseña actual no es correcta.', 'err'); redirect('app/perfil'); }
        if ($e = password_ok((string) $_POST['new_pass'])) { flash($e, 'err'); redirect('app/perfil'); }
        update('users', ['pass' => hash_pass((string) $_POST['new_pass'])], 'id=?', [$u['id']]);
    }
    flash('Perfil guardado.'); redirect('app/perfil');
});
route('GET', '/app/suscripcion', function () {
    $u = require_client();
    render('client/billing', ['u' => $u, 'plan' => client_plan($u), 'plans' => rows("SELECT * FROM plans WHERE active=1 ORDER BY sort_order"), 'pays' => rows("SELECT p.*, pl.name AS plan_name FROM payments p LEFT JOIN plans pl ON pl.id=p.plan_id WHERE p.user_id=? ORDER BY p.id DESC LIMIT 20", [$u['id']]), 'title' => 'Suscripción'], 'client');
});
route('POST', '/app/suscripcion', function () {
    $u = require_client(); $plan = row("SELECT * FROM plans WHERE id=? AND active=1", [(int) post('plan_id')]);
    if (!$plan) { flash('Elige un plan.', 'err'); redirect('app/suscripcion'); }
    try { $file = save_upload('receipt', 'uploads', ['jpg', 'jpeg', 'png', 'pdf', 'webp']); } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); redirect('app/suscripcion'); }
    if (!$file && post('reference') === '') { flash('Adjunta tu comprobante o escribe el número de boleta.', 'err'); redirect('app/suscripcion'); }
    insert('payments', ['user_id' => $u['id'], 'plan_id' => $plan['id'], 'amount' => $plan['price'], 'method' => mb_substr(post('method', 'Transferencia'), 0, 40), 'reference' => mb_substr(post('reference'), 0, 120), 'receipt' => $file, 'period_days' => $plan['period_days'], 'status' => 'pendiente', 'created_at' => now()]);
    send_template_mail((int) $u['id'], $u['email'], 'payment_received', ['nombre' => $u['name']]);
    flash('Recibimos tu comprobante. Activaremos tu plan en cuanto lo verifiquemos.'); redirect('app/suscripcion');
});
route('GET', '/app/ayuda', function () { $u = client_gate(false); render('client/help', ['u' => $u, 'faqs' => rows("SELECT * FROM faqs WHERE active=1 ORDER BY sort_order"), 'title' => 'Ayuda'], 'client'); });
route('GET', '/archivo/comprobante/{id}', function ($id) {
    $u = user(); if (!$u) redirect('ingresar');
    $p = row("SELECT * FROM payments WHERE id=?", [(int) $id]);
    $isAdmin = $u['role'] === 'admin' && can('pagos') && empty($_SESSION['impersonator']);
    if (!$p || !$p['receipt'] || (!$isAdmin && (int) $p['user_id'] !== (int) $u['id'])) not_found();
    serve_receipt($p['receipt']);
});
function serve_receipt(string $name): void {
    $f = APP_ROOT . '/storage/uploads/' . basename($name); if (!is_file($f)) not_found();
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream')); header('Content-Disposition: inline; filename="comprobante.' . $ext . '"'); header('Content-Security-Policy: default-src \'none\'; img-src \'self\' data:');
    readfile($f); exit;
}
