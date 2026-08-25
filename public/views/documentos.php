<?php
/** @var list<array<string,mixed>> $documentos @var array<string,string> $filtros
 *  @var array<string,array{nombre:string,iva:bool,nota:bool}> $tipos */
use Fel\Web\Vista;
?>
<div class="encabezado-pagina">
  <h1>Documentos</h1>
  <a class="boton" href="index.php?r=nuevo">Emitir documento</a>
</div>

<form class="tarjeta" method="get" action="index.php">
  <input type="hidden" name="r" value="documentos">
  <div class="fila">
    <div class="campo">
      <label for="f-receptor">Receptor (NIT o nombre)</label>
      <input id="f-receptor" name="receptor" value="<?= Vista::e($filtros['receptor']) ?>">
    </div>
    <div class="campo">
      <label for="f-tipo">Tipo</label>
      <select id="f-tipo" name="tipo">
        <option value="">Todos</option>
        <?php foreach ($tipos as $codigo => $tipo): ?>
          <option value="<?= Vista::e($codigo) ?>" <?= $filtros['tipo'] === $codigo ? 'selected' : '' ?>>
            <?= Vista::e($codigo . ' — ' . $tipo['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="f-estado">Estado</label>
      <select id="f-estado" name="estado">
        <option value="">Todos</option>
        <?php foreach (['CERTIFICADO', 'PENDIENTE', 'RECHAZADO', 'ANULADO'] as $estado): ?>
          <option value="<?= $estado ?>" <?= $filtros['estado'] === $estado ? 'selected' : '' ?>><?= $estado ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="f-desde">Desde</label>
      <input id="f-desde" name="desde" type="date" value="<?= Vista::e($filtros['desde']) ?>">
    </div>
    <div class="campo">
      <label for="f-hasta">Hasta</label>
      <input id="f-hasta" name="hasta" type="date" value="<?= Vista::e($filtros['hasta']) ?>">
    </div>
    <div class="campo" style="display:flex;align-items:flex-end;gap:8px">
      <button class="boton" type="submit">Filtrar</button>
      <a class="boton secundario" href="index.php?r=documentos">Limpiar</a>
    </div>
  </div>
</form>

<div class="tarjeta">
  <?php if ($documentos === []): ?>
    <p class="vacio">No hay documentos que coincidan con el filtro.</p>
  <?php else: ?>
    <table class="datos">
      <thead>
        <tr>
          <th>#</th><th>Tipo</th><th>Serie-Número</th><th>Autorización</th>
          <th>Receptor</th><th>Fecha</th><th class="num">IVA</th><th class="num">Total</th><th>Estado</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($documentos as $documento): ?>
          <tr>
            <td><a href="index.php?r=ver&amp;id=<?= (int) $documento['id'] ?>"><?= (int) $documento['id'] ?></a></td>
            <td><?= Vista::e($documento['tipo']) ?></td>
            <td><?= Vista::e(($documento['serie'] ?: '—') . '-' . ($documento['numero'] ?: '—')) ?></td>
            <td style="font-family:monospace;font-size:11px"><?= Vista::e(mb_substr((string) $documento['uuid'], 0, 13)) ?><?= $documento['uuid'] ? '…' : '—' ?></td>
            <td><?= Vista::e($documento['receptor_nombre']) ?><br><small style="color:#5b6875"><?= Vista::e($documento['receptor_id']) ?></small></td>
            <td><?= Vista::e(Vista::fecha((string) $documento['fecha_emision'])) ?></td>
            <td class="num"><?= Vista::moneda($documento['total_iva']) ?></td>
            <td class="num"><?= Vista::moneda($documento['gran_total']) ?></td>
            <td><span class="etiqueta <?= Vista::e($documento['estado']) ?>"><?= Vista::e($documento['estado']) ?></span></td>
            <td class="acciones">
              <a class="boton pequeno secundario" href="index.php?r=imprimir&amp;id=<?= (int) $documento['id'] ?>" target="_blank">Ver</a>
              <a class="boton pequeno secundario" href="index.php?r=xml&amp;id=<?= (int) $documento['id'] ?>">XML</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
