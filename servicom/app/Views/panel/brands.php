<?php $e = $edit; ?>
<div class="cols cols--sidebar">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2><?= e(count($brands)) ?> marcas</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <?php if (!$brands): ?>
        <p class="muted" style="padding:36px;text-align:center;margin:0">Aún no registra marcas.</p>
      <?php else: ?>
        <table class="datatable" style="border:0;border-radius:0">
          <caption class="sr-only">Marcas</caption>
          <thead><tr><th scope="col">Logo</th><th scope="col">Marca</th><th scope="col" class="num">Productos</th><th scope="col">Estado</th><th scope="col"></th></tr></thead>
          <tbody>
            <?php foreach ($brands as $b): ?>
              <tr>
                <td style="width:70px"><?php if ($b['logo']): ?><img class="thumb" src="<?= e(upload($b['logo'])) ?>" alt="" aria-hidden="true" width="46" height="36"><?php endif; ?></td>
                <td><strong><?= e($b['name']) ?></strong><?php if ($b['website']): ?><br><span class="small muted"><?= e($b['website']) ?></span><?php endif; ?></td>
                <td class="num"><?= e((int) $b['product_count']) ?></td>
                <td><span class="badge<?= $b['active'] ? ' badge--ok' : '' ?>"><?= $b['active'] ? 'Activa' : 'Oculta' ?></span></td>
                <td class="nowrap">
                  <a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/marcas?editar=' . $b['id'])) ?>">Editar</a>
                  <?php if (\App\Core\Auth::isAdmin()): ?><button class="btn btn--ghost btn--xs" type="submit" form="delb<?= e($b['id']) ?>">Eliminar</button><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <form class="card" method="post" action="<?= e(url('/panel/marcas')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= e($e['id']) ?>"><?php endif; ?>
    <div class="card__head"><span class="secnum">02/</span><h2><?= $e ? 'Editar marca' : 'Nueva marca' ?></h2></div>
    <div class="card__body">
      <div class="field"><label for="name">Nombre *</label><input class="input" id="name" name="name" maxlength="120" required value="<?= e($e['name'] ?? '') ?>"></div>
      <div class="field"><label for="website">Sitio del fabricante</label><input class="input" id="website" name="website" type="url" maxlength="190" value="<?= e($e['website'] ?? '') ?>" placeholder="https://"></div>
      <div class="field"><label for="logo">Logo</label><input class="input" id="logo" name="logo" type="file" accept="image/*"></div>
      <div class="field"><label for="sort">Orden</label><input class="input" id="sort" name="sort" type="number" value="<?= e((int) ($e['sort'] ?? 0)) ?>"></div>
      <label class="check"><input type="checkbox" name="active" value="1"<?= (!$e || $e['active']) ? ' checked' : '' ?>><span>Mostrar en el sitio</span></label>
      <div class="flex" style="gap:8px">
        <button class="btn btn--accent" type="submit"><?= $e ? 'Guardar' : 'Crear marca' ?></button>
        <?php if ($e): ?><a class="btn btn--ghost" href="<?= e(url('/panel/marcas')) ?>">Cancelar</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>
<?php foreach ($brands as $b): ?>
  <form id="delb<?= e($b['id']) ?>" method="post" action="<?= e(url('/panel/marcas/' . $b['id'] . '/eliminar')) ?>" class="hide" data-confirm="¿Eliminar la marca «<?= e($b['name']) ?>»?"><?= csrf_field() ?></form>
<?php endforeach; ?>
