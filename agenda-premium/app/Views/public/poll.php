<?php /** @var array $biz @var array $poll @var array $options @var string $tzLabel @var string $duration @var string $host @var ?string $error @var array $values @var bool $saved */
$open = !empty($poll['is_open']);
$final = $poll['status'] === 'finalized';
$labels = ['yes' => 'Sí puedo', 'maybe' => 'Tal vez', 'no' => 'No puedo'];
?>
<?php partial('public/_header', ['biz' => $biz]); ?>
<main id="main" class="pub-page-main">
  <div class="container pub-narrow">
    <p class="eyebrow">Encuesta de horarios</p>
    <h1 class="serif"><?= e($poll['title']) ?></h1>
    <p class="muted"><?= e($duration) ?><?= $host !== '' ? ' · con ' . e($host) : '' ?> · Horarios en <?= e($tzLabel) ?></p>
    <?php if (trim((string) ($poll['description'] ?? '')) !== '') : ?><div class="prose"><?= \App\Core\Str::richText($poll['description']) ?></div><?php endif; ?>
    <?php if ($saved) : ?><div class="alert alert-ok" role="status"><?= icon('check') ?><div>Gracias, registramos tu respuesta. Puedes volver a este enlace para cambiarla mientras la encuesta siga abierta.</div></div><?php endif; ?>
    <?php if ($error) : ?><div class="alert alert-err" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
    <?php if ($final) : ?><div class="alert alert-info" role="status"><?= icon('info') ?><div>La fecha ya fue elegida. Te llegó la confirmación por correo si participaste.</div></div>
    <?php elseif (!$open) : ?><div class="alert alert-warn" role="status"><?= icon('clock') ?><div>Esta encuesta ya cerró y no recibe más respuestas.</div></div><?php endif; ?>

    <form method="post" action="<?= e(url('/encuesta/' . $poll['token'])) ?>" class="stack pub-form" novalidate>
      <?= public_csrf_field('book') ?>
      <div class="hp-wrap" aria-hidden="true"><label>No llenar este campo<input type="text" name="company_site" tabindex="-1" autocomplete="off"></label></div>
      <ul class="poll-list">
        <?php foreach ($options as $o) : $cur = $values['v'][$o['id']] ?? 'yes'; ?>
        <li class="poll-opt<?= $o['final'] ? ' is-final' : '' ?>">
          <fieldset <?= $open ? '' : 'disabled' ?>>
            <legend>
              <span class="poll-date serif"><?= e($o['date']) ?></span>
              <span class="poll-time mono"><?= e($o['time']) ?></span>
              <span class="poll-local muted" data-local-time="<?= e($o['iso']) ?>"></span>
              <?php if ($o['final']) : ?><span class="badge badge-ok">Elegido</span><?php elseif ($o['best'] && $open) : ?><span class="badge badge-gold">Va ganando</span><?php endif; ?>
            </legend>
            <div class="chips" role="radiogroup">
              <?php foreach ($labels as $k => $lab) : ?>
              <label class="chip"><input type="radio" name="v[<?= e($o['id']) ?>]" value="<?= e($k) ?>"<?= chk($cur === $k && $open) ?>> <?= e($lab) ?></label>
              <?php endforeach; ?>
            </div>
            <p class="poll-tally mono muted">Sí <?= e($o['yes']) ?> · Tal vez <?= e($o['maybe']) ?> · No <?= e($o['no']) ?></p>
          </fieldset>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($open) : ?>
      <div class="form-grid">
        <div class="field"><label for="p-name">Tu nombre <span class="req" aria-hidden="true">*</span></label><input class="input" id="p-name" name="name" type="text" autocomplete="name" required value="<?= e($values['name'] ?? '') ?>"></div>
        <div class="field"><label for="p-email">Tu correo <span class="req" aria-hidden="true">*</span></label><input class="input" id="p-email" name="email" type="email" autocomplete="email" required value="<?= e($values['email'] ?? '') ?>"><p class="hint">Solo lo usamos para avisarte la fecha final. No se muestra a otras personas.</p></div>
      </div>
      <div class="form-actions"><button class="btn btn-gold btn-lg" type="submit">Enviar mi respuesta</button></div>
      <?php endif; ?>
    </form>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
