<?php
use App\Controllers\Configuracion;
use App\Core\Settings;

$temaClave = (string)Settings::get('tema', 'default');
if (!isset(Configuracion::TEMAS[$temaClave])) {
    $temaClave = 'default';
}
$logo = (string)Settings::get('colegio_logo', '');
?>
<!doctype html>
<html lang="es-GT" data-tema="<?= e($temaClave) ?>" data-oscuro="0"
      data-base="<?= e(base_path_url()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<head><?= App\Core\View::partial('partials/head', ['titulo' => $titulo ?? 'Ingresar']) ?></head>
<body>
<div class="acceso">
  <section class="acceso__arte">
    <div class="acceso__marca">
      <?php if ($logo !== ''): ?><img src="<?= e(archivo_url($logo)) ?>" alt=""><?php endif; ?>
      <strong><?= e(Settings::get('colegio_nombre', 'EduPortal')) ?></strong>
    </div>
    <div class="acceso__lema">
      <div class="linea"></div>
      <h2><?= e(Settings::get('colegio_lema', 'Formación integral con excelencia')) ?></h2>
      <p>Acceda al portal para consultar calificaciones, estado de cuenta, asistencia,
         avisos y comunicarse con los docentes del colegio.</p>
    </div>
    <div class="acceso__pie">&copy; <?= date('Y') ?> <?= e(Settings::get('colegio_nombre', 'EduPortal')) ?>. Todos los derechos reservados.</div>
  </section>
  <section class="acceso__panel">
    <div class="acceso__caja"><?= $contenido ?? '' ?></div>
  </section>
</div>
<?= App\Core\View::partial('partials/flash') ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
