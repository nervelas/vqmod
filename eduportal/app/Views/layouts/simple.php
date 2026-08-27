<?php
use App\Controllers\Configuracion;
use App\Core\Settings;

$temaClave = (string)Settings::get('tema', 'default');
if (!isset(Configuracion::TEMAS[$temaClave])) {
    $temaClave = 'default';
}
?>
<!doctype html>
<html lang="es-GT" data-tema="<?= e($temaClave) ?>" data-oscuro="0"
      data-base="<?= e(base_path_url()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<head><?= App\Core\View::partial('partials/head', ['titulo' => $titulo ?? '']) ?></head>
<body>
<main class="pantalla-centro" id="contenido"><?= $contenido ?? '' ?></main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
