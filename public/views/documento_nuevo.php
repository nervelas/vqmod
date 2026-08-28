<?php
/** @var string $csrf @var array<string,array{nombre:string,iva:bool,nota:bool}> $tipos
 *  @var array<string,string> $unidades @var array<string,string> $monedas
 *  @var list<array<string,mixed>> $clientes @var list<array<string,mixed>> $productos
 *  @var array<string,array{tipo:int,escenario:int,texto:string}> $frases
 *  @var \Fel\Plataforma\Empresa $empresa */
use Fel\Dte\Frase;
use Fel\Web\Vista;

$afiliacion = (string) $empresa->valor('afiliacion_iva', 'GEN');

$frasesSugeridas = [];
foreach (Frase::sugeridasPara($afiliacion) as $sugerida) {
    foreach ($frases as $clave => $definicion) {
        if ($definicion['tipo'] === $sugerida->tipo && $definicion['escenario'] === $sugerida->escenario) {
            $frasesSugeridas[] = $clave;
        }
    }
}
?>
<div class="encabezado-pagina">
  <h1>Nuevo documento</h1>
  <a class="boton secundario" href="index.php?r=documentos">Ver documentos</a>
</div>

<form method="post" action="index.php?r=emitir" id="formulario-dte">
<input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">

<fieldset>
  <legend>Documento</legend>
  <div class="fila">
    <div class="campo">
      <label for="tipo">Tipo de DTE</label>
      <select id="tipo" name="tipo" required>
        <?php foreach ($tipos as $codigo => $tipo): ?>
          <option value="<?= Vista::e($codigo) ?>" data-iva="<?= $tipo['iva'] ? '1' : '0' ?>" <?= $codigo === 'FACT' ? 'selected' : '' ?>>
            <?= Vista::e($codigo . ' — ' . $tipo['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="moneda">Moneda</label>
      <select id="moneda" name="moneda">
        <?php foreach ($monedas as $codigo => $nombre): ?>
          <option value="<?= Vista::e($codigo) ?>"><?= Vista::e($codigo . ' — ' . $nombre) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="tipo_cambio">Tipo de cambio</label>
      <input id="tipo_cambio" name="tipo_cambio" type="number" step="0.000001" min="0" value="1">
    </div>
    <div class="campo">
      <label for="referencia_interna">Referencia interna</label>
      <input id="referencia_interna" name="referencia_interna" maxlength="100" placeholder="Ej.: ORD-1001">
    </div>
  </div>
  <p id="aviso-iva" class="mensaje aviso" hidden style="margin-bottom:0">
    Este tipo de documento no desglosa IVA (regímenes sin derecho a crédito fiscal).
  </p>
</fieldset>

<fieldset>
  <legend>Receptor</legend>
  <div class="fila">
    <div class="campo">
      <label for="cliente_id">Cliente guardado</label>
      <select id="cliente_id" name="cliente_id">
        <option value="">— Capturar manualmente —</option>
        <?php foreach ($clientes as $cliente): ?>
          <option value="<?= (int) $cliente['id'] ?>"
                  data-identificador="<?= Vista::e($cliente['identificador']) ?>"
                  data-nombre="<?= Vista::e($cliente['nombre']) ?>"
                  data-correo="<?= Vista::e($cliente['correo']) ?>"
                  data-direccion="<?= Vista::e($cliente['direccion']) ?>"
                  data-municipio="<?= Vista::e($cliente['municipio']) ?>"
                  data-departamento="<?= Vista::e($cliente['departamento']) ?>"
                  data-tipo-especial="<?= Vista::e($cliente['tipo_especial']) ?>">
            <?= Vista::e($cliente['identificador'] . ' — ' . $cliente['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="receptor_id">NIT / CF / CUI</label>
      <input id="receptor_id" name="receptor_id" value="CF" required maxlength="25">
    </div>
    <div class="campo">
      <label for="receptor_tipo_especial">Tipo de identificación</label>
      <select id="receptor_tipo_especial" name="receptor_tipo_especial">
        <option value="">NIT (o CF)</option>
        <option value="CUI">CUI / DPI</option>
        <option value="EXT">Extranjero / pasaporte</option>
      </select>
    </div>
    <div class="campo">
      <label for="receptor_nombre">Nombre</label>
      <input id="receptor_nombre" name="receptor_nombre" value="Consumidor Final" required maxlength="255">
    </div>
    <div class="campo">
      <label for="receptor_correo">Correo (se le envía el DTE)</label>
      <input id="receptor_correo" name="receptor_correo" type="email" maxlength="255">
    </div>
  </div>
  <div class="fila">
    <div class="campo">
      <label for="receptor_direccion">Dirección</label>
      <input id="receptor_direccion" name="receptor_direccion" value="Ciudad" maxlength="255">
    </div>
    <div class="campo">
      <label for="receptor_municipio">Municipio</label>
      <input id="receptor_municipio" name="receptor_municipio" value="Guatemala" maxlength="100">
    </div>
    <div class="campo">
      <label for="receptor_departamento">Departamento</label>
      <input id="receptor_departamento" name="receptor_departamento" value="Guatemala" maxlength="100">
    </div>
  </div>
</fieldset>

<fieldset>
  <legend>Detalle</legend>
  <table class="datos">
    <thead>
      <tr>
        <th style="width:170px">Producto</th>
        <th>Descripción</th>
        <th style="width:90px">Cant.</th>
        <th style="width:90px">U/M</th>
        <th style="width:80px">B/S</th>
        <th style="width:110px">P. unitario</th>
        <th style="width:100px">Descuento</th>
        <th style="width:70px">Exento</th>
        <th class="num" style="width:100px">Total</th>
        <th style="width:40px"></th>
      </tr>
    </thead>
    <tbody id="lineas">
      <?= Vista::parcial('_linea_documento', ['indice' => 0, 'unidades' => $unidades, 'productos' => $productos]) ?>
    </tbody>
  </table>

  <p style="margin:12px 0 0">
    <button class="boton secundario pequeno" id="agregar-linea" type="button">+ Agregar línea</button>
  </p>

  <div class="totales-caja" style="margin-top:14px">
    <div><span>Base gravable</span><span>Q<span id="t-gravable">0.00</span></span></div>
    <div><span>Descuentos</span><span>Q<span id="t-descuento">0.00</span></span></div>
    <div><span>IVA</span><span>Q<span id="t-iva">0.00</span></span></div>
    <div class="grande"><span>Total</span><span>Q<span id="t-total">0.00</span></span></div>
  </div>
  <p style="font-size:11.5px;color:#5b6875;text-align:right;margin:4px 0 0">
    Los precios se capturan <strong>con IVA incluido</strong>, como se venden al público.
  </p>
</fieldset>

<fieldset>
  <legend>Frases obligatorias</legend>
  <p style="font-size:12.5px;color:#5b6875;margin-top:0">
    Se marcan las que corresponden a su régimen (<?= Vista::e($afiliacion) ?>).
    Consulte con su contador si su actividad exige alguna adicional.
  </p>
  <?php foreach ($frases as $clave => $frase): ?>
    <label class="casilla">
      <input type="checkbox" name="frases[]" value="<?= Vista::e($clave) ?>"
             <?= in_array($clave, $frasesSugeridas, true) ? 'checked' : '' ?>>
      <?= Vista::e($frase['texto']) ?>
      <small style="color:#5b6875">(tipo <?= (int) $frase['tipo'] ?>, escenario <?= (int) $frase['escenario'] ?>)</small>
    </label>
  <?php endforeach; ?>
</fieldset>

<fieldset>
  <legend>Referencia al documento origen (solo notas de crédito y débito)</legend>
  <div class="fila">
    <div class="campo">
      <label for="ref_uuid">Número de autorización del documento original</label>
      <input id="ref_uuid" name="ref_uuid" maxlength="50" placeholder="UUID del DTE que se ajusta">
    </div>
    <div class="campo">
      <label for="ref_fecha">Fecha de emisión original</label>
      <input id="ref_fecha" name="ref_fecha" type="date">
    </div>
    <div class="campo">
      <label for="ref_serie">Serie original</label>
      <input id="ref_serie" name="ref_serie" maxlength="30">
    </div>
    <div class="campo">
      <label for="ref_numero">Número original</label>
      <input id="ref_numero" name="ref_numero" maxlength="30">
    </div>
  </div>
  <div class="campo">
    <label for="ref_motivo">Motivo del ajuste</label>
    <input id="ref_motivo" name="ref_motivo" maxlength="255">
  </div>
</fieldset>

<fieldset>
  <legend>Observaciones</legend>
  <div class="campo">
    <label for="observaciones">Texto libre (viaja en la adenda, no es información fiscal)</label>
    <textarea id="observaciones" name="observaciones" rows="2" maxlength="500"></textarea>
  </div>
</fieldset>

<div class="acciones">
  <button class="boton" type="submit">Certificar y emitir</button>
  <a class="boton secundario" href="index.php?r=panel">Cancelar</a>
</div>
</form>

<template id="plantilla-linea">
  <?= Vista::parcial('_linea_documento', ['indice' => '__i__', 'unidades' => $unidades, 'productos' => $productos]) ?>
</template>

<script src="assets/app.js"></script>
