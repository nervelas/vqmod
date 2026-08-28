<?php
/** @var array $restaurantes, $planes; string $q, $estado */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Restaurantes');
View::set('subtitulo', count($restaurantes) . ' restaurante(s) en la plataforma');

View::start('acciones');
?>
<a class="bt bt--oro" href="<?= e(url('super/restaurantes/nuevo')) ?>"><?= icon('plus') ?><span>Nuevo restaurante</span></a>
<?php View::stop(); ?>

<form class="filtros-barra" method="get" action="<?= e(url('super/restaurantes')) ?>">
  <div class="campo-p" style="flex:2 1 220px">
    <label for="buscarPanel">Buscar</label>
    <input type="search" id="buscarPanel" name="q" value="<?= e($q) ?>" placeholder="Nombre, dirección web o dominio">
  </div>
  <div class="campo-p">
    <label for="fEstado">Estado</label>
    <select id="fEstado" name="estado">
      <option value="">Todos</option>
      <?php foreach (['activo' => 'Activos', 'prueba' => 'En prueba', 'suspendido' => 'Suspendidos'] as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $estado === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="bt bt--linea" type="submit"><?= icon('filter') ?> Filtrar</button>
</form>

<div class="tarjeta-p tarjeta-p--plana">
  <?php if (!$restaurantes): ?>
    <div class="vacio-p"><?= icon('store', 'ico-lg') ?><h3>Sin resultados</h3>
      <p>Crea el primer restaurante de tu plataforma.</p>
      <a class="bt bt--oro" href="<?= e(url('super/restaurantes/nuevo')) ?>"><?= icon('plus') ?> Nuevo restaurante</a></div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Restaurante</th><th>Plan</th><th>Uso</th><th>Estado</th><th>Vence</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($restaurantes as $x): ?>
            <tr>
              <td>
                <strong><?= e((string)$x['nombre']) ?></strong>
                <?php if ((int)$x['demo'] === 1): ?><span class="insignia insignia--oro">Demo</span><?php endif; ?>
                <br><small class="mono" style="color:var(--p-tenue)">
                  <?= e($x['dominio'] ? (string)$x['dominio'] : '/r/' . $x['slug']) ?>
                </small>
              </td>
              <td><?= e((string)($x['plan'] ?? '—')) ?></td>
              <td style="font-size:13px;color:var(--p-suave)">
                <?= (int)$x['productos'] ?> platillos · <?= (int)$x['mesas'] ?> mesas · <?= (int)$x['usuarios'] ?> usuarios
              </td>
              <td>
                <select class="entrada" style="min-height:34px;padding:5px 8px;font-size:13px"
                        data-estado="<?= (int)$x['id'] ?>">
                  <?php foreach (['activo' => 'Activo', 'prueba' => 'Prueba', 'suspendido' => 'Suspendido'] as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $x['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="font-size:13px;color:var(--p-tenue)"><?= e($x['vence_el'] ? dt((string)$x['vence_el'], 'd/m/Y') : '—') ?></td>
              <td class="tabla__acciones">
                <a class="bt bt--sm bt--suave" href="<?= e(url('r/' . $x['slug'])) ?>" target="_blank" title="Ver menú"><?= icon('eye', 'ico-sm') ?></a>
                <a class="bt bt--sm bt--suave" href="<?= e(url('super/entrar/' . $x['id'])) ?>" title="Entrar al panel"><?= icon('login', 'ico-sm') ?></a>
                <a class="bt bt--sm bt--suave" href="<?= e(url('super/restaurantes/' . $x['id'])) ?>" title="Editar"><?= icon('edit', 'ico-sm') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('change', function (ev) {
  var s = ev.target;
  if (!s.matches('[data-estado]')) return;
  var M = window.MGPanel;
  M.pedir('super/restaurantes/estado', { id: Number(s.dataset.estado), estado: s.value }).then(function (r) {
    M.avisar(r.ok ? r.mensaje : r.error, r.ok ? 'ok' : 'error');
  });
});
</script>
<?php View::stop(); ?>
