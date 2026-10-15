<section class="section legal"><div class="container narrow">
  <p class="eyebrow">Legal</p>
  <h1><?= e($page['title']) ?></h1>
  <div class="prose"><?= $page['body'] /* HTML filtrado al guardarse desde el admin */ ?></div>
  <p class="fine">Última revisión: <?= e(fdate(date('Y-m-d'))) ?></p>
</div></section>
