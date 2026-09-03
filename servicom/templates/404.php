<?php declare(strict_types=1); ?>
<section class="err">
  <div class="wrap">
    <div class="err__code">404</div>
    <h1 style="margin-top:1rem">Esta página no existe</h1>
    <p class="muted" style="max-width:34rem;margin:1rem auto 2rem">
      Es posible que el enlace haya cambiado o que la dirección esté mal escrita.
      Le dejamos algunos accesos rápidos para continuar.
    </p>
    <div class="cta__actions">
      <a class="btn btn--lg" data-magnetic=".22" href="<?= e(base('')) ?>"><?= icon('inicio', 19) ?><span>Ir al inicio</span></a>
      <a class="btn btn--ghost btn--lg" href="<?= e(base('servicios/')) ?>"><?= icon('servicios', 19) ?><span>Ver servicios</span></a>
      <a class="btn btn--ghost btn--lg" href="<?= e(base('contacto/')) ?>"><?= icon('contacto', 19) ?><span>Contacto</span></a>
    </div>
  </div>
</section>
