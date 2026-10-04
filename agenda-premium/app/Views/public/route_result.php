<?php /** @var array $biz @var array $form @var string $message @var array $events */ ?>
<?php partial('public/_header', ['biz' => $biz]); ?>
<main id="main" class="pub-page-main">
  <div class="container pub-narrow">
    <p class="eyebrow">Gracias por tus respuestas</p>
    <h1 class="serif"><?= e($form['name']) ?></h1>
    <?php if (trim($message) !== '') : ?><div class="card card-gold"><div class="card-body prose"><?= \App\Core\Str::richText($message) ?></div></div><?php endif; ?>
    <?php if ($events) : ?>
      <h2 class="serif mt-3">Elige una opción para reservar</h2>
      <ul class="pub-choice">
        <?php foreach ($events as $ev) : ?>
        <li><a class="btn btn-outline btn-block" href="<?= e((string) ($ev['url'] ?? url('/e/' . $ev['slug']))) ?>"><?= e($ev['name']) ?> <?= icon('arrow-right') ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="mt-3"><a class="btn btn-ghost" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?> Volver al inicio</a></p>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
