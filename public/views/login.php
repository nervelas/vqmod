<?php
/** @var string $csrf @var list<array{tipo:string,texto:string}> $mensajes */
use Fel\Core\Config;
use Fel\Web\Vista;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar — <?= Vista::e(Config::get('app.nombre', 'Facturación FEL')) ?></title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="ingreso">
  <form class="caja" method="post" action="index.php?r=ingresar">
    <h1><?= Vista::e(Config::get('app.nombre', 'Facturación FEL')) ?></h1>
    <p class="sub">Régimen FEL — Guatemala</p>

    <?php foreach ($mensajes as $mensaje): ?>
      <div class="mensaje <?= Vista::e($mensaje['tipo']) ?>"><?= Vista::e($mensaje['texto']) ?></div>
    <?php endforeach; ?>

    <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">

    <div class="campo">
      <label for="usuario">Usuario</label>
      <input id="usuario" name="usuario" autocomplete="username" autofocus required>
    </div>

    <div class="campo">
      <label for="clave">Contraseña</label>
      <input id="clave" name="clave" type="password" autocomplete="current-password" required>
    </div>

    <button class="boton" style="width:100%" type="submit">Ingresar</button>
  </form>
</div>
</body>
</html>
