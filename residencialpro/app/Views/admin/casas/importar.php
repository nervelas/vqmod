<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <form method="post" enctype="multipart/form-data">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Importar viviendas desde una hoja de cálculo</h3></div>
      <div class="tarjeta-cuerpo">
        <?php if ($resultado !== null): ?>
          <div class="aviso-caja <?= $resultado['errores'] === [] ? 'ok' : 'alerta' ?> mb-3">
            <?= ico($resultado['errores'] === [] ? 'checkCirculo' : 'alerta', 20) ?>
            <div>
              <strong>Importación terminada</strong>
              <?= (int) $resultado['creadas'] ?> vivienda(s) creadas y
              <?= (int) $resultado['actualizadas'] ?> actualizadas.
              <?php if ($resultado['errores'] !== []): ?>
                <ul style="margin:8px 0 0;padding-left:18px;font-size:.86rem">
                  <?php foreach (array_slice($resultado['errores'], 0, 12) as $er): ?>
                    <li><?= e($er) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <p class="texto-2" style="font-size:.93rem">
          Prepare su listado en Excel o Google Sheets y guárdelo como <strong>CSV</strong>.
          La primera fila debe contener los nombres de las columnas.
        </p>
        <div class="campo">
          <label for="archivo">Archivo CSV *</label>
          <input type="file" id="archivo" name="archivo" accept=".csv,text/csv" required>
        </div>
        <label class="marca-check">
          <input type="checkbox" name="actualizar" value="1">
          <span>Si la vivienda ya existe, actualizar sus datos</span>
        </label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/casas')) ?>">Cancelar</a>
        <button class="btn btn-oro" type="submit"><?= ico('subir', 17) ?> Importar</button>
      </div>
    </div>
  </form>

  <article class="tarjeta" style="align-self:start">
    <div class="tarjeta-cab"><h3>Columnas aceptadas</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <table class="tabla">
        <tbody>
          <?php foreach ([
            ['codigo', 'Obligatoria. Ej. A-01'],
            ['fase', 'Se crea si no existe'],
            ['calle', 'Se crea si no existe'],
            ['tipo', 'casa, apartamento, lote o local'],
            ['metros', 'Metros de construcción'],
            ['coeficiente', 'Porcentaje de participación'],
            ['parqueos', 'Número de parqueos'],
            ['bodegas', 'Número de bodegas'],
            ['estado', 'habitada, desocupada, venta, alquiler'],
            ['residente', 'Nombre del propietario'],
            ['dpi', 'DPI del propietario'],
            ['correo', 'Correo del propietario'],
            ['telefono', 'Teléfono del propietario'],
          ] as [$col, $desc]): ?>
            <tr><td><code><?= e($col) ?></code></td><td class="texto-3" style="font-size:.84rem"><?= e($desc) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="aviso-caja info mt-2" style="font-size:.85rem">
        <?= ico('info', 18) ?>
        <div>Ejemplo de primera fila:<br>
          <code style="font-size:.78rem">codigo,fase,calle,metros,coeficiente,residente,telefono</code></div>
      </div>
    </div>
  </article>
</div>
