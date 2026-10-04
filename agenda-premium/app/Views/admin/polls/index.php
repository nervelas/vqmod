<?php
use App\Core\Fmt;

$tzb = \App\Core\Settings::tz();
$st = ['open' => ['Abierta', 'badge-ok'], 'closed' => ['Cerrada', 'badge-muted'], 'finalized' => ['Confirmada', 'badge-gold']];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Encuestas de horarios</h1><p class="page-sub">Propón varias fechas, comparte un enlace y deja que los invitados voten. Cuando decidas, se crea la cita con un clic.</p></div>
    <div class="page-actions"><a class="btn btn-gold" href="<?= e(url('/admin/encuestas/nueva')) ?>"><?= icon('plus') ?>Nueva encuesta</a></div>
  </div>
  <?php if (!$rows) : ?>
    <div class="empty"><?= icon('check') ?><p class="empty-title">Aún no hay encuestas</p><p class="empty-text">Son ideales para reuniones con varias personas: propones los horarios y todos votan sí, quizá o no.</p><a class="btn btn-gold" href="<?= e(url('/admin/encuestas/nueva')) ?>">Crear mi primera encuesta</a></div>
  <?php else : ?>
    <div class="card"><div class="table-wrap"><table class="table">
      <caption class="sr-only">Encuestas de horarios</caption>
      <thead><tr><th scope="col">Encuesta</th><th scope="col" class="hide-sm">Anfitrión</th><th scope="col" class="right">Opciones</th><th scope="col" class="right">Votantes</th><th scope="col" class="hide-sm">Límite</th><th scope="col">Estado</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r) :
          $expired = $r['status'] === 'open' && $r['deadline_at'] && $r['deadline_at'] <= $now;
          $s = $expired ? ['Plazo vencido', 'badge-warn'] : ($st[$r['status']] ?? ['—', 'badge-muted']); ?>
        <tr>
          <th scope="row"><a class="text-gold" href="<?= e(url('/admin/encuestas/' . (int) $r['id'])) ?>"><?= e($r['title']) ?></a><div class="muted"><?= e($r['event_name']) ?> · <?= e(Fmt::duration((int) $r['duration'])) ?></div></th>
          <td class="hide-sm"><?= e($r['host_name']) ?></td>
          <td class="right mono"><?= (int) $r['option_count'] ?></td>
          <td class="right mono"><?= (int) $r['voter_count'] ?></td>
          <td class="hide-sm nowrap"><?= $r['deadline_at'] ? e(Fmt::dateShort((string) $r['deadline_at'], $tzb)) : '—' ?></td>
          <td><span class="badge <?= e($s[1]) ?>"><?= e($s[0]) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  <?php endif; ?>
</div>
