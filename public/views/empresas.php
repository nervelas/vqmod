<?php
/** @var list<array{empresa:\Fel\Plataforma\Empresa,uso:array<string,mixed>}> $lista
 *  @var string $csrf @var string $desde @var string $hasta */
use Fel\Web\Vista;
?>
<div class="encabezado-pagina">
  <h1>Empresas</h1>
  <a class="boton" href="index.php?r=empresa_nueva">Agregar empresa</a>
</div>

<p style="color:#5b6875;font-size:12.5px;margin:-8px 0 18px">
  Cada empresa es un cliente suyo con su propio NIT, sus credenciales de certificador y sus documentos.
  El uso mostrado corresponde al período
  <?= Vista::e(date('d/m/Y', strtotime($desde))) ?> — <?= Vista::e(date('d/m/Y', strtotime($hasta))) ?>,
  y le sirve para facturar el servicio.
</p>

<div class="tarjeta">
  <?php if ($lista === []): ?>
    <p class="vacio">Todavía no ha dado de alta ninguna empresa.</p>
  <?php else: ?>
    <table class="datos">
      <thead>
        <tr>
          <th>Empresa</th><th>NIT</th><th>Certificador</th><th>Ambiente</th>
          <th class="num">DTE del mes</th><th class="num">Facturado</th>
          <th>Estado</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lista as $registro):
            $empresa = $registro['empresa'];
            $uso     = $registro['uso'];
            $alerta  = $empresa->problemas();
        ?>
          <tr>
            <td>
              <strong><?= Vista::e($empresa->nombreInterno()) ?></strong><br>
              <small style="color:#5b6875"><?= Vista::e($empresa->nombreComercial()) ?></small>
              <?php if ($alerta !== []): ?>
                <br><small style="color:#b32431"><?= Vista::e($alerta[0]) ?></small>
              <?php endif; ?>
            </td>
            <td><?= Vista::e($empresa->nit()) ?></td>
            <td><?= Vista::e($empresa->proveedorCertificador()) ?></td>
            <td>
              <span class="etiqueta <?= $empresa->ambiente() === 'produccion' ? 'CERTIFICADO' : 'PENDIENTE' ?>">
                <?= Vista::e($empresa->ambiente()) ?>
              </span>
            </td>
            <td class="num">
              <?= (int) $uso['certificados'] ?>
              <?php if ((int) $uso['pendientes'] > 0): ?>
                <br><small style="color:#8a6100"><?= (int) $uso['pendientes'] ?> pend.</small>
              <?php endif; ?>
            </td>
            <td class="num"><?= Vista::moneda($uso['total']) ?></td>
            <td>
              <span class="etiqueta <?= $empresa->activa() ? 'CERTIFICADO' : 'ANULADO' ?>">
                <?= $empresa->activa() ? 'activa' : 'inactiva' ?>
              </span>
            </td>
            <td class="acciones">
              <a class="boton pequeno" href="index.php?r=usar_empresa&amp;id=<?= $empresa->id() ?>">Entrar</a>
              <a class="boton pequeno secundario" href="index.php?r=empresa_editar&amp;id=<?= $empresa->id() ?>">Editar</a>
              <form method="post" action="index.php?r=empresa_estado" style="margin:0">
                <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $empresa->id() ?>">
                <input type="hidden" name="activa" value="<?= $empresa->activa() ? '0' : '1' ?>">
                <button class="boton pequeno secundario" type="submit">
                  <?= $empresa->activa() ? 'Suspender' : 'Activar' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
