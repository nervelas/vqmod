<?php
declare(strict_types=1);
/** Mensajes recibidos desde el formulario de contacto. */

$token = Csrf::token();
$view  = (int) get('id', '0');

if (get('action') === 'delete' && $view > 0 && Csrf::check(get('token'))) {
    Database::delete('submissions', 'id = :id', ['id' => $view]);
    flash('Mensaje eliminado.');
    redirect('admin/index.php?p=mensajes');
}
if (get('action') === 'read-all' && Csrf::check(get('token'))) {
    Database::run('UPDATE submissions SET is_read = 1 WHERE is_read = 0');
    flash('Todos los mensajes se marcaron como leídos.');
    redirect('admin/index.php?p=mensajes');
}

$detail = null;
if ($view > 0) {
    $detail = Database::first('SELECT * FROM submissions WHERE id = :id', ['id' => $view]);
    if ($detail !== null && (int) $detail['is_read'] === 0) {
        Database::update('submissions', ['is_read' => 1], 'id = :id', ['id' => $view]);
        $detail['is_read'] = 1;
    }
}

$rows = Database::all('SELECT * FROM submissions ORDER BY id DESC LIMIT 300');

admin_header('Mensajes recibidos', 'mensajes', [
    ['label' => 'Marcar todos como leídos', 'url' => admin_url('mensajes', ['action' => 'read-all', 'token' => $token]), 'icon' => 'check'],
]);
?>
<?php if ($detail !== null): ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= icon('contacto', 19) ?>Mensaje de <?= e($detail['name']) ?></h2>
      <a class="btn btn--light btn--sm" href="<?= e(admin_url('mensajes')) ?>"><?= icon('cerrar', 15) ?><span>Cerrar</span></a>
    </div>
    <div class="panel__body">
      <div class="form-grid" style="margin-bottom:1.1rem">
        <div><strong>Correo:</strong> <a href="mailto:<?= e((string) $detail['email']) ?>"><?= e((string) $detail['email']) ?></a></div>
        <div><strong>Teléfono:</strong> <?= $detail['phone'] ? '<a href="tel:' . e(digits((string) $detail['phone'])) . '">' . e((string) $detail['phone']) . '</a>' : '—' ?></div>
        <div><strong>Servicio:</strong> <?= e((string) ($detail['service'] ?: '—')) ?></div>
        <div><strong>Recibido:</strong> <?= e(date('d/m/Y H:i', strtotime((string) $detail['created_at']) ?: time())) ?></div>
        <div class="f--full"><strong>Asunto:</strong> <?= e((string) $detail['subject']) ?></div>
      </div>
      <div style="background:var(--a-panel-2);border:1px solid var(--a-line);border-radius:10px;padding:1rem;white-space:pre-wrap"><?= e((string) $detail['message']) ?></div>
      <div style="display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap">
        <a class="btn" href="mailto:<?= e((string) $detail['email']) ?>?subject=<?= e(rawurlencode('Re: ' . (string) $detail['subject'])) ?>"><?= icon('contacto', 17) ?><span>Responder por correo</span></a>
        <?php if ($detail['phone']): ?>
          <a class="btn btn--light" target="_blank" rel="noopener" href="<?= e(whatsapp_link((string) $detail['phone'])) ?>"><?= icon('whatsapp', 17) ?><span>Responder por WhatsApp</span></a>
        <?php endif; ?>
        <a class="btn btn--danger" data-confirm="¿Eliminar este mensaje?" href="<?= e(admin_url('mensajes', ['action' => 'delete', 'id' => $detail['id'], 'token' => $token])) ?>"><?= icon('cerrar', 17) ?><span>Eliminar</span></a>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel__head"><h2><?= icon('contacto', 19) ?>Todos los mensajes (<?= count($rows) ?>)</h2></div>
  <?php if ($rows === []): ?>
    <div class="empty"><?= icon('contacto', 38) ?><p>Todavía no ha recibido mensajes desde el formulario de contacto.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Estado</th><th>Nombre</th><th>Correo</th><th>Servicio</th><th>Fecha</th><th style="text-align:right">Acciones</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $m): ?>
            <tr>
              <td><?= (int) $m['is_read'] === 0 ? '<span class="pill pill--new">Nuevo</span>' : '<span class="pill pill--off">Leído</span>' ?></td>
              <td><?= e((string) $m['name']) ?></td>
              <td><a href="mailto:<?= e((string) $m['email']) ?>"><?= e((string) $m['email']) ?></a></td>
              <td><?= e(excerpt((string) $m['service'], 24) ?: '—') ?></td>
              <td><?= e(date('d/m/Y H:i', strtotime((string) $m['created_at']) ?: time())) ?></td>
              <td class="actions">
                <a class="btn btn--light btn--sm" href="<?= e(admin_url('mensajes', ['id' => $m['id']])) ?>"><?= icon('documento', 15) ?><span>Ver</span></a>
                <a class="btn btn--danger btn--icon" data-confirm="¿Eliminar este mensaje?" href="<?= e(admin_url('mensajes', ['action' => 'delete', 'id' => $m['id'], 'token' => $token])) ?>"><?= icon('cerrar', 15) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
