<?php
/** @var string $contenido @var string $titulo @var list<array{tipo:string,texto:string}> $mensajes */
use Fel\Core\Config;
use Fel\Web\Sesion;
use Fel\Web\Vista;

$rutaActual = (string) ($_GET['r'] ?? 'panel');
$ambiente   = (string) Config::get('ambiente', 'pruebas');
$proveedor  = (string) Config::get('certificador.proveedor', 'simulador');

$menu = [
    'panel'      => 'Panel',
    'nuevo'      => 'Nuevo documento',
    'documentos' => 'Documentos',
    'clientes'   => 'Clientes',
    'productos'  => 'Productos',
    'ajustes'    => 'Ajustes',
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Vista::e(($titulo !== '' ? $titulo . ' — ' : '') . Config::get('app.nombre', 'Facturación FEL')) ?></title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>

<header class="barra">
  <div class="marca"><?= Vista::e(Config::get('app.nombre', 'Facturación FEL')) ?></div>
  <nav>
    <?php foreach ($menu as $ruta => $etiqueta): ?>
      <a href="index.php?r=<?= $ruta ?>" class="<?= $rutaActual === $ruta ? 'activo' : '' ?>"><?= Vista::e($etiqueta) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="usuario">
    <span><?= Vista::e(Sesion::usuario()['nombre'] ?? '') ?></span>
    <a href="index.php?r=salir">Salir</a>
  </div>
</header>

<main class="contenedor">

  <?php if ($ambiente !== 'produccion' || $proveedor === 'simulador'): ?>
    <div class="cinta">
      <strong>Ambiente de pruebas.</strong>
      Certificador: <?= Vista::e($proveedor) ?>.
      Los documentos emitidos aquí <strong>no tienen validez fiscal</strong>.
    </div>
  <?php endif; ?>

  <?php foreach ($mensajes as $mensaje): ?>
    <div class="mensaje <?= Vista::e($mensaje['tipo']) ?>"><?= Vista::e($mensaje['texto']) ?></div>
  <?php endforeach; ?>

  <?= $contenido ?>
</main>

</body>
</html>
