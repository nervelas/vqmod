<div class="fila-entre mb-3">
  <div class="fila envolver" style="gap:16px;font-size:.85rem">
    <span class="fila" style="gap:6px"><i style="width:12px;height:12px;border-radius:50%;background:var(--ok);display:block"></i> Solvente</span>
    <span class="fila" style="gap:6px"><i style="width:12px;height:12px;border-radius:50%;background:var(--grave);display:block"></i> Con saldo</span>
    <span class="fila" style="gap:6px"><i style="width:12px;height:12px;border-radius:50%;background:var(--piedra);display:block"></i> Desocupada</span>
  </div>
  <?php if (esRol('admin')): ?>
    <form method="post" enctype="multipart/form-data" class="fila" style="gap:8px">
      <?= csrf() ?>
      <input type="hidden" name="accion" value="plano">
      <label class="solo-lectores" for="plano-mapa">Imagen del plano del residencial</label>
      <input type="file" id="plano-mapa" name="plano" accept="image/*" required style="max-width:250px">
      <button class="btn btn-claro btn-sm" type="submit"><?= ico('subir', 15) ?> Subir plano</button>
    </form>
  <?php endif; ?>
</div>

<div class="tarjeta">
  <div class="tarjeta-cuerpo">
    <?php if ($plano === ''): ?>
      <div class="aviso-caja info mb-3"><?= ico('info', 20) ?>
        <div>Suba la imagen del plano del residencial para ubicar cada vivienda sobre él.
          Mientras tanto se muestra una cuadrícula con la posición asignada a cada casa.</div>
      </div>
    <?php endif; ?>

    <div class="mapa-caja" id="mapa" style="<?= $plano === '' ? 'min-height:520px;background:repeating-linear-gradient(45deg,var(--panel-2),var(--panel-2) 12px,var(--fondo-2) 12px,var(--fondo-2) 24px)' : '' ?>">
      <?php if ($plano !== ''): ?>
        <img src="<?= e(subida($plano, 'casas')) ?>" alt="Plano del residencial">
      <?php endif; ?>
      <?php foreach ($casas as $c):
        $x = $c['mapa_x'] !== null ? (float) $c['mapa_x'] : null;
        $y = $c['mapa_y'] !== null ? (float) $c['mapa_y'] : null;
        if ($x === null || $y === null) { continue; }
        $clase = (float) $c['saldo'] > 0.009 ? 'moroso' : ((string) $c['estado'] === 'desocupada' ? 'vacia' : '');
      ?>
        <a class="mapa-punto <?= $clase ?>" style="left:<?= $x ?>%;top:<?= $y ?>%"
           href="<?= e(url('/admin/casas/' . (int) $c['id'])) ?>"
           title="<?= e($c['codigo']) ?> — <?= e((float) $c['saldo'] > 0.009 ? 'saldo ' . q((float) $c['saldo']) : 'solvente') ?>">
          <?= e(preg_replace('/^[A-Za-z]+-?/', '', (string) $c['codigo'])) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <p class="ayuda mt-2">
      La posición de cada vivienda se define en su ficha (campos “Posición en el mapa X e Y”, en porcentaje).
      Haga clic en un punto para abrir la vivienda.
    </p>
  </div>
</div>
