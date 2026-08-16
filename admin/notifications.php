<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('settings.manage');

Push::ensureSchema();

if (is_post()) {
    Security::requireCsrf();
    $op = str_input('op');
    if ($op === 'gen_keys') {
        Push::generateAndStoreKeys();
        Audit::log('vapid_keys', 'notifications');
        flash('success', 'Claves VAPID generadas. Ya puedes activar las notificaciones.');
    } elseif ($op === 'save_config') {
        Settings::set('push_enabled', post('push_enabled') ? '1' : '0', 'push');
        Settings::set('push_subject', str_input('push_subject') ?: '', 'push');
        Settings::set('push_delay_hours', (string)max(0, (int)int_input('push_delay_hours', 24)), 'push');
        Audit::log('update', 'notifications');
        flash('success', 'Configuración de notificaciones guardada.');
    } elseif ($op === 'send_test') {
        if (!Push::vapidReady()) { flash('danger', 'Primero genera las claves VAPID.'); }
        else {
            $n = Push::sendToAll('Notificación de prueba', 'Las notificaciones funcionan correctamente. ⚽', base_url('index.php'), 'test');
            flash('success', "Notificación de prueba enviada a {$n} dispositivo(s).");
        }
    } elseif ($op === 'process_now') {
        $r = Push::processDue();
        flash('info', 'Procesado: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
    } elseif ($op === 'clear_subs') {
        Database::q("DELETE FROM push_subscriptions");
        Audit::log('clear', 'notifications');
        flash('success', 'Suscripciones eliminadas.');
    }
    redirect(base_url('admin/notifications.php'));
}

$c = Push::config();
$subs = Push::subscriberCount();
$log = Database::all("SELECT * FROM notifications_log ORDER BY created_at DESC LIMIT 50");
$cronUrl = base_url('cron/notify.php');
$cronPath = FL_ROOT . '/cron/notify.php';

$PAGE_TITLE = 'Notificaciones';
$ACTIVE = 'notifications';
require 'partials/head.php';
?>
<div class="page-head">
    <h1>Notificaciones push</h1>
    <p>App instalable (PWA) y avisos automáticos al teléfono con los resultados de cada jornada.</p>
</div>

<div class="cols-2">
    <div class="card">
        <h3 style="margin-top:0">Estado</h3>
        <div class="detail-list">
            <div class="row"><span class="k">Claves VAPID</span><span class="v"><?= Push::vapidReady() ? '<span class="badge badge-success">Generadas</span>' : '<span class="badge badge-danger">Faltan</span>' ?></span></div>
            <div class="row"><span class="k">Notificaciones activas</span><span class="v"><?= $c['enabled'] ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-muted">No</span>' ?></span></div>
            <div class="row"><span class="k">Dispositivos suscritos</span><span class="v"><?= (int)$subs ?></span></div>
            <div class="row"><span class="k">Retraso tras resultados</span><span class="v"><?= (int)$c['delay'] ?> horas</span></div>
        </div>
        <div class="page-actions mt-3">
            <form method="post" data-confirm="<?= Push::vapidReady() ? 'Regenerar las claves invalidará las suscripciones actuales. ¿Continuar?' : '' ?>">
                <?= Security::csrfField() ?><input type="hidden" name="op" value="gen_keys">
                <button class="btn btn-sm" type="submit"><?= Push::vapidReady() ? 'Regenerar claves VAPID' : 'Generar claves VAPID' ?></button>
            </form>
            <form method="post"><?= Security::csrfField() ?><input type="hidden" name="op" value="send_test"><button class="btn btn-sm btn-ghost" type="submit">Enviar prueba</button></form>
            <form method="post"><?= Security::csrfField() ?><input type="hidden" name="op" value="process_now"><button class="btn btn-sm btn-ghost" type="submit">Procesar pendientes ahora</button></form>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Configuración</h3>
        <form method="post">
            <?= Security::csrfField() ?>
            <input type="hidden" name="op" value="save_config">
            <div class="field">
                <label class="check-item" style="max-width:100%"><input type="checkbox" name="push_enabled" value="1"<?= checked($c['enabled']) ?>> Activar notificaciones push</label>
            </div>
            <div class="field">
                <label for="push_subject">Correo de contacto (VAPID subject)</label>
                <input class="input" id="push_subject" name="push_subject" value="<?= e(Settings::get('push_subject', '')) ?>" placeholder="mailto:admin@midominio.com">
                <div class="help">Requerido por los servidores push. Si se deja vacío se usa el correo de contacto.</div>
            </div>
            <div class="field">
                <label for="push_delay_hours">Horas después de subir los resultados</label>
                <input class="input" type="number" min="0" id="push_delay_hours" name="push_delay_hours" value="<?= (int)$c['delay'] ?>">
                <div class="help">Cuando se suben todos los resultados de una jornada, se envían las notificaciones pasadas estas horas (por defecto 24).</div>
            </div>
            <button class="btn" type="submit">Guardar configuración</button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <h3 style="margin-top:0">Tarea programada (cron)</h3>
    <p class="muted" style="margin-top:-.4rem">Para que las notificaciones se envíen automáticamente, crea en cPanel un <strong>Cron Job</strong> que ejecute este archivo cada hora:</p>
    <div class="table-wrap"><table class="data" style="min-width:auto"><tbody>
        <tr><td>Comando PHP (recomendado)</td><td><code>php <?= e($cronPath) ?></code></td></tr>
        <tr><td>Alternativa por URL</td><td><code>curl -s "<?= e($cronUrl) ?>?key=TU_CLAVE"</code></td></tr>
        <tr><td>Frecuencia sugerida</td><td>Cada hora: <code>0 * * * *</code></td></tr>
    </tbody></table></div>
    <p class="help mt-1">Para la ejecución por URL, define una clave en <code>push_cron_key</code> (Configuración) e inclúyela como <code>?key=</code>. Por comando PHP no se requiere clave.</p>
</div>

<div class="card mt-3">
    <div class="flex justify-between items-center wrap"><h3 style="margin:0">Historial de envíos</h3>
        <form method="post" data-confirm="¿Eliminar todas las suscripciones?"><?= Security::csrfField() ?><input type="hidden" name="op" value="clear_subs"><button class="btn btn-sm btn-ghost" type="submit">Vaciar suscripciones</button></form>
    </div>
    <?php if (!$log): ?><p class="muted mt-2">Aún no se han enviado notificaciones.</p><?php else: ?>
        <div class="table-wrap mt-2"><table class="data">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Título</th><th class="num">Enviados</th></tr></thead>
            <tbody>
            <?php foreach ($log as $l): ?>
                <tr><td><?= e(fmt_date($l['created_at'], 'd/m/Y H:i')) ?></td><td><span class="badge badge-muted"><?= e($l['type']) ?></span></td><td><?= e($l['title']) ?><br><span class="muted" style="font-size:.8rem"><?= e(mb_substr((string)$l['body'],0,90)) ?></span></td><td class="num"><?= (int)$l['sent_count'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php require 'partials/foot.php'; ?>
