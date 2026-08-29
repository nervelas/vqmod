<?php
/** Mesas y códigos QR. */
use MenuGold\Core\Csrf;
use MenuGold\Models\TableModel;
$view->extend('layouts/panel');
$view->set('title', 'Mesas y QR');
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/mesas/qr')) ?>">Ver códigos</a>
  <a class="btn btn-sm" href="<?= e(mg_url('/panel/mesas/mesa/nueva')) ?>">Nueva mesa</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head">
      <div><h2>Tus mesas</h2><p><?= count($tables) ?> mesas</p></div>
    </div>

    <?php if ($tables): ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Mesa</th><th>Zona</th><th>Lugares</th><th>Estado</th><th class="num">QR</th></tr></thead>
          <tbody>
            <?php foreach ($tables as $t): ?>
              <tr>
                <td><a class="cell-title link-line" href="<?= e(mg_url('/panel/mesas/mesa/' . (int)$t['id'])) ?>"><?= e($t['name']) ?></a>
                  <?php if ((int)$t['is_active'] === 0): ?><span class="chip chip-dim">Inactiva</span><?php endif; ?></td>
                <td class="muted"><?= e($t['zone'] !== '' ? $t['zone'] : '—') ?></td>
                <td class="tabular"><?= (int)$t['seats'] ?></td>
                <td><span class="chip <?= $t['status'] === 'free' ? 'chip-dim' : ($t['status'] === 'bill' ? 'chip-ember' : '') ?>">
                  <?= e($t['status'] === 'free' ? 'Libre' : ($t['status'] === 'bill' ? 'Pide la cuenta' : 'Ocupada')) ?></span></td>
                <td class="num">
                  <a class="btn btn-ghost btn-sm" href="<?= e(mg_url('/panel/mesas/qr.pdf?formato=tent&mesa=' . (int)$t['id'])) ?>" target="_blank" rel="noopener">PDF</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><h3>Sin mesas</h3><p>Genera varias de una vez con el formulario de la derecha.</p></div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <form class="card" method="post" action="<?= e(mg_url('/panel/mesas/generar')) ?>">
      <?= Csrf::field() ?>
      <div class="card-head"><h3>Generar varias</h3></div>
      <div class="grid grid-2">
        <div class="field"><label for="count">¿Cuántas?</label><input class="input" id="count" name="count" type="number" min="1" max="60" value="10"></div>
        <div class="field"><label for="seats">Lugares</label><input class="input" id="seats" name="seats" type="number" min="1" max="60" value="4"></div>
      </div>
      <div class="field"><label for="prefix">Prefijo</label><input type="text" class="input" id="prefix" name="prefix" maxlength="30" value="Mesa"></div>
      <div class="field"><label for="zone">Zona</label><input type="text" class="input" id="zone" name="zone" maxlength="60" placeholder="Terraza, salón, barra"></div>
      <button class="btn btn-block" type="submit">Crear mesas con su QR</button>
    </form>

    <div class="card">
      <div class="card-head"><h3>Imprimir códigos</h3></div>
      <p class="muted" style="font-size:var(--step--1)">Tres formatos listos para imprenta, con tu logo y tus colores.</p>
      <div class="stack mt-2" style="gap:.6rem">
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/panel/mesas/qr.pdf?formato=tent')) ?>" target="_blank" rel="noopener">Tarjeta de mesa (se dobla)</a>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/panel/mesas/qr.pdf?formato=card')) ?>" target="_blank" rel="noopener">Tarjeta de bolsillo</a>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/panel/mesas/qr.pdf?formato=sticker')) ?>" target="_blank" rel="noopener">Etiquetas adhesivas</a>
      </div>
    </div>
  </div>
</div>
<?php $view->stop() ?>
