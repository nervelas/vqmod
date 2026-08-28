<section class="garita-panel">
  <h2><?= ico('sirena', 20) ?> Números de emergencia</h2>
  <div class="garita-lista">
    <?php foreach ($emergencia as $c): ?>
      <a class="garita-item" href="tel:<?= e(preg_replace('/\D+/', '', (string) $c['telefono'])) ?>" style="text-decoration:none;color:inherit">
        <span style="color:var(--arcilla-3)"><?= ico('telefono', 22) ?></span>
        <div class="crecer">
          <b><?= e($c['nombre']) ?></b>
          <small><?= e($c['tipo'] ?? '') ?></small>
        </div>
        <b style="color:var(--arcilla-3);font-size:1.1rem"><?= e($c['telefono']) ?></b>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="garita-panel">
  <h2><?= ico('casa', 20) ?> Directorio de viviendas</h2>
  <div class="campo">
    <label for="buscar-casa" class="solo-lectores">Buscar vivienda</label>
    <input type="search" id="buscar-casa" aria-label="Buscar casa o residente" placeholder="Buscar casa o residente…" data-filtra="#tabla-directorio">
  </div>
  <div class="garita-lista" style="max-height:520px">
    <table class="tabla" id="tabla-directorio" style="width:100%">
      <tbody>
        <?php foreach ($casas as $c): ?>
          <tr>
            <td style="border:0;padding:9px 6px">
              <b style="color:var(--arcilla-3)"><?= e($c['codigo']) ?></b>
              <?php if ((int) $c['restringida'] === 1): ?><span class="chip grave">Restringida</span><?php endif; ?>
              <div style="color:color-mix(in srgb, #fff 80%, transparent);font-size:.82rem"><?= e(recortar((string) $c['residente'], 30) ?: 'Sin residente') ?></div>
            </td>
            <td style="border:0;padding:9px 6px;text-align:right">
              <?php if (!empty($c['telefono'])): ?>
                <a class="btn btn-sm btn-fantasma" style="color:#EFF3EF;border-color:rgba(255,255,255,.2)"
                   href="tel:<?= e(preg_replace('/\D+/', '', (string) $c['telefono'])) ?>"><?= ico('telefono', 15) ?> <?= e($c['telefono']) ?></a>
              <?php else: ?><span style="color:color-mix(in srgb, #fff 70%, transparent)">Sin teléfono</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
