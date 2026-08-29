<?php
/** Contenido del sitio de venta. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Sitio de venta');
$v = $values;
$field = function ($key, $label, $type = 'text', $hint = '') use ($v) {
    $val = isset($v[$key]) ? $v[$key] : '';
    echo '<div class="field"><label for="' . e($key) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        echo '<textarea class="textarea" id="' . e($key) . '" name="' . e($key) . '" rows="3">' . e($val) . '</textarea>';
    } else {
        echo '<input type="text" class="input" id="' . e($key) . '" name="' . e($key) . '" value="' . e($val) . '">';
    }
    if ($hint !== '') { echo '<p class="field-hint">' . e($hint) . '</p>'; }
    echo '</div>';
};
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/super/landing/planes')) ?>">Planes</a>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/super/landing/testimonios')) ?>">Testimonios</a>
  <a class="btn btn-sm" href="<?= e(mg_url('/')) ?>" target="_blank" rel="noopener">Vista previa</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<form method="post" enctype="multipart/form-data" action="<?= e(mg_url('/super/landing')) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-2">
    <div class="card">
      <div class="card-head"><h2>Portada</h2></div>
      <?php $field('brand_name', 'Nombre de marca'); ?>
      <?php $field('hero_eyebrow', 'Etiqueta superior'); ?>
      <?php $field('hero_title', 'Titular grande'); ?>
      <?php $field('hero_subtitle', 'Subtítulo', 'textarea'); ?>
      <?php $field('hero_cta', 'Texto del botón'); ?>
      <?php $field('hero_qr_note', 'Texto bajo el QR'); ?>
      <div class="field"><label for="demo_slug">Restaurante de demostración</label>
        <select class="select" id="demo_slug" name="demo_slug">
          <?php foreach ($places as $p): ?>
            <option value="<?= e($p['slug']) ?>" <?= $v['demo_slug'] === $p['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="field-hint">Es el que se abre con «Ver demo» y el que da las fotos de la galería.</p></div>
      <?php $field('marquee', 'Marquesina (separa con ·)', 'textarea'); ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>El problema</h2></div>
      <?php $field('problem_eyebrow', 'Etiqueta'); ?>
      <?php $field('problem_1', 'Frase 1', 'textarea'); ?>
      <?php $field('problem_2', 'Frase 2', 'textarea'); ?>
      <?php $field('problem_3', 'Frase 3', 'textarea'); ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>La experiencia</h2></div>
      <?php $field('experience_eyebrow', 'Etiqueta'); ?>
      <?php $field('experience_title', 'Título'); ?>
      <?php $field('experience_text', 'Texto', 'textarea'); ?>
      <?php $field('gallery_eyebrow', 'Etiqueta de la galería'); ?>
      <?php $field('gallery_title', 'Título de la galería'); ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>Cómo funciona</h2></div>
      <?php $field('steps_eyebrow', 'Etiqueta'); ?>
      <?php $field('steps_title', 'Título'); ?>
      <?php for ($i = 1; $i <= 3; $i++) { $field('step_' . $i . '_title', 'Paso ' . $i . ' · título'); $field('step_' . $i . '_text', 'Paso ' . $i . ' · texto', 'textarea'); } ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>Para el dueño</h2></div>
      <?php $field('owner_eyebrow', 'Etiqueta'); ?>
      <?php $field('owner_title', 'Título'); ?>
      <?php $field('owner_text', 'Texto', 'textarea'); ?>
      <div class="grid grid-2">
        <?php for ($i = 1; $i <= 4; $i++) { $field('stat_' . $i . '_value', 'Número ' . $i); $field('stat_' . $i . '_label', 'Etiqueta ' . $i); } ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Precios y cierre</h2></div>
      <?php $field('pricing_eyebrow', 'Etiqueta de precios'); ?>
      <?php $field('pricing_title', 'Título de precios'); ?>
      <?php $field('pricing_note', 'Nota bajo el título', 'textarea'); ?>
      <?php $field('testimonials_eyebrow', 'Etiqueta de testimonios'); ?>
      <?php $field('testimonials_title', 'Título de testimonios'); ?>
      <?php $field('cta_title', 'Título del cierre'); ?>
      <?php $field('cta_text', 'Texto del cierre', 'textarea'); ?>
      <?php $field('cta_button', 'Botón del cierre'); ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>Contacto</h2></div>
      <?php $field('whatsapp', 'WhatsApp (con código de país)', 'text', 'Ejemplo: 50255555555'); ?>
      <?php $field('whatsapp_message', 'Mensaje precargado', 'textarea'); ?>
      <?php $field('contact_email', 'Correo'); ?>
      <?php $field('contact_phone', 'Teléfono visible'); ?>
      <?php $field('contact_city', 'Ciudad'); ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>SEO</h2></div>
      <?php $field('seo_title', 'Título de la página'); ?>
      <?php $field('seo_description', 'Descripción', 'textarea', 'Entre 120 y 160 caracteres.'); ?>
      <div class="field"><label for="seo_og_image">Imagen para redes</label>
        <input class="input" id="seo_og_image" name="seo_og_image" type="file" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($v['seo_og_image'])): ?>
          <div style="max-width:220px;margin-top:.8rem"><?= mg_img($v['seo_og_image'], array('alt' => '', 'sizes' => '220px', 'ratio' => '16/9')) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row mt-2"><button class="btn" type="submit">Guardar el sitio</button></div>
</form>
<?php $view->stop() ?>
