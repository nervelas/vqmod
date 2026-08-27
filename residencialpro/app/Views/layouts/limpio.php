<?php
use App\Core\Ajustes;
use App\Core\Url;
use App\Core\Vista;
?><!DOCTYPE html>
<html lang="es" data-tema="<?= e(Ajustes::get('tema', 'verde-oro')) ?>" data-modo="claro"
      data-base="<?= e(Url::basePath()) ?>" data-color-marca="<?= e(Ajustes::get('color_primario', '#0F2E24')) ?>">
<head><?= Vista::parcial('partials/head', ['titulo' => $tituloPagina ?? 'Acceso']) ?></head>
<body>
<?= $contenido ?>
<script<?= nonce() ?> src="<?= e(url('/assets/js/app.js')) ?>?v=<?= RPRO_VERSION ?>"></script>
<?php if (!empty($scripts)): ?><?= $scripts ?><?php endif; ?>
</body>
</html>
