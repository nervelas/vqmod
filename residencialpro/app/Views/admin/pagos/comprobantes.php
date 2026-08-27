<?php use App\Core\Vista; use App\Models\Cuota; ?>
<?php if ($pendientes === []): ?>
  <div class="tarjeta">
    <?= Vista::parcial('partials/vacio', ['icono' => 'checkCirculo', 'titulo' => 'No hay comprobantes pendientes',
        'texto' => 'Todos los pagos reportados por los residentes ya fueron revisados.']) ?>
  </div>
<?php else: ?>
  <div class="rejilla rejilla-2">
    <?php foreach ($pendientes as $p):
      $cargos = Cuota::cargos((int) $p['casa_id'], 'pendientes', 12);
      $ext = strtolower(pathinfo((string) ($p['comprobante'] ?? ''), PATHINFO_EXTENSION));
    ?>
      <article class="tarjeta">
        <div class="tarjeta-cab">
          <div>
            <h3 style="margin:0">Casa <?= e($p['casa']) ?> · <?= e(q((float) $p['monto'])) ?></h3>
            <div class="texto-3" style="font-size:.82rem">
              <?= e(recortar((string) $p['residente'], 30)) ?> · reportado <?= e(hace((string) $p['creado_en'])) ?>
            </div>
          </div>
          <span class="chip aviso">En revisión</span>
        </div>
        <div class="tarjeta-cuerpo">
          <table class="tabla mb-2">
            <tbody>
              <tr><td class="texto-3">Fecha declarada</td><td class="d"><?= e(fecha((string) $p['fecha'])) ?></td></tr>
              <tr><td class="texto-3">Forma de pago</td><td class="d"><?= e(ucfirst((string) $p['metodo'])) ?></td></tr>
              <?php if (!empty($p['banco'])): ?><tr><td class="texto-3">Banco</td><td class="d"><?= e($p['banco']) ?></td></tr><?php endif; ?>
              <?php if (!empty($p['referencia'])): ?><tr><td class="texto-3">Referencia</td><td class="d fuerte"><?= e($p['referencia']) ?></td></tr><?php endif; ?>
              <tr><td class="texto-3">Saldo de la vivienda</td><td class="d fuerte"><?= e(q(App\Models\Casa::saldo((int) $p['casa_id']))) ?></td></tr>
            </tbody>
          </table>

          <?php if (!empty($p['comprobante'])): ?>
            <?php if ($ext === 'pdf'): ?>
              <a class="btn btn-claro btn-bloque mb-2" target="_blank" rel="noopener"
                 href="<?= e(url('/archivo/comprobantes/' . $p['comprobante'])) ?>"><?= ico('archivo', 17) ?> Abrir el comprobante (PDF)</a>
            <?php else: ?>
              <a target="_blank" rel="noopener" href="<?= e(url('/archivo/comprobantes/' . $p['comprobante'])) ?>">
                <img src="<?= e(url('/archivo/comprobantes/' . $p['comprobante'])) ?>" alt="Comprobante enviado por el residente"
                     style="border-radius:var(--r-sm);width:100%;max-height:280px;object-fit:cover">
              </a>
            <?php endif; ?>
          <?php else: ?>
            <div class="aviso-caja alerta mb-2"><?= ico('alerta', 18) ?><div>El residente no adjuntó imagen del comprobante.</div></div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('/admin/comprobantes/' . (int) $p['id'] . '/aprobar')) ?>">
            <?= csrf() ?>
            <?php if ($cargos !== []): ?>
              <div class="mayus mt-2 mb-1">Aplicar a</div>
              <div class="tabla-caja desplaza" data-etiqueta="Detalle del comprobante" style="max-height:210px;overflow:auto">
                <table class="tabla">
                  <tbody>
                    <?php foreach ($cargos as $g): $s = Cuota::saldoCargo($g); ?>
                      <tr>
                        <td style="font-size:.85rem"><?= e(recortar((string) $g['descripcion'], 34)) ?></td>
                        <td class="d num" style="font-size:.85rem"><?= e(q($s)) ?></td>
                        <td class="d" style="width:120px">
                          <input type="number" name="cargo[<?= (int) $g['id'] ?>]" step="0.01" min="0" max="<?= $s ?>"
                                 style="text-align:right;padding:7px 9px" aria-label="Monto a aplicar">
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <span class="ayuda">Si lo deja vacío, se aplicará automáticamente a los cargos más antiguos.</span>
            <?php endif; ?>
            <div class="fila-fin mt-2">
              <button class="btn btn-ok" type="submit"><?= ico('checkCirculo', 17) ?> Aprobar y enviar recibo</button>
            </div>
          </form>
        </div>
        <div class="tarjeta-pie">
          <form method="post" action="<?= e(url('/admin/comprobantes/' . (int) $p['id'] . '/rechazar')) ?>">
            <?= csrf() ?>
            <div class="fila envolver" style="gap:8px">
              <input type="text" name="motivo" class="crecer" required minlength="5"
                     placeholder="Motivo del rechazo (lo verá el residente)">
              <button class="btn btn-peligro btn-sm" type="submit"><?= ico('equis', 15) ?> Rechazar</button>
            </div>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($recientes !== []): ?>
  <article class="tarjeta mt-3">
    <div class="tarjeta-cab"><h3>Revisados recientemente</h3></div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Casa</th><th>Fecha</th><th class="d">Monto</th><th class="c">Resultado</th><th>Motivo</th></tr></thead>
        <tbody>
          <?php foreach ($recientes as $r): ?>
            <tr>
              <td class="fuerte"><?= e($r['casa']) ?></td>
              <td class="texto-3"><?= e(fechahora((string) $r['aprobado_en'])) ?></td>
              <td class="d num"><?= e(q((float) $r['monto'])) ?></td>
              <td class="c"><span class="chip <?= e(estadoBadge((string) $r['estado'])) ?>"><?= e(ucfirst((string) $r['estado'])) ?></span></td>
              <td class="texto-3"><?= e(recortar((string) ($r['motivo_rechazo'] ?? ''), 50)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>
<?php endif; ?>
