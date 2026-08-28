<section class="garita-panel" style="grid-column:1/-1">
  <div class="fila-entre">
    <h2 style="margin:0"><?= ico('usuarios', 20) ?> Dentro del residencial (<?= count($adentro) ?>)</h2>
    <a class="btn btn-oro" href="<?= e(url('/garita/ingreso')) ?>"><?= ico('mas', 17) ?> Nuevo ingreso</a>
  </div>
  <?php if ($adentro === []): ?>
    <p style="color:color-mix(in srgb, #fff 76%, transparent);margin-top:14px">No hay visitas dentro en este momento.</p>
  <?php else: ?>
    <div class="garita-lista mt-2" style="max-height:none">
      <?php foreach ($adentro as $v): ?>
        <div class="garita-item">
          <span class="avatar"><?= e(iniciales((string) $v['visitante'])) ?></span>
          <div class="crecer">
            <b><?= e($v['visitante']) ?></b>
            <small>
              <?= e($v['casa'] ?? 'Sin destino') ?> · entró <?= e(hora((string) $v['entrada'])) ?>
              <?= !empty($v['placa']) ? ' · ' . e($v['placa']) : '' ?>
              <?= (int) $v['personas'] > 1 ? ' · ' . (int) $v['personas'] . ' personas' : '' ?>
            </small>
          </div>
          <form method="post" action="<?= e(url('/garita/salida/' . (int) $v['id'])) ?>">
            <?= csrf() ?>
            <button class="btn btn-oro" type="submit"><?= ico('salir', 17) ?> Registrar salida</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="garita-panel" style="grid-column:1/-1">
  <h2><?= ico('lista', 20) ?> Últimos movimientos</h2>
  <div class="tabla-caja">
    <table class="tabla apilar">
      <thead><tr><th>Visitante</th><th>Casa</th><th>Tipo</th><th>Placa</th><th>Entrada</th><th class="c">Salida</th></tr></thead>
      <tbody>
        <?php foreach ($recientes as $v): ?>
          <tr>
            <td data-et="Visitante" class="fuerte"><?= e(recortar((string) $v['visitante'], 30)) ?></td>
            <td data-et="Casa"><span class="texto-acento"><?= e($v['casa'] ?? '—') ?></span></td>
            <td data-et="Tipo" class="texto-2"><?= e(ucfirst((string) $v['tipo'])) ?></td>
            <td data-et="Placa"><?= !empty($v['placa']) ? '<span class="placa">' . e($v['placa']) . '</span>' : '<span class="texto-3">—</span>' ?></td>
            <td data-et="Entrada" class="texto-2"><?= e(fechahora((string) $v['entrada'])) ?></td>
            <td data-et="Salida" class="c"><?= $v['salida'] ? e(hora((string) $v['salida'])) : '<span class="chip info">Adentro</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<style<?= nonce() ?>>
.garita table.tabla thead th { background: rgba(255,255,255,.06); color: color-mix(in srgb, #fff 76%, transparent); border-bottom-color: rgba(255,255,255,.1); }
.garita table.tabla tbody td { border-bottom-color: rgba(255,255,255,.08); }
.garita table.tabla tbody tr:hover { background: rgba(255,255,255,.05); }
</style>
