<div class="pagina-cab">
  <div><h1>Cuadro de honor</h1><p class="pagina-cab__sub">Mejores promedios del ciclo <?= e($ciclo['nombre'] ?? '') ?></p></div>
</div>
<?php if ($grupos === []): ?>
  <div class="tarjeta vacio"><?= icono('estrella', 44) ?><p>Aún no hay calificaciones suficientes para generar el cuadro de honor.</p></div>
<?php else: ?>
  <div class="rejilla rejilla--3">
    <?php foreach ($grupos as $grupo => $alumnos): ?>
      <div class="tarjeta">
        <div class="tarjeta__cab"><h3><?= e($grupo) ?></h3></div>
        <ol style="list-style:none;padding:0;margin:0">
          <?php foreach ($alumnos as $i => $a): ?>
            <li class="flex flex--sep" style="padding:9px 0;border-bottom:1px solid var(--borde)">
              <span class="flex">
                <span class="badge badge--<?= $i === 0 ? 'oro' : 'mute' ?>"><?= $i + 1 ?></span>
                <a href="<?= e(url('alumnos/' . (int)$a['id'])) ?>"><?= e(trim($a['nombres'] . ' ' . $a['apellidos'])) ?></a>
              </span>
              <strong class="nota-alta"><?= e(number_format((float)$a['promedio'], 2)) ?></strong>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
