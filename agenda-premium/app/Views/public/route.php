<?php /** @var array $biz @var array $form @var array $questions @var array $values @var ?string $error */ ?>
<?php partial('public/_header', ['biz' => $biz]); ?>
<main id="main" class="pub-page-main">
  <div class="container pub-narrow">
    <p class="eyebrow">Te orientamos</p>
    <h1 class="serif"><?= e($form['name']) ?></h1>
    <?php if (trim((string) ($form['description'] ?? '')) !== '') : ?><div class="prose"><?= \App\Core\Str::richText($form['description']) ?></div><?php endif; ?>
    <?php if ($error) : ?><div class="alert alert-err" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
    <?php if (!$questions) : ?>
      <div class="empty"><?= icon('route') ?><p class="empty-title">Este formulario aún no tiene preguntas</p><a class="btn btn-gold" href="<?= e(url('/')) ?>">Ver servicios</a></div>
    <?php else : ?>
    <form class="stack pub-form" method="post" action="<?= e(url('/enrutar/' . $form['slug'])) ?>" novalidate>
      <?= public_csrf_field('book') ?>
      <div class="hp-wrap" aria-hidden="true"><label>No llenar este campo<input type="text" name="company_site" tabindex="-1" autocomplete="off"></label></div>
      <?php foreach ($questions as $q) :
          $id = 'q-' . $q['id']; $name = 'a[' . $q['id'] . ']'; $val = $values[$q['id']] ?? ''; ?>
        <div class="field">
          <?php if (in_array($q['type'], ['radio', 'checkbox'], true)) : ?>
            <fieldset class="fieldset"><legend><?= e($q['label']) ?><?= $q['required'] ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></legend>
              <div class="stack">
              <?php foreach ($q['options'] as $i => $o) :
                  $checked = is_array($val) ? in_array($o, $val, true) : ((string) $val === $o); ?>
                <label class="check"><input type="<?= e($q['type']) ?>" name="<?= e($name . ($q['type'] === 'checkbox' ? '[]' : '')) ?>" value="<?= e($o) ?>"<?= chk($checked) ?><?= $q['required'] && $q['type'] === 'radio' && $i === 0 ? ' required' : '' ?>> <span><?= e($o) ?></span></label>
              <?php endforeach; ?>
              </div>
            </fieldset>
          <?php else : ?>
            <label for="<?= e($id) ?>"><?= e($q['label']) ?><?= $q['required'] ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></label>
            <?php if ($q['type'] === 'select') : ?>
              <select class="select" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $q['required'] ? ' required' : '' ?>>
                <option value="">Elige una opción</option>
                <?php foreach ($q['options'] as $o) : ?><option value="<?= e($o) ?>"<?= sel($val, $o) ?>><?= e($o) ?></option><?php endforeach; ?>
              </select>
            <?php elseif ($q['type'] === 'textarea') : ?>
              <textarea class="textarea" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="4"<?= $q['required'] ? ' required' : '' ?>><?= e(is_array($val) ? '' : $val) ?></textarea>
            <?php else :
                $it = ['number' => 'number', 'email' => 'email', 'phone' => 'tel'][$q['type']] ?? 'text'; ?>
              <input class="input" id="<?= e($id) ?>" type="<?= e($it) ?>" name="<?= e($name) ?>" value="<?= e(is_array($val) ? '' : $val) ?>"<?= $q['required'] ? ' required' : '' ?>>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($q['help'] !== '') : ?><p class="hint"><?= e($q['help']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="form-actions"><button class="btn btn-gold btn-lg" type="submit">Continuar <?= icon('arrow-right') ?></button></div>
    </form>
    <?php endif; ?>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
