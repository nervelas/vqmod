<?php
/** Panel de administración: tablero, clientes, planes, pagos y concursos. */

function approve_payment(int $pid): bool {
    $p = row("SELECT * FROM payments WHERE id=?", [$pid]);
    if (!$p || $p['status'] === 'aprobado') return false;
    $u = row("SELECT * FROM users WHERE id=?", [$p['user_id']]); if (!$u) return false;
    $base = max(time(), $u['sub_ends'] ? strtotime($u['sub_ends']) : time());
    $end = date('Y-m-d H:i:s', $base + 86400 * max(1, (int) $p['period_days']));
    update('users', ['plan_id' => $p['plan_id'], 'sub_ends' => $end], 'id=?', [$u['id']]);
    update('payments', ['status' => 'aprobado', 'approved_at' => now()], 'id=?', [$pid]);
    $plan = $p['plan_id'] ? val("SELECT name FROM plans WHERE id=?", [$p['plan_id']]) : 'Plan';
    send_template_mail((int) $u['id'], $u['email'], 'payment_approved', ['nombre' => $u['name'], 'plan' => $plan, 'fecha' => fdate($end)]);
    return true;
}
function tender_from_post(): array {
    $dl = post('deadline'); $st = post('status');
    return ['title' => mb_substr(post('title'), 0, 400), 'description' => post('description') ?: null, 'entity' => mb_substr(post('entity'), 0, 250), 'department' => in_array(post('department'), array_merge(deptos(), ['Nacional']), true) ? post('department') : 'Nacional',
        'modality' => isset(modalidades()[post('modality')]) ? post('modality') : 'cotizacion', 'category' => isset(categorias()[post('category')]) ? post('category') : 'otros', 'amount' => to_amount(post('amount')),
        'publish_date' => to_date(post('publish_date')) ?: date('Y-m-d'), 'deadline' => $dl ? to_date(str_replace('T', ' ', $dl), true) : null, 'status' => in_array($st, ['abierto', 'adjudicado', 'cerrado', 'desierto'], true) ? $st : 'abierto',
        'awarded_amount' => to_amount(post('awarded_amount')), 'awarded_to' => mb_substr(post('awarded_to'), 0, 250) ?: null, 'url' => mb_substr(post('url'), 0, 500) ?: null, 'requirements' => post('requirements') ?: null];
}

route('GET', '/admin', function () {
    $a = require_admin('dashboard');
    $m0 = date('Y-m-01 00:00:00'); $t0 = date('Y-m-d 00:00:00');
    $d = [
        'active' => (int) val("SELECT COUNT(*) FROM users WHERE role='client' AND status='activo' AND sub_ends>=?", [now()]),
        'paying' => (int) val("SELECT COUNT(*) FROM users WHERE role='client' AND status='activo' AND plan_id IS NOT NULL AND sub_ends>=?", [now()]),
        'income' => (float) val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='aprobado' AND approved_at>=?", [$m0]),
        'imported' => (int) val("SELECT COUNT(*) FROM tenders WHERE created_at>=?", [$t0]),
        'alerts' => (int) val("SELECT COUNT(*) FROM queue WHERE status IN ('enviado','manual') AND created_at>=?", [$t0]),
        'pending_pay' => (int) val("SELECT COUNT(*) FROM payments WHERE status='pendiente'"),
        'expiring' => rows("SELECT id,name,email,sub_ends,plan_id FROM users WHERE role='client' AND status='activo' AND sub_ends BETWEEN ? AND ? ORDER BY sub_ends LIMIT 10", [now(), date('Y-m-d H:i:s', time() + 7 * 86400)]),
        'run' => row("SELECT * FROM sync_runs ORDER BY id DESC LIMIT 1"),
        'open' => (int) val("SELECT COUNT(*) FROM tenders WHERE status='abierto'"), 'total' => (int) val("SELECT COUNT(*) FROM tenders"),
    ];
    // Serie de 14 días para la gráfica
    $d['s_imp'] = $d['s_alert'] = $d['s_lbl'] = [];
    for ($i = 13; $i >= 0; $i--) {
        $ds = date('Y-m-d', strtotime("-$i days"));
        $d['s_lbl'][] = date('j', strtotime($ds));
        $d['s_imp'][] = (int) val("SELECT COUNT(*) FROM tenders WHERE created_at>=? AND created_at<?", [$ds . ' 00:00:00', date('Y-m-d', strtotime($ds . ' +1 day')) . ' 00:00:00']);
        $d['s_alert'][] = (int) val("SELECT COUNT(*) FROM queue WHERE created_at>=? AND created_at<?", [$ds . ' 00:00:00', date('Y-m-d', strtotime($ds . ' +1 day')) . ' 00:00:00']);
    }
    render('admin/dashboard', ['d' => $d, 'a' => $a, 'title' => 'Tablero'], 'admin');
});

