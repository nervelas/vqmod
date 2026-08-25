<?php
/** @var array<string,mixed> $documento @var list<array<string,mixed>> $items
 *  @var list<array<string,mixed>> $bitacora @var list<array<string,mixed>> $anulaciones @var string $csrf */
use Fel\Dte\Catalogos;
use Fel\Web\Vista;

$tipos      = Catalogos::tiposDte();
$nombreTipo = $tipos[(string) $documento['tipo']]['nombre'] ?? (string) $documento['tipo'];
$simbolo    = $documento['moneda'] === 'GTQ' ? 'Q' : ($documento['moneda'] === 'USD' ? '$' : $documento['moneda'] . ' ');
?>
<div class="encabezado-pagina">
  <h1><?= Vista::e($nombreTipo) ?> <?= Vista::e(($documento['serie'] ?: '—') . '-' . ($documento['numero'] ?: '—')) ?></h1>
  <div class="acciones">
    <span class="etiqueta <?= Vista::e($documento['estado']) ?>"><?= Vista::e($documento['estado']) ?></span>
    <a class="boton secundario" href="index.php?r=imprimir&amp;id=<?= (int) $documento['id'] ?>" target="_blank">Representación gráfica</a>
    <a class="boton secundario" href="index.php?r=imprimir&amp;id=<?= (int) $documento['id'] ?>&amp;imprimir=1" target="_blank">Imprimir / PDF</a>
    <a class="boton secundario" href="index.php?r=xml&amp;id=<?= (int) $documento['id'] ?>">Descargar XML</a>
  </div>
</div>

<div class="rejilla k2">
  <div class="tarjeta">
    <h2>Documento</h2>
    <dl class="definiciones">
      <dt>Número de autorización</dt>
      <dd><?= $documento['uuid'] ? '<span class="uuid">' . Vista::e($documento['uuid']) . '</span>' : '—' ?></dd>
      <dt>Serie / Número</dt><dd><?= Vista::e(($documento['serie'] ?: '—') . ' / ' . ($documento['numero'] ?: '—')) ?></dd>
      <dt>Emisión</dt><dd><?= Vista::e(Vista::fecha((string) $documento['fecha_emision'])) ?></dd>
      <dt>Certificación</dt><dd><?= Vista::e(Vista::fecha((string) $documento['fecha_certificacion'])) ?></dd>
      <dt>Certificador</dt><dd><?= Vista::e($documento['certificador'] ?: '—') ?></dd>
      <dt>Establecimiento</dt><dd><?= Vista::e($documento['establecimiento']) ?></dd>
      <dt>Moneda</dt><dd><?= Vista::e($documento['moneda']) ?></dd>
      <dt>Referencia interna</dt><dd><?= Vista::e($documento['referencia_interna'] ?: '—') ?></dd>
      <dt>Creado por</dt><dd><?= Vista::e($documento['creado_por'] ?: '—') ?></dd>
    </dl>
  </div>

  <div class="tarjeta">
    <h2>Receptor</h2>
    <dl class="definiciones">
      <dt>Identificación</dt><dd><?= Vista::e($documento['receptor_id']) ?></dd>
      <dt>Nombre</dt><dd><?= Vista::e($documento['receptor_nombre']) ?></dd>
      <dt>Correo</dt><dd><?= Vista::e($documento['receptor_correo'] ?: '—') ?></dd>
    </dl>

    <?php if ($documento['error_mensaje']): ?>
      <h3>Último error</h3>
      <div class="mensaje error"><?= Vista::e($documento['error_mensaje']) ?></div>
    <?php endif; ?>

    <?php if ($documento['estado'] === 'PENDIENTE'): ?>
      <form method="post" action="index.php?r=reintentar">
        <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $documento['id'] ?>">
        <button class="boton" type="submit">Reintentar certificación</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="tarjeta">
  <h2>Detalle</h2>
  <table class="datos">
    <thead>
      <tr><th>#</th><th>Cant.</th><th>U/M</th><th>Descripción</th>
          <th class="num">P. unitario</th><th class="num">Descuento</th>
          <th class="num">Gravable</th><th class="num">IVA</th><th class="num">Total</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= (int) $item['numero_linea'] ?></td>
          <td><?= Vista::e(rtrim(rtrim(number_format((float) $item['cantidad'], 6, '.', ''), '0'), '.')) ?></td>
          <td><?= Vista::e($item['unidad_medida']) ?></td>
          <td><?= Vista::e($item['descripcion']) ?></td>
          <td class="num"><?= Vista::moneda($item['precio_unitario'], $simbolo) ?></td>
          <td class="num"><?= Vista::moneda($item['descuento'], $simbolo) ?></td>
          <td class="num"><?= Vista::moneda($item['monto_gravable'], $simbolo) ?></td>
          <td class="num"><?= Vista::moneda($item['monto_impuesto'], $simbolo) ?></td>
          <td class="num"><?= Vista::moneda($item['total'], $simbolo) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totales-caja" style="margin-top:14px">
    <div><span>Base gravable</span><span><?= Vista::moneda($documento['total_gravable'], $simbolo) ?></span></div>
    <div><span>Descuentos</span><span><?= Vista::moneda($documento['total_descuentos'], $simbolo) ?></span></div>
    <div><span>IVA</span><span><?= Vista::moneda($documento['total_iva'], $simbolo) ?></span></div>
    <div class="grande"><span>Total</span><span><?= Vista::moneda($documento['gran_total'], $simbolo) ?></span></div>
  </div>
