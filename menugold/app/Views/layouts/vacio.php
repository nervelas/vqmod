<?php
use MenuGold\Core\View;
use MenuGold\Core\Security;
?><!doctype html>
<html lang="es" data-tema="negro-oro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(View::section('titulo', 'MenúGold')) ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(asset('css/temas.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/menu.css')) ?>">
</head>
<body class="menu" style="padding-bottom:0">
<?= View::section('contenido') ?>
</body>
</html>
