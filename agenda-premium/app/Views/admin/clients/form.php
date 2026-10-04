<?php /** @var ?array $client @var array $v @var string $error @var array $zones @var string $bizTz */
$action = $client ? '/admin/clientes/' . (int) $client['id'] . '/editar' : '/admin/clientes/nuevo';
?>
<div class="page page-narrow">
  <div class="page-head"><div>
    <p class="crumbs muted"><a href="<?= e(url('/admin/clientes')) ?>">Clientes</a> / <?= $client ? e($client['name']) : 'Nuevo' ?></p>
    <h1 class="page-title serif"><?= $client ? 'Editar ficha' : 'Nuevo cliente' ?></h1>
  </div></div>
  <?php if ($error !== '') : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="card" method="post" action="<?= e(url($action)) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card-body stack">
      <div class="form-grid">
        <div class="field"><label for="name">Nombre completo</label><input class="input" id="name" name="name" value="<?= e($v['name'] ?? '') ?>" maxlength="160" required autofocus></div>
        <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" type="tel" value="<?= e(($v['phone'] ?? '') !== '' ? \App\Core\Str::phoneDisplay((string) $v['phone']) : '') ?>" inputmode="tel" placeholder="5555 1234" maxlength="30"></div>
        <div class="field"><label for="email">Correo electrónico</label><input class="input" id="email" name="email" type="email" value="<?= e($v['email'] ?? '') ?>" maxlength="190"></div>
        <div class="field"><label for="nit">NIT <span class="muted">(opcional)</span></label><input class="input" id="nit" name="nit" value="<?= e($v['nit'] ?? '') ?>" maxlength="30"></div>
        <div class="field"><label for="tags">Etiquetas</label><input class="input" id="tags" name="tags" value="<?= e($v['tags'] ?? '') ?>" maxlength="255" placeholder="vip, recurrente"><p class="hint">Sepáralas con comas.</p></div>
        <div class="field"><label for="source">Origen</label><input class="input" id="source" name="source" value="<?= e($v['source'] ?? '') ?>" maxlength="190" placeholder="Recomendación, Instagram…"></div>
        <div class="field"><label for="timezone">Zona horaria</label>
          <select class="select" id="timezone" name="timezone"><option value="">La del negocio (<?= e($bizTz) ?>)</option>
            <?php foreach ($zones as $z) : ?><option value="<?= e($z) ?>"<?= sel($v['timezone'] ?? '', $z) ?>><?= e($z) ?></option><?php endforeach; ?>
          </select></div>
      </div>
    </div>
    <div class="card-foot form-actions">
      <a class="btn btn-ghost" href="<?= e(url($client ? '/admin/clientes/' . (int) $client['id'] : '/admin/clientes')) ?>">Cancelar</a>
      <button class="btn btn-gold" type="submit"><?= icon('check') ?>Guardar</button>
    </div>
  </form>
</div>
