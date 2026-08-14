<?php
/** WhatsApp configuration. */
if (!defined('BASE_PATH')) { exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    Settings::set('whatsapp_enabled', isset($_POST['whatsapp_enabled']) ? '1' : '0', 'contact');
    Settings::set('whatsapp_number', preg_replace('/[^0-9]/','',post('whatsapp_number')), 'contact');
    Settings::set('whatsapp_message', post('whatsapp_message'), 'contact');
    Settings::set('whatsapp_button_text', post('whatsapp_button_text'), 'contact');
    Auth::log('settings_whatsapp','Actualizó WhatsApp');
    flash('success','Configuración de WhatsApp guardada.');
    redirect('admin/index.php?page=whatsapp');
}
admin_header('WhatsApp');
$num = Settings::get('whatsapp_number');
?>
<div class="card" style="max-width:640px">
  <h2>Botón de WhatsApp</h2>
  <p class="muted">El botón flotante aparece en todo el sitio y abre una conversación con el número indicado.</p>
  <form method="post" class="form">
    <?= Csrf::field() ?>
    <label class="switch"><input type="checkbox" name="whatsapp_enabled" <?= Settings::bool('whatsapp_enabled',true)?'checked':'' ?>> Mostrar botón de WhatsApp</label>
    <div class="form-group"><label>Número (con código de país, sólo dígitos)</label><input type="text" name="whatsapp_number" value="<?= e($num) ?>" placeholder="50222775656"></div>
    <div class="form-group"><label>Texto del botón</label><input type="text" name="whatsapp_button_text" value="<?= e(Settings::get('whatsapp_button_text')) ?>"></div>
    <div class="form-group"><label>Mensaje predeterminado</label><textarea name="whatsapp_message" rows="3"><?= e(Settings::get('whatsapp_message')) ?></textarea></div>
    <?php if ($num): ?><p><a class="btn btn--whatsapp btn--sm" target="_blank" href="<?= e(whatsapp_link($num, Settings::get('whatsapp_message'))) ?>">Probar enlace ↗</a></p><?php endif; ?>
    <div class="form-actions"><button class="btn btn--primary">Guardar</button></div>
  </form>
</div>
<?php admin_footer(); ?>
