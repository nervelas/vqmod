<?php
/** Administración: fuente de datos, notificaciones, contenido y ajustes. */

/** Definición de formularios de ajustes: clave => [etiqueta, tipo, ayuda]. tipo: text|textarea|select:a=A,b=B|secret|color|number */
function setting_sections(): array {
    return [
    'sitio' => ['mod' => 'contenido', 'title' => 'Textos y contacto', 'fields' => [
        'site_name' => ['Nombre del sitio', 'text'], 'tagline' => ['Lema', 'text'], 'hero_title' => ['Título principal (portada)', 'text'], 'hero_sub' => ['Subtítulo de portada', 'textarea'], 'cta_text' => ['Texto del botón principal', 'text'],
        'problem_t' => ['Sección problema: título', 'text'], 'problem_d' => ['Sección problema: texto', 'textarea'],
        'how1_t' => ['Paso 1: título', 'text'], 'how1_d' => ['Paso 1: texto', 'textarea'], 'how2_t' => ['Paso 2: título', 'text'], 'how2_d' => ['Paso 2: texto', 'textarea'], 'how3_t' => ['Paso 3: título', 'text'], 'how3_d' => ['Paso 3: texto', 'textarea'],
        'contact_email' => ['Correo de contacto', 'text'], 'contact_phone' => ['Teléfono', 'text'], 'contact_whatsapp' => ['WhatsApp de soporte (con código de país)', 'text', 'Ej.: 50255551234'],
        'footer_text' => ['Texto del pie de página', 'textarea'], 'bank_info' => ['Datos para depósitos/transferencias', 'textarea', 'Se muestra al cliente en Suscripción.']]],
    'seo' => ['mod' => 'contenido', 'title' => 'SEO y colores', 'fields' => [
        'seo_title' => ['Título SEO (portada)', 'text'], 'seo_desc' => ['Descripción SEO', 'textarea'],
        'gold' => ['Color de acento (oro)', 'color'], 'accent_new' => ['Color "concurso nuevo"', 'color'], 'accent_urgent' => ['Color "vence pronto"', 'color']]],
    'general' => ['mod' => 'ajustes', 'title' => 'Generales', 'fields' => [
        'timezone' => ['Zona horaria', 'text', 'America/Guatemala'], 'trial_days' => ['Días de prueba gratis', 'number'], 'trial_kw_limit' => ['Palabras clave en la prueba', 'number'], 'trial_alert_limit' => ['Alertas/mes en la prueba', 'number'],
        'min_score' => ['Puntaje mínimo de coincidencia (0-100)', 'number', 'Más alto = menos avisos, más precisos.'], 'digest_hour' => ['Hora del resumen diario (0-23)', 'number'], 'clean_urls' => ['URLs limpias', 'select:1=Sí (recomendado),0=No (usar index.php?r=)', 'Desactívalo solo si el hosting no soporta mod_rewrite.']]],
    'correo' => ['mod' => 'ajustes', 'title' => 'Correo (SMTP)', 'fields' => [
        'smtp_host' => ['Servidor SMTP', 'text', 'Vacío = usar mail() de PHP.'], 'smtp_port' => ['Puerto', 'number'], 'smtp_secure' => ['Seguridad', 'select:tls=STARTTLS (587),ssl=SSL (465),none=Ninguna'], 'smtp_user' => ['Usuario', 'text'], 'smtp_pass' => ['Contraseña', 'secret'],
        'mail_from' => ['Correo remitente', 'text'], 'mail_from_name' => ['Nombre remitente', 'text'], 'admin_alert_email' => ['Correo para avisos de error al admin', 'text']]],
    'whatsapp' => ['mod' => 'ajustes', 'title' => 'WhatsApp', 'fields' => [
        'wa_provider' => ['Proveedor', 'select:manual=Cola manual (enlaces wa.me),cloud=WhatsApp Cloud API de Meta'], 'wa_phone_id' => ['ID del número de teléfono (Cloud API)', 'text'], 'wa_token' => ['Token de acceso permanente', 'secret'],
        'wa_template_name' => ['Nombre de plantilla aprobada (opcional)', 'text', 'Si la defines, el mensaje completo va como variable {{1}} del cuerpo.'], 'wa_template_lang' => ['Idioma de la plantilla', 'text']]],
    'ia' => ['mod' => 'ajustes', 'title' => 'Inteligencia artificial', 'fields' => [
        'ai_key' => ['Clave de la API de Claude', 'secret', 'Sin clave, los borradores usan plantillas inteligentes.'], 'ai_model' => ['Modelo', 'text']]],
    ];
}
route('GET', '/admin/config/{sec}', function ($sec) {
    $defs = setting_sections(); if (!isset($defs[$sec])) not_found(); require_admin($defs[$sec]['mod']);
    render('admin/settings', ['sec' => $sec, 'def' => $defs[$sec], 'title' => $defs[$sec]['title']], 'admin');
});
route('POST', '/admin/config/{sec}', function ($sec) {
    $defs = setting_sections(); if (!isset($defs[$sec])) not_found(); require_admin($defs[$sec]['mod']);
    foreach ($defs[$sec]['fields'] as $k => $f) {
        $v = post($k, '__none__'); if ($v === '__none__') continue;
        if ($f[1] === 'secret' && $v === '') continue;
        if ($f[1] === 'color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) continue;
        if ($k === 'timezone' && !in_array($v, timezone_identifiers_list(), true)) { flash('Zona horaria no válida.', 'err'); continue; }
        if ($k === 'mail_from' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { flash('Correo remitente no válido.', 'err'); continue; }
        if ($f[1] === 'number') $v = (string) (int) $v;
        set_setting($k, $v);
    }
    foreach ($defs[$sec]['fields'] as $k => $f) if ($f[1] === 'secret' && post($k . '_clear')) set_setting($k, '');
    if ($sec === 'sitio' && !empty($_FILES['logo']['name'])) {
        try { $n = save_upload('logo', 'uploads', ['png', 'jpg', 'jpeg', 'webp'], 1048576); if ($n) set_setting('logo', $n); } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); }
    }
    flash('Cambios guardados.'); redirect('admin/config/' . $sec);
});
route('GET', '/archivo/logo', function () {
    $n = S('logo'); if (!$n) not_found();
    $f = APP_ROOT . '/storage/uploads/' . basename($n); if (!is_file($f)) not_found();
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION)); header('Content-Type: ' . ($ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'))); header('Cache-Control: public, max-age=86400'); readfile($f); exit;
});

