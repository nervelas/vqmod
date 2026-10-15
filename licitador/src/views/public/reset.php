<?php $bare = true; $quote = $quote ?? "Que la próxima oportunidad del Estado <em>no se te escape</em>."; ?>
<div class="auth">
  <aside class="auth-art" aria-hidden="true"><img src="<?= e(asset("img/sector-servicios.webp")) ?>" alt="" width="1200" height="800"><a href="<?= e(url("")) ?>"><?= brand_html() ?></a><div class="quote"><blockquote><?= $quote ?></blockquote><p class="muted" style="margin-top:14px">Radar diario de concursos de GUATECOMPRAS para pequeñas empresas.</p></div></aside>
  <section class="auth-panel">
    <div class="auth-top"><a href="<?= e(url("")) ?>"><?= brand_html() ?></a><button class="icon-btn" type="button" data-theme-toggle aria-label="Cambiar tema"><?= icon("moon") ?></button></div>
    <div class="auth-card">
  <p class="eyebrow">Casi listo</p>
  <h1>Elige tu nueva <em>contraseña</em></h1>
  <?= flash_html() ?>
  <form method="post" class="form"><?= csrf_field() ?>
    <?= f_input('pass', 'Nueva contraseña (8+ caracteres, letras y números)', '', 'password', 'required minlength="8" autocomplete="new-password"') ?>
    <button class="btn btn-gold btn-lg btn-block">Guardar contraseña</button>
  </form>
    </div>
  </section>
</div>
