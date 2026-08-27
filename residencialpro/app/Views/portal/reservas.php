<?php
$primerDia  = (int) strtotime($mes . '-01');
$diasMes    = (int) date('t', $primerDia);
$inicioSem  = (int) date('w', $primerDia);
$ocupadasPorDia = [];
foreach ($ocupadas as $o) {
    $ocupadasPorDia[(string) $o['fecha']][] = $o;
}
$diasArea = $area !== null ? array_filter(array_map('trim', explode(',', (string) $area['dias'])), static fn($d) => $d !== '') : [];
?>
<div class="rejilla mb-3" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Disponibilidad</h3>
      <form method="get" class="fila" style="gap:8px">
        <label class="solo-lectores" for="sel-area">Área común</label>
        <select id="sel-area" name="area" data-auto-enviar style="max-width:220px">
          <?php foreach ($areas as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= $areaId === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="solo-lectores" for="sel-mes">Mes</label>
        <input type="month" id="sel-mes" name="mes" value="<?= e($mes) ?>" data-auto-enviar style="max-width:170px">
        <button class="btn btn-claro btn-sm" type="submit" aria-label="Ver la disponibilidad"><?= ico('buscar', 15) ?></button>
      </form>
    </div>
    <div class="tarjeta-cuerpo">
      <?php if ($area === null): ?>
        <p class="texto-3 centrado" style="padding:30px 0">Todavía no hay áreas comunes configuradas.</p>
      <?php else: ?>
        <div class="calendario mb-2">
          <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $d): ?>
            <div class="cal-dia-nom"><?= e($d) ?></div>
          <?php endforeach; ?>
          <?php for ($i = 0; $i < $inicioSem; $i++): ?><div></div><?php endfor; ?>
          <?php for ($d = 1; $d <= $diasMes; $d++):
            $fecha = $mes . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $dow   = (int) date('w', (int) strtotime($fecha));
            $pasado = strtotime($fecha) < strtotime(date('Y-m-d'));
            $noDisponible = $pasado || ($diasArea !== [] && !in_array((string) $dow, $diasArea, true));
            $tiene = isset($ocupadasPorDia[$fecha]);
            $hoy   = $fecha === date('Y-m-d');
          ?>
            <button type="button" class="cal-dia <?= $noDisponible ? 'no' : '' ?> <?= $hoy ? 'hoy' : '' ?> <?= $tiene ? 'ocupado' : '' ?>"
                    <?= $noDisponible ? 'disabled' : '' ?> data-fecha="<?= e($fecha) ?>"
                    aria-label="<?= e(fecha($fecha)) ?><?= $tiene ? ' — con reservas' : '' ?>">
              <span class="n"><?= $d ?></span>
              <?php if ($tiene): ?><span class="pt"></span><?php endif; ?>
            </button>
          <?php endfor; ?>
        </div>
        <div class="fila envolver texto-3" style="gap:16px;font-size:.8rem">
          <span class="fila" style="gap:6px"><i style="width:9px;height:9px;border-radius:50%;background:var(--grave);display:block"></i> Con reservas</span>
          <span class="fila" style="gap:6px"><i style="width:9px;height:9px;border-radius:50%;background:var(--fondo-2);border:1px solid var(--borde);display:block"></i> No disponible</span>
        </div>

        <?php if ($ocupadas !== []): ?>
          <h4 class="mt-3">Reservas del mes</h4>
          <ul class="lista-limpia">
            <?php foreach ($ocupadas as $o): ?>
              <li class="item-lista" style="padding:9px 0">
                <span class="chip oro"><?= e(date('d/m', (int) strtotime((string) $o['fecha']))) ?></span>
                <div class="crecer" style="font-size:.88rem">
                  <?= e(hora((string) $o['hora_desde'])) ?> a <?= e(hora((string) $o['hora_hasta'])) ?>
                  <span class="texto-3">· casa <?= e($o['casa']) ?></span>
                </div>
                <span class="chip <?= e(estadoBadge((string) $o['estado'])) ?>"><?= e(ucfirst((string) $o['estado'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </article>

  <div class="columna">
    <?php if ($area !== null): ?>
      <article class="tarjeta">
        <?php if (!empty($area['foto'])): ?>
          <img src="<?= e(subida($area['foto'], 'areas')) ?>" alt="<?= e($area['nombre']) ?>" style="width:100%;height:150px;object-fit:cover">
        <?php endif; ?>
        <div class="tarjeta-cuerpo">
          <h3 style="margin-bottom:4px"><?= e($area['nombre']) ?></h3>
          <p class="texto-2" style="font-size:.9rem"><?= e($area['descripcion'] ?? '') ?></p>
          <table class="tabla">
            <tbody>
              <tr><td class="texto-3">Horario</td><td class="d"><?= e(hora((string) $area['hora_desde'])) ?> a <?= e(hora((string) $area['hora_hasta'])) ?></td></tr>
              <?php if ((int) $area['capacidad'] > 0): ?>
                <tr><td class="texto-3">Capacidad</td><td class="d"><?= (int) $area['capacidad'] ?> personas</td></tr>
              <?php endif; ?>
              <tr><td class="texto-3">Costo</td><td class="d fuerte"><?= (float) $area['costo'] > 0 ? e(q((float) $area['costo'])) : 'Sin costo' ?></td></tr>
              <?php if ((float) $area['deposito'] > 0): ?>
                <tr><td class="texto-3">Depósito</td><td class="d"><?= e(q((float) $area['deposito'])) ?></td></tr>
              <?php endif; ?>
              <tr><td class="texto-3">Aprobación</td><td class="d"><?= $area['aprobacion'] === 'automatica' ? 'Inmediata' : 'Requiere confirmación' ?></td></tr>
            </tbody>
          </table>
          <?php if (!empty($area['reglas'])): ?>
            <div class="aviso-caja info mt-2" style="font-size:.85rem">
              <?= ico('info', 18) ?><div><?= nl2br(e((string) $area['reglas'])) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </article>

      <?php if (!$solvente && (int) $area['bloquea_mora'] === 1): ?>
        <div class="aviso-caja alerta">
          <?= ico('alerta', 20) ?>
          <div><strong>Su vivienda tiene saldo pendiente</strong>
            Para reservar esta área es necesario estar solvente.
            <a href="<?= e(url('/portal/pagar')) ?>">Reportar mi pago</a>.</div>
        </div>
      <?php else: ?>
        <form method="post" id="f-reserva">
          <?= csrf() ?>
          <input type="hidden" name="area_id" value="<?= (int) $area['id'] ?>">
          <div class="tarjeta">
            <div class="tarjeta-cab"><h3>Solicitar la reserva</h3></div>
            <div class="tarjeta-cuerpo">
              <div class="campo">
                <label for="fecha">Fecha *</label>
                <input type="date" id="fecha" name="fecha" required min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>">
              </div>
              <div class="campos">
                <div class="campo"><label for="hora_desde">Desde *</label>
                  <input type="time" id="hora_desde" name="hora_desde" required value="<?= e(substr((string) $area['hora_desde'], 0, 5)) ?>"></div>
                <div class="campo"><label for="hora_hasta">Hasta *</label>
                  <input type="time" id="hora_hasta" name="hora_hasta" required value="<?= e(substr((string) $area['hora_hasta'], 0, 5)) ?>"></div>
              </div>
              <div class="campo">
                <label for="personas">Número de personas</label>
                <input type="number" id="personas" name="personas" min="1" max="<?= (int) $area['capacidad'] > 0 ? (int) $area['capacidad'] : 200 ?>" value="10">
              </div>
              <div class="campo">
                <label for="motivo">Motivo</label>
                <input type="text" id="motivo" name="motivo" maxlength="190" placeholder="Cumpleaños, reunión familiar…">
              </div>
            </div>
            <div class="tarjeta-pie fila-fin">
              <button class="btn btn-oro btn-bloque" type="submit"><?= ico('calendario', 17) ?> Solicitar reserva</button>
            </div>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<article class="tarjeta">
  <div class="tarjeta-cab"><h3>Mis reservas</h3></div>
  <div class="tarjeta-cuerpo compacto">
    <?php if ($misReservas === []): ?>
      <p class="texto-3 centrado" style="padding:22px 0;margin:0">Todavía no ha reservado ninguna área.</p>
    <?php else: ?>
      <ul class="lista-limpia">
        <?php foreach ($misReservas as $r): ?>
          <li class="item-lista">
            <span style="color:var(--acento-3)"><?= ico('calendario', 20) ?></span>
            <div class="crecer">
              <b><?= e($r['area']) ?></b>
              <div class="meta">
                <?= e(fecha((string) $r['fecha'])) ?> · <?= e(hora((string) $r['hora_desde'])) ?> a <?= e(hora((string) $r['hora_hasta'])) ?>
                <?= (float) $r['costo'] > 0 ? ' · ' . e(q((float) $r['costo'])) : '' ?>
              </div>
              <?php if (!empty($r['motivo_rechazo'])): ?>
                <div class="meta texto-grave"><?= e($r['motivo_rechazo']) ?></div>
              <?php endif; ?>
            </div>
            <span class="chip <?= e(estadoBadge((string) $r['estado'])) ?>"><?= e(ucfirst((string) $r['estado'])) ?></span>
            <?php if (in_array($r['estado'], ['pendiente', 'aprobada'], true) && strtotime((string) $r['fecha']) >= strtotime(date('Y-m-d'))): ?>
              <form method="post" action="<?= e(url('/portal/reservas/' . (int) $r['id'] . '/cancelar')) ?>"
                    data-confirmar="Se liberará el horario para otros residentes."
                    data-confirmar-titulo="¿Cancelar la reserva?" data-confirmar-boton="Sí, cancelar">
                <?= csrf() ?>
                <button class="btn btn-sm btn-fantasma" type="submit" aria-label="Cancelar"><?= ico('equis', 15) ?></button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</article>

<script<?= nonce() ?>>
document.querySelectorAll('.cal-dia[data-fecha]').forEach(function (b) {
  b.addEventListener('click', function () {
    var f = document.getElementById('fecha');
    if (!f) return;
    f.value = b.dataset.fecha;
    document.querySelectorAll('.cal-dia').forEach(function (x) { x.classList.remove('sel'); });
    b.classList.add('sel');
    document.getElementById('f-reserva').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
});
</script>
