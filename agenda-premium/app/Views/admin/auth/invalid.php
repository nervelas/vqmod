<?php /** @var string $what */ ?>
<div class="auth-seal auth-seal-warn"><?= icon('clock') ?></div>
<h1 class="serif auth-title">Este enlace ya no sirve</h1>
<p class="muted">El enlace de <?= e($what) ?> caducó o ya se usó. Por seguridad solo funciona una vez y durante un tiempo limitado.</p>
<p class="center mt-3">
  <a class="btn btn-gold" href="<?= e(url('/admin/olvide')) ?>">Pedir un enlace nuevo</a>
  <a class="btn btn-ghost" href="<?= e(url('/admin/login')) ?>">Ir a iniciar sesión</a>
</p>
