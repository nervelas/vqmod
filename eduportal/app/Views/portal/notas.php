<div class="pagina-cab">
  <div><h1>Calificaciones</h1>
    <p class="pagina-cab__sub"><?= e(App\Models\Alumno::nombre($alumno)) ?> · promedio general
      <strong class="<?= e(nota_clase((float)$boleta['promedio'])) ?>"><?= e(number_format((float)$boleta['promedio'], 2)) ?></strong></p></div>
  <div class="acciones">
    <a href="<?= e(url('boleta/' . (int)$alumno['id'])) ?>" class="btn" target="_blank" rel="noopener">
      <?= icono('descargar', 17) ?> Descargar boleta</a>
  </div>
</div>

<div class="aviso aviso--info"><?= icono('notas', 18) ?>
  <span>La nota mínima de promoción es <strong><?= e(number_format((float)$minima, 0)) ?></strong> puntos.</span></div>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Materia</th>
      <?php foreach ($boleta['periodos'] as $p): ?><th class="cen"><?= e($p['nombre']) ?></th><?php endforeach; ?>
      <th class="cen">Promedio</th></tr></thead>
    <tbody>
    <?php foreach ($boleta['materias'] as $m): ?>
      <tr>
        <td><strong><?= e($m['materia']) ?></strong><div class="xs txt-3"><?= e($m['docente'] ?? '') ?></div></td>
        <?php foreach ($boleta['periodos'] as $p): $n = $m['periodos'][(int)$p['id']] ?? null; ?>
          <td class="cen <?= $n && $n['total'] !== null ? e(nota_clase((float)$n['total'])) : '' ?>">
            <?= $n && $n['total'] !== null ? e(number_format((float)$n['total'], 2)) : '—' ?>
            <?php if ($n && !empty($n['comentario'])): ?>
              <div class="xs txt-3"><?= e(recorta((string)$n['comentario'], 40)) ?></div>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td class="cen negrita <?= $m['promedio'] !== null ? e(nota_clase((float)$m['promedio'])) : '' ?>">
          <?= $m['promedio'] !== null ? e(number_format((float)$m['promedio'], 2)) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($boleta['materias'] === []): ?>
      <tr><td colspan="<?= count($boleta['periodos']) + 2 ?>" class="tabla__vacio">
        <?= icono('notas', 40) ?><p>Aún no hay calificaciones publicadas.</p></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
