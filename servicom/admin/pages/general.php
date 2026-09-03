<?php
declare(strict_types=1);
/** Datos del sitio: identidad, contacto, redes y efectos visuales. */

$groups = [
  'identidad' => ['Identidad del sitio', 'web', [
      'site_name'    => ['label' => 'Nombre del sitio', 'type' => 'text', 'max' => 120, 'required' => true],
      'site_tagline' => ['label' => 'Frase descriptiva', 'type' => 'text', 'max' => 200, 'full' => true],
      'logo'         => ['label' => 'Logotipo (fondo claro)', 'type' => 'media', 'hint' => 'Se usa en los temas claros y en el panel.'],
      'logo_light'   => ['label' => 'Logotipo (fondo oscuro)', 'type' => 'media', 'hint' => 'Versión clara del logo para los temas oscuros.'],
      'favicon'      => ['label' => 'Favicon', 'type' => 'media', 'hint' => 'Icono de la pestaña del navegador. SVG o PNG cuadrado.'],
      'footer_text'  => ['label' => 'Texto del pie de página', 'type' => 'textarea', 'full' => true],
      'copyright'    => ['label' => 'Aviso de derechos', 'type' => 'text', 'max' => 200, 'full' => true],
  ]],
  'contacto' => ['Datos de contacto', 'contacto', [
      'phone'     => ['label' => 'Teléfono principal', 'type' => 'text', 'max' => 40],
      'phone_alt' => ['label' => 'Teléfono alterno', 'type' => 'text', 'max' => 40],
      'whatsapp'  => ['label' => 'Número de WhatsApp', 'type' => 'text', 'max' => 40, 'hint' => 'Con o sin código de país. Si tiene 8 dígitos se antepone 502.'],
      'whatsapp_message' => ['label' => 'Mensaje previo de WhatsApp', 'type' => 'textarea', 'full' => true],
      'email'     => ['label' => 'Correo electrónico', 'type' => 'email', 'max' => 160, 'hint' => 'A este correo llegan los avisos del formulario.'],
      'address_line'   => ['label' => 'Dirección visible', 'type' => 'text', 'max' => 200, 'full' => true],
      'address_city'   => ['label' => 'Ciudad', 'type' => 'text', 'max' => 120],
      'address_region' => ['label' => 'Departamento / región', 'type' => 'text', 'max' => 120],
      'schedule'  => ['label' => 'Horario de atención', 'type' => 'text', 'max' => 160, 'full' => true],
      'map_embed' => ['label' => 'Mapa de Google (URL del iframe)', 'type' => 'text', 'max' => 500, 'full' => true, 'hint' => 'Pegue solo la dirección que empieza por https://www.google.com/maps/embed…'],
  ]],
  'redes' => ['Redes sociales', 'redes', [
      'social_facebook'  => ['label' => 'Facebook', 'type' => 'url', 'max' => 255],
      'social_instagram' => ['label' => 'Instagram', 'type' => 'url', 'max' => 255],
      'social_linkedin'  => ['label' => 'LinkedIn', 'type' => 'url', 'max' => 255],
      'social_youtube'   => ['label' => 'YouTube', 'type' => 'url', 'max' => 255],
      'social_tiktok'    => ['label' => 'TikTok', 'type' => 'url', 'max' => 255],
      'social_x'         => ['label' => 'X (Twitter)', 'type' => 'url', 'max' => 255],
  ]],
  'efectos' => ['Efectos y comportamiento', 'chispa', [
      'fx_cursor'    => ['label' => 'Cursor personalizado animado', 'type' => 'checkbox'],
      'fx_grain'     => ['label' => 'Textura y luces de fondo', 'type' => 'checkbox'],
      'fx_parallax'  => ['label' => 'Efecto parallax al hacer scroll', 'type' => 'checkbox'],
      'fx_reveal'    => ['label' => 'Animaciones de entrada', 'type' => 'checkbox'],
      'fx_preloader' => ['label' => 'Pantalla de carga inicial', 'type' => 'checkbox'],
      'slider_autoplay' => ['label' => 'Slider automático', 'type' => 'checkbox'],
      'slider_interval' => ['label' => 'Segundos entre diapositivas (en milisegundos)', 'type' => 'number', 'hint' => '6500 = 6.5 segundos.'],
      'theme_allow_visitor_switch' => ['label' => 'Permitir que el visitante cambie de tema', 'type' => 'checkbox'],
      'maintenance'  => ['label' => 'Modo mantenimiento (oculta el sitio a las visitas)', 'type' => 'checkbox'],
      'form_success' => ['label' => 'Mensaje de éxito del formulario', 'type' => 'text', 'max' => 255, 'full' => true],
      'form_error'   => ['label' => 'Mensaje de error del formulario', 'type' => 'text', 'max' => 255, 'full' => true],
  ]],
];

$tab = get('tab', 'identidad');
if (!isset($groups[$tab])) { $tab = 'identidad'; }

if (is_post()) {
    Csrf::verify();
    $saveTab = post('tab', $tab);
    $fields  = $groups[$saveTab][2] ?? [];
    $values  = [];
    foreach ($fields as $key => $f) {
        if (($f['type'] ?? '') === 'checkbox') {
            $values[$key] = isset($_POST[$key]) ? '1' : '0';
            continue;
        }
        if (($f['type'] ?? '') === 'media') {
            $v = post($key);
            $up = $_FILES['upload_' . $key] ?? null;
            if (is_array($up) && ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $res = Media::upload($up);
                if ($res['ok']) { $v = (string) $res['path']; } else { flash($res['error'] ?? 'Error al subir.', 'error'); }
            }
            $values[$key] = $v;
            continue;
        }
        $v = post($key);
        if (isset($f['max'])) { $v = mb_substr($v, 0, (int) $f['max']); }
        $values[$key] = $v;
    }
    Settings::setMany($values, $saveTab === 'efectos' ? 'tema' : $saveTab);
    Settings::flush();
    flash('Datos guardados correctamente.');
    redirect('admin/index.php?p=general&tab=' . $saveTab);
}

admin_header('Datos del sitio', 'general');
?>
<div class="tabs">
  <?php foreach ($groups as $key => [$label, $ic, $_]): ?>
    <a class="<?= $key === $tab ? 'is-active' : '' ?>" href="<?= e(admin_url('general', ['tab' => $key])) ?>"><?= icon($ic, 16) ?><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form class="panel" method="post" enctype="multipart/form-data" action="<?= e(admin_url('general', ['tab' => $tab])) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="panel__head"><h2><?= icon($groups[$tab][1], 19) ?><?= e($groups[$tab][0]) ?></h2></div>
  <div class="panel__body">
    <div class="form-grid">
      <?php foreach ($groups[$tab][2] as $key => $f): ?>
        <?php admin_field($key, $f, Settings::get($key)); ?>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit"><?= icon('check', 17) ?><span>Guardar cambios</span></button>
    <a class="btn btn--light" href="<?= e(base('')) ?>" target="_blank" rel="noopener"><?= icon('web', 17) ?><span>Ver el sitio</span></a>
  </div>
</form>
<?php admin_pickers(); admin_footer(); ?>
