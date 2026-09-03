<?php
declare(strict_types=1);
if (!Content::blockEnabled('faq')) { return; }
$faqs = Content::faqs($limitFaqs ?? null);
if ($faqs === []) { return; }

$entities = [];
foreach ($faqs as $f) {
    $entities[] = [
        '@type' => 'Question',
        'name'  => (string) $f['question'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $f['answer'])],
    ];
}
Seo::addSchema(['@type' => 'FAQPage', 'mainEntity' => $entities]);
?>
<section class="section section--alt" id="seccion-faq" aria-labelledby="tit-faq">
  <div class="wrap">
    <header class="shead shead--center" data-reveal>
      <span class="shead__index">07</span>
      <div class="shead__eyebrow"><?= e(Content::b('faq', 'eyebrow')) ?></div>
      <h2 class="shead__title" id="tit-faq"><?= e(Content::b('faq', 'title')) ?></h2>
      <p class="shead__sub"><?= e(Content::b('faq', 'subtitle')) ?></p>
    </header>

    <div class="faq" data-accordion="single" data-stagger>
      <?php foreach ($faqs as $i => $f): ?>
        <div class="faq__item<?= $i === 0 ? ' is-open' : '' ?>">
          <button class="faq__q" type="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" id="faq-q<?= (int) $f['id'] ?>" aria-controls="faq-a<?= (int) $f['id'] ?>">
            <span><?= e($f['question']) ?></span>
            <span class="faq__sign" aria-hidden="true"></span>
          </button>
          <div class="faq__a" id="faq-a<?= (int) $f['id'] ?>" role="region" aria-labelledby="faq-q<?= (int) $f['id'] ?>">
            <div><p><?= nl2br(e($f['answer'])) ?></p></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
