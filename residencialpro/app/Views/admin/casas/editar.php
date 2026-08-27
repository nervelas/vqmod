<?php use App\Core\Sesion; $esNueva = $casa === null; ?>
<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url($esNueva ? '/admin/casas' : '/admin/casas/' . (int) $casa['id'])) ?>">
    <?= ico('flechaIzq', 16) ?> Volver
  </a>
  <form method="post" enctype="multipart/form-data">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3><?= $esNueva ? 'Datos de la nueva vivienda' : 'Editar vivienda' ?></h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo">
            <label for="codigo">Código de la vivienda *</label>
            <input type="text" id="codigo" name="codigo" required maxlength="30" style="text-transform:uppercase"
                   value="<?= e($casa['codigo'] ?? Sesion::viejo('codigo')) ?>" placeholder="A-01">
            <span class="ayuda">Como aparece en la garita y en los recibos.</span>
          </div>
          <div class="campo">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo">
              <?php foreach (['casa' => 'Casa', 'apartamento' => 'Apartamento', 'lote' => 'Lote', 'local' => 'Local'] as $k => $et): ?>
                <option value="<?= e($k) ?>" <?= ($casa['tipo'] ?? 'casa') === $k ? 'selected' : '' ?>><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="fase_id">Fase *</label>
            <select id="fase_id" name="fase_id" required>
              <?php foreach ($fases as $f): ?>
                <option value="<?= (int) $f['id'] ?>" <?= (int) ($casa['fase_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="calle_id">Calle</label>
            <select id="calle_id" name="calle_id">
              <option value="">Sin calle asignada</option>
              <?php foreach ($calles as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($casa['calle_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['fase'] ?? '') ?> · <?= e($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="metros">Metros de construcción</label>
            <input type="number" id="metros" name="metros" step="0.01" min="0" value="<?= e($casa['metros'] ?? '0') ?>">
          </div>
          <div class="campo">
            <label for="coeficiente">Coeficiente de participación (%)</label>
            <input type="number" id="coeficiente" name="coeficiente" step="0.00001" min="0" max="100"
                   value="<?= e($casa['coeficiente'] ?? '0') ?>">
            <span class="ayuda">La suma de todas las viviendas debería ser 100%.</span>
          </div>
          <div class="campo">
            <label for="parqueos">Parqueos</label>
            <input type="number" id="parqueos" name="parqueos" min="0" max="20" value="<?= e($casa['parqueos'] ?? '0') ?>">
          </div>
          <div class="campo">
            <label for="bodegas">Bodegas</label>
            <input type="number" id="bodegas" name="bodegas" min="0" max="20" value="<?= e($casa['bodegas'] ?? '0') ?>">
          </div>
          <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
              <?php foreach (['habitada' => 'Habitada', 'desocupada' => 'Desocupada', 'venta' => 'En venta', 'alquiler' => 'En alquiler'] as $k => $et): ?>
                <option value="<?= e($k) ?>" <?= ($casa['estado'] ?? 'habitada') === $k ? 'selected' : '' ?>><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="foto">Fotografía</label>
            <input type="file" id="foto" name="foto" accept="image/*" data-previa="#previa-casa">
            <?php if (!empty($casa['foto'])): ?>
              <img id="previa-casa" src="<?= e(subida($casa['foto'], 'casas')) ?>" alt="Fotografía de la vivienda"
                   style="margin-top:10px;border-radius:var(--r-sm);max-height:130px">
            <?php else: ?>
              <img id="previa-casa" src="<?= e(url('/assets/img/vacio.svg')) ?>" alt="" hidden
                   style="margin-top:10px;border-radius:var(--r-sm);max-height:130px">
            <?php endif; ?>
          </div>
          <div class="campo campo-ancho">
            <label for="notas">Notas internas</label>
            <textarea id="notas" name="notas" rows="3" maxlength="1000"><?= e($casa['notas'] ?? '') ?></textarea>
          </div>
          <div class="campo">
            <label for="mapa_x">Posición en el mapa · X (%)</label>
            <input type="number" id="mapa_x" name="mapa_x" step="0.01" min="0" max="100" value="<?= e($casa['mapa_x'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="mapa_y">Posición en el mapa · Y (%)</label>
            <input type="number" id="mapa_y" name="mapa_y" step="0.01" min="0" max="100" value="<?= e($casa['mapa_y'] ?? '') ?>">
          </div>
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <?php if (!$esNueva): ?>
          <button type="button" class="btn btn-fantasma" data-enviar="#f-eliminar">
            <?= ico('basura', 16) ?> Eliminar
          </button>
        <?php endif; ?>
        <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar vivienda</button>
      </div>
    </div>
  </form>

  <?php if (!$esNueva): ?>
    <form id="f-eliminar" method="post" action="<?= e(url('/admin/casas/' . (int) $casa['id'] . '/eliminar')) ?>"
          data-confirmar="Se eliminará la vivienda y sus residentes. Esta acción no se puede deshacer."
          data-confirmar-titulo="¿Eliminar la vivienda <?= e($casa['codigo']) ?>?"
          data-confirmar-boton="Sí, eliminar" hidden>
      <?= csrf() ?>
      <button type="submit" class="solo-lectores">Confirmar la eliminación</button>
    </form>
  <?php endif; ?>
</div>
