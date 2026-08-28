<?php
/** @var array $clientes, $resumen; string $q */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Clientes');
View::set('subtitulo', (int)$resumen['n'] . ' cliente(s) · ' . money($resumen['t'], (string)$r['simbolo']) . ' en compras');
$s = (string)($r['simbolo'] ?? 'Q');

View::start('acciones');
?>
<button class="bt bt--oro" type="button" data-modal="modalCliente" data-limpiar="1" data-titulo="Nuevo cliente">
  <?= icon('plus') ?><span>Nuevo</span>
</button>
<?php View::stop(); ?>

<form class="filtros-barra" method="get" action="<?= e(url('panel/clientes')) ?>">
  <div class="campo-p" style="flex:1 1 260px">
    <label for="buscarPanel">Buscar cliente</label>
    <input type="search" id="buscarPanel" name="q" value="<?= e($q) ?>" placeholder="Nombre, teléfono o correo">
  </div>
  <button class="bt bt--linea" type="submit"><?= icon('search') ?> Buscar</button>
</form>

<div class="tarjeta-p tarjeta-p--plana">
  <?php if (!$clientes): ?>
    <div class="vacio-p">
      <?= icon('users', 'ico-lg') ?>
      <h3><?= $q !== '' ? 'Sin resultados' : 'Aún no tienes clientes guardados' ?></h3>
      <p>Los clientes de delivery y para llevar se guardan solos con cada pedido.</p>
    </div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Cliente</th><th>Teléfono</th><th class="num">Pedidos</th><th class="num">Total gastado</th><th class="num">Puntos</th><th>Último pedido</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($clientes as $c): ?>
            <tr>
              <td>
                <strong><?= e((string)$c['nombre']) ?></strong>
                <?php if (!empty($c['direccion'])): ?>
                  <br><small style="color:var(--p-tenue)"><?= e(mb_strimwidth((string)$c['direccion'], 0, 46, '…')) ?></small>
                <?php endif; ?>
              </td>
              <td class="mono"><?= e((string)$c['telefono']) ?></td>
              <td class="num"><?= (int)$c['pedidos'] ?></td>
              <td class="num"><strong><?= e(money($c['total_gastado'], $s)) ?></strong></td>
              <td class="num"><span class="insignia insignia--oro"><?= (int)$c['puntos'] ?></span></td>
              <td style="color:var(--p-tenue);font-size:13px"><?= e($c['ultimo_pedido'] ? dt((string)$c['ultimo_pedido'], 'd/m/Y') : '—') ?></td>
              <td class="tabla__acciones">
                <a class="bt bt--sm bt--suave" href="<?= e(url('panel/clientes/' . $c['id'])) ?>"><?= icon('eye', 'ico-sm') ?></a>
                <button class="bt bt--sm bt--suave" type="button" data-modal="modalCliente" data-titulo="Editar cliente"
                        data-rellenar='<?= e(json_encode([
                            'id' => (int)$c['id'], 'nombre' => $c['nombre'], 'telefono' => $c['telefono'],
                            'email' => $c['email'], 'direccion' => $c['direccion'],
                            'referencia' => $c['referencia'], 'notas' => $c['notas'],
                        ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal-p" id="modalCliente" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(480px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/clientes/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nuevo cliente</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="cNom">Nombre *</label><input type="text" id="cNom" name="nombre" required maxlength="120"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="cTel2">Teléfono *</label><input type="tel" id="cTel2" name="telefono" required maxlength="30" inputmode="tel"></div>
          <div class="campo-p"><label for="cMail">Correo</label><input type="email" id="cMail" name="email" maxlength="190"></div>
        </div>
        <div class="campo-p"><label for="cDir2">Dirección</label><textarea id="cDir2" name="direccion" maxlength="255"></textarea></div>
        <div class="campo-p"><label for="cRef2">Referencia</label><input type="text" id="cRef2" name="referencia" maxlength="255"></div>
        <div class="campo-p"><label for="cNotas">Notas internas</label><input type="text" id="cNotas" name="notas" maxlength="255" placeholder="Ej. alérgico a los mariscos"></div>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>
