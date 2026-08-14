<?php
/** Admissions page with primer-ingreso form. */
if (!defined('BASE_PATH')) { exit; }
$map = Content::sectionMap($sections);
$intro = $map['intro'] ?? null;
$old = $_SESSION['_old'] ?? []; unset($_SESSION['_old']);
$val = fn($k) => e($old[$k] ?? '');
?>
<section class="page-hero page-hero--level">
  <div class="container">
    <h1><?= e($page['h1'] ?: $page['title']) ?></h1>
    <?php if (!empty($page['intro'])): ?><p class="page-hero__intro"><?= e($page['intro']) ?></p><?php endif; ?>
  </div>
</section>

<?php if ($intro): ?>
<section class="section text-block">
  <div class="container text-block__grid text-block__grid--single">
    <div class="text-block__body text-block__body--center">
      <h2><?= e($intro['title']) ?></h2>
      <p><?= nl2br(e($intro['body'])) ?></p>
      <?php if (!empty($intro['button_text']) && !empty($intro['button_url'])): ?>
        <a class="btn btn--outline" href="<?= e(Content::url($intro['button_url'])) ?>" <?= $intro['button_target']==='_blank'?'target="_blank" rel="noopener"':'' ?>><?= e($intro['button_text']) ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section form-section">
  <div class="container form-card">
    <h2>Solicitud de primer ingreso</h2>
    <p class="form-card__lead">Completa los datos y un asesor de admisiones te contactará.</p>
    <form method="post" action="<?= e(base_url('admisiones')) ?>" class="form form--grid" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="admision">
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="form-group form-group--full">
        <label>Nombre completo del estudiante *</label>
        <input type="text" name="estudiante" value="<?= $val('estudiante') ?>" required>
      </div>
      <div class="form-group">
        <label>Fecha de nacimiento</label>
        <input type="date" name="fecha_nacimiento" value="<?= $val('fecha_nacimiento') ?>">
      </div>
      <div class="form-group">
        <label>Edad</label>
        <input type="number" name="edad" min="2" max="25" value="<?= $val('edad') ?>">
      </div>
      <div class="form-group">
        <label>Grado al que aplica *</label>
        <input type="text" name="grado" value="<?= $val('grado') ?>" required>
      </div>
      <div class="form-group">
        <label>Jornada</label>
        <select name="jornada">
          <option value="">Seleccione</option>
          <option>Matutina</option>
          <option>Vespertina</option>
        </select>
      </div>
      <div class="form-group form-group--full">
        <label>Carrera (si aplica al diversificado)</label>
        <input type="text" name="carrera" value="<?= $val('carrera') ?>">
      </div>
      <div class="form-group form-group--full">
        <label>Institución educativa anterior</label>
        <input type="text" name="institucion_anterior" value="<?= $val('institucion_anterior') ?>">
      </div>
      <div class="form-group">
        <label>Padre o encargado *</label>
        <input type="text" name="encargado" value="<?= $val('encargado') ?>" required>
      </div>
      <div class="form-group">
        <label>Teléfono *</label>
        <input type="tel" name="telefono" value="<?= $val('telefono') ?>" required>
      </div>
      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" name="correo" value="<?= $val('correo') ?>">
      </div>
      <div class="form-group">
        <label>¿Cómo conoció el colegio?</label>
        <input type="text" name="como_conocio" value="<?= $val('como_conocio') ?>">
      </div>
      <div class="form-group form-group--full">
        <label>Dirección</label>
        <input type="text" name="direccion" value="<?= $val('direccion') ?>">
      </div>
      <div class="form-group form-group--full">
        <button type="submit" class="btn btn--primary btn--lg">Enviar solicitud</button>
      </div>
    </form>
  </div>
</section>
