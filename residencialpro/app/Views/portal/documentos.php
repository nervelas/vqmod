<div class="rejilla rejilla-2">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Documentos del residencial</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <ul class="lista-limpia">
        <?php if ($reglamento !== ''): ?>
          <li class="item-lista">
            <span style="color:var(--arcilla)"><?= ico('libro', 20) ?></span>
            <div class="crecer"><b>Reglamento interno</b><div class="meta">Normas de convivencia del residencial</div></div>
            <a class="btn btn-sm btn-claro" href="<?= e(url('/archivo/documentos/' . $reglamento)) ?>" target="_blank" rel="noopener">
              <?= ico('descargar', 15) ?> Ver
            </a>
          </li>
        <?php endif; ?>
        <?php foreach ($documentos as $d): ?>
          <li class="item-lista">
            <span style="color:var(--arcilla)"><?= ico('archivo', 20) ?></span>
            <div class="crecer"><b><?= e($d['titulo']) ?></b><div class="meta"><?= e(fecha((string) $d['creado_en'])) ?></div></div>
            <a class="btn btn-sm btn-claro" href="<?= e(url('/archivo/documentos/' . $d['archivo'])) ?>" target="_blank" rel="noopener">
              <?= ico('descargar', 15) ?> Ver
            </a>
          </li>
        <?php endforeach; ?>
        <?php if ($documentos === [] && $reglamento === ''): ?>
          <li class="vacio" style="padding:26px 12px"><?= ico('carpeta', 40) ?><h3>Sin documentos publicados</h3></li>
        <?php endif; ?>
      </ul>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Mis documentos</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <ul class="lista-limpia">
        <li class="item-lista">
          <span style="color:var(--arcilla)"><?= ico('billetera', 20) ?></span>
          <div class="crecer"><b>Estado de cuenta</b><div class="meta">Detalle de cargos y saldos</div></div>
          <a class="btn btn-sm btn-claro" href="<?= e(url('/doc/estado-cuenta/' . (int) $casaActual['id'])) ?>" target="_blank" rel="noopener">PDF</a>
        </li>
        <li class="item-lista">
          <span style="color:var(--arcilla)"><?= ico('escudo', 20) ?></span>
          <div class="crecer"><b>Constancia de solvencia</b><div class="meta">Para trámites de venta o alquiler</div></div>
          <a class="btn btn-sm btn-claro" href="<?= e(url('/doc/solvencia/' . (int) $casaActual['id'])) ?>" target="_blank" rel="noopener">PDF</a>
        </li>
        <li class="item-lista">
          <span style="color:var(--arcilla)"><?= ico('barras', 20) ?></span>
          <div class="crecer"><b>Estado de cuenta en Excel</b><div class="meta">Para su control personal</div></div>
          <a class="btn btn-sm btn-claro" href="<?= e(url('/excel/estado-cuenta/' . (int) $casaActual['id'])) ?>">XLSX</a>
        </li>
      </ul>
    </div>
  </article>
</div>
