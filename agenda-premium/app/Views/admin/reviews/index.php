<?php
use App\Core\Fmt;

$tzb = \App\Core\Settings::tz();
$stars = static function (?int $n): string {
    $n = (int) $n;
    $out = '<span class="p3-stars" role="img" aria-label="' . $n . ' de 5 estrellas">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="' . ($i <= $n ? 'is-on' : '') . '" aria-hidden="true">' . icon('star') . '</span>';
    }
    return $out . '</span>';
};
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Reseñas</h1><p class="page-sub">Modera lo que opinan tus clientes y pídeles una reseña cuando termine su cita.</p></div>
  </div>

  <section class="card mb-4" aria-labelledby="h-avg">
    <div class="card-head"><h2 id="h-avg" class="serif">Calificación promedio</h2></div>
    <div class="card-body">
      <?php if (!$avg) : ?><p class="muted">Aún no hay anfitriones.</p><?php else : ?>
      <ul class="p3-avg">
        <?php foreach ($avg as $a) : ?>
          <li><span><?= e($a['name']) ?></span>
            <?php if ((int) $a['n'] > 0) : ?><span class="p3-avg-v"><?= $stars((int) round((float) $a['avg_rating'])) ?> <strong class="mono"><?= e(number_format((float) $a['avg_rating'], 1)) ?></strong> <span class="muted">(<?= (int) $a['n'] ?>)</span></span>
            <?php else : ?><span class="muted">Sin reseñas aprobadas</span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul><?php endif; ?>
    </div>
  </section>

  <section class="card mb-4" aria-labelledby="h-sol">
    <div class="card-head"><h2 id="h-sol" class="serif">Solicitar una reseña</h2></div>
    <?php if (!$eligible) : ?>
      <div class="card-body"><p class="muted">Cuando marques citas como completadas, podrás pedirles su reseña desde aquí. Las citas que ya tienen solicitud no aparecen.</p></div>
    <?php else : ?>
      <form method="post" action="<?= e(url('/admin/resenas/solicitar')) ?>" class="card-body">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="field"><label for="rv-b">Cita completada</label>
            <select class="select" id="rv-b" name="booking_id" required><option value="">Elige una cita…</option>
              <?php foreach ($eligible as $b) : ?><option value="<?= (int) $b['id'] ?>"><?= e($b['guest_name'] . ' · ' . $b['event_name'] . ' · ' . Fmt::dateShort((string) $b['starts_at'], $tzb)) ?></option><?php endforeach; ?></select></div>
          <div class="field p3-filter-btn"><button class="btn btn-gold" type="submit"><?= icon('mail') ?>Solicitar reseña</button></div>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <div class="tabs mb-3" role="tablist" aria-label="Estado de las reseñas">
    <?php foreach ($statuses as $k => $l) : ?>
      <a class="tab<?= $status === $k ? ' is-active' : '' ?>" href="<?= e(url('/admin/resenas', ['estado' => $k])) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <span class="muted">(<?= (int) ($counts[$k] ?? 0) ?>)</span></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$rows) : ?>
    <div class="empty"><?= icon('star') ?><p class="empty-title">No hay reseñas en esta pestaña</p><p class="empty-text"><?= $status === 'pending' ? 'Cuando un cliente envíe su opinión, aparecerá aquí para que la apruebes.' : 'Cambia de pestaña para ver otras reseñas.' ?></p></div>
  <?php else : ?>
    <ul class="stack">
      <?php foreach ($rows as $r) : ?>
        <li class="card">
          <div class="card-body">
            <div class="row row-between row-wrap gap-2">
              <div><strong><?= e($r['client_name']) ?></strong> <span class="muted">· <?= e($r['host_name'] ?: 'Sin anfitrión') ?><?= $r['event_name'] ? ' · ' . e($r['event_name']) : '' ?></span></div>
              <?php if ($r['rating'] !== null) : ?><?= $stars((int) $r['rating']) ?><?php endif; ?>
            </div>
            <?php if ($r['comment']) : ?><blockquote class="p3-msg-body"><?= nl2br(e($r['comment'])) ?></blockquote><?php elseif ($r['status'] !== 'requested') : ?><p class="muted">Sin comentario, solo calificación.</p><?php endif; ?>
            <?php if ($r['status'] === 'requested') : ?><p class="muted">Esperando respuesta. Enlace: <span class="mono p3-url"><?= e($r['link']) ?></span></p><?php endif; ?>
            <p class="muted"><?= e(Fmt::dateShort((string) ($r['submitted_at'] ?: $r['created_at']), $tzb)) ?></p>
          </div>
          <div class="card-foot row row-wrap gap-2">
            <?php if ($r['status'] === 'requested') : ?>
              <button class="btn btn-outline btn-sm" type="button" data-copy="<?= e($r['link']) ?>"><?= icon('copy') ?>Copiar enlace</button>
              <?php if ($r['wa']) : ?><a class="btn btn-gold btn-sm" href="<?= e($r['wa']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?>Enviar por WhatsApp</a><?php endif; ?>
            <?php else : ?>
              <?php if ($r['status'] !== 'approved') : ?><form method="post" action="<?= e(url('/admin/resenas/' . (int) $r['id'] . '/aprobar')) ?>"><?= csrf_field() ?><button class="btn btn-gold btn-sm" type="submit"><?= icon('check') ?>Aprobar</button></form><?php endif; ?>
              <?php if ($r['status'] !== 'rejected') : ?><form method="post" action="<?= e(url('/admin/resenas/' . (int) $r['id'] . '/rechazar')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('x') ?>Rechazar</button></form><?php endif; ?>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
