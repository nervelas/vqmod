<div class="pagina-cab">
  <div><h1>Bitácora de auditoría</h1><p class="pagina-cab__sub"><?= number_format((float)$total) ?> registros · quién, qué, cuándo y desde dónde</p></div>
</div>

<form method="get" class="filtros">
  <div class="campo"><label for="b-q">Buscar</label>
    <input type="search" id="b-q" name="q" value="<?= e($q) ?>" placeholder="Acción, detalle o usuario" data-buscar></div>
  <button type="submit" class="btn btn--linea"><?= icono('buscar', 17) ?> Buscar</button>
</form>

<div class="tabla-env tabla-env--alta" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr>
        <td class="sm"><?= e(fecha_hora((string)$f['creado_en'])) ?></td>
        <td class="sm"><?= e($f['usuario'] ?? 'Sistema') ?></td>
        <td><span class="badge badge--mute"><?= e($f['accion']) ?></span></td>
        <td class="sm txt-2"><?= e(recorta($f['detalle'] ?? '', 90)) ?>
          <?php if (!empty($f['entidad'])): ?><div class="xs txt-3"><?= e($f['entidad']) ?> #<?= (int)$f['entidad_id'] ?></div><?php endif; ?></td>
        <td class="sm txt-3"><?= e($f['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($filas === []): ?><tr><td colspan="5" class="tabla__vacio">Sin registros.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= App\Core\View::partial('partials/paginacion', ['total' => $total, 'pagina' => $pagina, 'porPagina' => $porPagina]) ?>
