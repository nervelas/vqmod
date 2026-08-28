<?php
/** @var string $contenido @var string $titulo @var list<array{tipo:string,texto:string}> $mensajes */
use Fel\Core\Config;
use Fel\Web\Contexto;
use Fel\Web\Sesion;
use Fel\Web\Vista;

$rutaActual = (string) ($_GET['r'] ?? 'panel');
$empresa    = Contexto::empresa();

$menu = [];
if ($empresa !== null) {
    $menu = [
        'panel'      => 'Panel',
        'nuevo'      => 'Nuevo documento',
        'documentos' => 'Documentos',
        'clientes'   => 'Clientes',
        'productos'  => 'Productos',
        'ajustes'    => 'Ajustes',
    ];
}

$menuPlataforma = Sesion::esSuperadmin() ? ['empresas' => 'Empresas', 'usuarios' => 'Usuarios'] : [];
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
    <?php if ($menuPlataforma !== []): ?>
      <span class="separador"></span>
      <?php foreach ($menuPlataforma as $ruta => $etiqueta): ?>
        <a href="index.php?r=<?= $ruta ?>" class="plataforma <?= $rutaActual === $ruta ? 'activo' : '' ?>"><?= Vista::e($etiqueta) ?></a>
      <?php endforeach; ?>
    <?php endif; ?>
  </nav>
  <div class="usuario">
    <?php if ($empresa !== null): ?>
      <span class="empresa" title="Empresa sobre la que está trabajando"><?= Vista::e($empresa->nombreComercial()) ?></span>
    <?php endif; ?>
    <span><?= Vista::e(Sesion::usuario()['nombre'] ?? '') ?></span>
    <a href="index.php?r=salir">Salir</a>
  </div>
</header>

<main class="contenedor">

  <?php if ($empresa !== null && ($empresa->esSimulador() || $empresa->ambiente() !== 'produccion')): ?>
    <div class="cinta">
      <strong>Ambiente de pruebas.</strong>
      Certificador: <?= Vista::e($empresa->proveedorCertificador()) ?>.
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
