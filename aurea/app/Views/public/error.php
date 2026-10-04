<section class="error-page">
  <div>
    <div class="num"><?= e((string)$code) ?></div>
    <h1><?= e($code === 404 ? __('No encontramos esta página') : __('Algo no salió bien')) ?></h1>
    <p style="color:var(--muted)"><?= e($message) ?></p>
    <a class="btn btn-gold" href="<?= e(url('/')) ?>"><?= e(__('Volver al inicio')) ?></a>
  </div>
</section>
