<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

const GX_MENU_ICONS = ['' => 'Sin ícono', 'house' => 'Casa (inicio)', 'users' => 'Equipo (nosotros)', 'truck' => 'Grúa (servicio)', 'phone-call' => 'Llamada (contacto)',
    'phone' => 'Teléfono', 'mail' => 'Correo', 'map-pin' => 'Ubicación', 'info' => 'Información', 'briefcase' => 'Maletín', 'car-front' => 'Automóvil',
    'handshake' => 'Acuerdo', 'message-circle' => 'Mensaje', 'shield-check' => 'Escudo', 'wrench' => 'Llave', 'clock' => 'Reloj', 'target' => 'Objetivo'];
const GX_VIEWS = ['panel', 'paginas', 'pagina', 'medios', 'ajustes', 'mensajes', 'mensaje', 'seguridad', 'respaldos', 'registro', 'cuenta'];

function gx_admin_main(): void
{
    gx_security_headers(true);
    gx_ensure_installed();
    if (gx_guard()) {
        gx_a_bare('Modo de seguridad', '<div class="al al-bad"><strong>Modo de seguridad activo.</strong><br>Por precaución el panel está desactivado temporalmente. '
            . 'El sitio público sigue funcionando con normalidad. Se envió una alerta por correo con los detalles.</div>', 503);
    }
    if (!gx_ip_allowed(gx_ip(), (string)gx_setting('admin_ips', ''))) {
        gx_audit('ACCESO_DENEGADO_IP', 'IP no autorizada para el panel', 'anónimo');
        gx_fail(403, 'Acceso denegado');
    }
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        gx_fail(405, 'Método no permitido');
    }
    gx_session_start();
    $user = gx_current_user();
    if ($method === 'POST') {
        $act = is_string($_POST['act'] ?? null) ? (string)$_POST['act'] : '';
        if (!gx_csrf_ok()) {
            gx_audit('CSRF_FALLIDO', 'Petición POST sin token válido (' . mb_substr($act, 0, 30) . ')', $user ? (string)$user['username'] : 'anónimo');
            gx_flash('bad', 'La sesión del formulario no es válida. Intente de nuevo.');
            gx_redirect();
        }
        if (!$user) {
            if ($act === 'login') {
                gx_do_login();
            } elseif ($act === 'login2') {
                gx_do_login2();
            } elseif ($act === 'cancel2') {
                unset($_SESSION['gx_pre']);
            }
            gx_redirect();
        }
        gx_admin_post($act, $user);
    }
    if (!$user) {
        gx_view_login();
    }
    if ((int)$user['must_change'] === 1) {
        gx_view_force_change($user);
    }
    $p = is_string($_GET['p'] ?? null) && in_array($_GET['p'], GX_VIEWS, true) ? (string)$_GET['p'] : 'panel';
    $fn = 'gx_view_' . $p;
    $fn($user);
}

/* ====================== acceso ====================== */

function gx_view_login(): void
{
    $pre = $_SESSION['gx_pre'] ?? null;
    if (is_array($pre) && gx_now() - (int)$pre['t'] < 300) {
        gx_a_bare('Verificación en dos pasos', gx_f_open('login2') . '<h2>Verificación en dos pasos</h2><p class="note">Escriba el código de 6 dígitos de su aplicación de autenticación.</p>'
            . gx_f_in('Código', 'code', '', 'text', '', 'inputmode="numeric" pattern="[0-9 ]*" maxlength="8" autocomplete="one-time-code" autofocus required')
            . '<button class="b b-p b-w" type="submit">Verificar</button></form>'
            . gx_f_open('cancel2') . '<button class="b b-g b-w" type="submit">Cancelar</button></form>');
    }
    gx_a_bare('Iniciar sesión', gx_f_open('login') . '<h2>Iniciar sesión</h2>'
        . gx_f_in('Usuario', 'u', '', 'text', '', 'maxlength="60" autocomplete="username" autofocus required')
        . gx_f_in('Contraseña', 'pw', '', 'password', '', 'maxlength="200" autocomplete="current-password" required')
        . '<button class="b b-p b-w" type="submit">Entrar</button></form>');
}

function gx_do_login(): void
{
    $ip = gx_ip();
    $u = gx_line($_POST['u'] ?? '', 60);
    $pw = is_string($_POST['pw'] ?? null) ? mb_substr((string)$_POST['pw'], 0, 200) : '';
    if (gx_login_locked($ip, $u)) {
        gx_audit('LOGIN_BLOQUEADO', 'Demasiados intentos fallidos', $u);
        gx_flash('bad', 'Demasiados intentos fallidos. Espere 15 minutos e intente de nuevo.');
        return;
    }
    $user = gx_check_credentials($u, $pw);
    if (!$user) {
        gx_login_record($ip, $u, false);
        gx_audit('LOGIN_FALLIDO', 'Credenciales incorrectas', $u);
        usleep(400000);
        gx_flash('bad', 'Usuario o contraseña incorrectos.');
        return;
    }
    if ((int)$user['totp_enabled'] === 1) {
        session_regenerate_id(true);
        $_SESSION['gx_pre'] = ['uid' => (int)$user['id'], 't' => gx_now(), 'fails' => 0];
        return;
    }
    gx_finish_login($user);
}

function gx_do_login2(): void
{
    $pre = $_SESSION['gx_pre'] ?? null;
    if (!is_array($pre) || gx_now() - (int)$pre['t'] > 300) {
        unset($_SESSION['gx_pre']);
        gx_flash('bad', 'La verificación expiró. Inicie sesión de nuevo.');
        return;
    }
    $user = gx_user_by_id((int)$pre['uid']);
    $ip = gx_ip();
    if (!$user || gx_login_locked($ip, (string)$user['username'])) {
        unset($_SESSION['gx_pre']);
        gx_flash('bad', 'Demasiados intentos fallidos. Espere 15 minutos.');
        return;
    }
    if (gx_totp_verify((int)$user['id'], (string)$user['totp_secret'], (string)($_POST['code'] ?? ''))) {
        gx_finish_login($user);
        return;
    }
    gx_login_record($ip, (string)$user['username'], false);
    gx_audit('2FA_FALLIDO', 'Código incorrecto', (string)$user['username']);
    $_SESSION['gx_pre']['fails'] = (int)$pre['fails'] + 1;
    if ($_SESSION['gx_pre']['fails'] >= 5) {
        unset($_SESSION['gx_pre']);
    }
    gx_flash('bad', 'Código incorrecto.');
}

