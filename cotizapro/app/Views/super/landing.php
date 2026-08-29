<?php
$e = $edit;
$sections = ['problema' => 'Problemas (01/)', 'paso' => 'Cómo funciona (03/)', 'beneficio' => 'Beneficios (04/)', 'testimonio' => 'Testimonios (06/)', 'faq' => 'Preguntas frecuentes'];
$g = static fn (string $k): string => (string) ($s['landing_' . $k] ?? '');
?>
<div class="cols cols--sidebar">
  <div class="stack">
    <form class="card" method="post" action="<?= e(url('/super/landing')) ?>">
      <?= csrf_field() ?><input type="hidden" name="what" value="texts">
      <div class="card__head"><span class="secnum">01/</span><h2>Textos principales</h2>
        <button class="btn btn--accent btn--sm ml-auto" type="submit">Guardar textos</button></div>
      <div class="card__body">
        <div class="field"><label for="hero_kicker">Antetítulo del hero</label>
          <input class="input" id="hero_kicker" name="hero_kicker" maxlength="190" value="<?= e($g('hero_kicker')) ?>" placeholder="Catálogo · Cotizador · Seguimiento"></div>
        <div class="field"><label for="hero_title">Titular del hero (separe las líneas con |)</label>
          <input class="input" id="hero_title" name="hero_title" maxlength="300" value="<?= e($g('hero_title')) ?>" placeholder="Su catálogo|cotiza solo.|Usted cierra."></div>
        <div class="field"><label for="hero_sub">Bajada del hero</label>
          <textarea class="textarea" id="hero_sub" name="hero_sub" rows="3" maxlength="600"><?= e($g('hero_sub')) ?></textarea></div>
        <div class="row-2">
          <div class="field"><label for="problem_title">Título del problema</label>
            <input class="input" id="problem_title" name="problem_title" maxlength="190" value="<?= e($g('problem_title')) ?>"></div>
          <div class="field"><label for="steps_title">Título de los pasos</label>
            <input class="input" id="steps_title" name="steps_title" maxlength="190" value="<?= e($g('steps_title')) ?>"></div>
        </div>
        <div class="field"><label for="problem_body">Bajada del problema</label>
          <textarea class="textarea" id="problem_body" name="problem_body" rows="2" maxlength="600"><?= e($g('problem_body')) ?></textarea></div>
        <div class="row-2">
          <div class="field"><label for="plans_title">Título de planes</label>
            <input class="input" id="plans_title" name="plans_title" maxlength="190" value="<?= e($g('plans_title')) ?>"></div>
          <div class="field"><label for="plans_sub">Bajada de planes</label>
            <input class="input" id="plans_sub" name="plans_sub" maxlength="300" value="<?= e($g('plans_sub')) ?>"></div>
        </div>
        <div class="row-2">
          <div class="field"><label for="cta_title">Título del cierre</label>
            <input class="input" id="cta_title" name="cta_title" maxlength="190" value="<?= e($g('cta_title')) ?>"></div>
          <div class="field"><label for="cta_body">Bajada del cierre</label>
            <input class="input" id="cta_body" name="cta_body" maxlength="400" value="<?= e($g('cta_body')) ?>"></div>
        </div>
      </div>
    </form>

    <?php foreach ($sections as $key => $label): ?>
      <div class="card">
        <div class="card__head"><h2><?= e($label) ?></h2>
          <span class="badge ml-auto"><?= e(count($blocks[$key] ?? [])) ?></span></div>
        <div class="card__body card__body--flush">
          <?php foreach ($blocks[$key] ?? [] as $b): ?>
            <div style="display:flex;gap:14px;align-items:flex-start;padding:13px 18px;border-bottom:1px solid var(--paper-2)">
              <span class="secnum" style="padding-top:3px"><?= e(str_pad((string) $b['sort'], 2, '0', STR_PAD_LEFT)) ?>/</span>
              <span style="flex:1"><strong><?= e($b['title']) ?></strong><?= !$b['active'] ? ' <span class="badge">Oculto</span>' : '' ?>
                <?php if ($b['subtitle']): ?><br><span class="small muted"><?= e($b['subtitle']) ?></span><?php endif; ?>
                <?php if ($b['body']): ?><br><span class="small muted"><?= e(str_limit((string) $b['body'], 120)) ?></span><?php endif; ?></span>
              <a class="btn btn--ghost btn--xs" href="<?= e(url('/super/landing?editar=' . $b['id'])) ?>">Editar</a>
              <button class="btn btn--ghost btn--xs" type="submit" form="dellb<?= e($b['id']) ?>">Quitar</button>
            </div>
          <?php endforeach; ?>
          <?php if (empty($blocks[$key])): ?>
            <p class="small muted" style="padding:20px;margin:0">Sin bloques. Se muestran los textos por defecto del sistema.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <form class="card" method="post" action="<?= e(url('/super/landing')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= e($e['id']) ?>"><?php endif; ?>
    <div class="card__head"><span class="secnum">02/</span><h2><?= $e ? 'Editar bloque' : 'Nuevo bloque' ?></h2></div>
    <div class="card__body">
      <div class="field"><label for="section">Sección</label>
        <select class="select" id="section" name="section">
          <?php foreach ($sections as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= ($e['section'] ?? '') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="title">Título / nombre</label><input class="input" id="title" name="title" maxlength="190" value="<?= e($e['title'] ?? '') ?>"></div>
      <div class="field"><label for="subtitle">Subtítulo (en testimonios: cargo y empresa)</label>
        <input class="input" id="subtitle" name="subtitle" maxlength="255" value="<?= e($e['subtitle'] ?? '') ?>"></div>
      <div class="field"><label for="body">Texto</label>
        <textarea class="textarea" id="body" name="body" rows="5" maxlength="3000"><?= e($e['body'] ?? '') ?></textarea></div>
      <div class="field"><label for="image">Imagen</label><input class="input" id="image" name="image" type="file" accept="image/*"></div>
      <div class="field"><label for="sort">Orden</label><input class="input" id="sort" name="sort" type="number" value="<?= e((int) ($e['sort'] ?? 0)) ?>"></div>
      <label class="check"><input type="checkbox" name="active" value="1"<?= (!$e || $e['active']) ? ' checked' : '' ?>><span>Visible</span></label>
      <div class="flex" style="gap:8px">
        <button class="btn btn--accent" type="submit"><?= $e ? 'Guardar' : 'Agregar bloque' ?></button>
        <?php if ($e): ?><a class="btn btn--ghost" href="<?= e(url('/super/landing')) ?>">Cancelar</a><?php endif; ?>
      </div>
      <a class="btn btn--ghost btn--block" style="margin-top:14px" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver la landing ↗</a>
    </div>
  </form>
</div>
<?php foreach ($blocks as $list): foreach ($list as $b): ?>
  <form id="dellb<?= e($b['id']) ?>" method="post" action="<?= e(url('/super/landing/' . $b['id'] . '/eliminar')) ?>" class="hide" data-confirm="¿Quitar este bloque?"><?= csrf_field() ?></form>
<?php endforeach; endforeach; ?>
