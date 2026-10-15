<div class="page-head">
  <div><p class="eyebrow">Hola, <?= e(explode(' ', $u['name'])[0]) ?></p><h1>Mis oportunidades</h1></div>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('app/asistente')) ?>">Ajustar mis preferencias</a>
</div>
<?php if (!$u['email_verified']): ?><div class="flash warn">Confirma tu correo con el enlace que te enviamos para no perder tus alertas.</div><?php endif; ?>
<div class="tabs" role="tablist">
  <?php foreach (['activas' => 'Activas', 'nuevo' => 'Nuevas', 'guardado' => 'Guardadas', 'participando' => 'Participando', 'descartado' => 'Descartadas'] as $k => $l): ?>
    <a role="tab" class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(url('app?tab=' . $k . '&orden=' . $ord)) ?>"><?= $l ?> <span class="count mono"><?= $counts[$k] ?></span></a>
  <?php endforeach; ?>
  <span class="tabs-sp"></span>
  <a class="sort <?= $ord === 'relevancia' ? 'on' : '' ?>" href="<?= e(url('app?tab=' . $tab . '&orden=relevancia')) ?>">Relevancia</a>
  <a class="sort <?= $ord === 'fecha' ? 'on' : '' ?>" href="<?= e(url('app?tab=' . $tab . '&orden=fecha')) ?>">Fecha límite</a>
</div>
<?php if (!$items): ?>
  <div class="empty card">
    <div class="empty-ic" aria-hidden="true">◎</div>
    <h3><?= $tab === 'activas' || $tab === 'nuevo' ? 'Aún no hay concursos que coincidan' : 'Nada por aquí todavía' ?></h3>
    <p class="muted"><?= $tab === 'activas' || $tab === 'nuevo' ? 'Revisamos los concursos cada día. Mientras tanto, prueba con más palabras clave o amplía los departamentos y el rango de montos.' : 'Cuando guardes o marques concursos, aparecerán en esta pestaña.' ?></p>
    <a class="btn btn-gold" href="<?= e(url('app/asistente')) ?>">Ajustar mis preferencias</a>
  </div>
<?php else: ?>
  <div class="cards-list"><?php foreach ($items as $t) echo tender_card($t); ?></div>
  <?= pager($page, $per, $counts[$tab], 'app') ?>
<?php endif; ?>