function gx_finish_login(array $user): void
{
    gx_login_record(gx_ip(), (string)$user['username'], true);
    if (password_needs_rehash((string)$user['pass_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        // se recalcula en el próximo cambio de contraseña; no se conserva la contraseña en memoria
    }
    gx_login_session($user);
    gx_audit('LOGIN_OK', 'Inicio de sesión', (string)$user['username']);
    gx_backup_if_due();
}

function gx_view_force_change(array $user): void
{
    gx_a_bare('Cambiar contraseña', gx_f_open('pw_change', '<input type="hidden" name="forced" value="1">')
        . '<h2>Cree su contraseña personal</h2><p class="note">Por seguridad debe cambiar la contraseña inicial antes de continuar. Mínimo 12 caracteres con al menos 3 tipos (minúsculas, mayúsculas, números, símbolos).</p>'
        . gx_f_in('Contraseña actual (la del README)', 'cur', '', 'password', '', 'autocomplete="current-password" required')
        . gx_f_in('Nueva contraseña', 'new1', '', 'password', '', 'autocomplete="new-password" minlength="12" required')
        . gx_f_in('Repita la nueva contraseña', 'new2', '', 'password', '', 'autocomplete="new-password" minlength="12" required')
        . '<button class="b b-p b-w" type="submit">Guardar y continuar</button></form>');
}

/* ====================== acciones POST ====================== */

function gx_admin_post(string $act, array $user): void
{
    $must = ((int)$user['must_change'] === 1);
    if ($must && !in_array($act, ['pw_change', 'logout'], true)) {
        gx_redirect();
    }
    switch ($act) {
        case 'logout':
            gx_audit('LOGOUT', 'Cierre de sesión');
            gx_session_destroy();
            header('Location: ' . gx_admin_base(), true, 303);
            exit;
        case 'publish':
            gx_act_publish();
            break;
        case 'page_save':
            gx_act_page_save();
            break;
        case 'media_upload':
            gx_act_media_upload();
            break;
        case 'media_replace':
            gx_act_media_replace();
            break;
        case 'media_alt':
            gx_act_media_alt();
            break;
        case 'media_delete':
            gx_act_media_delete();
            break;
        case 'settings_save':
            gx_act_settings_save();
            break;
        case 'msg_delete':
            gx_db()->prepare('DELETE FROM messages WHERE id = :i')->execute([':i' => gx_int($_POST['id'] ?? '', 1, PHP_INT_MAX, 0)]);
            gx_audit('MENSAJE_ELIMINADO', 'id ' . (int)($_POST['id'] ?? 0));
            gx_flash('ok', 'Mensaje eliminado.');
            gx_redirect('mensajes');
            break;
        case 'msg_export':
            gx_act_msg_export();
            break;
        case 'integrity_check':
            $r = gx_integrity_scan();
            $_SESSION['gx_integrity'] = $r;
            gx_guard(true);
            gx_audit('INTEGRIDAD_VERIFICADA', $r['ok'] ? 'Sin cambios' : 'Cambios detectados');
            gx_redirect('seguridad');
            break;
        case 'scan':
            $_SESSION['gx_scan'] = gx_malware_scan();
            gx_audit('ESCANEO', 'Archivos revisados: ' . $_SESSION['gx_scan']['scanned'] . ', hallazgos: ' . count($_SESSION['gx_scan']['findings']));
            gx_redirect('seguridad');
            break;
        case 'pw_change':
            gx_act_pw_change($user);
            break;
        case 'totp_start':
            $_SESSION['gx_totp_new'] = gx_totp_secret();
            gx_redirect('cuenta');
            break;
        case 'totp_enable':
            gx_act_totp_enable($user);
            break;
        case 'totp_disable':
            gx_act_totp_disable($user);
            break;
        case 'ht_change':
            gx_act_ht_change($user);
            break;
        case 'backup_create':
            $n = gx_backup_db();
            gx_audit('RESPALDO', $n ?? 'fallo');
            gx_flash($n ? 'ok' : 'bad', $n ? 'Respaldo creado.' : 'No se pudo crear el respaldo.');
            gx_redirect('respaldos');
            break;
        case 'backup_download':
            gx_audit('RESPALDO_DESCARGA', (string)($_POST['name'] ?? ''));
            gx_backup_download((string)($_POST['name'] ?? ''));
            break;
        case 'backup_zip':
            gx_audit('RESPALDO_ZIP', 'Descarga completa');
            gx_backup_zip_download();
            break;
    }
    gx_redirect();
}

function gx_act_publish(): void
{
    $r = gx_publish();
    if ($r['errors']) {
        gx_flash('bad', 'No se pudo publicar todo: ' . implode(' ', $r['errors']));
        gx_audit('PUBLICAR_ERROR', implode(' | ', $r['errors']));
    } else {
        gx_set('pending_publish', false);
        gx_flash('ok', 'Sitio publicado: ' . count($r['files']) . ' archivos generados (páginas, sitemap.xml, robots.txt y estilos).');
        gx_audit('PUBLICAR', count($r['files']) . ' archivos');
        gx_backup_if_due();
        gx_guard(true);
    }
    gx_redirect('panel');
}

function gx_act_page_save(): void
{
    $slug = (string)($_POST['slug'] ?? '');
    $old = preg_match('/^[a-z0-9-]{1,60}$/', $slug) ? gx_page($slug) : null;
    if (!$old) {
        gx_flash('bad', 'Página no encontrada.');
        gx_redirect('paginas');
    }
    gx_page_save($slug, gx_parse_page_post($old));
    gx_set('pending_publish', true);
    gx_audit('PAGINA_EDITADA', $slug);
    gx_flash('ok', 'Página guardada. Pulse «Publicar» para verla en el sitio.');
    gx_redirect('pagina', ['s' => $slug]);
}

function gx_media_used_ids(): array
{
    $used = [];
    $walk = static function ($v, $key = '') use (&$walk, &$used): void {
        if (is_array($v)) {
            foreach ($v as $k => $x) {
                $walk($x, is_string($k) ? $k : $key);
            }
        } elseif (is_int($v) && $v > 0 && in_array($key, ['media', 'image', 'poster', 'og_image', 'logo', 'logo_mobile', 'favicon'], true)) {
            $used[$v] = true;
        }
    };
    foreach (gx_pages() as $p) {
        $walk($p);
    }
    $walk(gx_settings_all());
    return $used;
}

function gx_uploaded_files(string $field): array
{
    $out = [];
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        if (isset($_FILES[$field]) && is_string($_FILES[$field]['name'] ?? null)) {
            return [[$_FILES[$field]['name'], $_FILES[$field]['tmp_name'], (int)$_FILES[$field]['error']]];
        }
        return [];
    }
    foreach ($_FILES[$field]['name'] as $i => $n) {
        $out[] = [(string)$n, (string)($_FILES[$field]['tmp_name'][$i] ?? ''), (int)($_FILES[$field]['error'][$i] ?? 4)];
    }
    return array_slice($out, 0, 12);
}

function gx_act_media_upload(): void
{
    $ok = 0;
    foreach (gx_uploaded_files('img') as [$name, $tmp, $err]) {
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            gx_flash('bad', $name . ': error al subir el archivo.');
            continue;
        }
        $r = gx_process_image($tmp, $name, GX_ROOT . '/uploads');
        if (is_string($r)) {
            gx_flash('bad', gx_line($name, 60) . ': ' . $r);
            gx_audit('SUBIDA_RECHAZADA', gx_line($name, 60) . ' — ' . $r);
            continue;
        }
        $alt = gx_line(preg_replace('/\.[a-z0-9]+$/i', '', $name) ?? '', 120);
        $st = gx_db()->prepare('INSERT INTO media (file, thumb, w, h, tw, th, alt, name, created) VALUES (:f,:t,:w,:h,:tw,:th,:a,:n,:c)');
        $st->execute([':f' => $r['file'], ':t' => $r['thumb'], ':w' => $r['w'], ':h' => $r['h'], ':tw' => $r['tw'], ':th' => $r['th'],
            ':a' => $alt, ':n' => gx_line($name, 120), ':c' => gx_now()]);
        $ok++;
        gx_audit('IMAGEN_SUBIDA', $r['file']);
    }
    if ($ok > 0) {
        gx_flash('ok', $ok . ' imagen(es) subida(s) y procesada(s). Recuerde completar el texto alternativo (alt).');
    }
    gx_redirect('medios');
}

