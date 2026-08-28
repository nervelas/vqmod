<?php
/** @var \Fel\Plataforma\Empresa|null $empresa @var string $csrf
 *  @var list<string> $departamentos @var array<string,string> $afiliaciones
 *  @var array<string,string> $proveedores @var array<string,mixed> $credenciales
 *  @var list<array<string,mixed>> $usuarios */
use Fel\Web\Vista;

$d = $empresa?->datos() ?? [];
$v = static fn (string $clave, string $porDefecto = ''): string => Vista::e($d[$clave] ?? $porDefecto);
$c = static fn (string $clave): string => Vista::e((string) ($credenciales[$clave] ?? ''));
?>
<div class="encabezado-pagina">
  <h1><?= $empresa === null ? 'Nueva empresa' : 'Editar ' . Vista::e($empresa->nombreInterno()) ?></h1>
  <a class="boton secundario" href="index.php?r=empresas">Volver</a>
</div>

<form method="post" action="index.php?r=empresa_guardar">
<input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
<input type="hidden" name="id" value="<?= (int) ($d['id'] ?? 0) ?>">

<fieldset>
  <legend>Identificación del cliente</legend>
  <div class="fila">
    <div class="campo">
      <label for="nombre_interno">Nombre interno</label>
      <input id="nombre_interno" name="nombre_interno" value="<?= $v('nombre_interno') ?>" required maxlength="120"
             placeholder="Como usted identifica a este cliente">
    </div>
    <div class="campo">
      <label for="plan">Plan</label>
      <input id="plan" name="plan" value="<?= $v('plan') ?>" maxlength="40" placeholder="Ej.: Básico Q150/mes">
    </div>
  </div>
  <div class="campo">
    <label for="notas">Notas internas</label>
    <input id="notas" name="notas" value="<?= $v('notas') ?>" maxlength="500">
  </div>
</fieldset>

<fieldset>
  <legend>Datos del emisor ante SAT — deben coincidir exactamente con el RTU</legend>
  <div class="fila">
    <div class="campo">
      <label for="nit">NIT (sin guion)</label>
      <input id="nit" name="nit" value="<?= $v('nit') ?>" required maxlength="25">
    </div>
    <div class="campo" style="grid-column:span 2">
      <label for="nombre_emisor">Razón social</label>
      <input id="nombre_emisor" name="nombre_emisor" value="<?= $v('nombre_emisor') ?>" required maxlength="255">
    </div>
    <div class="campo">
      <label for="nombre_comercial">Nombre comercial</label>
      <input id="nombre_comercial" name="nombre_comercial" value="<?= $v('nombre_comercial') ?>" required maxlength="255">
    </div>
  </div>
  <div class="fila">
    <div class="campo">
      <label for="afiliacion_iva">Afiliación de IVA</label>
      <select id="afiliacion_iva" name="afiliacion_iva">
        <?php foreach ($afiliaciones as $codigo => $nombre): ?>
          <option value="<?= Vista::e($codigo) ?>" <?= ($d['afiliacion_iva'] ?? 'GEN') === $codigo ? 'selected' : '' ?>>
            <?= Vista::e($codigo . ' — ' . $nombre) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="codigo_establecimiento">Código de establecimiento</label>
      <input id="codigo_establecimiento" name="codigo_establecimiento" value="<?= $v('codigo_establecimiento', '1') ?>" maxlength="10">
    </div>
    <div class="campo">
      <label for="correo">Correo</label>
      <input id="correo" name="correo" type="email" value="<?= $v('correo') ?>" maxlength="255">
    </div>
    <div class="campo">
      <label for="telefono">Teléfono</label>
      <input id="telefono" name="telefono" value="<?= $v('telefono') ?>" maxlength="50">
    </div>
  </div>
  <div class="fila">
    <div class="campo" style="grid-column:span 2">
      <label for="direccion">Dirección del establecimiento</label>
      <input id="direccion" name="direccion" value="<?= $v('direccion', 'Ciudad') ?>" maxlength="255">
    </div>
    <div class="campo">
      <label for="codigo_postal">Código postal</label>
      <input id="codigo_postal" name="codigo_postal" value="<?= $v('codigo_postal', '01001') ?>" maxlength="10">
    </div>
    <div class="campo">
      <label for="municipio">Municipio</label>
      <input id="municipio" name="municipio" value="<?= $v('municipio', 'Guatemala') ?>" maxlength="100">
    </div>
    <div class="campo">
      <label for="departamento">Departamento</label>
      <select id="departamento" name="departamento">
        <?php foreach ($departamentos as $departamento): ?>
          <option value="<?= Vista::e($departamento) ?>" <?= ($d['departamento'] ?? 'Guatemala') === $departamento ? 'selected' : '' ?>>
            <?= Vista::e($departamento) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</fieldset>