// ---- Fuente de datos ----
route('GET', '/admin/fuente', function () {
    require_admin('fuente');
    render('admin/source', ['runs' => rows("SELECT * FROM sync_runs ORDER BY id DESC LIMIT 25"), 'title' => 'Fuente de datos'], 'admin');
});
route('POST', '/admin/fuente', function () {
    require_admin('fuente');
    if (in_array(post('source_type'), ['demo', 'ocds', 'csv', 'none'], true)) set_setting('source_type', post('source_type'));
    $u = post('source_url'); if ($u === '' || preg_match('#^https?://#i', $u)) set_setting('source_url', $u); else flash('La URL debe empezar con http:// o https://', 'err');
    set_setting('source_ua', mb_substr(post('source_ua'), 0, 200)); set_setting('official_url_tpl', mb_substr(post('official_url_tpl'), 0, 300));
    flash('Configuración de la fuente guardada.'); redirect('admin/fuente');
});
route('POST', '/admin/fuente/sincronizar', function () {
    require_admin('fuente'); @set_time_limit(300); $r = run_sync('manual');
    flash(($r['status'] === 'ok' ? 'Sincronización completada. ' : 'Sincronización con avisos. ') . $r['message'], $r['status'] === 'ok' ? 'ok' : 'warn'); redirect('admin/fuente');
});

