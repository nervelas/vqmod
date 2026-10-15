<?php $bare = true; $quote = $quote ?? "Que la próxima oportunidad del Estado <em>no se te escape</em>."; ?>
<div class="auth">
  <aside class="auth-art" aria-hidden="true"><img src="<?= e(asset("img/sector-servicios.webp")) ?>" alt="" width="1200" height="800"><a href="<?= e(url("")) ?>"><?= brand_html() ?></a><div class="quote"><blockquote><?= $quote ?></blockquote><p class="muted" style="margin-top:14px">Radar diario de concursos de GUATECOMPRAS para pequeñas empresas.</p></div></aside>
  <section class="auth-panel">
    <div class="auth-top"><a href="<?= e(url("")) ?>"><?= brand_html() ?></a><button class="icon-btn" type="button" data-theme-toggle aria-label="Cambiar tema"><?= icon("moon") ?></button></div>
    <div class="auth-card">
  <p class="eyebrow">Bienvenido</p>
  <h1>Qué bueno <em>verte</em></h1><p class="muted">Ingresa para ver tus oportunidades.</p>
  <?= flash_html() ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <?= f_input('email', 'Correo electrónico', $_POST['email'] ?? '', 'email', 'required autocomplete="username" inputmode="email"') ?>
    <?= f_input('pass', 'Contraseña', '', 'password', 'required autocomplete="current-password"') ?>
    <button class="btn btn-gold btn-lg btn-block">Ingresar <?= icon('arrow') ?></button>
  </form>
  <p class="center" style="margin-top:18px"><a href="<?= e(url('recuperar')) ?>">Olvidé mi contraseña</a></p>
  <p class="muted center">¿Primera vez? <a href="<?= e(url('registro')) ?>">Crea tu cuenta gratis</a></p>
    </div>
  </section>
</div>
