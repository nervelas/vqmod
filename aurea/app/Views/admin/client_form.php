<form method="post" action="<?= e(url('/admin/clientes/guardar')) ?>" class="card form-wide"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
  <div class="row row-2"><div class="field"><label class="req" for="n"><?= e(__('Nombre')) ?></label><input id="n" name="name" required value="<?= e($c['name']) ?>"></div>
    <div class="field"><label class="req" for="p"><?= e(__('Teléfono / WhatsApp')) ?></label><div class="phone-row"><input name="cc" value="<?= e($c['phone_cc']) ?>" aria-label="<?= e(__('Código de país')) ?>"><input id="p" name="phone" required value="<?= e($c['phone']) ?>"></div></div></div>
  <div class="row row-2"><div class="field"><label for="e"><?= e(__('Correo')) ?></label><input id="e" name="email" type="email" value="<?= e($c['email']) ?>"></div><div class="field"><label for="nit">NIT</label><input id="nit" name="nit" value="<?= e($c['nit']) ?>"><div class="help"><?= e(__('Solo para datos de facturación en el recibo. La facturación FEL no está incluida.')) ?></div></div></div>
  <div class="field"><label for="t"><?= e(__('Etiquetas (separadas por coma)')) ?></label><input id="t" name="tags" value="<?= e($c['tags']) ?>"></div>
  <div class="field"><label for="no"><?= e(__('Notas internas (privadas)')) ?></label><textarea id="no" name="notes"><?= e($c['notes']) ?></textarea></div>
  <?php if (\Aurea\Core\Auth::role() !== 'professional'): ?><label class="check"><input type="checkbox" name="blocked" value="1" <?= chk($c['blocked']) ?>><span><?= e(__('Bloquear reservas en línea de este cliente')) ?></span></label><?php endif; ?>
  <button class="btn btn-gold" type="submit"><?= e(__('Guardar')) ?></button> <a class="btn btn-ghost" href="<?= e(url('/admin/clientes')) ?>"><?= e(__('Cancelar')) ?></a>
</form>
