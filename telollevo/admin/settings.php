<?php
require __DIR__ . '/_boot.php';
auth_require();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['action'] ?? '');
    $S = get_settings();

    if ($act === 'save_mail' || $act === 'test_mail') {
        $to = trim((string)($_POST['mail_to'] ?? ''));
        $from = trim((string)($_POST['mail_from'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash('error', 'El correo de destino no es válido.'); header('Location: ' . url('admin/settings.php'), true, 303); exit; }
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) { flash('error', 'El correo remitente no es válido.'); header('Location: ' . url('admin/settings.php'), true, 303); exit; }
        $S['mail_to'] = mb_substr($to, 0, 120);
        $S['mail_from'] = mb_substr($from, 0, 120);
        $S['smtp']['enabled'] = !empty($_POST['smtp_enabled']);
        $S['smtp']['host'] = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_POST['smtp_host'] ?? ''));
        $port = (int)($_POST['smtp_port'] ?? 587); $S['smtp']['port'] = ($port > 0 && $port < 65536) ? $port : 587;
        $sec = (string)($_POST['smtp_secure'] ?? 'tls'); $S['smtp']['secure'] = in_array($sec, ['tls', 'ssl', 'none'], true) ? $sec : 'tls';
        $S['smtp']['user'] = mb_substr(trim((string)($_POST['smtp_user'] ?? '')), 0, 120);
        $pw = (string)($_POST['smtp_pass'] ?? '');
        if ($pw !== '') $S['smtp']['pass'] = mb_substr($pw, 0, 200);
        if (!empty($_POST['smtp_pass_clear'])) $S['smtp']['pass'] = '';
        save_settings($S);
        if ($act === 'test_mail') {
            $brand = get_content()['site']['brand'];
            [$ok, $err] = send_mail($S['mail_to'], 'Prueba de correo - ' . $brand, "Este es un correo de prueba enviado desde el panel de $brand.\nSi lo recibes, el formulario de la página está funcionando.", '<div style="font-family:Arial,sans-serif;padding:24px;border:1px solid #DCE6F5;border-radius:12px;max-width:520px"><h2 style="color:#062B5F;margin:0 0 8px">Prueba de correo</h2><p style="color:#54657F">Este es un correo de prueba enviado desde el panel de <strong>' . e($brand) . '</strong>. Si lo recibes, el formulario de la página está funcionando.</p></div>', '', $brand);
            flash($ok ? 'ok' : 'error', $ok ? 'Correo de prueba enviado a ' . $S['mail_to'] . '. Revisa tu bandeja (y la carpeta de spam).' : 'No se pudo enviar: ' . $err);
        } else {
            flash('ok', 'Configuración de correo guardada.');
        }
    } elseif ($act === 'save_account') {
        $cur = (string)($_POST['cur_pass'] ?? '');
        $newUser = trim((string)($_POST['new_user'] ?? ''));
        $p1 = (string)($_POST['new_pass'] ?? ''); $p2 = (string)($_POST['new_pass2'] ?? '');
        $u = user_admin();
        $err = '';
        if (rl_hits('pw:' . client_ip(), 900) >= 5) $err = 'Demasiados intentos. Espera unos minutos.';
        elseif (!password_verify($cur, (string)$u['hash'])) { rl_add('pw:' . client_ip(), 900); $err = 'La contraseña actual no es correcta.'; }
        elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,32}$/', $newUser)) $err = 'El usuario debe tener entre 3 y 32 caracteres (letras, números, punto, guion).';
        elseif ($p1 !== '' && ($p1 !== $p2)) $err = 'Las contraseñas nuevas no coinciden.';
        elseif ($p1 !== '' && (strlen($p1) < 10 || !preg_match('/[A-Za-z]/', $p1) || !preg_match('/\d/', $p1))) $err = 'La contraseña nueva debe tener al menos 10 caracteres, con letras y números.';
        elseif ($p1 !== '' && strcasecmp($p1, $newUser) === 0) $err = 'La contraseña no puede ser igual al usuario.';
        if ($err) { flash('error', $err); }
        else {
            $d = users_load();
            $d['users'][0]['username'] = $newUser;
            if ($p1 !== '') $d['users'][0]['hash'] = password_hash($p1, PASSWORD_DEFAULT);
            $d['users'][0]['updated'] = date('Y-m-d H:i:s');
            store_write('users', $d);
            // Nueva sesión tras el cambio
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            session_regenerate_id(true);
            flash('ok', 'Datos de acceso actualizados.' . ($p1 !== '' ? ' Usa la nueva contraseña la próxima vez que ingreses.' : ''));
        }
    }
    header('Location: ' . url('admin/settings.php'), true, 303);
    exit;
}

