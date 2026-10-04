<?php
use App\Core\Fmt;

$tzb = \App\Core\Settings::tz();
$act = ['event' => 'Cita', 'host' => 'Anfitrión', 'team' => 'Equipo', 'message' => 'Mensaje', 'url' => 'Enlace', 'default' => 'Por defecto'];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Bitácora: <?= e($form['name']) ?></h1><p class="page-sub">Qué respondió cada persona, qué regla se aplicó y a dónde se le envió.</p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/enrutamiento')) ?>"><?= icon('arrow-left') ?>Volver</a><a class="btn btn-outline" href="<?= e(url('/admin/enrutamiento/' . (int) $form['id'] . '/editar')) ?>"><?= icon('edit') ?>Editar formulario</a></div>
  </div>

  <section class="card mb-4" aria-labelledby="h-st">
    <div class="card-head"><h2 id="h-st" class="serif">Estadísticas por regla <span class="muted">· <?= (int) $stats['total'] ?> respuestas</span></h2></div>
    <div class="table-wrap"><table class="table">
      <caption class="sr-only">Cuántas personas fueron enviadas por cada regla</caption>
      <thead><tr><th scope="col">Regla</th><th scope="col">Acción</th><th scope="col" class="right">Personas</th><th scope="col">Porcentaje</th></tr></thead>
      <tbody>
        <?php foreach ($stats['rules'] as $r) : ?>
          <tr<?= $r['rule_id'] !== null && !$r['active'] ? ' class="muted"' : '' ?>>
            <th scope="row"><?= $r['rule_id'] === null ? 'Ninguna coincidió (por defecto)' : 'Regla de prioridad ' . (int) $r['priority'] . ($r['active'] ? '' : ' (pausada)') ?></th>
            <td><?= e($act[$r['action']] ?? $r['action']) ?></td>
            <td class="right mono"><?= (int) $r['count'] ?></td>
            <td><div class="p3-bar-row"><div class="progress" role="img" aria-label="<?= e((string) $r['pct']) ?> %"><span <?= vars(['--p' => min(100, (float) $r['pct'])]) ?>></span></div><span class="mono"><?= e(number_format((float) $r['pct'], 1)) ?> %</span></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="card" aria-labelledby="h-lg">
    <div class="card-head"><h2 id="h-lg" class="serif">Últimas respuestas</h2></div>
    <?php if (!$logs) : ?>
      <div class="card-body"><div class="empty"><?= icon('route') ?><p class="empty-title">Aún no hay respuestas</p><p class="empty-text">Cuando alguien complete el formulario público, verás aquí su recorrido completo.</p></div></div>
    <?php else : ?>
      <ul class="p3-list">
        <?php foreach ($logs as $l) : ?>
          <li class="p3-list-item p3-log">
            <div class="p3-log-main">
              <p class="muted mono"><?= e(Fmt::dateShort((string) $l['created_at'], $tzb)) ?> <?= e(Fmt::time((string) $l['created_at'], $tzb)) ?></p>
              <dl class="p3-answers">
                <?php foreach ($l['answers'] as $qid => $val) : ?><div><dt><?= e($labels[(string) $qid] ?? (string) $qid) ?></dt><dd><?= e(is_array($val) ? implode(', ', $val) : (string) $val) ?></dd></div><?php endforeach; ?>
              </dl>
              <p><?= e($l['reason']) ?></p>
            </div>
            <div class="p3-log-side">
              <span class="badge <?= $l['rule_id'] ? 'badge-gold' : 'badge-muted' ?>"><?= $l['rule_id'] ? 'Regla ' . (int) ($prio[(int) $l['rule_id']] ?? 0) : 'Por defecto' ?></span>
              <span class="muted"><?= e($act[$l['action']] ?? $l['action']) ?><?= $l['target'] ? ' · ' . e($l['target']) : '' ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