// ---- Notificaciones ----
route('GET', '/admin/notificaciones', function () {
    require_admin('notificaciones'); $st = get('st'); $ch = get('ch');
    $w = ['1=1']; $p = [];
    if (in_array($st, ['pendiente', 'enviado', 'error', 'manual', 'descartado'], true)) { $w[] = 'q.status=?'; $p[] = $st; }
    if (in_array($ch, ['email', 'whatsapp'], true)) { $w[] = 'q.channel=?'; $p[] = $ch; }
    render('admin/notifications', ['tpls' => rows("SELECT * FROM templates ORDER BY label"), 'queue' => rows("SELECT q.*, u.name FROM queue q LEFT JOIN users u ON u.id=q.user_id WHERE " . implode(' AND ', $w) . " ORDER BY q.id DESC LIMIT 100", $p),
        'counts' => rows("SELECT status, COUNT(*) n FROM queue GROUP BY status"), 'edit' => get('t') ? row("SELECT * FROM templates WHERE k=?", [get('t')]) : null, 'title' => 'Notificaciones'], 'admin');
});
route('POST', '/admin/notificaciones/plantilla', function () {
    require_admin('notificaciones'); $k = post('k');
    if (!val("SELECT COUNT(*) FROM templates WHERE k=?", [$k])) not_found();
    update('templates', ['subject' => mb_substr(post('subject'), 0, 250), 'body' => post('body')], 'k=?', [$k]); flash('Plantilla guardada.'); redirect('admin/notificaciones');
});
route('POST', '/admin/notificaciones/procesar', function () {
    require_admin('notificaciones'); $r = process_queue(100);
    flash("Cola procesada: {$r['sent']} enviados, {$r['manual']} para envío manual, {$r['err']} con error.", $r['err'] ? 'warn' : 'ok'); redirect('admin/notificaciones');
});
route('POST', '/admin/notificaciones/{id}/{acc}', function ($id, $acc) {
    require_admin('notificaciones');
    if ($acc === 'enviado') update('queue', ['status' => 'enviado', 'sent_at' => now()], 'id=?', [(int) $id]);
    elseif ($acc === 'descartar') update('queue', ['status' => 'descartado'], 'id=?', [(int) $id]);
    elseif ($acc === 'reintentar') { update('queue', ['status' => 'pendiente', 'error' => null], 'id=?', [(int) $id]); process_queue(1, (int) $id); }
    if (post('ajax')) json_out(['ok' => true]);
    redirect('admin/notificaciones');
});

// ---- Contenido: FAQs, sectores, páginas legales ----
route('GET', '/admin/contenido', function () {
    require_admin('contenido');
    render('admin/content', ['faqs' => rows("SELECT * FROM faqs ORDER BY sort_order"), 'sectors' => rows("SELECT * FROM sectors ORDER BY sort_order"), 'pages' => rows("SELECT * FROM pages ORDER BY slug"), 'editpage' => get('pg') ? row("SELECT * FROM pages WHERE slug=?", [get('pg')]) : null, 'title' => 'Contenido del sitio'], 'admin');
});
route('POST', '/admin/contenido/faq', function () {
    require_admin('contenido'); $id = (int) post('id');
    if (post('del')) { q("DELETE FROM faqs WHERE id=?", [$id]); flash('Pregunta eliminada.'); redirect('admin/contenido'); }
    if (post('question') === '' || post('answer') === '') { flash('Escribe la pregunta y la respuesta.', 'err'); redirect('admin/contenido'); }
    $d = ['question' => mb_substr(post('question'), 0, 300), 'answer' => post('answer'), 'sort_order' => (int) post('sort_order'), 'active' => post('active') ? 1 : 0];
    if ($id) update('faqs', $d, 'id=?', [$id]); else insert('faqs', $d); flash('Pregunta guardada.'); redirect('admin/contenido');
});
route('POST', '/admin/contenido/sector', function () {
    require_admin('contenido'); $id = (int) post('id');
    if (post('del')) { q("DELETE FROM sectors WHERE id=?", [$id]); flash('Sector eliminado.'); redirect('admin/contenido'); }
    if (post('name') === '') { flash('El sector necesita nombre.', 'err'); redirect('admin/contenido'); }
    $slug = slugify(post('name'));
    $d = ['name' => mb_substr(post('name'), 0, 100), 'slug' => $slug, 'tagline' => mb_substr(post('tagline'), 0, 250), 'keywords' => mb_substr(post('keywords'), 0, 500), 'sort_order' => (int) post('sort_order'), 'active' => post('active') ? 1 : 0];
    $img = row("SELECT image FROM sectors WHERE id=?", [$id]);
    $d['image'] = $img['image'] ?? ('img/sector-' . (is_file(APP_ROOT . "/assets/img/sector-$slug.svg") ? $slug : 'servicios') . '.svg');
    if ($id) update('sectors', $d, 'id=?', [$id]); else insert('sectors', $d); flash('Sector guardado.'); redirect('admin/contenido');
});
route('POST', '/admin/contenido/pagina', function () {
    require_admin('contenido'); $slug = post('slug');
    if (!val("SELECT COUNT(*) FROM pages WHERE slug=?", [$slug])) not_found();
    // Contenido HTML solo editable por administradores con permiso; se filtra a etiquetas básicas
    $body = strip_tags((string) ($_POST['body'] ?? ''), '<h2><h3><p><ul><ol><li><strong><em><b><i><a><br>');
    $body = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $body);
    $body = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $body);
    update('pages', ['title' => mb_substr(post('title'), 0, 200), 'body' => $body], 'slug=?', [$slug]); flash('Página guardada.'); redirect('admin/contenido');
});

