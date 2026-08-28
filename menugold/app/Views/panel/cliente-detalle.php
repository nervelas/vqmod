<?php
/** @var array $cliente, $historial */
use MenuGold\Core\View;
use MenuGold\Models\Order;
View::set('titulo', (string)$cliente['nombre']);
View::set('subtitulo', (int)$cliente['pedidos'] . ' pedido(s) · ' . money($cliente['total_gastado'], (string)$r['simbolo']));
$s = (string)($r['simbolo'] ?? 'Q');
View::start('acciones');
?>
<a class="bt bt--suave" href="<?= e(url('panel/clientes')) ?>"><?= icon('arrow-left') ?></a>
<?php if (!empty($cliente['telefono'])): ?>
  <a class="bt bt--linea" href="https://wa.me/<?= e(preg_replace('/\D/', '', (string)$cliente['telefono'])) ?>" target="_blank" rel="noopener">
    <?= icon('whatsapp') ?><span class="oculto-movil">WhatsApp</span></a>
<?php endif; ?>
<?php View::stop(); ?>

<div class="rejilla" style="grid-template-columns:minmax(280px,1fr) minmax(0,1.7fr);align-items:start">
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('user') ?> Ficha</h2></div>
    <?php foreach ([
      'Teléfono' => (string)$cliente['telefono'],
      'Correo' => (string)$cliente['email'],
      'Dirección' => (string)$cliente['direccion'],
      'Referencia' => (string)$cliente['referencia'],
      'Notas' => (string)$cliente['notas'],
      'Cliente desde' => dt((string)$cliente['creado'], 'd/m/Y'),
      'Último pedido' => $cliente['ultimo_pedido'] ? dt((string)$cliente['ultimo_pedido'], 'd/m/Y H:i') : '—',
      'Puntos acumulados' => (string)(int)$cliente['puntos'],
    ] as $k => $v): if ($v === '') continue; ?>
      <div class="entre" style="padding:8px 0;border-bottom:1px solid var(--p-borde);font-size:13.5px">
        <span style="color:var(--p-tenue)"><?= e($k) ?></span><span style="text-align:right"><?= e($v) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="tarjeta-p tarjeta-p--plana">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('history') ?> Historial de pedidos</h2></div>
    <?php if (!$historial): ?>
      <div class="vacio-p"><?= icon('receipt', 'ico-lg') ?><p>Este cliente aún no tiene pedidos.</p></div>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Código</th><th>Fecha</th><th>Estado</th><th class="num">Total</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($historial as $o): ?>
              <tr>
                <td class="mono"><?= e((string)$o['codigo']) ?></td>
                <td style="color:var(--p-tenue);font-size:13px"><?= e(dt((string)$o['creado'])) ?></td>
                <td><span class="insignia insignia--<?= $o['estado'] === 'pagado' ? 'exito' : ($o['estado'] === 'anulado' ? 'peligro' : 'aviso') ?>">
                    <?= e(Order::ETIQUETA_ESTADO[$o['estado']] ?? '') ?></span></td>
                <td class="num"><?= e(money($o['total'], $s)) ?></td>
                <td class="tabla__acciones"><a class="bt bt--sm bt--suave" href="<?= e(url('panel/pedidos/' . $o['id'])) ?>"><?= icon('eye', 'ico-sm') ?></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