</div>

<?php if ($documento['estado'] === 'CERTIFICADO'): ?>
  <div class="tarjeta">
    <h2>Anular documento</h2>
    <p style="font-size:12.5px;color:#5b6875;margin-top:-8px">
      La anulación se transmite a SAT y queda registrada. Si ya pasó el plazo permitido,
      lo correcto es emitir una <strong>nota de crédito (NCRE)</strong> en lugar de anular.
    </p>
    <form method="post" action="index.php?r=anular" onsubmit="return confirm('¿Confirma anular este documento ante SAT?')">
      <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $documento['id'] ?>">
      <div class="fila">
        <div class="campo" style="flex:1">
          <label for="motivo">Motivo de la anulación</label>
          <input id="motivo" name="motivo" required maxlength="255" placeholder="Ej.: error en el detalle del documento">
        </div>
        <div class="campo" style="display:flex;align-items:flex-end">
          <button class="boton peligro" type="submit">Anular</button>
        </div>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if ($anulaciones !== []): ?>
  <div class="tarjeta">
    <h2>Anulaciones</h2>
    <table class="datos">
      <thead><tr><th>Fecha</th><th>Motivo</th><th>Estado</th><th>Autorización</th></tr></thead>
      <tbody>
        <?php foreach ($anulaciones as $anulacion): ?>
          <tr>
            <td><?= Vista::e($anulacion['creado_en']) ?></td>
            <td><?= Vista::e($anulacion['motivo']) ?></td>
            <td><span class="etiqueta <?= Vista::e($anulacion['estado']) ?>"><?= Vista::e($anulacion['estado']) ?></span></td>
            <td style="font-family:monospace;font-size:11px"><?= Vista::e($anulacion['uuid_anulacion'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div class="tarjeta">
  <h2>Bitácora con el certificador</h2>
  <?php if ($bitacora === []): ?>
    <p class="vacio">Sin registros.</p>
  <?php else: ?>
    <table class="datos">
      <thead><tr><th>Fecha</th><th>Operación</th><th>Resultado</th><th>Mensaje</th></tr></thead>
      <tbody>
        <?php foreach ($bitacora as $registro): ?>
          <tr>
            <td><?= Vista::e($registro['creado_en']) ?></td>
            <td><?= Vista::e($registro['operacion']) ?></td>
            <td><?= $registro['exito'] ? '<span class="etiqueta CERTIFICADO">OK</span>' : '<span class="etiqueta RECHAZADO">Error</span>' ?></td>
            <td style="font-size:12px"><?= Vista::e(mb_substr((string) $registro['mensaje'], 0, 300)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
