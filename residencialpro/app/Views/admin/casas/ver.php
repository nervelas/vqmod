<?php use App\Core\Vista; ?>
<div class="fila-entre mb-3">
  <a class="btn btn-claro btn-sm" href="<?= e(url('/admin/casas')) ?>"><?= ico('flechaIzq', 16) ?> Volver al listado</a>
  <div class="fila" style="gap:8px">
    <a class="btn btn-claro" href="<?= e(url('/doc/estado-cuenta/' . (int) $casa['id'])) ?>" target="_blank" rel="noopener"><?= ico('archivo', 17) ?> Estado de cuenta</a>
    <a class="btn btn-claro" href="<?= e(url('/doc/solvencia/' . (int) $casa['id'])) ?>" target="_blank" rel="noopener"><?= ico('escudo', 17) ?> Solvencia</a>
    <?php if ($saldo > 0.009): ?>
      <a class="btn btn-claro" href="<?= e(url('/doc/carta/' . (int) $casa['id'])) ?>" target="_blank" rel="noopener"><?= ico('correo', 17) ?> Carta de cobro</a>
    <?php endif; ?>
    <a class="btn btn-oro" href="<?= e(url('/admin/pagos/nuevo', ['casa' => (int) $casa['id']])) ?>"><?= ico('tarjeta', 17) ?> Registrar pago</a>
    <?php if (esRol('admin')): ?>
      <a class="btn btn-claro btn-icono" href="<?= e(url('/admin/casas/' . (int) $casa['id'] . '/editar')) ?>" aria-label="Editar vivienda"><?= ico('editar', 17) ?></a>
    <?php endif; ?>
  </div>
</div>

