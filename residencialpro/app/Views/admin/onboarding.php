<?php use App\Core\Ajustes; ?>
<div style="min-height:100dvh;background:linear-gradient(160deg,var(--petroleo-3),var(--petroleo) 60%,var(--petroleo-2));padding:36px 16px">
  <div class="contenedor-sm">
    <div class="centrado" style="color:#E9EEE9;margin-bottom:26px">
      <div style="font-family:var(--f-titulo);font-size:2rem;color:var(--arcilla-3)">Bienvenido a ResidencialPro</div>
      <p style="color:color-mix(in srgb, #fff 80%, transparent);margin:4px 0 0">Cuatro pasos y su residencial queda funcionando.</p>
    </div>

    <div class="fila" style="justify-content:center;gap:8px;margin-bottom:24px;flex-wrap:wrap">
      <?php foreach ([1 => 'Condominio', 2 => 'Viviendas', 3 => 'Cuota mensual', 4 => 'Primer aviso'] as $n => $et): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:8px 15px;border-radius:var(--r-full);font-size:.83rem;font-weight:600;
             background:<?= $n === $paso ? 'var(--arcilla)' : ($n < $paso ? 'color-mix(in srgb, var(--arcilla-3) 26%, transparent)' : 'rgba(255,255,255,.07)') ?>;
             color:<?= $n === $paso ? '#fff' : ($n < $paso ? 'var(--arcilla-3)' : 'color-mix(in srgb, #fff 80%, transparent)') ?>">
          <span style="width:21px;height:21px;border-radius:50%;display:grid;place-items:center;background:rgba(0,0,0,.16);font-size:.74rem">
            <?= $n < $paso ? '✓' : $n ?>
          </span><?= e($et) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="tarjeta">
      <?php if ($paso === 1): ?>
        <form method="post">
          <?= csrf() ?>
          <input type="hidden" name="accion" value="condominio">
          <div class="tarjeta-cab"><h3>Paso 1 · Datos del residencial</h3></div>
          <div class="tarjeta-cuerpo">
            <p class="texto-2" style="font-size:.93rem">Confirme los datos que aparecerán en los recibos, correos y el sitio público.</p>
            <div class="campos">
              <div class="campo campo-ancho"><label for="nombre">Nombre del residencial *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e(Ajustes::get('nombre')) ?>"></div>
              <div class="campo campo-ancho"><label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" maxlength="255" value="<?= e(Ajustes::get('direccion')) ?>"></div>
              <div class="campo"><label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="40" value="<?= e(Ajustes::get('telefono')) ?>"></div>
              <div class="campo"><label for="correo">Correo de la administración</label>
                <input type="email" id="correo" name="correo" maxlength="160" value="<?= e(Ajustes::get('correo')) ?>"></div>
            </div>
          </div>
          <div class="tarjeta-pie fila-entre">
            <button class="btn btn-fantasma btn-sm" type="submit" name="omitir" value="1">Omitir configuración</button>
            <button class="btn btn-oro" type="submit"><?= ico('flechaDer', 17) ?> Continuar</button>
          </div>
        </form>

      <?php elseif ($paso === 2): ?>
        <form method="post">
          <?= csrf() ?>
          <input type="hidden" name="accion" value="casas">
          <div class="tarjeta-cab"><h3>Paso 2 · Crear las viviendas</h3></div>
          <div class="tarjeta-cuerpo">
            <p class="texto-2" style="font-size:.93rem">
              Creamos todas las viviendas de una sola vez con una numeración correlativa.
              Después podrá editarlas una por una o importarlas desde Excel.
              <?php if ($totalCasas > 0): ?>
                <br><strong>Ya tiene <?= (int) $totalCasas ?> vivienda(s) registradas.</strong>
              <?php endif; ?>
            </p>
            <div class="campos">
              <div class="campo campo-ancho"><label for="fase">Nombre de la fase o sector</label>
                <input type="text" id="fase" name="fase" maxlength="90" value="Fase única"></div>
              <div class="campo"><label for="prefijo">Prefijo</label>
                <input type="text" id="prefijo" name="prefijo" maxlength="5" value="C" style="text-transform:uppercase">
                <span class="ayuda">Se crearán C-1, C-2, C-3…</span></div>
              <div class="campo"><label for="desde">Desde el número</label>
                <input type="number" id="desde" name="desde" min="1" value="1"></div>
              <div class="campo"><label for="hasta">Hasta el número</label>
                <input type="number" id="hasta" name="hasta" min="1" max="500" value="20"></div>
              <div class="campo"><label for="metros">Metros de construcción (opcional)</label>
                <input type="number" id="metros" name="metros" step="0.01" min="0" value="0"></div>
            </div>
            <div class="aviso-caja info"><?= ico('info', 19) ?>
              <div>El coeficiente de participación se reparte por igual entre todas. Puede ajustarlo después vivienda por vivienda.</div>
            </div>
          </div>
          <div class="tarjeta-pie fila-entre">
            <a class="btn btn-claro btn-sm" href="<?= e(url('/admin/onboarding', ['paso' => 3])) ?>">Ya las tengo, saltar</a>
            <button class="btn btn-oro" type="submit"><?= ico('casa', 17) ?> Crear las viviendas</button>
          </div>
        </form>

      <?php elseif ($paso === 3): ?>
        <form method="post">
          <?= csrf() ?>
          <input type="hidden" name="accion" value="cuota">
          <div class="tarjeta-cab"><h3>Paso 3 · Cuota de mantenimiento</h3></div>
          <div class="tarjeta-cuerpo">
            <p class="texto-2" style="font-size:.93rem">Defina la cuota mensual que paga cada vivienda. Podrá agregar más conceptos después.</p>
            <div class="campos">
              <div class="campo"><label for="monto">Monto mensual por vivienda *</label>
                <input type="number" id="monto" name="monto" step="0.01" min="0" required value="750" inputmode="decimal"></div>
              <div class="campo"><label for="dia_vence">Día de vencimiento</label>
                <input type="number" id="dia_vence" name="dia_vence" min="1" max="28" value="10"></div>
              <div class="campo"><label for="mora">Recargo por mora (% mensual)</label>
                <input type="number" id="mora" name="mora" step="0.01" min="0" value="2"></div>
            </div>
            <label class="marca-check">
              <input type="checkbox" name="generar" value="1" checked>
              <span>Emitir de una vez los cargos del mes en curso</span>
            </label>
          </div>
          <div class="tarjeta-pie fila-entre">
            <a class="btn btn-claro btn-sm" href="<?= e(url('/admin/onboarding', ['paso' => 4])) ?>">Saltar</a>
            <button class="btn btn-oro" type="submit"><?= ico('billetera', 17) ?> Guardar la cuota</button>
          </div>
        </form>

      <?php else: ?>
        <form method="post">
          <?= csrf() ?>
          <input type="hidden" name="accion" value="aviso">
          <div class="tarjeta-cab"><h3>Paso 4 · Su primer aviso</h3></div>
          <div class="tarjeta-cuerpo">
            <p class="texto-2" style="font-size:.93rem">Dé la bienvenida a los residentes. El aviso aparecerá en su portal.</p>
            <div class="campo"><label for="titulo">Título</label>
              <input type="text" id="titulo" name="titulo" maxlength="190" value="Bienvenidos al nuevo portal del residencial"></div>
            <div class="campo"><label for="cuerpo">Mensaje</label>
              <textarea id="cuerpo" name="cuerpo" rows="6" maxlength="3000">Estimados vecinos:

A partir de hoy pueden consultar su estado de cuenta, reportar sus pagos, autorizar visitas con código QR y reservar las áreas comunes desde el portal del residente.

Quedamos atentos a cualquier consulta.

La administración</textarea></div>
          </div>
          <div class="tarjeta-pie fila-entre">
            <button class="btn btn-fantasma btn-sm" type="submit" name="omitir" value="1">Terminar sin publicar</button>
            <button class="btn btn-oro" type="submit"><?= ico('megafono', 17) ?> Publicar y terminar</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
