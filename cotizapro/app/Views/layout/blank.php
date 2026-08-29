<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body class="blueprint">
<main id="contenido"><?= $content ?></main>
<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