// ---- Clientes ----
route('GET', '/admin/clientes', function () {
    require_admin('clientes');
    $w = ["role='client'"]; $p = [];
    if ($qq = get('q')) { $w[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)"; array_push($p, "%$qq%", "%$qq%", "%$qq%"); }
    $f = get('f');
    if ($f === 'activos') { $w[] = "status='activo' AND sub_ends>=?"; $p[] = now(); }
    elseif ($f === 'vencidos') { $w[] = "(sub_ends<? OR sub_ends IS NULL)"; $p[] = now(); }
    elseif ($f === 'prueba') { $w[] = "plan_id IS NULL AND sub_ends>=?"; $p[] = now(); }
    elseif ($f === 'suspendidos') $w[] = "status='suspendido'";
    $page = max(1, (int) get('p', '1')); $per = 25;
    $tot = (int) val("SELECT COUNT(*) FROM users WHERE " . implode(' AND ', $w), $p);
    $list = rows("SELECT u.*, (SELECT name FROM plans WHERE id=u.plan_id) AS plan_name, (SELECT COUNT(*) FROM matches WHERE user_id=u.id) AS nmatch FROM users u WHERE " . implode(' AND ', $w) . " ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
    render('admin/clients', ['list' => $list, 'tot' => $tot, 'page' => $page, 'per' => $per, 'title' => 'Clientes'], 'admin');
});
route('GET', '/admin/clientes/nuevo', function () { require_admin('clientes'); render('admin/client_form', ['c' => null, 'plans' => rows("SELECT * FROM plans ORDER BY sort_order"), 'title' => 'Nuevo cliente'], 'admin'); });
route('GET', '/admin/clientes/{id}', function ($id) {
    require_admin('clientes'); $c = row("SELECT * FROM users WHERE id=? AND role='client'", [(int) $id]); if (!$c) not_found();
    $ms = rows("SELECT t.id,t.title,t.deadline,m.score,m.status,m.reasons FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? ORDER BY m.score DESC LIMIT 30", [$c['id']]);
    render('admin/client_form', ['c' => $c, 'p' => client_profile((int) $c['id']), 'ms' => $ms, 'plans' => rows("SELECT * FROM plans ORDER BY sort_order"), 'pays' => rows("SELECT * FROM payments WHERE user_id=? ORDER BY id DESC LIMIT 10", [$c['id']]), 'title' => $c['name']], 'admin');
});
route('POST', '/admin/clientes/guardar', function () {
    require_admin('clientes'); $id = (int) post('id');
    $email = mb_strtolower(post('email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || post('name') === '') { flash('Nombre y correo válidos son obligatorios.', 'err'); redirect($id ? "admin/clientes/$id" : 'admin/clientes/nuevo'); }
    if (val("SELECT COUNT(*) FROM users WHERE email=? AND id<>?", [$email, $id])) { flash('Ese correo ya existe.', 'err'); redirect($id ? "admin/clientes/$id" : 'admin/clientes/nuevo'); }
    $d = ['name' => mb_substr(post('name'), 0, 150), 'email' => $email, 'phone' => preg_replace('/[^0-9+ \-]/', '', post('phone')), 'status' => post('status') === 'suspendido' ? 'suspendido' : 'activo',
          'plan_id' => (int) post('plan_id') ?: null, 'sub_ends' => post('sub_ends') ? to_date(str_replace('T', ' ', post('sub_ends')), true) : null];
    if ($id) { update('users', $d, "id=? AND role='client'", [$id]); }
    else {
        $pw = post('pass') !== '' ? post('pass') : bin2hex(random_bytes(5)) . 'A1';
        $d += ['role' => 'client', 'pass' => hash_pass($pw), 'created_at' => now(), 'email_verified' => 1];
        if (!$d['sub_ends']) $d['sub_ends'] = date('Y-m-d H:i:s', time() + 86400 * (int) S('trial_days', 7));
        $id = insert('users', $d); insert('profiles', ['user_id' => $id, 'wa_phone' => $d['phone']]);
        flash('Cliente creado. Contraseña temporal: ' . $pw . ' (cópiala ahora; no se vuelve a mostrar).');
    }
    flash('Cliente guardado.'); redirect("admin/clientes/$id");
});
route('POST', '/admin/clientes/{id}/accion', function ($id) {
    $a = require_admin('clientes'); $c = row("SELECT * FROM users WHERE id=? AND role='client'", [(int) $id]); if (!$c) not_found();
    switch (post('accion')) {
        case 'extender':
            $days = max(1, min(365, (int) post('dias', '7'))); $base = max(time(), $c['sub_ends'] ? strtotime($c['sub_ends']) : time());
            update('users', ['sub_ends' => date('Y-m-d H:i:s', $base + 86400 * $days)], 'id=?', [$c['id']]); flash("Se extendió $days días."); break;
        case 'suspender': update('users', ['status' => 'suspendido'], 'id=?', [$c['id']]); flash('Cliente suspendido.'); break;
        case 'activar': update('users', ['status' => 'activo'], 'id=?', [$c['id']]); flash('Cliente activado.'); break;
        case 'recalcular': $n = match_for_user((int) $c['id']); flash("Coincidencias recalculadas: $n."); break;
        case 'enlace':
            $tok = make_token((int) $c['id'], 'reset', 3600);
            send_template_mail((int) $c['id'], $c['email'], 'reset', ['nombre' => $c['name'], 'enlace' => site_url('restablecer/' . $tok)]); flash('Se envió un enlace para restablecer la contraseña.'); break;
        case 'impersonar':
            $_SESSION['impersonator'] = (int) $a['id']; session_regenerate_id(true); $_SESSION['uid'] = (int) $c['id']; flash('Estás viendo el panel como ' . $c['name'] . '.'); redirect('app');
        case 'eliminar':
            foreach (['matches', 'checks', 'drafts', 'payments', 'profiles', 'tokens', 'queue'] as $t) q("DELETE FROM $t WHERE user_id=?", [$c['id']]);
            q("DELETE FROM users WHERE id=?", [$c['id']]); flash('Cliente eliminado.'); redirect('admin/clientes');
    }
    redirect('admin/clientes/' . (int) $id);
});
route('POST', '/admin/volver', function () {
    $aid = $_SESSION['impersonator'] ?? null; if (!$aid) redirect('');
    unset($_SESSION['impersonator']); session_regenerate_id(true); $_SESSION['uid'] = (int) $aid; redirect('admin/clientes');
});

// ---- Planes ----
route('GET', '/admin/planes', function () { require_admin('planes'); render('admin/plans', ['list' => rows("SELECT p.*, (SELECT COUNT(*) FROM users WHERE plan_id=p.id) AS nusers FROM plans p ORDER BY sort_order"), 'edit' => get('e') ? row("SELECT * FROM plans WHERE id=?", [(int) get('e')]) : null, 'title' => 'Planes'], 'admin'); });
route('POST', '/admin/planes/guardar', function () {
    require_admin('planes'); $id = (int) post('id');
    $d = ['name' => mb_substr(post('name'), 0, 100), 'price' => to_amount(post('price')) ?? 0, 'period_days' => max(1, (int) post('period_days', '30')), 'kw_limit' => max(1, (int) post('kw_limit', '10')), 'alert_limit' => max(0, (int) post('alert_limit', '100')), 'features' => post('features'),
        'pay_link' => filter_var(post('pay_link'), FILTER_VALIDATE_URL) ? post('pay_link') : null, 'highlight' => post('highlight') ? 1 : 0, 'active' => post('active') ? 1 : 0, 'sort_order' => (int) post('sort_order')];
    if ($d['name'] === '') { flash('El plan necesita un nombre.', 'err'); redirect('admin/planes'); }
    if ($id) update('plans', $d, 'id=?', [$id]); else insert('plans', $d);
    flash('Plan guardado.'); redirect('admin/planes');
});
route('POST', '/admin/planes/{id}/eliminar', function ($id) {
    require_admin('planes');
    if (val("SELECT COUNT(*) FROM users WHERE plan_id=?", [(int) $id])) { flash('Hay clientes con este plan; desactívalo en lugar de eliminarlo.', 'err'); redirect('admin/planes'); }
    q("DELETE FROM plans WHERE id=?", [(int) $id]); flash('Plan eliminado.'); redirect('admin/planes');
});

// ---- Pagos ----
route('GET', '/admin/pagos', function () {
    require_admin('pagos'); $f = get('f', 'pendiente');
    $w = in_array($f, ['pendiente', 'aprobado', 'rechazado'], true) ? "p.status='$f'" : '1=1';
    render('admin/payments', ['list' => rows("SELECT p.*, u.name, u.email, pl.name AS plan_name FROM payments p JOIN users u ON u.id=p.user_id LEFT JOIN plans pl ON pl.id=p.plan_id WHERE $w ORDER BY p.id DESC LIMIT 100"), 'f' => $f, 'plans' => rows("SELECT * FROM plans WHERE active=1 ORDER BY sort_order"), 'clients' => rows("SELECT id,name,email FROM users WHERE role='client' ORDER BY name LIMIT 500"), 'title' => 'Pagos'], 'admin');
});
route('POST', '/admin/pagos/registrar', function () {
    require_admin('pagos'); $uid = (int) post('user_id'); $plan = row("SELECT * FROM plans WHERE id=?", [(int) post('plan_id')]);
    if (!val("SELECT COUNT(*) FROM users WHERE id=? AND role='client'", [$uid]) || !$plan) { flash('Elige cliente y plan.', 'err'); redirect('admin/pagos'); }
    try { $file = save_upload('receipt'); } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); redirect('admin/pagos'); }
    $pid = insert('payments', ['user_id' => $uid, 'plan_id' => $plan['id'], 'amount' => to_amount(post('amount')) ?? $plan['price'], 'method' => mb_substr(post('method', 'Transferencia'), 0, 40), 'reference' => mb_substr(post('reference'), 0, 120), 'receipt' => $file, 'period_days' => max(1, (int) (post('period_days') !== '' ? post('period_days') : $plan['period_days'])), 'notes' => mb_substr(post('notes'), 0, 400), 'status' => 'pendiente', 'created_at' => now()]);
    approve_payment($pid); flash('Pago registrado y plan activado.'); redirect('admin/pagos?f=aprobado');
});
route('POST', '/admin/pagos/{id}/{acc}', function ($id, $acc) {
    require_admin('pagos');
    if ($acc === 'aprobar') { flash(approve_payment((int) $id) ? 'Pago aprobado y plan activado.' : 'Ese pago ya estaba aprobado.'); }
    elseif ($acc === 'rechazar') { update('payments', ['status' => 'rechazado', 'notes' => mb_substr(post('notes'), 0, 400)], "id=? AND status='pendiente'", [(int) $id]); flash('Pago rechazado.'); }
    redirect('admin/pagos');
});

// ---- Concursos ----
route('GET', '/admin/concursos', function () {
    require_admin('concursos'); $w = ['1=1']; $p = [];
    if ($qq = get('q')) { $w[] = "(title LIKE ? OR entity LIKE ? OR ext_id LIKE ?)"; array_push($p, "%$qq%", "%$qq%", "%$qq%"); }
    if (in_array(get('st'), ['abierto', 'adjudicado', 'cerrado', 'desierto'], true)) { $w[] = 'status=?'; $p[] = get('st'); }
    if (get('demo') === '1') $w[] = 'is_demo=1';
    $page = max(1, (int) get('p', '1')); $per = 30;
    $tot = (int) val("SELECT COUNT(*) FROM tenders WHERE " . implode(' AND ', $w), $p);
    render('admin/tenders', ['list' => rows("SELECT * FROM tenders WHERE " . implode(' AND ', $w) . " ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p), 'tot' => $tot, 'page' => $page, 'per' => $per, 'title' => 'Concursos'], 'admin');
});
route('GET', '/admin/concursos/nuevo', function () { require_admin('concursos'); render('admin/tender_form', ['t' => null, 'title' => 'Nuevo concurso'], 'admin'); });
route('GET', '/admin/concursos/importar', function () { require_admin('concursos'); render('admin/tender_import', ['step' => 1, 'title' => 'Importar CSV'], 'admin'); });
route('POST', '/admin/concursos/importar', function () {
    require_admin('concursos');
    try { $f = save_upload('csv', 'tmp', ['csv', 'txt'], 20 * 1048576); } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); redirect('admin/concursos/importar'); }
    if (!$f) { flash('Elige un archivo CSV.', 'err'); redirect('admin/concursos/importar'); }
    $rows = csv_parse(file_get_contents(APP_ROOT . '/storage/tmp/' . $f));
    if (count($rows) < 2) { flash('El CSV está vacío o no tiene encabezados.', 'err'); redirect('admin/concursos/importar'); }
    $header = array_map('trim', $rows[0]);
    render('admin/tender_import', ['step' => 2, 'file' => $f, 'header' => $header, 'auto' => csv_auto_map($header) ?: (json_decode(S('csv_map', '{}'), true) ?: []), 'sample' => array_slice($rows, 1, 3), 'nrows' => count($rows) - 1, 'title' => 'Importar CSV'], 'admin');
});
route('POST', '/admin/concursos/importar/aplicar', function () {
    require_admin('concursos'); $f = post('file');
    if (!preg_match('/^[a-f0-9]{24}\.(csv|txt)$/', $f) || !is_file(APP_ROOT . '/storage/tmp/' . $f)) { flash('El archivo temporal ya no existe; súbelo de nuevo.', 'err'); redirect('admin/concursos/importar'); }
    $map = []; foreach (csv_fields() as $k => $_) { $v = $_POST['map'][$k] ?? ''; if (is_string($v) && $v !== '') $map[$k] = $v; }
    try {
        $list = csv_to_tenders(file_get_contents(APP_ROOT . '/storage/tmp/' . $f), $map);
        [$new, $upd, $bad] = import_tenders($list);
        if (post('guardar_mapa')) set_setting('csv_map', json_encode($map, JSON_UNESCAPED_UNICODE));
        $tag = insert('sync_runs', ['source' => 'CSV manual', 'started_at' => now(), 'finished_at' => now(), 'status' => 'ok', 'imported' => $new, 'updated' => $upd, 'message' => "Importación manual: $new nuevos, $upd actualizados, $bad omitidos."]);
        match_all();
        flash("Importación lista: $new nuevos, $upd actualizados" . ($bad ? ", $bad omitidos" : '') . '.');
    } catch (Throwable $e) { flash('No se pudo importar: ' . $e->getMessage(), 'err'); }
    @unlink(APP_ROOT . '/storage/tmp/' . $f); redirect('admin/concursos');
});
route('GET', '/admin/concursos/{id}', function ($id) {
    require_admin('concursos'); $t = row("SELECT * FROM tenders WHERE id=?", [(int) $id]); if (!$t) not_found();
    render('admin/tender_form', ['t' => $t, 'nmatch' => (int) val("SELECT COUNT(*) FROM matches WHERE tender_id=?", [$t['id']]), 'title' => 'Editar concurso'], 'admin');
});
route('POST', '/admin/concursos/guardar', function () {
    require_admin('concursos'); $id = (int) post('id'); $d = tender_from_post();
    if ($d['title'] === '') { flash('El título es obligatorio.', 'err'); redirect($id ? "admin/concursos/$id" : 'admin/concursos/nuevo'); }
    if ($d['status'] === 'adjudicado' && !$d['awarded_amount']) { flash('Para marcar como adjudicado indica el monto final.', 'err'); redirect($id ? "admin/concursos/$id" : 'admin/concursos/nuevo'); }
    $d['norm_text'] = tender_norm_text($d);
    if ($id) update('tenders', $d, 'id=?', [$id]);
    else { $d['ext_id'] = 'MAN-' . strtoupper(bin2hex(random_bytes(4))); $d['created_at'] = now(); $id = insert('tenders', $d); }
    flash('Concurso guardado.'); redirect('admin/concursos/' . $id);
});
route('POST', '/admin/concursos/{id}/eliminar', function ($id) {
    require_admin('concursos'); foreach (['matches', 'checks', 'drafts'] as $t) q("DELETE FROM $t WHERE tender_id=?", [(int) $id]);
    q("DELETE FROM tenders WHERE id=?", [(int) $id]); flash('Concurso eliminado.'); redirect('admin/concursos');
});
route('POST', '/admin/concursos/borrar-demo', function () {
    require_admin('concursos'); $ids = array_column(rows("SELECT id FROM tenders WHERE is_demo=1"), 'id');
    if ($ids) { $in = implode(',', array_map('intval', $ids)); foreach (['matches', 'checks', 'drafts'] as $t) q("DELETE FROM $t WHERE tender_id IN ($in)"); q("DELETE FROM tenders WHERE id IN ($in)"); }
    flash(count($ids) . ' concursos demo eliminados.'); redirect('admin/concursos');
});
