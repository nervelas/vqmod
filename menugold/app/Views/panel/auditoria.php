<?php
/** @var array $registros, $filtros, $pag, $acciones */
use MenuGold\Core\View;
use MenuGold\Models\AuditLog;
View::set('titulo', 'Auditoría');
View::set('subtitulo', 'Quién cambió qué, cuándo y desde dónde');
?>
<form class="filtros-barra" method="get" action="<?= e(url('panel/auditoria')) ?>">
  <div class="campo-p" style="flex:2 1 200px">
    <label for="buscarPanel">Buscar</label>
    <input type="search" id="buscarPanel" name="q" value="<?= e($filtros['q']) ?>" placeholder="Usuario, acción o entidad">
  </div>
  <div class="campo-p">
    <label for="fAccion">Acción</label>
    <select id="fAccion" name="accion">
      <option value="">Todas</option>
      <?php foreach ($acciones as $a): ?>
        <option value="<?= e((string)$a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= e(AuditLog::etiqueta((string)$a)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="campo-p"><label for="fDesde">Desde</label><input type="date" id="fDesde" name="desde" value="<?= e($filtros['desde']) ?>"></div>
  <div class="campo-p"><label for="fHasta">Hasta</label><input type="date" id="fHasta" name="hasta" value="<?= e($filtros['hasta']) ?>"></div>
  <button class="bt bt--linea" type="submit"><?= icon('filter') ?> Filtrar</button>
  <a class="bt bt--suave" href="<?= e(url('panel/auditoria')) ?>"><?= icon('x') ?></a>
</form>

<div class="tarjeta-p tarjeta-p--plana">
  <?php if (!$registros): ?>
    <div class="vacio-p"><?= icon('history', 'ico-lg') ?><h3>Sin registros</h3><p>No hay movimientos con esos filtros.</p></div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach ($registros as $g): ?>
            <tr>
              <td style="white-space:nowrap;font-size:13px;color:var(--p-tenue)"><?= e(dt((string)$g['creado'])) ?></td>
              <td><strong><?= e((string)$g['usuario']) ?></strong></td>
              <td><span class="insignia"><?= e(AuditLog::etiqueta((string)$g['accion'])) ?></span></td>
              <td style="font-size:12.5px;color:var(--p-suave);max-width:420px">
                <?php
                $det = [];
                if (!empty($g['entidad'])) $det[] = $g['entidad'] . ($g['entidad_id'] ? ' #' . (int)$g['entidad_id'] : '');
                $cambios = is_array($g['despues']) ? $g['despues'] : [];
                foreach (array_slice($cambios, 0, 4, true) as $k => $v) {
                    if (is_array($v) && isset($v['antes'], $v['despues'])) {
                        $det[] = $k . ': ' . mb_strimwidth((string)(is_array($v['antes']) ? json_encode($v['antes']) : $v['antes']), 0, 24, '…')
                               . ' → ' . mb_strimwidth((string)(is_array($v['despues']) ? json_encode($v['despues']) : $v['despues']), 0, 24, '…');
                    } elseif (!is_array($v)) {
                        $det[] = $k . ': ' . mb_strimwidth((string)$v, 0, 30, '…');
                    }
                }
                echo e(implode(' · ', $det));
                ?>
              </td>
              <td class="mono" style="font-size:12px;color:var(--p-tenue)"><?= e((string)$g['ip']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ((int)$pag['paginas'] > 1): ?>
  <nav class="paginacion">
    <?php $base = $_GET; unset($base['pag']); for ($i = 1; $i <= (int)$pag['paginas']; $i++):
      if ($i > 3 && $i < (int)$pag['paginas'] - 2 && abs($i - (int)$pag['pagina']) > 2) { if ($i === 4) echo '<span>…</span>'; continue; } ?>
      <?php if ($i === (int)$pag['pagina']): ?><span class="actual"><?= $i ?></span>
      <?php else: ?><a href="<?= e(url('panel/auditoria', $base + ['pag' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
