<?php $noindex = true; ?>
<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body>
<a class="skip" href="#contenido">Saltar al contenido</a>
<header class="topbar">
  <div class="wrap topbar__in">
    <a class="brand" href="<?= e(url('/e/' . $company['slug'])) ?>">
      <?php if (!empty($company['logo'])): ?>
        <img src="<?= e(upload($company['logo'])) ?>" alt="<?= e($company['name']) ?>" width="140" height="34">
      <?php else: ?>
        <span class="brand__mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($company['name'], 0, 2))) ?></span>
        <span><?= e($company['name']) ?></span>
      <?php endif; ?>
    </a>
    <nav class="nav" aria-label="Principal" style="margin-left:auto">
      <a href="<?= e(url('/e/' . $company['slug'] . '/catalogo')) ?>">Catálogo</a>
      <?php if ($company['whatsapp']): ?>
        <a class="btn btn--ghost btn--sm" href="https://wa.me/<?= e($company['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="contenido"><?= $content ?></main>
<footer class="footer">
  <div class="wrap">
    <div class="footer__bottom" style="margin-top:0;padding-top:0;border:0">
      <span>&copy; <?= date('Y') ?> <?= e($company['legal_name'] ?: $company['name']) ?></span>
      <span><?= e($company['phone']) ?> · <?= e($company['email']) ?></span>
    </div>
  </div>
</footer>
<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