$S = get_settings();
$u = user_admin();
admin_header('Correo y seguridad', 'settings', 'A dónde llegan las solicitudes y cómo ingresas al panel.');
?>
<form method="post" class="edit-form" data-dirty-guard>
  <?= csrf_field() ?>
  <section class="card">
    <h2><?= icon('envelope-simple', '') ?> Recepción de solicitudes</h2>
    <p class="muted">Cada solicitud del formulario se envía a este correo y además se guarda en el buzón del panel.</p>
    <div class="fields">
      <div class="fld"><label for="mt">Correo que recibe las solicitudes</label><input id="mt" type="email" name="mail_to" value="<?= e($S['mail_to']) ?>" required></div>
      <div class="fld"><label for="mf">Correo remitente (opcional)</label><input id="mf" type="email" name="mail_from" value="<?= e($S['mail_from']) ?>" placeholder="Si lo dejas vacío se usa no-reply@tu-dominio"><small class="help">Debe ser una cuenta de tu mismo dominio para que no caiga en spam.</small></div>
    </div>
    <details class="adv" <?= $S['smtp']['enabled'] ? 'open' : '' ?>>
      <summary><?= icon('gear-six', '') ?> Opciones avanzadas: usar servidor SMTP</summary>
      <p class="muted small">Solo si los correos no llegan con la configuración normal. Usa los datos SMTP de tu cuenta de correo (los entrega tu hosting).</p>
      <div class="fields">
        <div class="fld fld-bool"><label class="switch"><input type="checkbox" name="smtp_enabled" value="1" <?= $S['smtp']['enabled'] ? 'checked' : '' ?>><span class="sw"></span><span class="sw-l">Usar SMTP para enviar</span></label></div>
        <div class="fld"><label for="sh">Servidor SMTP</label><input id="sh" type="text" name="smtp_host" value="<?= e($S['smtp']['host']) ?>" placeholder="mail.tudominio.com" autocomplete="off"></div>
        <div class="fld"><label for="sp">Puerto</label><input id="sp" type="number" name="smtp_port" value="<?= e($S['smtp']['port']) ?>"></div>
        <div class="fld"><label for="ss">Seguridad</label><select id="ss" name="smtp_secure"><?php foreach (['tls' => 'STARTTLS (puerto 587)', 'ssl' => 'SSL/TLS (puerto 465)', 'none' => 'Ninguna'] as $k => $l): ?><option value="<?= $k ?>" <?= $S['smtp']['secure'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="fld"><label for="su">Usuario SMTP</label><input id="su" type="text" name="smtp_user" value="<?= e($S['smtp']['user']) ?>" placeholder="info@telollevogt.com" autocomplete="off"></div>
        <div class="fld"><label for="spw">Contraseña SMTP</label><input id="spw" type="password" name="smtp_pass" placeholder="<?= $S['smtp']['pass'] !== '' ? '•••••••• (guardada, deja vacío para conservarla)' : 'Contraseña de la cuenta de correo' ?>" autocomplete="new-password"></div>
        <?php if ($S['smtp']['pass'] !== ''): ?><div class="fld fld-bool"><label class="switch"><input type="checkbox" name="smtp_pass_clear" value="1"><span class="sw"></span><span class="sw-l">Borrar contraseña guardada</span></label></div><?php endif; ?>
      </div>
    </details>
    <div class="row-actions">
      <button class="btn btn-primary" type="submit" name="action" value="save_mail"><?= icon('floppy-disk', '') ?><span>Guardar</span></button>
      <button class="btn btn-soft" type="submit" name="action" value="test_mail"><?= icon('paper-plane-tilt', '') ?><span>Guardar y enviar correo de prueba</span></button>
    </div>
  </section>
</form>

<form method="post" class="edit-form" id="cuenta" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="action" value="save_account">
  <section class="card">
    <h2><?= icon('lock-key', '') ?> Acceso al panel</h2>
    <p class="muted">Cambia tu usuario o contraseña. Usa una contraseña larga que no uses en otros sitios.</p>
    <div class="fields">
      <div class="fld"><label for="nu">Usuario</label><input id="nu" type="text" name="new_user" value="<?= e($u['username']) ?>" maxlength="32" autocomplete="username" required></div>
      <div class="fld"><label for="cp">Contraseña actual *</label><input id="cp" type="password" name="cur_pass" autocomplete="current-password" required></div>
      <div class="fld"><label for="np">Contraseña nueva</label><input id="np" type="password" name="new_pass" autocomplete="new-password" placeholder="Déjala vacía para no cambiarla"><small class="help">Mínimo 10 caracteres, con letras y números.</small></div>
      <div class="fld"><label for="np2">Repetir contraseña nueva</label><input id="np2" type="password" name="new_pass2" autocomplete="new-password"></div>
    </div>
    <div class="row-actions"><button class="btn btn-primary" type="submit"><?= icon('shield-check', '') ?><span>Actualizar acceso</span></button></div>
  </section>
</form>
<?php admin_footer();