<fieldset>
  <legend>Certificador</legend>
  <div class="mensaje aviso" style="margin-bottom:12px">
    Las credenciales se guardan <strong>cifradas</strong> en la base de datos.
    Al editar, los campos aparecen vacíos si nunca se guardaron; si los deja vacíos,
    se conservan las que ya tenía.
  </div>
  <div class="fila">
    <div class="campo">
      <label for="certificador_proveedor">Proveedor</label>
      <select id="certificador_proveedor" name="certificador_proveedor">
        <?php foreach ($proveedores as $codigo => $nombre): ?>
          <option value="<?= Vista::e($codigo) ?>" <?= ($d['certificador_proveedor'] ?? 'simulador') === $codigo ? 'selected' : '' ?>>
            <?= Vista::e($nombre) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="ambiente">Ambiente</label>
      <select id="ambiente" name="ambiente">
        <option value="pruebas" <?= ($d['ambiente'] ?? 'pruebas') === 'pruebas' ? 'selected' : '' ?>>Pruebas</option>
        <option value="produccion" <?= ($d['ambiente'] ?? '') === 'produccion' ? 'selected' : '' ?>>Producción</option>
      </select>
    </div>
    <div class="campo">
      <label for="certificador_nombre">Nombre del certificador (se imprime)</label>
      <input id="certificador_nombre" name="certificador_nombre" value="<?= $v('certificador_nombre') ?>" maxlength="255">
    </div>
    <div class="campo">
      <label for="certificador_nit">NIT del certificador</label>
      <input id="certificador_nit" name="certificador_nit" value="<?= $v('certificador_nit') ?>" maxlength="25">
    </div>
  </div>

  <h3>Credenciales</h3>
  <div class="fila">
    <div class="campo">
      <label for="cert_url_firma">URL del servicio de firma</label>
      <input id="cert_url_firma" name="cert_url_firma" value="<?= $c('url_firma') ?>" maxlength="255">
    </div>
    <div class="campo">
      <label for="cert_url_certificacion">URL de certificación</label>
      <input id="cert_url_certificacion" name="cert_url_certificacion" value="<?= $c('url_certificacion') ?>" maxlength="255">
    </div>
    <div class="campo">
      <label for="cert_url_anulacion">URL de anulación</label>
      <input id="cert_url_anulacion" name="cert_url_anulacion" value="<?= $c('url_anulacion') ?>" maxlength="255">
    </div>
  </div>
  <div class="fila">
    <div class="campo">
      <label for="cert_llave_firma">Llave de firma</label>
      <input id="cert_llave_firma" name="cert_llave_firma" value="<?= $c('llave_firma') ?>" maxlength="255" autocomplete="off">
    </div>
    <div class="campo">
      <label for="cert_alias_firma">Alias de firma</label>
      <input id="cert_alias_firma" name="cert_alias_firma" value="<?= $c('alias_firma') ?>" maxlength="120" autocomplete="off">
    </div>
    <div class="campo">
      <label for="cert_usuario_api">Usuario de API</label>
      <input id="cert_usuario_api" name="cert_usuario_api" value="<?= $c('usuario_api') ?>" maxlength="120" autocomplete="off">
    </div>
    <div class="campo">
      <label for="cert_llave_api">Llave de API</label>
      <input id="cert_llave_api" name="cert_llave_api" value="<?= $c('llave_api') ?>" maxlength="255" autocomplete="off">
    </div>
  </div>
  <div class="campo">
    <label for="cert_json">Ajustes adicionales en JSON (adaptador genérico)</label>
    <textarea id="cert_json" name="cert_json" rows="3"
              placeholder='{"firma":{"habilitada":true,"url":"..."},"certificacion":{"url":"...","formato":"xml"}}'></textarea>
  </div>
