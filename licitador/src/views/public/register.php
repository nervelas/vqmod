<section class="auth"><div class="auth-card card reveal in">
  <h1>Crea tu cuenta</h1><p class="muted">Prueba gratis <?= (int) S('trial_days', 7) ?> días. Sin tarjeta.</p>
  <?= flash_html() ?>
  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <?= f_input('name', 'Tu nombre o el de tu empresa', $_POST['name'] ?? '', 'text', 'required autocomplete="name"') ?>
    <?= f_input('email', 'Correo electrónico', $_POST['email'] ?? '', 'email', 'required autocomplete="email" inputmode="email"') ?>
    <?= f_input('phone', 'WhatsApp / teléfono', $_POST['phone'] ?? '', 'tel', 'autocomplete="tel" inputmode="tel" placeholder="5555 1234"') ?>
    <?= f_input('pass', 'Contraseña (8+ caracteres, letras y números)', '', 'password', 'required minlength="8" autocomplete="new-password"') ?>
    <div class="hp" aria-hidden="true"><label>No llenar<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <label class="check"><input type="checkbox" name="terms" value="1"> <span>Acepto los <a href="<?= e(url('legal/terminos')) ?>" target="_blank" rel="noopener">términos</a> y la <a href="<?= e(url('legal/privacidad')) ?>" target="_blank" rel="noopener">política de privacidad</a>.</span></label>
    <button class="btn btn-gold btn-block">Crear cuenta y empezar</button>
  </form>
  <p class="muted center">¿Ya tienes cuenta? <a href="<?= e(url('ingresar')) ?>">Ingresa</a></p>
  <p class="fine center">Licitador no garantiza adjudicaciones ni ofrece gestión de influencias; solo informa y asiste en la preparación de ofertas.</p>
</div></section>
