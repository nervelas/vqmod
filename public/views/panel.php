<?php
/** @var array<string,mixed> $resumen @var list<array<string,mixed>> $recientes
 *  @var list<array<string,mixed>> $pendientes @var string $certificador
 *  @var string $ambiente @var string $desde @var string $hasta */
use Fel\Web\Sesion;
use Fel\Web\Vista;
?>
<div class="encabezado-pagina">
  <h1>Panel</h1>
  <a class="boton" href="index.php?r=nuevo">Emitir documento</a>
</div>

<div class="rejilla k4" style="margin-bottom:18px">
  <div class="indicador">
    <span>Documentos certificados</span>
    <strong><?= (int) $resumen['documentos'] ?></strong>
  </div>
  <div class="indicador">
    <span>Base gravable</span>
    <strong><?= Vista::moneda($resumen['gravable']) ?></strong>
  </div>
  <div class="indicador">
    <span>IVA débito fiscal</span>
    <strong><?= Vista::moneda($resumen['iva']) ?></strong>
  </div>
  <div class="indicador">
    <span>Total facturado</span>
    <strong><?= Vista::moneda($resumen['total']) ?></strong>
  </div>
</div>

<p style="color:#5b6875;font-size:12.5px;margin:-8px 0 18px">
  Período <?= Vista::e(date('d/m/Y', strtotime($desde))) ?> al <?= Vista::e(date('d/m/Y', strtotime($hasta))) ?>.
  Estos totales sirven para conciliar la declaración mensual de IVA; el dato oficial es el que SAT registra.
</p>

<?php if ($pendientes !== []): ?>
  <div class="tarjeta">
    <h2>Documentos en contingencia (<?= count($pendientes) ?>)</h2>
    <p style="font-size:12.5px;color:#5b6875;margin-top:-8px">
      No se pudieron certificar por falta de comunicación. Se reintentan solos si tiene el cron configurado,
      o puede reintentarlos aquí.
    </p>
    <table class="datos">
      <thead>
        <tr><th>#</th><th>Tipo</th><th>Receptor</th><th class="num">Total</th><th>Intentos</th><th>Último error</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($pendientes as $documento): ?>
          <tr>
            <td><a href="index.php?r=ver&amp;id=<?= (int) $documento['id'] ?>"><?= (int) $documento['id'] ?></a></td>
            <td><?= Vista::e($documento['tipo']) ?></td>
            <td><?= Vista::e($documento['receptor_nombre']) ?></td>
            <td class="num"><?= Vista::moneda($documento['gran_total']) ?></td>
            <td><?= (int) $documento['intentos'] ?></td>
            <td style="font-size:12px;color:#b32431"><?= Vista::e(mb_substr((string) $documento['error_mensaje'], 0, 90)) ?></td>
            <td>
              <form method="post" action="index.php?r=reintentar" style="margin:0">
                <input type="hidden" name="csrf" value="<?= Vista::e(Sesion::tokenCsrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $documento['id'] ?>">
                <button class="boton pequeno secundario" type="submit">Reintentar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div class="tarjeta">
  <h2>Últimos documentos</h2>
  <?php if ($recientes === []): ?>
    <p class="vacio">Todavía no ha emitido documentos.</p>
  <?php else: ?>
    <table class="datos">
      <thead>
        <tr><th>#</th><th>Tipo</th><th>Serie-Número</th><th>Receptor</th><th>Fecha</th><th class="num">Total</th><th>Estado</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recientes as $documento): ?>
          <tr>
            <td><a href="index.php?r=ver&amp;id=<?= (int) $documento['id'] ?>"><?= (int) $documento['id'] ?></a></td>
            <td><?= Vista::e($documento['tipo']) ?></td>
            <td><?= Vista::e(($documento['serie'] ?: '—') . '-' . ($documento['numero'] ?: '—')) ?></td>
            <td><?= Vista::e($documento['receptor_nombre']) ?></td>
            <td><?= Vista::e(Vista::fecha((string) $documento['fecha_emision'])) ?></td>
            <td class="num"><?= Vista::moneda($documento['gran_total']) ?></td>
            <td><span class="etiqueta <?= Vista::e($documento['estado']) ?>"><?= Vista::e($documento['estado']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
