<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('audit.view');

$moduleFilter = str_input('module');

$modules = Database::all("SELECT DISTINCT module FROM audit_log ORDER BY module");
$moduleOptions = [];
foreach ($modules as $m) { $moduleOptions[$m['module']] = $m['module']; }

if ($moduleFilter !== '') {
    $rows = Database::all(
        "SELECT * FROM audit_log WHERE module = ? ORDER BY created_at DESC, id DESC LIMIT 200",
        [$moduleFilter]
    );
} else {
    $rows = Database::all("SELECT * FROM audit_log ORDER BY created_at DESC, id DESC LIMIT 200");
}

/** Map action to a badge variant. */
function audit_action_badge(string $action): string
{
    return [
        'create' => 'badge-success',
        'update' => 'badge-accent',
        'delete' => 'badge-danger',
    ][$action] ?? 'badge-muted';
}

$PAGE_TITLE = 'Auditoría';
$ACTIVE = 'audit';
require 'partials/head.php';
?>
<div class="page-head">
    <h1>Auditoría</h1>
    <p>Registro de cambios realizados en la plataforma (últimos 200 eventos).</p>
</div>

<form method="get" action="<?= e(base_url('admin/audit.php')) ?>" class="card card-pad-lg">
    <div class="form-row">
        <div class="field">
            <label for="module">Filtrar por módulo</label>
            <select class="select" id="module" name="module" onchange="this.form.submit()">
                <?= options($moduleOptions, $moduleFilter, 'Todos los módulos') ?>
            </select>
        </div>
    </div>
    <?php if ($moduleFilter !== ''): ?>
        <div class="page-actions mt-2"><a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/audit.php')) ?>">Quitar filtro</a></div>
    <?php endif; ?>
</form>

<?php if (!$rows): ?>
    <div class="empty-state card">
        <div class="es-icon">🧾</div>
        <h2>Sin registros de auditoría</h2>
        <p>Todavía no se han registrado cambios<?= $moduleFilter !== '' ? ' para este módulo' : '' ?>.</p>
    </div>
<?php else: ?>
    <div class="table-wrap mt-3">
        <table class="data">
            <thead>
                <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Registro</th><th>Detalle</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(fmt_date($r['created_at'], 'd/m/Y H:i')) ?></td>
                    <td><?= e($r['user_name'] ?? '—') ?></td>
                    <td><span class="badge <?= e(audit_action_badge($r['action'])) ?>"><?= e($r['action']) ?></span></td>
                    <td><?= e($r['module']) ?></td>
                    <td><?= e($r['record_id'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($r['before_json']) || !empty($r['after_json'])): ?>
                            <details>
                                <summary class="btn btn-sm btn-ghost">Ver antes/después</summary>
                                <?php if (!empty($r['before_json'])): ?>
                                    <div class="muted mt-1" style="font-size:.8rem">Antes:</div>
                                    <pre style="white-space:pre-wrap;word-break:break-word;max-width:520px;overflow:auto"><?= e($r['before_json']) ?></pre>
                                <?php endif; ?>
                                <?php if (!empty($r['after_json'])): ?>
                                    <div class="muted mt-1" style="font-size:.8rem">Después:</div>
                                    <pre style="white-space:pre-wrap;word-break:break-word;max-width:520px;overflow:auto"><?= e($r['after_json']) ?></pre>
                                <?php endif; ?>
                            </details>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
