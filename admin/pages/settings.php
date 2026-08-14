<?php
/** General site configuration. */
if (!defined('BASE_PATH')) { exit; }
$fields = [
  'branding' => ['logo'=>'img','logo_light'=>'img','favicon'=>'img','color_primary'=>'color','color_secondary'=>'color','color_dark'=>'color'],
  'general'  => ['site_name'=>'text','site_short_name'=>'text','tagline'=>'text','maintenance_mode'=>'bool'],
  'contact'  => ['phone'=>'text','phone_link'=>'text','email'=>'text','address'=>'text','map_embed'=>'text'],
  'social'   => ['facebook'=>'text','instagram'=>'text','tiktok'=>'text','youtube'=>'text'],
  'footer'   => ['footer_about'=>'area','copyright'=>'text'],
];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    foreach ($fields as $group=>$defs) {
      foreach ($defs as $key=>$type) {
        if ($type==='bool') { Settings::set($key, isset($_POST[$key])?'1':'0', $group); }
        else { Settings::set($key, post($key), $group); }
      }
    }
    Auth::log('settings_general','Actualizó configuración general');
    flash('success','Configuración guardada.');
    redirect('admin/index.php?page=settings');
}
admin_header('Configuración general');
$labels = [
 'logo'=>'Logotipo','logo_light'=>'Logotipo claro (footer)','favicon'=>'Favicon',
 'color_primary'=>'Color primario','color_secondary'=>'Color secundario','color_dark'=>'Color oscuro',
 'site_name'=>'Nombre de la institución','site_short_name'=>'Nombre corto','tagline'=>'Lema','maintenance_mode'=>'Modo mantenimiento',
 'phone'=>'Teléfono (visible)','phone_link'=>'Teléfono (marcado, sólo dígitos)','email'=>'Correo electrónico','address'=>'Dirección','map_embed'=>'URL de mapa embed',
 'facebook'=>'Facebook','instagram'=>'Instagram','tiktok'=>'TikTok','youtube'=>'YouTube',
 'footer_about'=>'Texto institucional (footer)','copyright'=>'Texto de copyright',
];
$titles = ['branding'=>'Identidad','general'=>'General','contact'=>'Contacto','social'=>'Redes sociales','footer'=>'Pie de página'];
?>
<form method="post" class="form">
  <?= Csrf::field() ?>
  <?php foreach ($fields as $group=>$defs): ?>
    <div class="card">
      <h2><?= e($titles[$group]) ?></h2>
      <div class="grid-2">
      <?php foreach ($defs as $key=>$type):
        $val = Settings::raw($key);
        if ($type==='img') { echo '<div class="form-group--full">'.media_field($key,$val,$labels[$key]).'</div>'; continue; }
      ?>
        <div class="form-group <?= $type==='area'?'form-group--full':'' ?>">
          <label><?= e($labels[$key]) ?></label>
          <?php if ($type==='bool'): ?>
            <label class="switch"><input type="checkbox" name="<?= $key ?>" <?= Settings::bool($key)?'checked':'' ?>> Activar</label>
          <?php elseif ($type==='color'): ?>
            <input type="color" name="<?= $key ?>" value="<?= e($val ?: '#0f5a3c') ?>" style="height:44px">
          <?php elseif ($type==='area'): ?>
            <textarea name="<?= $key ?>" rows="3"><?= e($val) ?></textarea>
          <?php else: ?>
            <input type="text" name="<?= $key ?>" value="<?= e($val) ?>">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="form-actions sticky-actions"><button class="btn btn--primary btn--lg">Guardar configuración</button></div>
</form>
<?php admin_footer(); ?>
