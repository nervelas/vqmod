<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <div class="columna">
    <?php if ($reservasMes !== []): ?>
      <div class="rejilla rejilla-3">
        <?php foreach ($reservasMes as $r): ?>
          <article class="kpi">
            <div class="kpi-et"><?= ico('calendario', 15) ?> <?= e(recortar((string) $r['nombre'], 22)) ?></div>
            <div class="kpi-valor"><?= (int) $r['n'] ?></div>
            <div class="kpi-nota">reservas este mes · <?= e(q((float) $r['total'])) ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($areas as $a): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab">
          <div>
            <h3 style="margin:0"><?= e($a['nombre']) ?></h3>
            <div class="texto-3" style="font-size:.8rem">
              <?= e(hora((string) $a['hora_desde'])) ?> a <?= e(hora((string) $a['hora_hasta'])) ?>
              <?= (int) $a['capacidad'] > 0 ? ' · hasta ' . (int) $a['capacidad'] . ' personas' : '' ?>
            </div>
          </div>
          <div class="fila" style="gap:6px">
            <span class="chip <?= $a['aprobacion'] === 'automatica' ? 'ok' : 'aviso' ?>">
              <?= $a['aprobacion'] === 'automatica' ? 'Aprobación inmediata' : 'Requiere aprobación' ?>
            </span>
            <span class="chip <?= (int) $a['activo'] === 1 ? 'ok' : 'neutro' ?>"><?= (int) $a['activo'] === 1 ? 'Activa' : 'Inactiva' ?></span>
          </div>
        </div>
        <div class="tarjeta-cuerpo">
          <div class="fila envolver" style="gap:22px;align-items:flex-start">
            <?php if (!empty($a['foto'])): ?>
              <img src="<?= e(subida($a['foto'], 'areas')) ?>" alt="" style="width:150px;height:100px;object-fit:cover;border-radius:var(--r-sm)">
            <?php endif; ?>
            <div class="crecer">
              <p class="texto-2" style="font-size:.9rem;margin-bottom:8px"><?= e($a['descripcion'] ?? '') ?></p>
              <div class="fila envolver" style="gap:18px;font-size:.86rem">
                <span class="texto-2">Costo: <b><?= (float) $a['costo'] > 0 ? e(q((float) $a['costo'])) : 'sin costo' ?></b></span>
                <?php if ((float) $a['deposito'] > 0): ?>
                  <span class="texto-2">Depósito: <b><?= e(q((float) $a['deposito'])) ?></b></span>
                <?php endif; ?>
                <span class="texto-2">Bloquea si hay mora: <b><?= (int) $a['bloquea_mora'] === 1 ? 'sí' : 'no' ?></b></span>
              </div>
            </div>
          </div>
        </div>
        <?php if (esRol('admin')): ?>
          <div class="tarjeta-pie fila-fin">
            <a class="btn btn-sm btn-claro" href="<?= e(url('/admin/reservas', ['area' => (int) $a['id']])) ?>"><?= ico('lista', 15) ?> Ver reservas</a>
            <a class="btn btn-sm btn-oro" href="<?= e(url('/admin/areas/' . (int) $a['id'] . '/editar')) ?>"><?= ico('editar', 15) ?> Editar</a>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if (esRol('admin')): ?>
    <form method="post" enctype="multipart/form-data" style="align-self:start">
      <?= csrf() ?>
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Nueva área común</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campo"><label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="120" placeholder="Salón de eventos"></div>
          <div class="campo"><label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="600"></textarea></div>
          <div class="campos">
            <div class="campo"><label for="hora_desde">Desde</label><input type="time" id="hora_desde" name="hora_desde" value="08:00"></div>
            <div class="campo"><label for="hora_hasta">Hasta</label><input type="time" id="hora_hasta" name="hora_hasta" value="22:00"></div>
            <div class="campo"><label for="capacidad">Capacidad</label><input type="number" id="capacidad" name="capacidad" min="0" value="0"></div>
            <div class="campo"><label for="costo">Costo</label><input type="number" id="costo" name="costo" step="0.01" min="0" value="0"></div>
            <div class="campo"><label for="deposito">Depósito</label><input type="number" id="deposito" name="deposito" step="0.01" min="0" value="0"></div>
            <div class="campo"><label for="duracion_min">Duración máxima (min)</label>
              <input type="number" id="duracion_min" name="duracion_min" min="30" step="30" value="240"></div>
          </div>
          <div class="campo">
            <label for="aprobacion">Aprobación</label>
            <select id="aprobacion" name="aprobacion">
              <option value="manual">La administración confirma</option>
              <option value="automatica">Inmediata</option>
            </select>
          </div>
          <div class="campo">
            <span class="etiqueta">Días disponibles</span>
            <div class="fila envolver" style="gap:10px">
              <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'] as $n => $et): ?>
                <label class="marca-check" style="margin:0"><input type="checkbox" name="dias[]" value="<?= $n ?>" checked><span><?= e($et) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="campo"><label for="reglas">Reglas de uso</label>
            <textarea id="reglas" name="reglas" rows="3" maxlength="1200"></textarea></div>
          <div class="campo"><label for="foto">Fotografía</label>
            <input type="file" id="foto" name="foto" accept="image/*"></div>
          <label class="marca-check"><input type="checkbox" name="bloquea_mora" value="1" checked><span>No permitir reservar si la vivienda tiene saldo</span></label>
          <label class="marca-check"><input type="checkbox" name="activo" value="1" checked><span>Área activa</span></label>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro btn-sm" type="submit"><?= ico('mas', 15) ?> Crear área</button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>
