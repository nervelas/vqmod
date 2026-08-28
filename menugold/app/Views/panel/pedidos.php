<?php
/** @var array $pedidos, $filtros, $pag, $resumen */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;
View::set('titulo', 'Pedidos');
View::set('subtitulo', (int)$pag['total'] . ' pedido(s) · hoy: ' . (int)$resumen['n'] . ' por ' . money($resumen['t'], (string)$r['simbolo']));
$s = (string)($r['simbolo'] ?? 'Q');
$colores = ['nuevo'=>'aviso','preparando'=>'info','listo'=>'exito','entregado'=>'exito','pagado'=>'exito','anulado'=>'peligro'];
?>
<form class="filtros-barra" method="get" action="<?= e(url('panel/pedidos')) ?>">
  <div class="campo-p" style="flex:2 1 200px">
    <label for="buscarPanel">Buscar</label>
    <input type="search" id="buscarPanel" name="q" value="<?= e($filtros['q']) ?>" placeholder="Código, cliente, teléfono o mesa">
  </div>
  <div class="campo-p">
    <label for="fEstado">Estado</label>
    <select id="fEstado" name="estado">
      <option value="">Todos</option>
      <?php foreach (Order::ETIQUETA_ESTADO as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="campo-p">
    <label for="fModo">Origen</label>
    <select id="fModo" name="modo">
      <option value="">Todos</option>
      <?php foreach (['mesa','llevar','delivery','whatsapp'] as $m): ?>
        <option value="<?= e($m) ?>" <?= $filtros['modo'] === $m ? 'selected' : '' ?>><?= e(Order::etiquetaModo($m)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="campo-p"><label for="fDesde">Desde</label><input type="date" id="fDesde" name="desde" value="<?= e($filtros['desde']) ?>"></div>
  <div class="campo-p"><label for="fHasta">Hasta</label><input type="date" id="fHasta" name="hasta" value="<?= e($filtros['hasta']) ?>"></div>
  <button class="bt bt--linea" type="submit"><?= icon('filter') ?> Filtrar</button>
  <a class="bt bt--suave" href="<?= e(url('panel/pedidos')) ?>"><?= icon('x') ?></a>
</form>

<div class="tarjeta-p tarjeta-p--plana">
  <?php if (!$pedidos): ?>
    <div class="vacio-p"><?= icon('receipt', 'ico-lg') ?><h3>Sin pedidos</h3><p>No hay pedidos que coincidan con esos filtros.</p></div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Código</th><th>Origen</th><th>Cliente</th><th>Estado</th><th>Fecha</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pedidos as $o): ?>
            <tr>
              <td class="mono"><strong><?= e((string)$o['codigo']) ?></strong></td>
              <td><?= e($o['mesa_nombre'] ?: Order::etiquetaModo((string)$o['modo'])) ?></td>
              <td><?= e($o['cliente_nombre'] ?: '—') ?></td>
              <td><span class="insignia insignia--<?= e($colores[$o['estado']] ?? '') ?>"><?= e(Order::ETIQUETA_ESTADO[$o['estado']] ?? '') ?></span></td>
              <td style="color:var(--p-tenue);font-size:13px"><?= e(dt((string)$o['creado'])) ?></td>
              <td class="num"><strong><?= e(money($o['total'], $s)) ?></strong></td>
              <td class="tabla__acciones">
                <a class="bt bt--sm bt--suave" href="<?= e(url('panel/pedidos/' . $o['id'])) ?>"><?= icon('eye', 'ico-sm') ?></a>
                <a class="bt bt--sm bt--suave" href="<?= e(url('panel/mesero/ticket/' . $o['id'])) ?>" target="_blank"><?= icon('printer', 'ico-sm') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ((int)$pag['paginas'] > 1): ?>
  <nav class="paginacion" aria-label="Paginación">
    <?php
    $base = $_GET; unset($base['pag']);
    for ($i = 1; $i <= (int)$pag['paginas']; $i++):
      if ($i > 3 && $i < (int)$pag['paginas'] - 2 && abs($i - (int)$pag['pagina']) > 2) {
        if ($i === 4) echo '<span>…</span>';
        continue;
      }
    ?>
      <?php if ($i === (int)$pag['pagina']): ?>
        <span class="actual"><?= $i ?></span>
      <?php else: ?>
        <a href="<?= e(url('panel/pedidos', $base + ['pag' => $i])) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
