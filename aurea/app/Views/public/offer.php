<div class="wrap" style="padding:50px 0 90px">
  <div class="success">
    <?php if ($valid): ?>
      <span class="eyebrow"><?= e(__('Se liberó un horario')) ?></span>
      <h1><?= e($w['service_name']) ?></h1>
      <dl class="detail">
        <div><dt><?= e(__('Fecha')) ?></dt><dd><?= e(\Aurea\Core\Util::dateLong($w['offer_start'])) ?></dd></div>
        <div><dt><?= e(__('Hora')) ?></dt><dd><?= e(ftime($w['offer_start'])) ?></dd></div>
        <div><dt><?= e(term('professional')) ?></dt><dd><?= e((string)$w['prof_name']) ?></dd></div>
        <div><dt><?= e(__('Disponible hasta')) ?></dt><dd><?= e(fdatetime($w['offer_expires'])) ?></dd></div>
      </dl>
      <?php if ($err !== ''): ?><div class="alert alert-err" role="alert"><?= e($err) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('/espera/' . $w['offer_token'])) ?>"><?= csrf_field('public') ?><button class="btn btn-gold" type="submit"><?= e(__('Reservar este horario')) ?></button></form>
    <?php else: ?>
      <h1><?= e($w['status'] === 'booked' ? __('Este horario ya fue reservado') : __('Esta oferta ya no está disponible')) ?></h1>
      <p style="color:var(--muted)"><?= e(__('Puedes reservar otro horario cuando quieras.')) ?></p>
      <a class="btn btn-gold" href="<?= e(url('/reservar?servicio=' . (int)$w['service_id'])) ?>"><?= e(__('Ver horarios')) ?></a>
    <?php endif; ?>
  </div>
</div>
