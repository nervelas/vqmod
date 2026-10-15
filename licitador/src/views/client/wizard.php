<?php
$kws = array_values(array_filter(array_map('trim', explode(',', (string) $p['keywords']))));
$myDeps = array_filter(explode(',', (string) $p['departments'])); $myMods = array_filter(explode(',', (string) $p['modalities']));
$sectors = rows("SELECT * FROM sectors WHERE active=1 ORDER BY sort_order");
?>
<div class="page-head"><div><p class="eyebrow">Asistente de 3 pasos</p><h1>Cuéntanos de tu negocio</h1></div></div>
<form method="post" class="wizard" data-wizard data-limit="<?= (int) $plan['kw_limit'] ?>" data-suggest="<?= e(url('app/api/sugerir')) ?>">
  <?= csrf_field() ?>
  <ol class="wiz-steps" aria-hidden="true"><li class="on">1 · Qué vendes</li><li>2 · Dónde trabajas</li><li>3 · Montos y modalidad</li></ol>

  <fieldset class="wiz-pane card on" data-step="1">
    <legend>1. ¿Qué vendes?</legend>
    <p class="muted">Escribe palabras clave y presiona Enter (por ejemplo: <em>tuberías PVC</em>, <em>cemento</em>). Tu plan permite hasta <strong><?= (int) $plan['kw_limit'] ?></strong>.</p>
    <div class="chips-input" data-chips>
      <div class="chips" data-chip-list><?php foreach ($kws as $k): ?><span class="chip"><?= e($k) ?><button type="button" aria-label="Quitar <?= e($k) ?>">×</button></span><?php endforeach; ?></div>
      <input type="text" data-chip-field list="sugerencias" placeholder="Escribe y presiona Enter" autocomplete="off" aria-label="Palabra clave">
      <datalist id="sugerencias"></datalist>
    </div>
    <input type="hidden" name="keywords" value="<?= e(implode(', ', $kws)) ?>" data-chip-value>
    <p class="muted small">O elige un rubro para empezar:</p>
    <div class="suggest-sectors">
      <?php foreach ($sectors as $s): ?><button type="button" class="chip chip-btn" data-add-kws="<?= e($s['keywords']) ?>">+ <?= e($s['name']) ?></button><?php endforeach; ?>
    </div>
    <div class="wiz-nav"><span></span><button type="button" class="btn btn-gold" data-next>Siguiente</button></div>
  </fieldset>

  <fieldset class="wiz-pane card" data-step="2">
    <legend>2. ¿Dónde trabajas?</legend>
    <p class="muted">Marca los departamentos donde puedes entregar. Si no marcas ninguno, te mostramos todo el país.</p>
    <div class="checks-grid">
      <?php foreach (deptos() as $d): ?><label class="check"><input type="checkbox" name="departments[]" value="<?= e($d) ?>" <?= in_array($d, $myDeps) ? 'checked' : '' ?>> <span><?= e($d) ?></span></label><?php endforeach; ?>
    </div>
    <div class="wiz-nav"><button type="button" class="btn btn-ghost" data-prev>Atrás</button><button type="button" class="btn btn-gold" data-next>Siguiente</button></div>
  </fieldset>

  <fieldset class="wiz-pane card" data-step="3">
    <legend>3. Montos y modalidades</legend>
    <div class="two-col">
      <?= f_input('min_amount', 'Monto mínimo (Q)', $p['min_amount'] !== null ? (float) $p['min_amount'] : '', 'text', 'inputmode="numeric" placeholder="Sin mínimo"') ?>
      <?= f_input('max_amount', 'Monto máximo (Q)', $p['max_amount'] !== null ? (float) $p['max_amount'] : '', 'text', 'inputmode="numeric" placeholder="Sin máximo"') ?>
    </div>
    <p class="muted">Modalidades que te interesan (si no marcas ninguna, todas):</p>
    <div class="checks-grid">
      <?php foreach (modalidades() as $k => $l): ?><label class="check"><input type="checkbox" name="modalities[]" value="<?= e($k) ?>" <?= in_array($k, $myMods) ? 'checked' : '' ?>> <span><?= e($l) ?></span></label><?php endforeach; ?>
    </div>
    <div class="wiz-nav"><button type="button" class="btn btn-ghost" data-prev>Atrás</button><button class="btn btn-gold">Guardar y ver oportunidades</button></div>
  </fieldset>
</form>
