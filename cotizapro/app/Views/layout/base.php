<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body>
<a class="skip" href="#contenido">Saltar al contenido</a>
<main id="contenido"><?= $content ?></main>
<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
