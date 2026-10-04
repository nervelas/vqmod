<div class="wrap" style="padding:50px 0 90px">
  <div class="success">
    <?php if ($done): ?>
      <div class="seal"><svg viewBox="0 0 40 40" aria-hidden="true"><path d="M8 21l9 9 15-19"/></svg></div>
      <h1><?= e(__('¡Gracias por tu opinión!')) ?></h1>
      <p style="color:var(--muted)"><?= e(__('La revisaremos y nos ayuda muchísimo a mejorar.')) ?></p>
    <?php else: ?>
      <span class="eyebrow"><?= e(__('Tu experiencia')) ?></span>
      <h1><?= e(__('¿Cómo te fue con %s?', $a['prof_name'])) ?></h1>
      <form method="post" class="box" style="text-align:left" action="<?= e(url('/resena/' . $a['token'])) ?>">
        <?= csrf_field('public') ?>
        <fieldset style="border:0;padding:0;margin:0 0 18px"><legend class="label"><?= e(__('Calificación')) ?></legend>
          <?php for ($i = 5; $i >= 1; $i--): ?><label class="check" style="display:inline-flex;margin-right:14px"><input type="radio" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>> <?= str_repeat('★', $i) ?></label><?php endfor; ?>
        </fieldset>
        <div class="field"><label for="c"><?= e(__('Comentario (opcional)')) ?></label><textarea id="c" name="comment" maxlength="800"></textarea></div>
        <button class="btn btn-gold" type="submit"><?= e(__('Enviar opinión')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
