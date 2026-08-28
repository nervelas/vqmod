<?php
/** @var \Fel\Plataforma\Empresa $empresa */
use Fel\Web\Sesion;
use Fel\Web\Vista;

$problemas = $empresa->problemas();
?>
<div class="encabezado-pagina">
  <h1>Ajustes</h1>
  <?php if (Sesion::esSuperadmin()): ?>
    <a class="boton" href="index.php?r=empresa_editar&amp;id=<?= $empresa->id() ?>">Editar esta empresa</a>
  <?php endif; ?>
</div>

<?php if ($problemas !== []): ?>
  <div class="mensaje error">
    <strong>Pendientes antes de facturar de verdad:</strong>
    <ul style="margin:6px 0 0 18px">
      <?php foreach ($problemas as $problema): ?><li><?= Vista::e($problema) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="rejilla k2">
  <div class="tarjeta">
    <h2>Emisor registrado en SAT</h2>
    <dl class="definiciones">
      <dt>NIT</dt><dd><?= Vista::e($empresa->nit()) ?></dd>
      <dt>Razón social</dt><dd><?= Vista::e($empresa->valor('nombre_emisor')) ?></dd>
      <dt>Nombre comercial</dt><dd><?= Vista::e($empresa->nombreComercial()) ?></dd>
      <dt>Afiliación IVA</dt><dd><?= Vista::e($empresa->valor('afiliacion_iva')) ?></dd>
      <dt>Establecimiento</dt><dd><?= Vista::e($empresa->valor('codigo_establecimiento')) ?></dd>
      <dt>Dirección</dt><dd><?= Vista::e($empresa->valor('direccion')) ?></dd>
      <dt>Municipio</dt><dd><?= Vista::e($empresa->valor('municipio')) ?></dd>
      <dt>Departamento</dt><dd><?= Vista::e($empresa->valor('departamento')) ?></dd>
      <dt>Correo</dt><dd><?= Vista::e($empresa->valor('correo')) ?></dd>
    </dl>
    <p style="font-size:12px;color:#5b6875">
      Deben coincidir exactamente con el RTU en la Agencia Virtual de SAT.
    </p>
  </div>

  <div class="tarjeta">
    <h2>Certificación e impresión</h2>
    <dl class="definiciones">
      <dt>Ambiente</dt><dd><?= Vista::e($empresa->ambiente()) ?></dd>
      <dt>Certificador</dt><dd><?= Vista::e($empresa->proveedorCertificador()) ?></dd>
      <dt>Nombre impreso</dt><dd><?= Vista::e($empresa->certificadorNombre() ?: '—') ?></dd>
      <dt>NIT del certificador</dt><dd><?= Vista::e($empresa->certificadorNit() ?: '—') ?></dd>
      <dt>Formato</dt><dd><?= $empresa->formatoImpresion() === 'ticket' ? 'Ticket 80 mm' : 'Hoja carta' ?></dd>
      <dt>Límite consumidor final</dt><dd><?= Vista::moneda($empresa->limiteConsumidorFinal()) ?></dd>
      <dt>Aviso de plazo de anulación</dt><dd><?= $empresa->diasMaximosAnulacion() ?> días</dd>
    </dl>
    <?php if ($empresa->esSimulador()): ?>
      <div class="mensaje error" style="margin-top:12px">
        Está usando el <strong>certificador simulado</strong>. Los documentos emitidos
        no tienen validez fiscal. Para facturar de verdad hay que contratar un
        certificador autorizado por SAT y cargar sus credenciales.
      </div>
    <?php endif; ?>
  </div>
</div>