// ---- Ajustes: cron, sinónimos, administradores, respaldo ----
route('GET', '/admin/ajustes', function () {
    require_admin('ajustes');
    render('admin/tools', ['syn' => rows("SELECT * FROM synonyms ORDER BY term"), 'admins' => rows("SELECT id,name,email,admin_role,status,last_login FROM users WHERE role='admin' ORDER BY id"), 'title' => 'Ajustes'], 'admin');
});
route('POST', '/admin/ajustes/cron-token', function () {
    require_admin('ajustes'); $tok = bin2hex(random_bytes(20)); set_setting('cron_token', $tok); flash('Nuevo token de cron generado. Actualiza la línea en cPanel.'); redirect('admin/ajustes');
});
route('POST', '/admin/ajustes/sinonimo', function () {
    require_admin('ajustes'); $id = (int) post('id');
    if (post('del')) { q("DELETE FROM synonyms WHERE id=?", [$id]); flash('Sinónimo eliminado.'); redirect('admin/ajustes'); }
    $term = mb_substr(norm(post('term')), 0, 100); $syn = mb_substr(post('synonyms'), 0, 500);
    if ($term === '' || $syn === '') { flash('Escribe el término y sus sinónimos.', 'err'); redirect('admin/ajustes'); }
    q("INSERT INTO synonyms (term,synonyms) VALUES (?,?) ON DUPLICATE KEY UPDATE synonyms=VALUES(synonyms)", [$term, $syn]); flash('Sinónimo guardado.'); redirect('admin/ajustes');
});
route('POST', '/admin/ajustes/admin', function () {
    $me = require_admin('ajustes'); $id = (int) post('id');
    if (post('del')) {
        if ($id === (int) $me['id']) { flash('No puedes eliminarte a ti mismo.', 'err'); redirect('admin/ajustes'); }
        q("DELETE FROM users WHERE id=? AND role='admin'", [$id]); flash('Administrador eliminado.'); redirect('admin/ajustes');
    }
    $role = in_array(post('admin_role'), ['superadmin', 'gestor', 'editor'], true) ? post('admin_role') : 'editor'; $email = mb_strtolower(post('email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || post('name') === '') { flash('Nombre y correo válidos son obligatorios.', 'err'); redirect('admin/ajustes'); }
    if (val("SELECT COUNT(*) FROM users WHERE email=? AND id<>?", [$email, $id])) { flash('Ese correo ya existe.', 'err'); redirect('admin/ajustes'); }
    if ($id) {
        if ($id === (int) $me['id'] && $role !== 'superadmin') { flash('No puedes quitarte tu propio rol de superadmin.', 'err'); redirect('admin/ajustes'); }
        $d = ['name' => post('name'), 'email' => $email, 'admin_role' => $role, 'status' => post('status') === 'suspendido' && $id !== (int) $me['id'] ? 'suspendido' : 'activo'];
        if (post('pass') !== '') { if ($e = password_ok(post('pass'))) { flash($e, 'err'); redirect('admin/ajustes'); } $d['pass'] = hash_pass(post('pass')); }
        update('users', $d, "id=? AND role='admin'", [$id]);
    } else {
        if ($e = password_ok(post('pass'))) { flash($e, 'err'); redirect('admin/ajustes'); }
        insert('users', ['role' => 'admin', 'admin_role' => $role, 'name' => post('name'), 'email' => $email, 'pass' => hash_pass(post('pass')), 'email_verified' => 1, 'created_at' => now()]);
    }
    flash('Administrador guardado.'); redirect('admin/ajustes');
});
route('POST', '/admin/ajustes/respaldo', function () {
    require_admin('ajustes'); @set_time_limit(300);
    $name = 'licitador-respaldo-' . date('Ymd-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '"');
    echo "-- Respaldo de Licitador " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach (db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM) as [$t]) {
        $c = db()->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        echo "DROP TABLE IF EXISTS `$t`;\n" . $c[1] . ";\n\n";
        $st = db()->query("SELECT * FROM `$t`");
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string) $v), array_values($r));
            echo "INSERT INTO `$t` (`" . implode('`,`', array_keys($r)) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n"; exit;
});
