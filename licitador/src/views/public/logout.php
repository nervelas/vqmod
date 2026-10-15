<section class="auth"><div class="auth-card card in center"><h1>¿Cerrar sesión?</h1>
<form method="post" class="form"><?= csrf_field() ?><button class="btn btn-gold btn-block">Sí, salir</button></form>
<p><a href="<?= e(url('app')) ?>">Cancelar</a></p></div></section>
