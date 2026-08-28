<form method="get" class="fila envolver mb-3" style="gap:10px">
  <div class="entrada-icono" style="max-width:280px">
    <?= ico('buscar', 18) ?>
    <input type="search" name="buscar" aria-label="Buscar por usuario, acción o detalle" value="<?= e($filtros['buscar']) ?>" placeholder="Usuario, acción o detalle">
  </div>
  <select aria-label="Filtrar por acción" name="accion" data-auto-enviar style="max-width:230px">
    <option value="">Todas las acciones</option>
    <?php foreach ($acciones as $a): ?>
      <option value="<?= e($a['accion']) ?>" <?= $filtros['accion'] === $a['accion'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', (string) $a['accion'])) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="desde" value="<?= e($filtros['desde']) ?>" style="max-width:170px" aria-label="Desde">
  <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
</form>

<div class="aviso-caja info mb-3">
  <?= ico('escudo', 20) ?>
  <div>Toda operación sensible queda registrada con usuario, dirección IP y fecha: pagos, anulaciones,
    cambios de residente, accesos en garita y modificaciones de configuración.</div>
</div>

<div class="tarjeta">
  <div class="tabla-caja">
    <table class="tabla apilar">
      <thead><tr><th class="c">Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($registros as $r): ?>
          <tr>
            <td data-et="Fecha" class="c texto-3 nowrap"><?= e(fechahora((string) $r['creado_en'])) ?></td>
            <td data-et="Usuario"><b><?= e($r['usuario'] ?? 'sistema') ?></b></td>
            <td data-et="Acción"><span class="chip neutro"><?= e(str_replace('_', ' ', (string) $r['accion'])) ?></span></td>
            <td data-et="Detalle" class="texto-2" style="font-size:.87rem">
              <?= e(recortar((string) $r['detalle'], 90)) ?>
              <?php if (!empty($r['entidad'])): ?>
                <div class="meta texto-3"><?= e($r['entidad']) ?><?= $r['entidad_id'] ? ' #' . (int) $r['entidad_id'] : '' ?></div>
              <?php endif; ?>
            </td>
            <td data-et="IP" class="texto-3" style="font-size:.82rem"><?= e($r['ip'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($registros === []): ?>
          <tr><td colspan="5" class="centrado texto-3" style="padding:34px">No hay registros con esos filtros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
