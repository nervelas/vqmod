<?php
$week = (int) val("SELECT COUNT(*) FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? AND m.status IN ('nuevo','guardado','participando') AND t.status='abierto' AND t.deadline BETWEEN ? AND ?", [$u['id'], now(), date('Y-m-d H:i:s', time() + 7 * 86400)]);
$pot = (float) val("SELECT COALESCE(SUM(t.amount),0) FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? AND m.status IN ('nuevo','guardado','participando') AND t.status='abierto'", [$u['id']]);
?>
<div class="page-head">
  <div><p class="eyebrow">Hola, <?= e(explode(' ', $u['name'])[0]) ?></p><h1>Tu <em>radar</em> de hoy</h1></div>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('app/asistente')) ?>"><?= icon('sliders') ?>Ajustar preferencias</a>
</div>
<?php if (!$u['email_verified']): ?><div class="flash warn"><?= icon('mail') ?><span>Confirma tu correo con el enlace que te enviamos para no perder tus alertas.</span></div><?php endif; ?>
<div class="kstrip">
  <div class="ks nw reveal"><?= icon('spark') ?><strong class="mono" data-count="<?= (int) $counts['nuevo'] ?>">0</strong><span>nuevas para ti</span></div>
  <div class="ks hot reveal" style="--d:.06s"><?= icon('flame') ?><strong class="mono" data-count="<?= $week ?>">0</strong><span>vencen esta semana</span></div>
  <div class="ks reveal" style="--d:.12s"><?= icon('award') ?><strong class="mono" data-count="<?= (int) $counts['participando'] ?>">0</strong><span>en los que participas</span></div>
  <div class="ks reveal" style="--d:.18s"><?= icon('coins') ?><strong class="mono"><?= e(money_short($pot)) ?></strong><span>valor estimado en juego</span></div>
</div>
<div class="tabs" role="tablist">
  <?php foreach (['activas' => 'Activas', 'nuevo' => 'Nuevas', 'guardado' => 'Guardadas', 'participando' => 'Participando', 'descartado' => 'Descartadas'] as $k => $l): ?>
    <a role="tab" class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(url('app?tab=' . $k . '&orden=' . $ord)) ?>"><?= $l ?> <span class="count"><?= $counts[$k] ?></span></a>
  <?php endforeach; ?>
  <span class="tabs-sp"></span>
  <a class="sort <?= $ord === 'relevancia' ? 'on' : '' ?>" href="<?= e(url('app?tab=' . $tab . '&orden=relevancia')) ?>"><?= icon('star') ?>Relevancia</a>
  <a class="sort <?= $ord === 'fecha' ? 'on' : '' ?>" href="<?= e(url('app?tab=' . $tab . '&orden=fecha')) ?>"><?= icon('clock') ?>Fecha límite</a>
</div>
<?php if (!$items): ?>
  <div class="empty card">
    <div class="eic"><?= icon('radar') ?></div>
    <h3><?= $tab === 'activas' || $tab === 'nuevo' ? 'Tu radar sigue buscando' : 'Nada por aquí todavía' ?></h3>
    <p class="muted" style="max-width:46ch;margin:0 auto 18px"><?= $tab === 'activas' || $tab === 'nuevo' ? 'Revisamos los concursos cada día. Mientras tanto, prueba con más palabras clave o amplía los departamentos y el rango de montos.' : 'Cuando guardes o marques concursos, aparecerán en esta pestaña.' ?></p>
    <a class="btn btn-gold" href="<?= e(url('app/asistente')) ?>">Ajustar mis preferencias</a>
  </div>
<?php else: ?>
  <div class="cards-list"><?php foreach ($items as $t) echo tender_card($t); ?></div>
  <?= pager($page, $per, $counts[$tab], 'app') ?>
<?php endif; ?>
