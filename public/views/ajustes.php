<?php
/** @var array<string,mixed> $emisor @var string $certificador @var list<string> $disponibles
 *  @var string $ambiente @var array<string,mixed> $reglas */
use Fel\Web\Vista;
?>
<div class="encabezado-pagina">
  <h1>Ajustes</h1>
</div>

<div class="mensaje aviso">
  Estos valores se editan en <strong>config/config.php</strong> por seguridad: contienen las llaves
  de firma y de API entregadas por su certificador. Esta pantalla es de solo lectura.
</div>

<div class="rejilla k2">
  <div class="tarjeta">
    <h2>Emisor registrado en SAT</h2>
    <dl class="definiciones">
      <dt>NIT</dt><dd><?= Vista::e($emisor['nit'] ?? '—') ?></dd>
      <dt>Nombre</dt><dd><?= Vista::e($emisor['nombre'] ?? '—') ?></dd>
      <dt>Nombre comercial</dt><dd><?= Vista::e($emisor['nombre_comercial'] ?? '—') ?></dd>
      <dt>Afiliación IVA</dt><dd><?= Vista::e($emisor['afiliacion_iva'] ?? '—') ?></dd>
      <dt>Establecimiento</dt><dd><?= Vista::e($emisor['codigo_establecimiento'] ?? '—') ?></dd>
      <dt>Dirección</dt><dd><?= Vista::e($emisor['direccion'] ?? '—') ?></dd>
      <dt>Municipio</dt><dd><?= Vista::e($emisor['municipio'] ?? '—') ?></dd>
      <dt>Departamento</dt><dd><?= Vista::e($emisor['departamento'] ?? '—') ?></dd>
      <dt>Correo</dt><dd><?= Vista::e($emisor['correo'] ?? '—') ?></dd>
    </dl>
    <p style="font-size:12px;color:#5b6875">
      Deben coincidir exactamente con su RTU en la Agencia Virtual de SAT.
    </p>
  </div>

  <div class="tarjeta">
    <h2>Certificación</h2>
    <dl class="definiciones">
      <dt>Ambiente</dt><dd><?= Vista::e($ambiente) ?></dd>
      <dt>Certificador activo</dt><dd><?= Vista::e($certificador) ?></dd>
      <dt>Adaptadores</dt><dd><?= Vista::e(implode(', ', $disponibles)) ?></dd>
      <dt>Límite consumidor final</dt><dd><?= Vista::moneda($reglas['limite_consumidor_final'] ?? 0) ?></dd>
      <dt>Aviso de plazo de anulación</dt><dd><?= (int) ($reglas['dias_maximos_anulacion'] ?? 0) ?> días</dd>
      <dt>Reintentos máximos</dt><dd><?= (int) ($reglas['maximo_intentos'] ?? 0) ?></dd>
    </dl>
    <?php if ($certificador === 'simulador'): ?>
      <div class="mensaje error" style="margin-top:12px">
        Está usando el <strong>certificador simulado</strong>. Los documentos emitidos no tienen
        validez fiscal. Para facturar de verdad debe contratar un certificador autorizado por SAT
        y configurarlo en <code>config/config.php</code>.
      </div>
    <?php endif; ?>
  </div>
</div>