function gx_act_media_replace(): void
{
    $id = gx_int($_POST['id'] ?? '', 1, PHP_INT_MAX, 0);
    $m = $id > 0 ? gx_media_get($id) : null;
    $f = gx_uploaded_files('img')[0] ?? null;
    if (!$m || !$f || $f[2] !== UPLOAD_ERR_OK || !is_uploaded_file($f[1])) {
        gx_flash('bad', 'No se pudo reemplazar la imagen.');
        gx_redirect('medios');
    }
    $r = gx_process_image($f[1], $f[0], GX_ROOT . '/uploads');
    if (is_string($r)) {
        gx_flash('bad', $r);
        gx_audit('SUBIDA_RECHAZADA', gx_line($f[0], 60) . ' — ' . $r);
        gx_redirect('medios');
    }
    gx_delete_media_files($m);
    gx_db()->prepare('UPDATE media SET file=:f, thumb=:t, w=:w, h=:h, tw=:tw, th=:th, name=:n WHERE id=:i')
        ->execute([':f' => $r['file'], ':t' => $r['thumb'], ':w' => $r['w'], ':h' => $r['h'], ':tw' => $r['tw'], ':th' => $r['th'], ':n' => gx_line($f[0], 120), ':i' => $id]);
    gx_set('pending_publish', true);
    if ((int)gx_setting('favicon', 0) === $id) {
        gx_set('favicon_dirty', true);
    }
    gx_audit('IMAGEN_REEMPLAZADA', 'id ' . $id . ' -> ' . $r['file']);
    gx_flash('ok', 'Imagen reemplazada. Pulse «Publicar» para aplicarla en el sitio.');
    gx_redirect('medios');
}

function gx_act_media_alt(): void
{
    $id = gx_int($_POST['id'] ?? '', 1, PHP_INT_MAX, 0);
    if ($id > 0 && gx_media_get($id)) {
        gx_db()->prepare('UPDATE media SET alt = :a WHERE id = :i')->execute([':a' => gx_line($_POST['alt'] ?? '', 160), ':i' => $id]);
        gx_set('pending_publish', true);
        gx_flash('ok', 'Texto alternativo guardado.');
    }
    gx_redirect('medios');
}

function gx_act_media_delete(): void
{
    $id = gx_int($_POST['id'] ?? '', 1, PHP_INT_MAX, 0);
    $m = $id > 0 ? gx_media_get($id) : null;
    if (!$m) {
        gx_redirect('medios');
    }
    if (isset(gx_media_used_ids()[$id])) {
        gx_flash('bad', 'No se puede eliminar: la imagen está en uso. Quítela primero de la página o de Ajustes.');
        gx_redirect('medios');
    }
    gx_delete_media_files($m);
    gx_db()->prepare('DELETE FROM media WHERE id = :i')->execute([':i' => $id]);
    gx_audit('IMAGEN_ELIMINADA', (string)$m['file']);
    gx_flash('ok', 'Imagen eliminada.');
    gx_redirect('medios');
}

function gx_act_msg_export(): void
{
    $cell = static function ($v): string {
        $s = str_replace(["\r", "\n"], ' ', (string)$v);
        if ($s !== '' && strpos('=+-@', $s[0]) !== false) {
            $s = "'" . $s;       // evita inyección de fórmulas en Excel
        }
        return '"' . str_replace('"', '""', $s) . '"';
    };
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="mensajes-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF" . "Fecha,Nombre,Teléfono,Correo,Servicio,Ubicación,Mensaje,Correo enviado\n";
    foreach (gx_db()->query('SELECT * FROM messages ORDER BY id DESC') as $m) {
        echo implode(',', [$cell(date('Y-m-d H:i', (int)$m['created'])), $cell($m['name']), $cell($m['phone']), $cell($m['email']),
            $cell($m['service']), $cell($m['place']), $cell($m['message']), $cell($m['mail_status'])]) . "\n";
    }
    gx_audit('MENSAJES_EXPORTADOS', 'CSV');
    exit;
}

function gx_act_pw_change(array $user): void
{
    $cur = (string)($_POST['cur'] ?? '');
    $n1 = (string)($_POST['new1'] ?? '');
    $n2 = (string)($_POST['new2'] ?? '');
    $back = !empty($_POST['forced']) ? '' : 'cuenta';
    if (!password_verify($cur, (string)$user['pass_hash'])) {
        gx_audit('PASSWORD_CAMBIO_FALLIDO', 'Contraseña actual incorrecta');
        gx_flash('bad', 'La contraseña actual no es correcta.');
        gx_redirect($back);
    }
    $err = gx_password_policy($n1, (string)$user['username']);
    if ($err === null && $n1 !== $n2) {
        $err = 'Las contraseñas nuevas no coinciden.';
    }
    if ($err === null && hash_equals($cur, $n1)) {
        $err = 'La nueva contraseña debe ser distinta de la actual.';
    }
    if ($err !== null) {
        gx_flash('bad', $err);
        gx_redirect($back);
    }
    gx_db()->prepare('UPDATE users SET pass_hash = :h, must_change = 0 WHERE id = :i')->execute([':h' => gx_hash_password($n1), ':i' => $user['id']]);
    session_regenerate_id(true);
    gx_audit('PASSWORD_CAMBIADA', 'Contraseña del panel actualizada');
    gx_flash('ok', 'Contraseña actualizada.' . ((int)$user['totp_enabled'] === 0 ? ' Recomendamos activar la verificación en dos pasos.' : ''));
    gx_redirect((int)$user['totp_enabled'] === 0 ? 'cuenta' : 'panel');
}

function gx_act_totp_enable(array $user): void
{
    $sec = (string)($_SESSION['gx_totp_new'] ?? '');
    if ($sec === '' || !password_verify((string)($_POST['cur'] ?? ''), (string)$user['pass_hash'])) {
        gx_flash('bad', 'Confirme con su contraseña actual.');
        gx_redirect('cuenta');
    }
    if (!gx_totp_verify((int)$user['id'], $sec, (string)($_POST['code'] ?? ''))) {
        gx_flash('bad', 'El código no coincide. Revise la hora del teléfono e intente de nuevo.');
        gx_redirect('cuenta');
    }
    gx_db()->prepare('UPDATE users SET totp_secret = :s, totp_enabled = 1 WHERE id = :i')->execute([':s' => $sec, ':i' => $user['id']]);
    unset($_SESSION['gx_totp_new']);
    gx_audit('2FA_ACTIVADO', 'Verificación en dos pasos activada');
    gx_flash('ok', 'Verificación en dos pasos activada.');
    gx_redirect('cuenta');
}

function gx_act_totp_disable(array $user): void
{
    if (!password_verify((string)($_POST['cur'] ?? ''), (string)$user['pass_hash'])
        || !gx_totp_verify((int)$user['id'], (string)$user['totp_secret'], (string)($_POST['code'] ?? ''))) {
        gx_flash('bad', 'Contraseña o código incorrectos.');
        gx_redirect('cuenta');
    }
    gx_db()->prepare("UPDATE users SET totp_secret = '', totp_enabled = 0 WHERE id = :i")->execute([':i' => $user['id']]);
    gx_audit('2FA_DESACTIVADO', 'Verificación en dos pasos desactivada');
    gx_flash('ok', 'Verificación en dos pasos desactivada.');
    gx_redirect('cuenta');
}

function gx_act_ht_change(array $user): void
{
    $cfg = gx_config();
    if (empty($cfg['basic_auth']) || !password_verify((string)($_POST['cur'] ?? ''), (string)$user['pass_hash'])) {
        gx_flash('bad', 'Confirme con su contraseña actual del panel (y verifique que el acceso HTTP esté activo).');
        gx_redirect('cuenta');
    }
    $u = gx_line($_POST['ht_user'] ?? '', 40);
    $p = (string)($_POST['ht_pass'] ?? '');
    $err = !preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $u) ? 'Usuario HTTP: 3 a 40 caracteres (letras, números, . _ -).' : gx_password_policy($p, $u);
    if ($err !== null) {
        gx_flash('bad', $err);
        gx_redirect('cuenta');
    }
    $ht = GX_DATA . '/.htpasswd';
    if (file_put_contents($ht, $u . ':' . gx_apr1($p) . "\n", LOCK_EX) === false) {
        gx_flash('bad', 'No se pudo escribir el archivo de acceso.');
        gx_redirect('cuenta');
    }
    gx_audit('HTPASSWD_CAMBIADO', 'Acceso HTTP actualizado');
    gx_flash('ok', 'Acceso HTTP actualizado. El navegador pedirá las nuevas credenciales en la próxima petición.');
    gx_redirect('cuenta');
}

