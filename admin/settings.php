<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('settings.manage');

/* ---- Handle POST -------------------------------------------------------- */
if (is_post()) {
    Security::requireCsrf();

    $before = [
        'default_theme_id'   => Settings::get('default_theme_id'),
        'login_max_attempts' => Settings::get('login_max_attempts'),
        'login_lockout_min'  => Settings::get('login_lockout_min'),
        'contact_email'      => Settings::get('contact_email'),
    ];

    $themeId = int_input('default_theme_id');
    if ($themeId && Database::scalar("SELECT id FROM themes WHERE id = ?", [$themeId])) {
        Settings::set('default_theme_id', $themeId, 'general');
    }

    $maxAttempts = max(1, min(100, (int)int_input('login_max_attempts', 8)));
    Settings::set('login_max_attempts', $maxAttempts, 'security');

    $lockout = max(1, min(1440, (int)int_input('login_lockout_min', 15)));
    Settings::set('login_lockout_min', $lockout, 'security');

    $contact = str_input('contact_email');
    if ($contact !== '' && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'El correo de contacto no es válido.');
        redirect(base_url('admin/settings.php'));
    }
    Settings::set('contact_email', $contact, 'content');

    $after = [
        'default_theme_id'   => (string)($themeId ?: $before['default_theme_id']),
        'login_max_attempts' => (string)$maxAttempts,
        'login_lockout_min'  => (string)$lockout,
        'contact_email'      => $contact,
    ];
    Audit::log('update', 'settings', 'general', $before, $after);
    flash('success', 'Configuración guardada correctamente.');
    redirect(base_url('admin/settings.php'));
}

$themes = Database::all("SELECT id, name FROM themes ORDER BY sort_order");
$themeOptions = [];
foreach ($themes as $t) { $themeOptions[$t['id']] = $t['name']; }

$PAGE_TITLE = 'Configuración';
$ACTIVE = 'settings';
require 'partials/head.php';
?>
<div class="page-head">
    <h1>Configuración</h1>
    <p>Ajustes técnicos generales de la plataforma.</p>
</div>

<form method="post" action="<?= e(base_url('admin/settings.php')) ?>" class="card card-pad-lg">
    <?= Security::csrfField() ?>

    <h3>General</h3>
    <div class="form-row">
        <div class="field">
            <label for="default_theme_id">Tema por defecto</label>
            <select class="select" id="default_theme_id" name="default_theme_id">
                <?= options($themeOptions, Settings::get('default_theme_id')) ?>
            </select>
            <div class="help">Se usa cuando una liga no define su propio tema.</div>
        </div>
        <div class="field">
            <label for="contact_email">Correo de contacto</label>
            <input class="input" type="email" id="contact_email" name="contact_email" value="<?= e(Settings::get('contact_email', '')) ?>" placeholder="contacto@ejemplo.com">
        </div>
    </div>

    <h3 class="mt-3">Seguridad de acceso</h3>
    <div class="form-row">
        <div class="field">
            <label for="login_max_attempts">Intentos de acceso máximos</label>
            <input class="input" type="number" min="1" max="100" id="login_max_attempts" name="login_max_attempts" value="<?= e(Settings::get('login_max_attempts', 8)) ?>">
            <div class="help">Intentos fallidos permitidos antes del bloqueo temporal.</div>
        </div>
        <div class="field">
            <label for="login_lockout_min">Duración del bloqueo (minutos)</label>
            <input class="input" type="number" min="1" max="1440" id="login_lockout_min" name="login_lockout_min" value="<?= e(Settings::get('login_lockout_min', 15)) ?>">
        </div>
    </div>

    <div class="page-actions mt-3">
        <button class="btn" type="submit">Guardar configuración</button>
    </div>
</form>

<h3 class="mt-3">Información técnica</h3>
<div class="card card-pad-lg">
    <div class="detail-list">
        <div class="row"><span class="k">Versión de PHP</span><span class="v"><?= e(PHP_VERSION) ?></span></div>
        <div class="row"><span class="k">Servidor</span><span class="v"><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'No disponible') ?></span></div>
        <div class="row"><span class="k">Configuración de base de datos</span><span class="v">app/config.php</span></div>
    </div>
    <p class="muted mt-2" style="font-size:.85rem">La configuración de conexión a la base de datos se gestiona directamente en el archivo <code>app/config.php</code> del servidor por seguridad y no es editable desde el panel.</p>
</div>
<?php require 'partials/foot.php'; ?>