</fieldset>

<fieldset>
  <legend>Operación e imagen</legend>
  <div class="fila">
    <div class="campo">
      <label for="formato_impresion">Formato de impresión</label>
      <select id="formato_impresion" name="formato_impresion">
        <option value="carta" <?= ($d['formato_impresion'] ?? 'carta') === 'carta' ? 'selected' : '' ?>>Hoja carta</option>
        <option value="ticket" <?= ($d['formato_impresion'] ?? '') === 'ticket' ? 'selected' : '' ?>>Ticket 80 mm (impresora térmica)</option>
      </select>
    </div>
    <div class="campo">
      <label for="color_marca">Color de marca</label>
      <input id="color_marca" name="color_marca" type="color" value="<?= $v('color_marca', '#0f5f8a') ?>">
    </div>
    <div class="campo">
      <label for="limite_consumidor_final">Límite consumidor final (Q)</label>
      <input id="limite_consumidor_final" name="limite_consumidor_final" type="number" step="0.01" min="0"
             value="<?= Vista::e(number_format((float) ($d['limite_consumidor_final'] ?? 2500), 2, '.', '')) ?>">
    </div>
    <div class="campo">
      <label for="dias_maximos_anulacion">Aviso de plazo de anulación (días)</label>
      <input id="dias_maximos_anulacion" name="dias_maximos_anulacion" type="number" min="0"
             value="<?= (int) ($d['dias_maximos_anulacion'] ?? 30) ?>">
    </div>
  </div>
  <div class="campo">
    <label for="logo">Logo en data URI (opcional)</label>
    <textarea id="logo" name="logo" rows="2" placeholder="data:image/png;base64,..."><?= $v('logo') ?></textarea>
  </div>
</fieldset>

<?php if ($empresa === null): ?>
  <fieldset>
    <legend>Primer usuario de la empresa (opcional)</legend>
    <div class="fila">
      <div class="campo">
        <label for="nuevo_usuario">Usuario</label>
        <input id="nuevo_usuario" name="nuevo_usuario" maxlength="60" autocomplete="off">
      </div>
      <div class="campo">
        <label for="nuevo_usuario_nombre">Nombre completo</label>
        <input id="nuevo_usuario_nombre" name="nuevo_usuario_nombre" maxlength="120">
      </div>
      <div class="campo">
        <label for="nueva_clave">Contraseña (mínimo 10 caracteres)</label>
        <input id="nueva_clave" name="nueva_clave" type="password" autocomplete="new-password">
      </div>
    </div>
  </fieldset>
<?php else: ?>
  <fieldset>
    <legend>Usuarios de esta empresa</legend>
    <?php if ($usuarios === []): ?>
      <p style="color:#5b6875;font-size:13px;margin:0">
        Sin usuarios. Créelos desde <a href="index.php?r=usuarios">Usuarios</a>.
      </p>
    <?php else: ?>
      <table class="datos">
        <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><?= Vista::e($u['usuario']) ?></td>
              <td><?= Vista::e($u['nombre']) ?></td>
              <td><?= Vista::e($u['rol']) ?></td>
              <td><?= $u['activo'] ? 'activo' : 'inactivo' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </fieldset>
<?php endif; ?>

<div class="acciones">
  <button class="boton" type="submit">Guardar</button>
  <a class="boton secundario" href="index.php?r=empresas">Cancelar</a>
</div>
</form>