/* ====================== ajustes ====================== */

function gx_act_settings_save(): void
{
    $P = $_POST;
    $S = gx_settings_all();
    $errs = [];
    $mail = static function ($v, bool $required, string $label) use (&$errs): string {
        $v = gx_line($v, 254);
        if ($v === '' && !$required) {
            return '';
        }
        if (!gx_valid_email($v)) {
            $errs[] = $label . ': correo no válido.';
            return '';
        }
        return $v;
    };
    $new = [];
    $new['phone_display'] = gx_line($P['phone_display'] ?? '', 40);
    $tel = preg_replace('/[^0-9+]/', '', (string)($P['phone_tel'] ?? '')) ?? '';
    if (strlen($tel) < 6 || strlen($tel) > 20) {
        $errs[] = 'Teléfono (para llamar): use solo números, de 6 a 20 dígitos.';
        $tel = (string)($S['phone_tel'] ?? '');
    }
    $new['phone_tel'] = $tel;
    $new['wa_display'] = gx_line($P['wa_display'] ?? '', 40);
    $wa = preg_replace('/\D/', '', (string)($P['wa_number'] ?? '')) ?? '';
    if (strlen($wa) < 8 || strlen($wa) > 15) {
        $errs[] = 'WhatsApp: número con código de país, de 8 a 15 dígitos (ej. 50235567904).';
        $wa = (string)($S['wa_number'] ?? '');
    }
    $new['wa_number'] = $wa;
    $new['email_display'] = $mail($P['email_display'] ?? '', true, 'Correo visible');
    $new['email_link'] = $mail($P['email_link'] ?? '', true, 'Correo del enlace');
    $new['form_to'] = $mail($P['form_to'] ?? '', false, 'Correo destino del formulario');
    $new['alert_email'] = $mail($P['alert_email'] ?? '', false, 'Correo de alertas');
    $new['address'] = gx_line($P['address'] ?? '', 200);
    $new['hours'] = gx_line($P['hours'] ?? '', 200);
    $soc = [];
    foreach ((array)($P['soc'] ?? []) as $s) {
        if (!is_array($s)) {
            continue;
        }
        $url = gx_safe_url($s['url'] ?? '');
        $net = (string)($s['net'] ?? '');
        if ($url !== '' && preg_match('/^https?:\/\//i', $url) && in_array($net, ['facebook', 'instagram', 'tiktok', 'youtube', 'x'], true)) {
            $soc[] = ['net' => $net, 'url' => $url];
        }
    }
    $new['socials'] = array_slice($soc, 0, 8);
    $new['site_name'] = gx_line($P['site_name'] ?? '', 100);
    $su = rtrim(gx_line($P['site_url'] ?? '', 200), '/');
    if (!preg_match('~^https?://[a-z0-9.-]+(:\d{2,5})?$~i', $su)) {
        $errs[] = 'Dirección del sitio: use el formato https://sudominio.com';
        $su = (string)($S['site_url'] ?? '');
    }
    $new['site_url'] = $su;
    $new['footer_text'] = gx_line($P['footer_text'] ?? '', 200);
    $new['topbar_on'] = !empty($P['topbar_on']);
    $new['topbar_phone_label'] = gx_line($P['topbar_phone_label'] ?? '', 40);
    $new['topbar_email_label'] = gx_line($P['topbar_email_label'] ?? '', 40);
    foreach (['logo', 'logo_mobile', 'favicon'] as $k) {
        $id = gx_int((string)($P[$k] ?? ''), 0, PHP_INT_MAX, 0);
        $new[$k] = ($id > 0 && gx_media_get($id)) ? $id : 0;
    }
    $new['favicon_dirty'] = ((int)($S['favicon'] ?? 0) !== $new['favicon']) || !empty($S['favicon_dirty']);
    $menu = [];
    foreach ((array)($P['menu'] ?? []) as $m) {
        if (!is_array($m)) {
            continue;
        }
        $label = gx_line($m['label'] ?? '', 40);
        $link = gx_safe_url($m['link'] ?? '', 300);
        if ($label !== '' && $link !== '') {
            $icon = isset(GX_MENU_ICONS[$m['icon'] ?? '']) ? (string)$m['icon'] : '';
            $menu[] = ['label' => $label, 'link' => $link, 'icon' => $icon, 'visible' => !empty($m['visible']), '_o' => gx_int((string)($m['order'] ?? ''), 0, 99, 50)];
        }
    }
    usort($menu, static function ($a, $b) {
        return $a['_o'] <=> $b['_o'];
    });
    foreach ($menu as &$mm) {
        unset($mm['_o']);
    }
    unset($mm);
    $new['menu'] = array_slice($menu, 0, 8);
    $c = [];
    foreach (['call_title', 'call_label', 'wa_title', 'wa_label', 'mail_title'] as $k) {
        $c[$k] = gx_line($P['cards'][$k] ?? '', 60);
    }
    $new['cards'] = $c;
    $def = ['navy' => '#072253', 'blue' => '#0b489d', 'red' => '#d3202e', 'bg' => '#ffffff', 'soft' => '#edf4fd', 'ink' => '#0d2147'];
    $col = [];
    foreach ($def as $k => $d) {
        $col[$k] = gx_color($P['col'][$k] ?? '', $d);
    }
    $new['colors'] = $col;
    $new['ld_description'] = gx_line($P['ld_description'] ?? '', 300);
    $new['ld_price'] = gx_line($P['ld_price'] ?? '', 60);
    $old = (array)($S['smtp'] ?? []);
    $port = gx_int((string)($P['smtp_port'] ?? ''), 1, 65535, 587);
    $sec = in_array($P['smtp_secure'] ?? '', ['none', 'tls', 'ssl'], true) ? (string)$P['smtp_secure'] : 'tls';
    $host = gx_line($P['smtp_host'] ?? '', 200);
    if ($host !== '' && !preg_match('/^[a-z0-9.\-]+$/i', $host)) {
        $errs[] = 'Servidor SMTP no válido.';
        $host = '';
    }
    $new['smtp'] = ['enabled' => !empty($P['smtp_on']) && $host !== '', 'host' => $host, 'port' => $port, 'secure' => $sec,
        'user' => gx_line($P['smtp_user'] ?? '', 200),
        'pass' => (string)($P['smtp_pass'] ?? '') !== '' ? mb_substr((string)$P['smtp_pass'], 0, 200) : (string)($old['pass'] ?? ''),
        'from' => $mail($P['smtp_from'] ?? '', false, 'Remitente SMTP')];
    $ips = gx_line($P['admin_ips'] ?? '', 300);
    foreach (preg_split('/[\s,;]+/', $ips, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $r) {
        if (!filter_var($r, FILTER_VALIDATE_IP) && !preg_match('~^\d{1,3}(\.\d{1,3}){3}/\d{1,2}$~', $r)) {
            $errs[] = 'Restricción por IP: «' . $r . '» no es una IP o rango válido.';
            $ips = (string)($S['admin_ips'] ?? '');
            break;
        }
    }
    if ($ips !== '' && !gx_ip_allowed(gx_ip(), $ips)) {
        $errs[] = 'Restricción por IP: su IP actual (' . gx_ip() . ') quedaría bloqueada. Inclúyala en la lista.';
        $ips = (string)($S['admin_ips'] ?? '');
    }
    $new['admin_ips'] = $ips;
    if ($errs) {
        gx_flash('bad', implode(' ', $errs));
        gx_redirect('ajustes');
    }
    foreach ($new as $k => $v) {
        gx_set($k, $v);
    }
    $cfg = gx_config();
    $ph = in_array($P['proxy_header'] ?? '', ['', 'CF-Connecting-IP', 'X-Forwarded-For'], true) ? (string)$P['proxy_header'] : '';
    if (($cfg['proxy_header'] ?? '') !== $ph) {
        $cfg['proxy_header'] = $ph;
        gx_config_save($cfg);
    }
    gx_set('pending_publish', true);
    gx_audit('AJUSTES_GUARDADOS', 'Ajustes generales actualizados');
    gx_flash('ok', 'Ajustes guardados. Pulse «Publicar» para aplicarlos en el sitio.');
    gx_redirect('ajustes');
}

/* ====================== vistas ====================== */

function gx_fmt_date(int $t): string
{
    return $t > 0 ? date('d/m/Y H:i', $t) : '—';
}

function gx_status_pill(bool $ok, string $yes, string $no, bool $neutralNo = false): string
{
    return '<span class="pill ' . ($ok ? 'pill-ok' : ($neutralNo ? 'pill-n' : 'pill-bad')) . '">' . e($ok ? $yes : $no) . '</span>';
}

function gx_view_panel(array $user): void
{
    $unread = (int)gx_db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
    $total = (int)gx_db()->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    $cfg = gx_config();
    $b = '<div class="stats">'
        . '<a class="stat" href="?p=mensajes"><span class="n">' . $unread . '</span><span>Mensajes nuevos</span><small>' . $total . ' en total</small></a>'
        . '<div class="stat"><span class="n">' . e(gx_fmt_date((int)gx_setting('last_publish', 0))) . '</span><span>Última publicación</span><small>' . (gx_setting('pending_publish', false) ? 'Hay cambios sin publicar' : 'Sitio al día') . '</small></div>'
        . '<div class="stat"><span class="n">' . e(gx_fmt_date((int)gx_setting('last_backup', 0))) . '</span><span>Último respaldo</span><small>Automático cada 24 h</small></div>'
        . '<a class="stat" href="?p=seguridad"><span class="n">' . (gx_guard(true) ? 'Bloqueo' : 'OK') . '</span><span>Integridad de archivos</span><small>Ver pestaña Seguridad</small></a></div>'
        . '<section class="card-p"><h2>Estado de seguridad</h2><ul class="chk">'
        . '<li>' . gx_status_pill(!empty($cfg['basic_auth']), 'Activa', 'Inactiva') . ' Contraseña HTTP (Basic Auth) delante del login</li>'
        . '<li>' . gx_status_pill((int)$user['totp_enabled'] === 1, 'Activa', 'Sin activar') . ' Verificación en dos pasos (2FA) <a href="?p=cuenta">configurar</a></li>'
        . '<li>' . gx_status_pill(trim((string)gx_setting('admin_ips', '')) !== '', 'Activa', 'Opcional', true) . ' Restricción por IP <a href="?p=ajustes">configurar</a></li>'
        . '<li>' . gx_status_pill(gx_is_https(), 'Sí', 'No') . ' Conexión segura (HTTPS)</li></ul></section>'
        . '<section class="card-p"><h2>Cómo funciona</h2><p>Edite los textos, imágenes y ajustes. Cuando termine pulse <strong>Publicar</strong>: el panel regenera las páginas estáticas, el <code>sitemap.xml</code> y el <code>robots.txt</code>. '
        . 'Los visitantes solo reciben archivos estáticos (sin PHP), igual que un sitio hecho con Xara.</p>'
        . '<p><a class="b b-s" href="?p=paginas">Editar páginas</a> <a class="b b-s" href="?p=ajustes">Teléfono, WhatsApp y correo</a> <a class="b b-s" href="?p=medios">Imágenes</a></p></section>';
    gx_a_page('Panel', 'panel', $b);
}

function gx_view_paginas(array $user): void
{
    $b = '<section class="card-p"><table class="tbl"><thead><tr><th>Página</th><th>Dirección</th><th></th></tr></thead><tbody>';
    foreach (gx_pages() as $slug => $p) {
        $b .= '<tr><td data-l="Página"><strong>' . e((string)($p['label'] ?? $slug)) . '</strong></td><td data-l="Dirección"><code>' . e((string)$p['path']) . '</code></td>'
            . '<td><a class="b b-s" href="?p=pagina&amp;s=' . e($slug) . '">Editar</a></td></tr>';
    }
    gx_a_page('Páginas', 'paginas', $b . '</tbody></table></section>');
}

function gx_view_pagina(array $user): void
{
    $s = is_string($_GET['s'] ?? null) ? (string)$_GET['s'] : '';
    $p = preg_match('/^[a-z0-9-]{1,60}$/', $s) ? gx_page($s) : null;
    if (!$p) {
        gx_redirect('paginas');
    }
    gx_a_page('Editar: ' . (string)($p['label'] ?? $s), 'paginas', gx_page_editor($p));
}

function gx_view_medios(array $user): void
{
    $used = gx_media_used_ids();
    $b = '<section class="card-p"><h2>Subir imágenes</h2>' . gx_f_open('media_upload', '', true)
        . '<p class="note">Solo JPG, PNG o WEBP (máx. 8 MB). Cada imagen se valida, se reprocesa y se convierte a WEBP para eliminar cualquier código oculto.</p>'
        . '<div class="f"><input type="file" name="img[]" accept="image/jpeg,image/png,image/webp" multiple required></div>'
        . '<button class="b b-p" type="submit">' . gx_icon('upload') . ' Subir</button></form></section><div class="mgrid">';
    foreach (gx_media_all() as $m) {
        $inUse = isset($used[(int)$m['id']]);
        $b .= '<article class="mc"><a href="/uploads/' . e($m['file']) . '" target="_blank" rel="noopener"><img src="/uploads/' . e($m['thumb']) . '" alt="' . e($m['alt']) . '" loading="lazy"></a>'
            . '<div class="mc-b"><small>' . e($m['name']) . ' · ' . (int)$m['w'] . '×' . (int)$m['h'] . ($inUse ? ' · en uso' : '') . '</small>'
            . gx_f_open('media_alt', '<input type="hidden" name="id" value="' . (int)$m['id'] . '">')
            . gx_f_in('Texto alternativo (alt)', 'alt', $m['alt'], 'text', '', 'maxlength="160"') . '<button class="b b-s" type="submit">Guardar alt</button></form>'
            . gx_f_open('media_replace', '<input type="hidden" name="id" value="' . (int)$m['id'] . '">', true)
            . '<input type="file" name="img" accept="image/jpeg,image/png,image/webp" required><button class="b b-s b-g" type="submit">Reemplazar</button></form>'
            . gx_f_open('media_delete', '<input type="hidden" name="id" value="' . (int)$m['id'] . '">')
            . '<button class="b b-s b-d" type="submit" data-confirm="¿Eliminar esta imagen?"' . ($inUse ? ' disabled title="En uso"' : '') . '>Eliminar</button></form></div></article>';
    }
    gx_a_page('Imágenes', 'medios', $b . '</div>');
}

function gx_view_ajustes(array $user): void
{
    $S = gx_settings_all();
    $c = (array)($S['cards'] ?? []);
    $col = (array)($S['colors'] ?? []);
    $sm = (array)($S['smtp'] ?? []);
    $cfg = gx_config();
    $b = gx_f_open('settings_save');
    $b .= '<div class="grid2"><section class="card-p"><h2>Contacto</h2>'
        . gx_f_in('Teléfono (como se muestra)', 'phone_display', $S['phone_display'] ?? '', 'text', 'Ej.: 3556-7904')
        . gx_f_in('Teléfono (para llamar, solo números)', 'phone_tel', $S['phone_tel'] ?? '', 'text', 'Ej.: 35567904')
        . gx_f_in('WhatsApp (como se muestra)', 'wa_display', $S['wa_display'] ?? '')
        . gx_f_in('WhatsApp (número con código de país)', 'wa_number', $S['wa_number'] ?? '', 'text', 'Ej.: 50235567904. A este número llegan los datos del formulario.')
        . gx_f_in('Correo (como se muestra)', 'email_display', $S['email_display'] ?? '', 'email')
        . gx_f_in('Correo (destino del enlace «Envíe un email»)', 'email_link', $S['email_link'] ?? '', 'email', 'Se conserva el valor del sitio original (info@agroinco.com). Cámbielo si desea que coincida con el visible.')
        . gx_f_in('Correo que recibe copia del formulario', 'form_to', $S['form_to'] ?? '', 'email', 'Además del WhatsApp, cada envío se guarda en Mensajes y se avisa a este correo.')
        . gx_f_in('Correo de alertas de seguridad', 'alert_email', $S['alert_email'] ?? '', 'email')
        . '</section><section class="card-p"><h2>Datos opcionales</h2><p class="note">Si los llena, aparecen en el pie de página y en los datos para Google. Si los deja vacíos, no se muestran.</p>'
        . gx_f_in('Dirección', 'address', $S['address'] ?? '') . gx_f_in('Horario', 'hours', $S['hours'] ?? '');
    $soc = array_values((array)($S['socials'] ?? []));
    for ($i = 0; $i < 4; $i++) {
        $b .= '<div class="row2">' . gx_f_sel('Red social ' . ($i + 1), 'soc[' . $i . '][net]', ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X'], $soc[$i]['net'] ?? 'facebook')
            . gx_f_in('Enlace', 'soc[' . $i . '][url]', $soc[$i]['url'] ?? '', 'url', '', 'placeholder="https://"') . '</div>';
    }
    $b .= '</section></div><div class="grid2"><section class="card-p"><h2>Marca y pie de página</h2>'
        . gx_f_in('Nombre del sitio', 'site_name', $S['site_name'] ?? '')
        . gx_f_in('Dirección web del sitio', 'site_url', $S['site_url'] ?? '', 'text', 'Ej.: https://gruasexpresstowing.gt (sin barra final). Se usa en sitemap, SEO y Open Graph.')
        . gx_f_in('Texto del pie de página', 'footer_text', $S['footer_text'] ?? '')
        . gx_f_chk('Mostrar la barra superior (teléfono y correo)', 'topbar_on', !empty($S['topbar_on']))
        . gx_f_in('Barra superior: etiqueta del teléfono', 'topbar_phone_label', $S['topbar_phone_label'] ?? '')
        . gx_f_in('Barra superior: etiqueta del correo', 'topbar_email_label', $S['topbar_email_label'] ?? '')
        . gx_f_media('Logo', 'logo', (int)($S['logo'] ?? 0))
        . gx_f_media('Logo para móvil', 'logo_mobile', (int)($S['logo_mobile'] ?? 0))
        . gx_f_media('Favicon (se recorta al emblema)', 'favicon', (int)($S['favicon'] ?? 0))
        . '</section><section class="card-p"><h2>Menú</h2><p class="note">Hasta 8 opciones. «Orden» menor aparece primero.</p>';
    $menu = array_values((array)($S['menu'] ?? []));
    for ($i = 0; $i < 7; $i++) {
        $m = (array)($menu[$i] ?? []);
        $b .= '<div class="row3 row-menu">' . gx_f_in('Texto', 'menu[' . $i . '][label]', $m['label'] ?? '') . gx_f_in('Enlace', 'menu[' . $i . '][link]', $m['link'] ?? '')
            . gx_f_sel('Ícono', 'menu[' . $i . '][icon]', GX_MENU_ICONS, $m['icon'] ?? '')
            . gx_f_in('Orden', 'menu[' . $i . '][order]', (string)(($i + 1) * 10), 'number', '', 'min="0" max="99"')
            . gx_f_chk('Visible', 'menu[' . $i . '][visible]', !empty($m['visible'])) . '</div>';
    }
    $b .= '</section></div><div class="grid2"><section class="card-p"><h2>Tarjetas de contacto</h2>'
        . gx_f_in('Título del teléfono', 'cards[call_title]', $c['call_title'] ?? '') . gx_f_in('Prefijo del teléfono', 'cards[call_label]', $c['call_label'] ?? '')
        . gx_f_in('Título de WhatsApp', 'cards[wa_title]', $c['wa_title'] ?? '') . gx_f_in('Prefijo de WhatsApp', 'cards[wa_label]', $c['wa_label'] ?? '')
        . gx_f_in('Título del correo', 'cards[mail_title]', $c['mail_title'] ?? '')
        . '</section><section class="card-p"><h2>Colores principales</h2><div class="cols">'
        . gx_f_in('Azul oscuro (títulos, cabeceras)', 'col[navy]', $col['navy'] ?? '#072253', 'color') . gx_f_in('Azul del logo', 'col[blue]', $col['blue'] ?? '#0b489d', 'color')
        . gx_f_in('Rojo del logo (botones)', 'col[red]', $col['red'] ?? '#d3202e', 'color') . gx_f_in('Fondo', 'col[bg]', $col['bg'] ?? '#ffffff', 'color')
        . gx_f_in('Fondo suave', 'col[soft]', $col['soft'] ?? '#edf4fd', 'color') . gx_f_in('Texto', 'col[ink]', $col['ink'] ?? '#0d2147', 'color')
        . '</div><h2>Datos para Google (LocalBusiness)</h2>'
        . gx_f_ta('Descripción', 'ld_description', $S['ld_description'] ?? '', 3) . gx_f_in('Rango de precio', 'ld_price', $S['ld_price'] ?? '')
        . '</section></div><div class="grid2"><section class="card-p"><h2>Envío de correo (SMTP opcional)</h2><p class="note">Si no lo activa, se usa la función mail() del hosting.</p>'
        . gx_f_chk('Usar SMTP', 'smtp_on', !empty($sm['enabled']))
        . gx_f_in('Servidor', 'smtp_host', $sm['host'] ?? '') . gx_f_in('Puerto', 'smtp_port', (string)($sm['port'] ?? 587), 'number')
        . gx_f_sel('Cifrado', 'smtp_secure', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Ninguno'], $sm['secure'] ?? 'tls')
        . gx_f_in('Usuario', 'smtp_user', $sm['user'] ?? '') . gx_f_in('Contraseña', 'smtp_pass', '', 'password', 'Déjela vacía para conservar la actual.', 'autocomplete="new-password"')
        . gx_f_in('Remitente (From)', 'smtp_from', $sm['from'] ?? '', 'email')
        . '</section><section class="card-p"><h2>Acceso al panel</h2>'
        . gx_f_in('Restringir por IP (opcional)', 'admin_ips', $S['admin_ips'] ?? '', 'text', 'IPs o rangos separados por coma. Ej.: 190.1.2.3, 200.5.6.0/24. Vacío = sin restricción. Su IP actual: ' . gx_ip())
        . gx_f_sel('Cabecera de IP real (si usa Cloudflare o proxy)', 'proxy_header', ['' => 'Ninguna (recomendado)', 'CF-Connecting-IP' => 'Cloudflare (CF-Connecting-IP)', 'X-Forwarded-For' => 'X-Forwarded-For'], $cfg['proxy_header'] ?? '')
        . '</section></div><div class="sticky-save"><button class="b b-p" type="submit">' . gx_icon('save') . ' Guardar ajustes</button></div></form>';
    gx_a_page('Ajustes', 'ajustes', $b);
}

function gx_view_mensajes(array $user): void
{
    $rows = gx_db()->query('SELECT * FROM messages ORDER BY id DESC LIMIT 300')->fetchAll();
    $b = '<section class="card-p"><div class="row-btns">' . gx_f_open('msg_export') . '<button class="b b-s" type="submit">' . gx_icon('download') . ' Exportar CSV</button></form></div>';
    if (!$rows) {
        $b .= '<p class="note">Todavía no hay mensajes. Cada envío del formulario se guarda aquí.</p>';
    } else {
        $b .= '<table class="tbl"><thead><tr><th>Fecha</th><th>Nombre</th><th>Teléfono</th><th>Servicio</th><th>Mensaje</th><th></th></tr></thead><tbody>';
        foreach ($rows as $m) {
            $b .= '<tr class="' . ($m['is_read'] ? '' : 'new') . '"><td data-l="Fecha">' . e(gx_fmt_date((int)$m['created'])) . '</td><td data-l="Nombre">' . e($m['name']) . '</td>'
                . '<td data-l="Teléfono">' . e($m['phone']) . '</td><td data-l="Servicio">' . e($m['service']) . '</td><td data-l="Mensaje">' . e(mb_substr((string)$m['message'], 0, 70)) . '</td>'
                . '<td><a class="b b-s" href="?p=mensaje&amp;id=' . (int)$m['id'] . '">Ver</a></td></tr>';
        }
        $b .= '</tbody></table>';
    }
    gx_a_page('Mensajes', 'mensajes', $b . '</section>');
}

function gx_view_mensaje(array $user): void
{
    $id = gx_int(is_string($_GET['id'] ?? null) ? (string)$_GET['id'] : '', 1, PHP_INT_MAX, 0);
    $st = gx_db()->prepare('SELECT * FROM messages WHERE id = :i');
    $st->execute([':i' => $id]);
    $m = $st->fetch();
    if (!$m) {
        gx_redirect('mensajes');
    }
    gx_db()->prepare('UPDATE messages SET is_read = 1 WHERE id = :i')->execute([':i' => $id]);
    $b = '<section class="card-p"><dl class="dl"><dt>Fecha</dt><dd>' . e(gx_fmt_date((int)$m['created'])) . '</dd><dt>Nombre</dt><dd>' . e($m['name']) . '</dd>'
        . '<dt>Teléfono</dt><dd><a href="tel:' . e(preg_replace('/[^0-9+]/', '', (string)$m['phone']) ?? '') . '">' . e($m['phone']) . '</a></dd>'
        . '<dt>Correo</dt><dd>' . e($m['email'] !== '' ? $m['email'] : '—') . '</dd><dt>Tipo de servicio</dt><dd>' . e($m['service']) . '</dd>'
        . '<dt>Ubicación / destino</dt><dd>' . e($m['place'] !== '' ? $m['place'] : '—') . '</dd><dt>Mensaje</dt><dd class="pre">' . e($m['message']) . '</dd>'
        . '<dt>Aviso por correo</dt><dd>' . e($m['mail_status']) . '</dd><dt>IP</dt><dd>' . e($m['ip']) . '</dd></dl>'
        . '<p><a class="b b-s" href="?p=mensajes">Volver</a> ' . gx_f_open('msg_delete', '<input type="hidden" name="id" value="' . $id . '">')
        . '<button class="b b-s b-d" type="submit" data-confirm="¿Eliminar este mensaje?">Eliminar</button></form></p></section>';
    gx_a_page('Mensaje', 'mensajes', $b);
}

function gx_view_seguridad(array $user): void
{
    $rep = $_SESSION['gx_integrity'] ?? null;
    $scan = $_SESSION['gx_scan'] ?? null;
    $cfg = gx_config();
    $base = gx_json_read(gx_baseline_path(), []);
    $dis = (string)ini_get('disable_functions');
    $need = ['exec', 'passthru', 'shell_exec', 'system', 'proc_open', 'popen', 'pcntl_exec', 'show_source'];
    $missing = array_filter($need, static function ($f) use ($dis) {
        return stripos($dis, $f) === false;
    });
    $writable = 0;
    foreach (['_core/lib/bootstrap.php', 'admin/index.php', 'enviar.php'] as $f) {
        if (is_file(GX_ROOT . '/' . $f) && is_writable(GX_ROOT . '/' . $f)) {
            $writable++;
        }
    }
    $b = '<section class="card-p"><h2>Estado</h2><ul class="chk">'
        . '<li>' . gx_status_pill(!gx_lockdown_active(), 'Normal', 'BLOQUEO') . ' Modo bloqueo (se activa solo si cambia un archivo de código o aparece PHP no autorizado)</li>'
        . '<li>' . gx_status_pill(!empty($cfg['basic_auth']), 'Activa', 'Inactiva') . ' Contraseña HTTP (Basic Auth) en la carpeta del panel</li>'
        . '<li>' . gx_status_pill((int)$user['totp_enabled'] === 1, 'Activa', 'Sin activar') . ' Verificación en dos pasos</li>'
        . '<li>' . gx_status_pill(PHP_VERSION_ID >= 80000, 'PHP ' . PHP_VERSION, 'PHP ' . PHP_VERSION . ' (actualice)') . ' Versión de PHP</li>'
        . '<li>' . gx_status_pill(!$missing, 'Activo', 'Parcial') . ' Funciones peligrosas deshabilitadas en PHP' . ($missing ? ' <small>(pida al hosting deshabilitar: ' . e(implode(', ', $missing)) . '; el sitio no usa ninguna)</small>' : '') . '</li>'
        . '<li>' . gx_status_pill(function_exists('imagewebp'), 'Disponible', 'Falta') . ' PHP-GD con WebP (necesario para subir imágenes)</li>'
        . '<li>' . gx_status_pill(!(bool)ini_get('display_errors'), 'Apagado', 'Encendido') . ' Mostrar errores al visitante</li>'
        . '<li>' . gx_status_pill($writable === 0, 'Solo lectura', 'Escribibles') . ' Archivos PHP del núcleo' . ($writable ? ' <small>(recomendado: permisos 444)</small>' : '') . '</li>'
        . '<li>Línea base creada: <strong>' . e(gx_fmt_date((int)($base['created'] ?? 0))) . '</strong> · ' . count((array)($base['files'] ?? [])) . ' archivos vigilados</li></ul></section>';

    $b .= '<section class="card-p"><h2>Monitor de integridad</h2><p class="note">Compara cada archivo de código con su huella SHA-256 tomada al instalar, y las páginas generadas con la última publicación.</p>'
        . gx_f_open('integrity_check') . '<button class="b b-p" type="submit">' . gx_icon('shield-check') . ' Verificar ahora</button></form>';
    if (is_array($rep)) {
        if ($rep['ok']) {
            $b .= '<div class="al al-ok">Todo en orden: ' . (int)$rep['checked'] . ' archivos verificados, sin cambios.</div>';
        } else {
            $b .= '<div class="al al-bad"><strong>Se detectaron diferencias.</strong></div><table class="tbl"><thead><tr><th>Tipo</th><th>Archivo</th></tr></thead><tbody>';
            foreach (['modified' => 'Modificado', 'added' => 'Agregado', 'deleted' => 'Eliminado', 'forbidden' => 'No permitido', 'generated' => 'Página pública alterada'] as $k => $lbl) {
                foreach ((array)$rep[$k] as $f) {
                    $b .= '<tr><td>' . e($lbl) . '</td><td><code>' . e((string)$f) . '</code></td></tr>';
                }
            }
            $b .= '</tbody></table>';
            if ($rep['generated'] && !$rep['critical']) {
                $b .= '<p class="note">Pulse <strong>Publicar</strong> para restaurar las páginas públicas y cambie las contraseñas de FTP/cPanel.</p>';
            }
        }
    }
    $b .= '</section><section class="card-p"><h2>Escáner de malware</h2><p class="note">Busca patrones típicos: código ofuscado, ejecución de comandos, iframes ocultos, scripts externos inyectados, redirecciones en .htaccess y código dentro de imágenes.</p>'
        . gx_f_open('scan') . '<button class="b b-p" type="submit">' . gx_icon('scan-search') . ' Escanear</button></form>';
    if (is_array($scan)) {
        if (!$scan['findings']) {
            $b .= '<div class="al al-ok">' . (int)$scan['scanned'] . ' archivos revisados: no se encontraron patrones sospechosos.</div>';
        } else {
            $b .= '<div class="al al-bad">' . count($scan['findings']) . ' hallazgo(s) en ' . (int)$scan['scanned'] . ' archivos.</div><table class="tbl"><thead><tr><th>Severidad</th><th>Archivo</th><th>Detalle</th></tr></thead><tbody>';
            foreach ($scan['findings'] as $f) {
                $b .= '<tr><td>' . e($f['sev']) . '</td><td><code>' . e($f['file']) . '</code></td><td>' . e($f['what']) . '</td></tr>';
            }
            $b .= '</tbody></table>';
        }
    }
    gx_a_page('Seguridad', 'seguridad', $b . '</section>');
}

function gx_view_respaldos(array $user): void
{
    $b = '<section class="card-p"><p class="note">La base de datos se respalda automáticamente cada 24 horas (se conservan los últimos ' . GX_BACKUP_KEEP . ').</p>'
        . '<div class="row-btns">' . gx_f_open('backup_create') . '<button class="b b-p" type="submit">Crear respaldo ahora</button></form>'
        . gx_f_open('backup_zip') . '<button class="b b-s" type="submit">' . gx_icon('download') . ' Descargar respaldo completo (.zip con imágenes)</button></form></div>'
        . '<table class="tbl"><thead><tr><th>Archivo</th><th>Fecha</th><th>Tamaño</th><th></th></tr></thead><tbody>';
    foreach (gx_backup_list() as $f) {
        $b .= '<tr><td data-l="Archivo"><code>' . e($f['name']) . '</code></td><td data-l="Fecha">' . e(gx_fmt_date($f['time'])) . '</td><td data-l="Tamaño">' . round($f['size'] / 1024) . ' KB</td><td>'
            . gx_f_open('backup_download', '<input type="hidden" name="name" value="' . e($f['name']) . '">') . '<button class="b b-s" type="submit">Descargar</button></form></td></tr>';
    }
    gx_a_page('Respaldos', 'respaldos', $b . '</tbody></table></section>');
}

function gx_view_registro(array $user): void
{
    $b = '<section class="card-p"><h2>Actividad reciente</h2><table class="tbl"><thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>IP</th><th>Detalle</th></tr></thead><tbody>';
    foreach (gx_db()->query('SELECT * FROM audit ORDER BY id DESC LIMIT 250') as $r) {
        $bad = preg_match('/FALLIDO|BLOQUE|DENEGADO|RECHAZADA|CSRF|ALERTA|LOCKDOWN/', (string)$r['event']);
        $b .= '<tr class="' . ($bad ? 'warnr' : '') . '"><td data-l="Fecha">' . e(date('d/m H:i:s', (int)$r['ts'])) . '</td><td data-l="Evento">' . e($r['event']) . '</td><td data-l="Usuario">' . e($r['usr']) . '</td>'
            . '<td data-l="IP">' . e($r['ip']) . '</td><td data-l="Detalle">' . e($r['detail']) . '</td></tr>';
    }
    gx_a_page('Registro', 'registro', $b . '</tbody></table></section>');
}

function gx_view_cuenta(array $user): void
{
    $cfg = gx_config();
    $b = '<div class="grid2"><section class="card-p"><h2>Cambiar contraseña</h2>' . gx_f_open('pw_change')
        . gx_f_in('Contraseña actual', 'cur', '', 'password', '', 'autocomplete="current-password" required')
        . gx_f_in('Nueva contraseña', 'new1', '', 'password', 'Mínimo 12 caracteres, con al menos 3 tipos.', 'autocomplete="new-password" minlength="12" required')
        . gx_f_in('Repetir nueva contraseña', 'new2', '', 'password', '', 'autocomplete="new-password" minlength="12" required')
        . '<button class="b b-p" type="submit">Cambiar contraseña</button></form></section>';
    $b .= '<section class="card-p"><h2>Verificación en dos pasos (2FA)</h2>';
    if ((int)$user['totp_enabled'] === 1) {
        $b .= '<p>' . gx_status_pill(true, 'Activa', '') . '</p>' . gx_f_open('totp_disable')
            . gx_f_in('Contraseña actual', 'cur', '', 'password', '', 'autocomplete="current-password" required')
            . gx_f_in('Código de su aplicación', 'code', '', 'text', '', 'inputmode="numeric" maxlength="8" required')
            . '<button class="b b-d" type="submit">Desactivar 2FA</button></form>';
    } elseif (!empty($_SESSION['gx_totp_new'])) {
        $sec = (string)$_SESSION['gx_totp_new'];
        $uri = 'otpauth://totp/' . rawurlencode('Grúas Express:' . $user['username']) . '?secret=' . $sec . '&issuer=' . rawurlencode('Grúas Express');
        $b .= '<p>1. En Google Authenticator, Microsoft Authenticator o Authy elija «Agregar cuenta» → «Ingresar clave de configuración» y escriba:</p>'
            . '<p class="secret"><code>' . e(trim(chunk_split($sec, 4, ' '))) . '</code></p><p class="note">Tipo: basado en tiempo. Si su aplicación lee enlaces: <a href="' . e($uri) . '">abrir otpauth</a>.</p>'
            . '<p>2. Escriba el código de 6 dígitos que muestra la aplicación:</p>' . gx_f_open('totp_enable')
            . gx_f_in('Contraseña actual', 'cur', '', 'password', '', 'autocomplete="current-password" required')
            . gx_f_in('Código', 'code', '', 'text', '', 'inputmode="numeric" maxlength="8" required')
            . '<button class="b b-p" type="submit">Activar 2FA</button></form>';
    } else {
        $b .= '<p>Añade un segundo factor (código del teléfono) al inicio de sesión. Muy recomendado.</p>' . gx_f_open('totp_start') . '<button class="b b-p" type="submit">Configurar 2FA</button></form>';
    }
    $b .= '</section></div>';
    if (!empty($cfg['basic_auth'])) {
        $b .= '<section class="card-p"><h2>Acceso HTTP (contraseña del servidor delante del login)</h2><p class="note">Esta es la primera puerta del panel, gestionada por el servidor web. Cámbiela aquí cuando quiera.</p>'
            . gx_f_open('ht_change')
            . '<div class="row3">' . gx_f_in('Usuario HTTP', 'ht_user', '', 'text', '', 'maxlength="40" autocomplete="off" required')
            . gx_f_in('Nueva contraseña HTTP', 'ht_pass', '', 'password', '', 'autocomplete="new-password" minlength="12" required')
            . gx_f_in('Su contraseña del panel', 'cur', '', 'password', '', 'autocomplete="current-password" required') . '</div>'
            . '<button class="b b-p" type="submit">Actualizar acceso HTTP</button></form></section>';
    }
    gx_a_page('Mi cuenta', 'cuenta', $b);
}
