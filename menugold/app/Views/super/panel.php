<?php
/** @var array $restaurantes, $porVencer, $resumen, $ultimosMensajes */
use MenuGold\Core\View;
View::set('titulo', 'Escritorio de la plataforma');
View::set('subtitulo', $resumen['total'] . ' restaurante(s) · ' . $resumen['usuarios'] . ' usuario(s)');
?>
<div class="rejilla rejilla--4" style="margin-bottom:18px">
  <?php foreach ([
    ['Restaurantes activos', (string)$resumen['activos'], 'store', 'ok'],
    ['Ventas del mes (todos)', money($resumen['ventas_mes'], 'Q'), 'money', ''],
    ['Pedidos de hoy', (string)$resumen['pedidos_hoy'], 'receipt', ''],
    ['Mensajes sin leer', (string)$resumen['mensajes'], 'mail', $resumen['mensajes'] > 0 ? 'alerta' : 'ok'],
  ] as $k): ?>
    <div class="kpi <?= $k[3] ? 'kpi--' . $k[3] : '' ?>">
      <div class="kpi__icono"><?= icon($k[2]) ?></div>
      <div class="kpi__etiqueta"><?= e($k[0]) ?></div>
      <div class="kpi__valor"><?= e($k[1]) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($porVencer): ?>
  <div class="tarjeta-p" style="border-color:var(--p-aviso)">
    <div class="tarjeta-p__cab">
      <h2 class="tarjeta-p__titulo"><?= icon('alert') ?> Suscripciones por vencer</h2>
      <span class="insignia insignia--aviso"><?= count($porVencer) ?></span>
    </div>
    <?php foreach ($porVencer as $v): ?>
      <?php $dias = (int)floor((strtotime((string)$v['vence_el']) - strtotime(date('Y-m-d'))) / 86400); ?>
      <div class="entre" style="padding:9px 0;border-bottom:1px solid var(--p-borde)">
        <span class="crece"><strong><?= e((string)$v['nombre']) ?></strong>
          <span style="color:var(--p-tenue);font-size:13px"> · <?= e((string)$v['plan']) ?></span></span>
        <span class="insignia insignia--<?= $dias < 0 ? 'peligro' : 'aviso' ?>">
          <?= $dias < 0 ? 'Venció hace ' . abs($dias) . ' día(s)' : 'Vence en ' . $dias . ' día(s)' ?>
        </span>
        <a class="bt bt--sm bt--suave" href="<?= e(url('super/restaurantes/' . $v['id'])) ?>"><?= icon('edit', 'ico-sm') ?></a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="tarjeta-p tarjeta-p--plana">
  <div class="tarjeta-p__cab">
    <h2 class="tarjeta-p__titulo"><?= icon('store') ?> Restaurantes</h2>
    <a class="bt bt--sm bt--oro" href="<?= e(url('super/restaurantes/nuevo')) ?>"><?= icon('plus', 'ico-sm') ?> Nuevo</a>
  </div>
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr><th>Restaurante</th><th>Plan</th><th>Estado</th><th class="num">Platillos</th>
        <th class="num">Pedidos (mes)</th><th class="num">Ventas (mes)</th><th>Vence</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($restaurantes as $x): ?>
          <tr>
            <td>
              <strong><?= e((string)$x['nombre']) ?></strong>
              <?php if ((int)$x['demo'] === 1): ?><span class="insignia insignia--oro">Demo</span><?php endif; ?>
              <br><small class="mono" style="color:var(--p-tenue)">/r/<?= e((string)$x['slug']) ?><?= $x['dominio'] ? ' · ' . e((string)$x['dominio']) : '' ?></small>
            </td>
            <td><?= e((string)($x['plan'] ?? '—')) ?></td>
            <td><span class="insignia insignia--<?= $x['estado'] === 'activo' ? 'exito' : ($x['estado'] === 'suspendido' ? 'peligro' : 'aviso') ?>">
                <?= e(ucfirst((string)$x['estado'])) ?></span></td>
            <td class="num"><?= (int)$x['productos'] ?></td>
            <td class="num"><?= (int)$x['pedidos_mes'] ?></td>
            <td class="num"><?= e(money($x['ventas_mes'], 'Q')) ?></td>
            <td style="font-size:13px;color:var(--p-tenue)"><?= e($x['vence_el'] ? dt((string)$x['vence_el'], 'd/m/Y') : '—') ?></td>
            <td class="tabla__acciones">
              <a class="bt bt--sm bt--suave" href="<?= e(url('r/' . $x['slug'])) ?>" target="_blank" title="Ver menú"><?= icon('eye', 'ico-sm') ?></a>
              <a class="bt bt--sm bt--suave" href="<?= e(url('super/entrar/' . $x['id'])) ?>" title="Entrar al panel"><?= icon('login', 'ico-sm') ?></a>
              <a class="bt bt--sm bt--suave" href="<?= e(url('super/restaurantes/' . $x['id'])) ?>" title="Editar"><?= icon('edit', 'ico-sm') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($ultimosMensajes): ?>
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab">
      <h2 class="tarjeta-p__titulo"><?= icon('mail') ?> Últimos interesados</h2>
      <a class="bt bt--sm bt--suave" href="<?= e(url('super/mensajes')) ?>">Ver todos</a>
    </div>
    <?php foreach ($ultimosMensajes as $m): ?>
      <div style="padding:10px 0;border-bottom:1px solid var(--p-borde)">
        <div class="entre">
          <strong><?= e((string)$m['nombre']) ?><?= $m['restaurante'] ? ' · ' . e((string)$m['restaurante']) : '' ?></strong>
          <span style="font-size:12.5px;color:var(--p-tenue)"><?= e(dt((string)$m['creado'])) ?></span>
        </div>
        <div style="font-size:13px;color:var(--p-suave)"><?= e((string)$m['email']) ?> <?= $m['telefono'] ? '· ' . e((string)$m['telefono']) : '' ?></div>
        <p style="margin:5px 0 0;font-size:13.5px;color:var(--p-suave)"><?= e(mb_strimwidth((string)$m['mensaje'], 0, 170, '…')) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
