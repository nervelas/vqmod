<div class="pagina-cab">
  <div><h1>Ficha del alumno</h1><p class="pagina-cab__sub">Ciclo <?= e($ciclo['nombre'] ?? '') ?></p></div>
</div>

<div class="split">
  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Datos generales</h2></div>
      <dl class="sm" style="margin:0">
        <div class="flex flex--sep" style="padding:6px 0;border-bottom:1px solid var(--borde)">
          <dt class="txt-3">Nombre</dt><dd style="margin:0"><strong><?= e(App\Models\Alumno::nombre($alumno)) ?></strong></dd></div>
        <div class="flex flex--sep" style="padding:6px 0;border-bottom:1px solid var(--borde)">
          <dt class="txt-3">Código</dt><dd style="margin:0"><?= e($alumno['codigo']) ?></dd></div>
        <div class="flex flex--sep" style="padding:6px 0;border-bottom:1px solid var(--borde)">
          <dt class="txt-3">Grado</dt><dd style="margin:0"><?= e($alumno['grupo'] ?? '—') ?></dd></div>
        <div class="flex flex--sep" style="padding:6px 0;border-bottom:1px solid var(--borde)">
          <dt class="txt-3">Nacimiento</dt><dd style="margin:0"><?= e(fecha($alumno['fecha_nacimiento'] ?? '') ?: '—') ?></dd></div>
        <div class="flex flex--sep" style="padding:6px 0">
          <dt class="txt-3">Estado</dt><dd style="margin:0">
            <span class="badge badge--<?= e(estado_badge((string)$alumno['estado'])) ?>"><?= e(ucfirst((string)$alumno['estado'])) ?></span></dd></div>
      </dl>
      <?php if (!empty($alumno['alergias'])): ?>
        <div class="aviso aviso--warn mt-3"><?= icono('escudo', 18) ?><span><?= e($alumno['alergias']) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Encargados registrados</h2></div>
      <?php foreach ($encargados as $en): ?>
        <div class="cargo-fila mb-2">
          <div class="cargo-fila__desc">
            <strong><?= e($en['nombre']) ?></strong>
            <?php if ((int)$en['principal'] === 1): ?><span class="badge badge--oro">Principal</span><?php endif; ?>
            <div class="sm txt-2"><?= e($en['parentesco'] ?? '') ?>
              <?php if (!empty($en['telefono'])): ?> · <?= e($en['telefono']) ?><?php endif; ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ($encargados === []): ?><p class="sm txt-3">Sin encargados registrados.</p><?php endif; ?>
      <p class="sm txt-3 mt-3">Para corregir estos datos, comuníquese con la secretaría del colegio.</p>
    </div>
  </div>

  <div class="col">
    <div class="tarjeta cen">
      <?php if (!empty($alumno['foto'])): ?>
        <img class="avatar avatar--xl" style="margin:0 auto" src="<?= e(archivo_url($alumno['foto'])) ?>" alt="">
      <?php else: ?>
        <span class="avatar avatar--xl iniciales" style="margin:0 auto;font-size:2rem">
          <?= e(mb_strtoupper(mb_substr($alumno['nombres'], 0, 1) . mb_substr($alumno['apellidos'], 0, 1))) ?></span>
      <?php endif; ?>
      <img class="mt-3" style="width:130px;margin:0 auto;border-radius:10px"
           src="<?= e(url('alumnos/' . (int)$alumno['id'] . '/qr')) ?>" alt="Código QR del carné" loading="lazy">
      <p class="mt-3"><a class="btn btn--linea btn--sm" target="_blank" rel="noopener"
         href="<?= e(url('alumnos/' . (int)$alumno['id'] . '/carne')) ?>"><?= icono('descargar', 15) ?> Descargar carné</a></p>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Historial académico</h2></div>
      <?php if ($historial === []): ?>
        <p class="sm txt-3">Sin inscripciones registradas.</p>
      <?php else: ?>
        <div class="pila sm">
          <?php foreach ($historial as $h): ?>
            <div class="flex flex--sep">
              <span><strong><?= e($h['ciclo']) ?></strong> · <?= e($h['grado'] . ' ' . $h['seccion']) ?></span>
              <span class="badge badge--<?= e(estado_badge((string)$h['estado'])) ?>"><?= e(ucfirst((string)$h['estado'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
