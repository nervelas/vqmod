<?php page('barActions', '<a class="btn btn--accent btn--sm" href="' . e(url('/super/empresas/nueva')) . '">Nueva empresa</a>'); ?>
<div class="card">
  <div class="card__head"><span class="secnum">01/</span><h2><?= e(count($rows)) ?> empresas</h2></div>
  <div class="card__body card__body--flush tablescroll">
    <table class="datatable" style="border:0;border-radius:0">
      <caption class="sr-only">Empresas registradas</caption>
      <thead><tr><th scope="col">Empresa</th><th scope="col">Plan</th><th scope="col">Estado</th><th scope="col">Vence</th>
        <th scope="col" class="num">Usuarios</th><th scope="col" class="num">Productos</th><th scope="col" class="num">Cotiz.</th><th scope="col"></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td>
              <span class="flex" style="gap:10px">
                <?php if ($c['logo']): ?><img class="thumb" src="<?= e(upload($c['logo'])) ?>" alt="" aria-hidden="true" width="46" height="36"><?php endif; ?>
                <span><a href="<?= e(url('/super/empresas/' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a>
                  <br><span class="small muted">/e/<?= e($c['slug']) ?><?= $c['domain'] ? ' · ' . e($c['domain']) : '' ?></span></span>
              </span>
            </td>
            <td class="small"><?= e($c['plan_name'] ?: '—') ?></td>
            <td><span class="badge<?= $c['status'] === 'activa' ? ' badge--ok' : ($c['status'] === 'suspendida' ? ' badge--bad' : '') ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
            <td class="small"><?= $c['expires_at'] ? e(fechaCorta((string) $c['expires_at'])) : '—' ?></td>
            <td class="num"><?= e((int) $c['n_users']) ?></td>
            <td class="num"><?= e((int) $c['n_products']) ?></td>
            <td class="num"><?= e((int) $c['n_quotes']) ?></td>
            <td class="nowrap">
              <a class="btn btn--ghost btn--xs" href="<?= e(url('/e/' . $c['slug'])) ?>" target="_blank" rel="noopener">Sitio</a>
              <a class="btn btn--ghost btn--xs" href="<?= e(url('/super/empresas/' . $c['id'] . '/entrar')) ?>">Entrar</a>
              <a class="btn btn--ghost btn--xs" href="<?= e(url('/super/empresas/' . $c['id'])) ?>">Editar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