<section class="rejilla mb-3" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Estado de cuenta</h3>
      <span class="chip <?= $saldo > 0.009 ? e(semaforoMora($dias)) : 'ok' ?>">
        <?= $saldo > 0.009 ? 'Con saldo · ' . $dias . ' días' : 'Solvente' ?>
      </span>
    </div>
    <?php if ($cargos === []): ?>
      <?= Vista::parcial('partials/vacio', ['icono' => 'recibo', 'titulo' => 'Sin cargos registrados', 'texto' => 'Genere los cargos del período desde el módulo de cuotas.']) ?>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla apilar">
          <thead><tr><th>Concepto</th><th class="c">Vence</th><th class="d">Cargo</th><th class="d">Mora</th><th class="d">Saldo</th><th class="c">Estado</th></tr></thead>
          <tbody>
            <?php foreach ($cargos as $g):
              $saldoCargo = App\Models\Cuota::saldoCargo($g); ?>
              <tr>
                <td data-et="Concepto"><?= e($g['descripcion']) ?></td>
                <td data-et="Vence" class="c texto-3"><?= e(fecha((string) $g['fecha_vence'])) ?></td>
                <td data-et="Cargo" class="d num"><?= e(q((float) $g['monto'])) ?></td>
                <td data-et="Mora" class="d num"><?= (float) $g['mora'] > 0 ? e(q((float) $g['mora'])) : '—' ?></td>
                <td data-et="Saldo" class="d num fuerte"><?= e(q($saldoCargo)) ?></td>
                <td data-et="Estado" class="c"><span class="chip <?= e(estadoBadge((string) $g['estado'])) ?>"><?= e(ucfirst((string) $g['estado'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="4">Saldo total pendiente</td><td class="d num"><?= e(q($saldo)) ?></td><td></td></tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <div class="columna">
    <article class="tarjeta">
      <div class="tarjeta-cuerpo">
        <div class="mayus">Saldo pendiente</div>
        <div class="kpi-valor" style="margin-top:6px"><?= e(q($saldo)) ?></div>
        <?php if ($aFavor > 0.009): ?>
          <div class="chip ok mt-1">Saldo a favor: <?= e(q($aFavor)) ?></div>
        <?php endif; ?>
        <div class="mt-2">
          <?php foreach (['Por vencer' => 'corriente', '1-30 días' => 'd30', '31-60 días' => 'd60', '61-90 días' => 'd90', '+90 días' => 'd120'] as $et => $k):
            if ((float) $antiguedad[$k] <= 0) { continue; } ?>
            <div class="fila-entre" style="padding:4px 0;font-size:.86rem">
              <span class="texto-2"><?= e($et) ?></span><b class="num"><?= e(q((float) $antiguedad[$k])) ?></b>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </article>

    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Datos de la vivienda</h3></div>
      <div class="tarjeta-cuerpo compacto">
        <table class="tabla">
          <tbody>
            <tr><td class="texto-3">Fase</td><td class="d"><?= e($casa['fase']) ?></td></tr>
            <tr><td class="texto-3">Calle</td><td class="d"><?= e($casa['calle'] ?? '—') ?></td></tr>
            <tr><td class="texto-3">Tipo</td><td class="d"><?= e(ucfirst((string) $casa['tipo'])) ?></td></tr>
            <tr><td class="texto-3">Metros</td><td class="d num"><?= e(number_format((float) $casa['metros'], 2)) ?> m²</td></tr>
            <tr><td class="texto-3">Coeficiente</td><td class="d num"><?= e(number_format((float) $casa['coeficiente'], 5)) ?>%</td></tr>
            <tr><td class="texto-3">Parqueos</td><td class="d num"><?= (int) $casa['parqueos'] ?></td></tr>
            <tr><td class="texto-3">Bodegas</td><td class="d num"><?= (int) $casa['bodegas'] ?></td></tr>
            <tr><td class="texto-3">Estado</td><td class="d"><span class="chip <?= e(estadoBadge((string) $casa['estado'])) ?>"><?= e(ucfirst((string) $casa['estado'])) ?></span></td></tr>
          </tbody>
        </table>
      </div>
    </article>
  </div>
</section>

<section class="rejilla rejilla-2 mb-3">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Residentes</h3>
      <?php if (esRol('admin')): ?>
        <a class="btn btn-sm btn-claro" href="<?= e(url('/admin/residentes/nuevo', ['casa' => (int) $casa['id']])) ?>"><?= ico('mas', 15) ?> Agregar</a>
      <?php endif; ?>
    </div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($residentes === []): ?>
        <p class="texto-3 centrado" style="padding:20px 0;margin:0">Sin residentes registrados.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($residentes as $r): ?>
            <li class="item-lista">
              <span class="avatar"><?= e(iniciales((string) $r['nombre'])) ?></span>
              <div class="crecer">
                <b><?= e($r['nombre']) ?></b>
                <div class="meta">
                  <?= e(ucfirst((string) $r['tipo'])) ?>
                  <?= !empty($r['telefono']) ? ' · ' . e($r['telefono']) : '' ?>
                  <?= !empty($r['correo']) ? ' · ' . e($r['correo']) : '' ?>
                </div>
              </div>
              <?php if ((int) $r['activo'] === 0): ?><span class="chip neutro">Inactivo</span><?php endif; ?>
              <?php if (!empty($r['telefono'])): ?>
                <a class="btn btn-sm btn-fantasma" target="_blank" rel="noopener"
                   href="<?= e(whatsapp((string) $r['telefono'])) ?>" aria-label="WhatsApp"><?= ico('chat', 15) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Vehículos y mascotas</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($vehiculos === [] && $mascotas === []): ?>
        <p class="texto-3 centrado" style="padding:20px 0;margin:0">Sin vehículos ni mascotas registradas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($vehiculos as $v): ?>
            <li class="item-lista">
              <span style="color:var(--arcilla)"><?= ico('carro', 20) ?></span>
              <div class="crecer">
                <b><?= e($v['placa']) ?></b>
                <div class="meta"><?= e(trim($v['marca'] . ' ' . $v['linea'] . ' · ' . $v['color'])) ?></div>
              </div>
            </li>
          <?php endforeach; ?>
          <?php foreach ($mascotas as $m): ?>
            <li class="item-lista">
              <span style="color:var(--arcilla)"><?= ico('mascota', 20) ?></span>
              <div class="crecer">
                <b><?= e($m['nombre']) ?></b>
                <div class="meta"><?= e(trim(($m['especie'] ?? '') . ' · ' . ($m['raza'] ?? ''), ' ·')) ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($empleados !== []): ?>
        <div class="mayus mt-2">Personal autorizado</div>
        <ul class="lista-limpia">
          <?php foreach ($empleados as $em): ?>
            <li class="item-lista">
              <span style="color:var(--arcilla)"><?= ico('maletin', 20) ?></span>
              <div class="crecer">
                <b><?= e($em['nombre']) ?></b>
                <div class="meta"><?= e($em['puesto'] ?? '') ?> · <?= e(hora((string) $em['hora_desde'])) ?> a <?= e(hora((string) $em['hora_hasta'])) ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="rejilla rejilla-2">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Últimos pagos</h3>
      <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/pagos', ['casa' => (int) $casa['id']])) ?>">Ver todos</a>
    </div>
    <?php if ($pagos === []): ?>
      <p class="texto-3 centrado" style="padding:26px 0;margin:0">Sin pagos registrados.</p>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Recibo</th><th>Fecha</th><th>Forma</th><th class="d">Monto</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($pagos as $p): ?>
              <tr>
                <td class="fuerte"><?= e($p['recibo'] ?? '—') ?></td>
                <td class="texto-3"><?= e(fecha((string) $p['fecha'])) ?></td>
                <td><?= e(ucfirst((string) $p['metodo'])) ?></td>
                <td class="d num"><?= e(q((float) $p['monto'])) ?></td>
                <td class="d">
                  <?php if ($p['estado'] === 'aprobado'): ?>
                    <a class="btn btn-sm btn-fantasma" href="<?= e(url('/doc/recibo/' . (int) $p['id'])) ?>" target="_blank" rel="noopener" aria-label="Recibo PDF"><?= ico('archivo', 15) ?></a>
                  <?php else: ?>
                    <span class="chip <?= e(estadoBadge((string) $p['estado'])) ?>"><?= e(ucfirst((string) $p['estado'])) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Últimas visitas</h3>
      <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/visitas', ['casa' => (int) $casa['id']])) ?>">Ver todas</a>
    </div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($visitas === []): ?>
        <p class="texto-3 centrado" style="padding:20px 0;margin:0">Sin visitas registradas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($visitas as $v): ?>
            <li class="item-lista">
              <span class="avatar sm"><?= e(iniciales((string) $v['visitante'])) ?></span>
              <div class="crecer">
                <b><?= e(recortar((string) $v['visitante'], 30)) ?></b>
                <div class="meta"><?= e(fechahora((string) $v['entrada'])) ?><?= !empty($v['placa']) ? ' · ' . e($v['placa']) : '' ?></div>
              </div>
              <span class="chip <?= $v['salida'] ? 'neutro' : 'info' ?>"><?= $v['salida'] ? 'Salió' : 'Adentro' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>
</section>
