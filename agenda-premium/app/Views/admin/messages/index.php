<?php
/** @var array $rows */
use App\Core\Fmt;
use App\Core\Tz;

$tzb = \App\Core\Settings::tz();
$qs = ['hasta' => $to] + ($from !== '' ? ['desde' => $from] : []);
$tabUrl = static fn (string $t): string => url('/admin/mensajes', $qs + ['estado' => $t]);
?>
<div class="page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Mensajes por enviar hoy</h1>
      <p class="page-sub">Recordatorios, confirmaciones y ofertas ya redactados. Toca el botón, WhatsApp se abre con el texto listo y solo falta presionar enviar.</p>
    </div>
  </div>

  <div class="grid cols-3 mb-3">
    <div class="stat"><div class="stat-label">Por enviar</div><div class="stat-value serif"><?= (int) $counts['pending'] ?></div></div>
    <div class="stat"><div class="stat-label">Enviados</div><div class="stat-value serif"><?= (int) $counts['sent'] ?></div></div>
    <div class="stat"><div class="stat-label">Descartados</div><div class="stat-value serif"><?= (int) $counts['dismissed'] ?></div></div>
  </div>

  <form class="card mb-3" method="get" action="<?= e(url('/admin/mensajes')) ?>">
    <div class="card-body">
      <div class="form-row p3-filters">
        <div class="field"><label for="f-desde">Desde</label><input class="input" type="date" id="f-desde" name="desde" value="<?= e($from) ?>"></div>
        <div class="field"><label for="f-hasta">Hasta</label><input class="input" type="date" id="f-hasta" name="hasta" value="<?= e($to) ?>"></div>
        <input type="hidden" name="estado" value="<?= e($tab) ?>">
        <div class="field p3-filter-btn"><button class="btn btn-outline" type="submit"><?= icon('filter') ?>Filtrar</button></div>
      </div>
    </div>
  </form>

  <div class="tabs mb-3" role="tablist" aria-label="Estado de los mensajes">
    <a class="tab<?= $tab === 'pending' ? ' is-active' : '' ?>" href="<?= e($tabUrl('pending')) ?>"<?= $tab === 'pending' ? ' aria-current="page"' : '' ?>>Por enviar</a>
    <a class="tab<?= $tab === 'sent' ? ' is-active' : '' ?>" href="<?= e($tabUrl('sent')) ?>"<?= $tab === 'sent' ? ' aria-current="page"' : '' ?>>Enviados</a>
    <a class="tab<?= $tab === 'dismissed' ? ' is-active' : '' ?>" href="<?= e($tabUrl('dismissed')) ?>"<?= $tab === 'dismissed' ? ' aria-current="page"' : '' ?>>Descartados</a>
  </div>

  <?php if (!$rows) : ?>
    <div class="empty">
      <?= icon('whatsapp') ?>
      <p class="empty-title"><?= $tab === 'pending' ? 'No hay mensajes pendientes' : 'Nada por mostrar aquí' ?></p>
      <p class="empty-text"><?= $tab === 'pending' ? 'Estás al día. Cuando se acerque una cita, el recordatorio aparecerá en esta lista para que lo envíes con un toque.' : 'Prueba con otro rango de fechas.' ?></p>
    </div>
  <?php else : ?>
    <ul class="p3-msg-list stack" aria-label="Lista de mensajes">
      <?php foreach ($rows as $m) : ?>
        <li class="card p3-msg<?= !empty($m['overdue']) && $tab === 'pending' ? ' is-overdue' : '' ?>">
          <div class="card-body">
            <div class="row row-between row-wrap gap-2">
              <div>
                <p class="p3-msg-to"><strong><?= e($m['guest_name'] ?: 'Sin nombre') ?></strong> <span class="muted mono"><?= e(\App\Core\Str::phoneDisplay(\App\Core\Str::phone((string) $m['phone']) ?: (string) $m['phone'])) ?></span></p>
                <p class="muted p3-msg-meta">
                  <?php if (!empty($m['event_name'])) : ?><?= e($m['event_name']) ?> · <?php endif; ?>
                  <?php if (!empty($m['starts_at'])) : ?>cita <?= e(Fmt::dateShort((string) $m['starts_at'], $tzb)) ?> <?= e(Fmt::time((string) $m['starts_at'], $tzb)) ?><?php endif; ?>
                  <?php if (!empty($m['host_name'])) : ?> · <?= e($m['host_name']) ?><?php endif; ?>
                </p>
              </div>
              <div class="row gap-2">
                <?php if ($m['channel'] === 'whatsapp_api') : ?><span class="badge badge-muted">API</span><?php endif; ?>
                <?php if ($tab === 'pending') : ?>
                  <span class="badge <?= !empty($m['overdue']) ? 'badge-warn' : 'badge-gold' ?>"><?= !empty($m['overdue']) ? 'Atrasado · ' : 'Hoy · ' ?><?= e(Fmt::time((string) $m['due_at'], $tzb)) ?></span>
                <?php elseif ($tab === 'sent') : ?>
                  <span class="badge badge-ok">Enviado <?= e($m['sent_at'] ? Fmt::dateShort((string) $m['sent_at'], $tzb) : '') ?></span>
                <?php else : ?>
                  <span class="badge badge-muted">Descartado</span>
                <?php endif; ?>
              </div>
            </div>
            <blockquote class="p3-msg-body"><?= nl2br(e((string) $m['body'])) ?></blockquote>
            <?php if (!empty($m['last_error'])) : ?><p class="text-err p3-msg-meta"><?= e($m['last_error']) ?></p><?php endif; ?>
          </div>
          <?php if ($tab === 'pending') : ?>
            <div class="card-foot row row-wrap gap-2">
              <a class="btn btn-gold btn-sm" href="<?= e($m['wa_url']) ?>" target="_blank" rel="noopener noreferrer" data-wa-open="<?= (int) $m['id'] ?>"><?= icon('whatsapp') ?>Enviar por WhatsApp</a>
              <form method="post" action="<?= e(url('/admin/mensajes/' . (int) $m['id'] . '/enviado')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="hasta" value="<?= e($to) ?>"><button class="btn btn-outline btn-sm" type="submit"><?= icon('check') ?>Marcar como enviado</button></form>
              <form method="post" action="<?= e(url('/admin/mensajes/' . (int) $m['id'] . '/descartar')) ?>" class="inline" data-confirm="¿Descartar este mensaje? No se enviará."><?= csrf_field() ?><input type="hidden" name="hasta" value="<?= e($to) ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('x') ?>Descartar</button></form>
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
