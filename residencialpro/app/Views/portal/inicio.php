<?php use App\Models\Cuota; ?>
<section class="portal-hero mb-3">
  <div class="fila-entre" style="align-items:flex-start">
    <div>
      <h2>Hola, <?= e(explode(' ', (string) (usuarioActual()['nombre'] ?? 'vecino'))[0]) ?></h2>
      <p>Vivienda <?= e($casaActual['codigo'] ?? '') ?><?= !empty($casaActual['fase']) ? ' · ' . e($casaActual['fase']) : '' ?></p>
      <div class="saldo"><?= e(q($saldo)) ?></div>
      <p>
        <?php if ($saldo <= 0.009): ?>
          Su vivienda está <strong style="color:#8FD3AD">solvente</strong>. ¡Gracias por su puntualidad!
        <?php else: ?>
          Saldo pendiente<?= $dias > 0 ? ' · ' . $dias . ' día(s) de atraso' : '' ?>
        <?php endif; ?>
        <?php if ($aFavor > 0.009): ?><br>Tiene <?= e(q($aFavor)) ?> a favor.<?php endif; ?>
      </p>
    </div>
    <div class="fila envolver" style="gap:8px">
      <?php if ($saldo > 0.009): ?>
        <a class="btn btn-oro" href="<?= e(url('/portal/pagar')) ?>"><?= ico('tarjeta', 17) ?> Reportar mi pago</a>
      <?php endif; ?>
      <a class="btn btn-fantasma" style="color:#E9EEE9;border-color:rgba(255,255,255,.26)"
         href="<?= e(url('/doc/estado-cuenta/' . (int) $casaActual['id'])) ?>" target="_blank" rel="noopener">
        <?= ico('archivo', 17) ?> Descargar
      </a>
    </div>
  </div>
</section>

<section class="accesos mb-3">
  <a class="acceso" href="<?= e(url('/portal/visitas/nueva')) ?>"><?= ico('qr', 26) ?> Autorizar visita</a>
  <a class="acceso" href="<?= e(url('/portal/reservas')) ?>"><?= ico('calendario', 26) ?> Reservar área</a>
  <a class="acceso" href="<?= e(url('/portal/pagar')) ?>"><?= ico('tarjeta', 26) ?> Reportar pago</a>
  <a class="acceso" href="<?= e(url('/portal/incidencias')) ?>"><?= ico('llave_inglesa', 26) ?> Reportar avería</a>
  <a class="acceso" href="<?= e(url('/portal/avisos')) ?>">
    <?= ico('megafono', 26) ?> Avisos
    <?php if ($sinLeer > 0): ?><span class="chip grave"><?= (int) $sinLeer ?> sin leer</span><?php endif; ?>
  </a>
  <a class="acceso" href="<?= e(url('/portal/mensajes')) ?>"><?= ico('chat', 26) ?> Escribir</a>
</section>

<?php if ($votaciones !== []): ?>
  <div class="aviso-caja info mb-3">
    <?= ico('voto', 20) ?>
    <div class="crecer"><strong>Hay una votación abierta</strong>
      <?= e(recortar((string) $votaciones[0]['titulo'], 90)) ?></div>
    <a class="btn btn-sm btn-oro" href="<?= e(url('/portal/votaciones')) ?>">Votar</a>
  </div>
<?php endif; ?>

