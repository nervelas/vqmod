<?php
use App\Core\Fmt;

$tz = (string) $poll['timezone'];
$tzb = \App\Core\Settings::tz();
$best = $poll['best_option_id'];
$sym = ['yes' => ['✓', 'Sí', 'p3-v-yes'], 'maybe' => ['?', 'Quizá', 'p3-v-maybe'], 'no' => ['✗', 'No', 'p3-v-no']];
$stl = ['open' => ['Abierta', 'badge-ok'], 'closed' => ['Cerrada', 'badge-muted'], 'finalized' => ['Confirmada', 'badge-gold']];
$state = $stl[$poll['status']] ?? ['—', 'badge-muted'];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title"><?= e($poll['title']) ?></h1>
      <p class="page-sub"><?= e($event['name'] ?? '') ?> con <?= e($host['name'] ?? '') ?> · <?= e(Fmt::duration((int) $poll['duration'])) ?> · <?= e(Fmt::tzLabel($tz)) ?> <span class="badge <?= e($state[1]) ?>"><?= e($state[0]) ?></span></p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/encuestas')) ?>"><?= icon('arrow-left') ?>Volver</a></div>
  </div>
  <?php if ($poll['description']) : ?><p class="mb-3"><?= nl2br(e($poll['description'])) ?></p><?php endif; ?>

  <section class="card mb-4" aria-labelledby="h-link">
    <div class="card-head"><h2 id="h-link" class="serif">Enlace para votar</h2></div>
    <div class="card-body"><div class="input-group"><input class="input mono" id="poll-link" readonly value="<?= e($link) ?>" aria-label="Enlace público de la encuesta"><button class="btn btn-outline" type="button" data-copy="#poll-link"><?= icon('copy') ?>Copiar</button><a class="btn btn-ghost" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Abrir</a></div>
      <?php if ($poll['deadline_at']) : ?><p class="muted mt-2">Se puede votar hasta el <?= e(Fmt::dateTime((string) $poll['deadline_at'], $tz)) ?>.</p><?php endif; ?></div>
  </section>

  <section class="card mb-4" aria-labelledby="h-res">
    <div class="card-head"><h2 id="h-res" class="serif">Resultados <span class="muted">· <?= (int) $poll['voter_count'] ?> votante(s)</span></h2></div>
    <?php if (!$poll['voters']) : ?>
      <div class="card-body"><div class="empty"><?= icon('users') ?><p class="empty-title">Todavía nadie ha votado</p><p class="empty-text">Comparte el enlace. Aquí aparecerá la matriz con las respuestas de cada persona.</p></div></div>
    <?php endif; ?>
    <div class="table-wrap"><table class="table p3-matrix">
      <caption class="sr-only">Votos por horario: ✓ sí, ? quizá, ✗ no</caption>
      <thead><tr><th scope="col">Persona</th>
        <?php foreach ($poll['options'] as $o) : $isBest = $best === (int) $o['id']; ?>
          <th scope="col" class="center<?= $isBest ? ' p3-best' : '' ?>"><?= e(Fmt::dateShort((string) $o['starts_at'], $tz)) ?><br><span class="mono"><?= e(Fmt::time((string) $o['starts_at'], $tz)) ?></span><?= $isBest ? '<br><span class="badge badge-gold">Mejor opción</span>' : '' ?></th>
        <?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($poll['voters'] as $v) : ?>
          <tr><th scope="row"><?= e($v['name']) ?><div class="muted"><?= e($v['email'] ?? '') ?></div></th>
            <?php foreach ($poll['options'] as $o) : $val = $v['votes'][(int) $o['id']] ?? null; $s = $val ? $sym[$val] : null; ?>
              <td class="center<?= $best === (int) $o['id'] ? ' p3-best' : '' ?>"><?php if ($s) : ?><span class="p3-vote <?= e($s[2]) ?>" title="<?= e($s[1]) ?>"><span aria-hidden="true"><?= $s[0] ?></span><span class="sr-only"><?= e($s[1]) ?></span></span><?php else : ?><span class="muted" aria-label="Sin respuesta">—</span><?php endif; ?></td>
            <?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th scope="row">Total</th>
        <?php foreach ($poll['options'] as $o) : ?><td class="center<?= $best === (int) $o['id'] ? ' p3-best' : '' ?>"><span class="mono"><?= (int) $o['yes'] ?> sí · <?= (int) $o['maybe'] ?> quizá · <?= (int) $o['no'] ?> no</span></td><?php endforeach; ?></tr></tfoot>
    </table></div>
  </section>

  <?php if ($poll['status'] === 'finalized') : ?>
    <div class="alert alert-ok">La encuesta se confirmó<?= $booking ? ' y la cita quedó para el ' . e(Fmt::dateTime((string) $booking['starts_at'], $tzb)) . '. <a class="text-gold" href="' . e(url('/admin/citas/' . (int) $booking['id'])) . '">Ver la cita</a>' : '.' ?></div>
  <?php elseif ($poll['voters']) : ?>
    <section class="card card-gold mb-4" aria-labelledby="h-fin">
      <div class="card-head"><h2 id="h-fin" class="serif">Confirmar el horario</h2></div>
      <form method="post" action="<?= e(url('/admin/encuestas/' . (int) $poll['id'] . '/finalizar')) ?>" data-confirm="¿Confirmar este horario? Se creará la cita y se avisará a los votantes.">
        <?= csrf_field() ?>
        <div class="card-body">
          <fieldset class="fieldset"><legend>Elige la opción ganadora</legend>
            <div class="p3-slot-list">
              <?php foreach ($poll['options'] as $o) : ?>
                <label class="check p3-slot"><input type="radio" name="option_id" value="<?= (int) $o['id'] ?>"<?= $best === (int) $o['id'] ? ' checked' : '' ?> required><span><?= e(Fmt::dateTime((string) $o['starts_at'], $tz)) ?> <span class="muted">· <?= (int) $o['yes'] ?> sí, <?= (int) $o['maybe'] ?> quizá</span><?= $best === (int) $o['id'] ? ' <span class="badge badge-gold">Mejor opción</span>' : '' ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        </div>
        <div class="card-foot form-actions"><button class="btn btn-gold" type="submit"><?= icon('check') ?>Confirmar y crear la cita</button></div>
      </form>
    </section>
  <?php endif; ?>

  <div class="row gap-2 row-wrap">
    <?php if ($poll['status'] === 'open') : ?><form method="post" action="<?= e(url('/admin/encuestas/' . (int) $poll['id'] . '/cerrar')) ?>" data-confirm="¿Cerrar la encuesta? Ya no se podrá votar."><?= csrf_field() ?><button class="btn btn-outline" type="submit">Cerrar votación</button></form><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/encuestas/' . (int) $poll['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar esta encuesta y todos sus votos?"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= icon('trash') ?>Eliminar encuesta</button></form>
  </div>
</div>
