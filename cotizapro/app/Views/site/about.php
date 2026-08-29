<?php $base = url('/e/' . $company['slug']); ?>
<section class="section blueprint" style="padding-top:60px">
  <div class="wrap">
    <div class="grid12" style="align-items:start">
      <div style="grid-column:span 7">
        <span class="secnum">01/</span>
        <h1 class="h1" style="margin:12px 0 20px"><?= e($company['name']) ?></h1>
        <p class="lead"><?= e($company['tagline']) ?></p>
        <?php if ($company['about']): ?>
          <div style="margin-top:24px;max-width:64ch;color:var(--ink-soft)"><?= nl2br(e($company['about'])) ?></div>
        <?php endif; ?>
        <div class="flex flex-wrap" style="margin-top:30px">
          <a class="btn btn--accent" href="<?= e($base . '/catalogo') ?>">Ver el catálogo <span class="arw" aria-hidden="true">&rarr;</span></a>
          <a class="btn btn--ghost" href="<?= e($base . '/contacto') ?>">Contacto</a>
        </div>
      </div>
      <div style="grid-column:span 5/-1">
        <div class="brackets" style="padding:24px">
          <table class="spectable">
            <caption class="sr-only">Datos de la empresa</caption>
            <tbody>
              <?php if ($company['legal_name']): ?><tr><th scope="row">Razón social</th><td><?= e($company['legal_name']) ?></td></tr><?php endif; ?>
              <?php if ($company['nit']): ?><tr><th scope="row">NIT</th><td><?= e($company['nit']) ?></td></tr><?php endif; ?>
              <?php if ((int) $company['years_experience'] > 0): ?><tr><th scope="row">Años en la industria</th><td><?= e($company['years_experience']) ?></td></tr><?php endif; ?>
              <?php if ($company['address']): ?><tr><th scope="row">Dirección</th><td><?= e($company['address']) ?><?= $company['city'] ? ', ' . e($company['city']) : '' ?></td></tr><?php endif; ?>
              <?php if ($company['phone']): ?><tr><th scope="row">Teléfono</th><td><?= e($company['phone']) ?></td></tr><?php endif; ?>
              <?php if ($company['email']): ?><tr><th scope="row">Correo</th><td><?= e($company['email']) ?></td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>
<?php if ($brands): ?>
<section class="section section--tight" style="background:var(--paper-2)">
  <div class="wrap">
    <div class="cota" style="margin-bottom:26px">Marcas que distribuimos</div>
    <div class="brandwall">
      <?php foreach ($brands as $b): ?>
        <div><?php if ($b['logo']): ?><img src="<?= e(upload($b['logo'])) ?>" alt="<?= e($b['name']) ?>" loading="lazy" decoding="async" width="150" height="44"><?php else: ?><span><?= e($b['name']) ?></span><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