<section class="rejilla rejilla-2 mb-3">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Cargos pendientes</h3>
      <a class="btn btn-sm btn-fantasma" href="<?= e(url('/portal/estado-cuenta')) ?>">Ver todo</a>
    </div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($cargos === []): ?>
        <div class="vacio" style="padding:26px 12px">
          <?= ico('checkCirculo', 38) ?>
          <h3>Sin cargos pendientes</h3>
          <p style="margin:0">Su cuenta está al día.</p>
        </div>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($cargos as $c): $s = Cuota::saldoCargo($c);
            $dias = (int) floor((time() - strtotime((string) $c['fecha_vence'])) / 86400); ?>
            <li class="item-lista">
              <div class="crecer">
                <b><?= e($c['descripcion']) ?></b>
                <div class="meta">
                  Vence <?= e(fecha((string) $c['fecha_vence'])) ?>
                  <?php if ((float) $c['mora'] > 0): ?> · mora <?= e(q((float) $c['mora'])) ?><?php endif; ?>
                </div>
              </div>
              <div style="text-align:right">
                <b class="num"><?= e(q($s)) ?></b>
                <?php if ($dias > 0): ?><div><span class="chip <?= e(semaforoMora($dias)) ?>"><?= $dias ?> d</span></div><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Avisos recientes</h3>
      <a class="btn btn-sm btn-fantasma" href="<?= e(url('/portal/avisos')) ?>">Ver todos</a>
    </div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($avisos === []): ?>
        <p class="texto-3 centrado" style="padding:22px 0;margin:0">No hay avisos publicados.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($avisos as $a): ?>
            <li class="item-lista">
              <span style="color:var(--acento-3)"><?= ico($a['prioridad'] === 'urgente' ? 'alerta' : 'megafono', 20) ?></span>
              <div class="crecer">
                <b><a href="<?= e(url('/portal/avisos/' . (int) $a['id'])) ?>"><?= e(recortar((string) $a['titulo'], 52)) ?></a></b>
                <div class="meta"><?= e(hace((string) $a['publicar_en'])) ?></div>
              </div>
              <?php if ($a['prioridad'] !== 'normal'): ?>
                <span class="chip <?= $a['prioridad'] === 'urgente' ? 'grave' : 'aviso' ?>"><?= e(ucfirst((string) $a['prioridad'])) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="rejilla rejilla-3">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Mis visitas autorizadas</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php $activas = array_filter($visitas, static fn($v) => $v['estado'] === 'activo'); ?>
      <?php if ($activas === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0;margin:0">Sin visitas autorizadas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($activas as $v): ?>
            <li class="item-lista">
              <div class="crecer">
                <b><?= e(recortar((string) $v['visitante'], 26)) ?></b>
                <div class="meta">Código <b style="color:var(--acento-3)"><?= e($v['codigo']) ?></b></div>
              </div>
              <a class="btn btn-sm btn-fantasma" href="<?= e(url('/portal/visitas')) ?>" aria-label="Ver el código QR de <?= e($v['visitante']) ?>"><?= ico('qr', 15) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a class="btn btn-claro btn-bloque mt-2" href="<?= e(url('/portal/visitas/nueva')) ?>"><?= ico('mas', 16) ?> Autorizar una visita</a>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Mis reservas</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($reservas === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0;margin:0">Sin reservas próximas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($reservas as $r): ?>
            <li class="item-lista">
              <span style="color:var(--acento-3)"><?= ico('calendario', 20) ?></span>
              <div class="crecer">
                <b><?= e($r['area']) ?></b>
                <div class="meta"><?= e(fecha((string) $r['fecha'])) ?> · <?= e(hora((string) $r['hora_desde'])) ?></div>
              </div>
              <span class="chip <?= e(estadoBadge((string) $r['estado'])) ?>"><?= e(ucfirst((string) $r['estado'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a class="btn btn-claro btn-bloque mt-2" href="<?= e(url('/portal/reservas')) ?>"><?= ico('calendario', 16) ?> Reservar un área</a>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Próximas actividades</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($eventos === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0;margin:0">Sin actividades programadas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($eventos as $ev): ?>
            <li class="item-lista">
              <span class="chip oro"><?= e(date('d/m', (int) strtotime((string) $ev['inicio']))) ?></span>
              <div class="crecer">
                <b><?= e(recortar((string) $ev['titulo'], 34)) ?></b>
                <div class="meta"><?= e($ev['lugar'] ?? '') ?> · <?= e(hora((string) $ev['inicio'])) ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>
</section>
