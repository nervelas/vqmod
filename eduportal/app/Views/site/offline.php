<!doctype html>
<html lang="es-GT" data-base="<?= e(base_path_url()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sin conexión · <?= e(App\Core\Settings::get('colegio_nombre', 'EduPortal')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/paginas.css')) ?>">
</head>
<body>
<main class="pantalla-centro">
  <div class="caja">
    <div class="codigo"><?= icono('escuela', 72) ?></div>
    <h1>Sin conexión</h1>
    <p class="txt-2">No hay conexión a internet en este momento. Las secciones que ya visitó
       siguen disponibles; el resto volverá a cargarse cuando se restablezca la señal.</p>
    <p class="acciones" style="justify-content:center;margin-top:24px">
      <a href="<?= e(url('portal')) ?>" class="btn">Reintentar</a>
    </p>
  </div>
</main>
</body>
</html>
