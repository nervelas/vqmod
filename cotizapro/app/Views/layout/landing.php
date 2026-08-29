<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body>
<a class="skip" href="#problema">Saltar al contenido</a>
<?= $content ?>
<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
