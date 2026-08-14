<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('security.manage');

$action = str_input('action', '');

/* ---- Handle POST -------------------------------------------------------- */
if (is_post()) {
    Security::requireCsrf();

    if ($action === 'clear_attempts') {
        $count = (int)Database::scalar("SELECT COUNT(*) FROM login_attempts");
        Database::q("DELETE FROM login_attempts");
        Audit::log('delete', 'security', 'login_attempts', ['count' => $count], null);
        flash('success', 'Se limpiaron los intentos de acceso registrados.');
        redirect(base_url('admin/security.php'));
    }

    // Update rate-limit settings.
    $before = [
        'login_max_attempts' => Settings::get('login_max_attempts'),
        'login_lockout_min'  => Settings::get('login_lockout_min'),
    ];
    $maxAttempts = max(1, min(100, (int)int_input('login_max_attempts', 8)));
    $lockout = max(1, min(1440, (int)int_input('login_lockout_min', 15)));
    Settings::set('login_max_attempts', $maxAttempts, 'security');
    Settings::set('login_lockout_min', $lockout, 'security');
    Audit::log('update', 'security', 'rate_limit', $before,
        ['login_max_attempts' => (string)$maxAttempts, 'login_lockout_min' => (string)$lockout]);
    flash('success', 'Ajustes de seguridad guardados.');
    redirect(base_url('admin/security.php'));
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
         || (($_SERVER['SERVER_PORT'] ?? '') == 443);

$attempts = Database::all(
    "SELECT ip, username, success, created_at FROM login_attempts ORDER BY created_at DESC LIMIT 50"
);

$PAGE_TITLE = 'Seguridad';
$ACTIVE = 'security';
require 'partials/head.php';
?>
<div class="page-head">
    <h1>Seguridad</h1>
    <p>Estado de seguridad de la plataforma y control de accesos.</p>
</div>

<h3>Estado de la sesión y el servidor</h3>
<div class="card card-pad-lg">
    <div class="detail-list">
        <div class="row">
            <span class="k">Conexión HTTPS</span>
            <span class="v"><?= $https ? '<span class="badge badge-success">Activa</span>' : '<span class="badge badge-warning">Inactiva</span>' ?></span>
        </div>
        <div class="row"><span class="k">Cookies de sesión</span><span class="v"><span class="badge badge-success">HttpOnly + SameSite=Lax</span></span></div>
        <div class="row"><span class="k">Regeneración de ID de sesión</span><span class="v"><span class="badge badge-success">Activa</span></span></div>
        <div class="row"><span class="k">Cabeceras de seguridad</span><span class="v"><span class="badge badge-success">Activas</span></span></div>
    </div>
    <p class="muted mt-2" style="font-size:.85rem">
        Se aplican las cabeceras <code>X-Content-Type-Options</code>, <code>X-Frame-Options</code>,
        <code>Referrer-Policy</code>, <code>Permissions-Policy</code> y una política de seguridad de contenido
        (CSP) restrictiva. Las sesiones utilizan cookies HttpOnly con <code>SameSite=Lax</code> y se regenera
        el identificador de sesión periódicamente para mitigar el secuestro de sesión. Todas las escrituras
        del panel están protegidas contra CSRF.
    </p>
</div>

<h3 class="mt-3">Límite de intentos de acceso</h3>
<form method="post" action="<?= e(base_url('admin/security.php')) ?>" class="card card-pad-lg">
    <?= Security::csrfField() ?>
    <div class="form-row">
        <div class="field">
            <label for="login_max_attempts">Intentos fallidos máximos</label>
            <input class="input" type="number" min="1" max="100" id="login_max_attempts" name="login_max_attempts" value="<?= e(Settings::get('login_max_attempts', 8)) ?>">
        </div>
        <div class="field">
            <label for="login_lockout_min">Duración del bloqueo (minutos)</label>
            <input class="input" type="number" min="1" max="1440" id="login_lockout_min" name="login_lockout_min" value="<?= e(Settings::get('login_lockout_min', 15)) ?>">
        </div>
    </div>
    <div class="page-actions mt-3">
        <button class="btn" type="submit">Guardar ajustes</button>
    </div>
</form>

<div class="flex justify-between items-center wrap mt-3" style="gap:1rem">
    <h3 style="margin:0">Intentos de acceso recientes</h3>
    <?php if ($attempts): ?>
        <form method="post" action="<?= e(base_url('admin/security.php?action=clear_attempts')) ?>" data-confirm="¿Limpiar todo el registro de intentos de acceso?">
            <?= Security::csrfField() ?>
            <button class="btn btn-sm btn-danger" type="submit">Limpiar intentos de acceso</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$attempts): ?>
    <div class="empty-state card">
        <div class="es-icon">🛡️</div>
        <h2>Sin registros</h2>
        <p>No hay intentos de acceso registrados.</p>
    </div>
<?php else: ?>
    <div class="table-wrap mt-2">
        <table class="data">
            <thead>
                <tr><th>Fecha</th><th>IP</th><th>Usuario</th><th>Resultado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($attempts as $a): ?>
                <tr>
                    <td><?= e(fmt_date($a['created_at'], 'd/m/Y H:i')) ?></td>
                    <td><?= e($a['ip']) ?></td>
                    <td><?= e($a['username'] ?? '—') ?></td>
                    <td>
                        <?php if ((int)$a['success'] === 1): ?>
                            <span class="badge badge-success">Exitoso</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Fallido</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
