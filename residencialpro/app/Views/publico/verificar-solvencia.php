<section class="seccion">
  <div class="contenedor-sm">
    <div class="tarjeta">
      <div class="tarjeta-cuerpo centrado" style="padding:44px 24px">
        <?php if ($casa === null || !$valido): ?>
          <div style="color:var(--grave)"><?= ico('equisCirculo', 46) ?></div>
          <h2 class="mt-2">Constancia no verificable</h2>
          <p class="texto-2">El código no corresponde a una constancia vigente. Las constancias tienen una validez de 30 días.
            Solicite un documento actualizado a la administración.</p>
        <?php else: ?>
          <div style="color:<?= $saldo <= 0.009 ? 'var(--ok)' : 'var(--aviso)' ?>"><?= ico($saldo <= 0.009 ? 'checkCirculo' : 'alerta', 46) ?></div>
          <h2 class="mt-2">Constancia auténtica</h2>
          <p class="texto-2">Vivienda <strong><?= e($casa['codigo']) ?></strong><?= !empty($casa['fase']) ? ' · ' . e($casa['fase']) : '' ?></p>
          <?php if ($saldo <= 0.009): ?>
            <span class="chip ok" style="font-size:.9rem;padding:8px 16px">Vivienda solvente a la fecha</span>
          <?php else: ?>
            <span class="chip grave" style="font-size:.9rem;padding:8px 16px">Saldo pendiente actual: <?= e(q($saldo)) ?></span>
            <p class="texto-3 mt-2" style="font-size:.86rem">La constancia fue emitida en una fecha anterior; el saldo mostrado es el actual.</p>
          <?php endif; ?>
        <?php endif; ?>
        <div class="mt-3"><a class="btn btn-claro" href="<?= e(url('/')) ?>">Volver al inicio</a></div>
      </div>
    </div>
  </div>
</section>
