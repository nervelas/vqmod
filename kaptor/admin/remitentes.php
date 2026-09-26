<?php
/** Kaptor - Buzones de salida (cuentas SMTP). */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

$editar = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion = (string) cr_post('accion');
    $id     = (int) cr_post('id', 0);

    switch ($accion) {
        case 'guardar':
            $r = Remitente::guardar($_POST, $id);
            cr_flash($r['ok'] ? 'exito' : 'error', $r['ok'] ? 'Buzón guardado.' : ($r['error'] ?? 'Error.'));
            break;

        case 'probar':
            $r = Remitente::probar($id);
            cr_flash($r['ok'] ? 'exito' : 'error',
                $r['ok'] ? ('Conexión correcta. ' . ($r['detalle'] ?? '') . ($r['aviso'] ?? '')) : ($r['error'] ?? 'Error.'));
            break;

        case 'enviar_prueba':
            $r = Remitente::enviarPrueba($id, (string) cr_post('destino'));
            cr_flash($r['ok'] ? 'exito' : 'error',
                $r['ok'] ? 'Correo de prueba enviado. Revisa la bandeja de entrada y la de spam.' : ($r['error'] ?? 'Error.'));
            break;

        case 'borrar':
            Remitente::borrar($id);
            cr_flash('exito', 'Buzón eliminado.');
            break;
    }
    cr_redirigir('admin/remitentes.php');
}

if ((int) cr_get('editar', 0) > 0) {
    $editar = Remitente::obtener((int) cr_get('editar'));
}
$buzones = Remitente::todos();

admin_cabecera(['titulo' => 'Buzones de salida', 'activo' => 'remitentes.php']);
?>

<div class="aviso aviso-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
    <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
  </svg>
  <span>
    Usa una cuenta de correo de tu propio dominio (por ejemplo <b class="mono">info@tudominio.com</b>).
    Los datos SMTP te los da tu hosting en <b>cPanel → Cuentas de correo → Conectar dispositivos</b>.
    Cuantos más buzones añadas, más rápido podrás enviar sin forzar ninguno.
  </span>
</div>

<div class="rejilla rejilla-2" style="align-items:start">

  <!-- ======================= LISTA ======================= -->
  <div class="tarjeta" style="grid-column:1 / -1">
    <h3>Buzones configurados (<?= cr_numero(count($buzones)) ?>)</h3>

    <?php if (!$buzones): ?>
      <div class="vacio"><p>Todavía no has añadido ningún buzón. Rellena el formulario de abajo.</p></div>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tabla panel-tabla">
          <thead>
            <tr><th>Buzón</th><th>Servidor</th><th>Límites</th><th>Uso ahora</th><th>Estado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
          <?php foreach ($buzones as $b): ?>
            <?php
              $hora = Remitente::enviadosUltimaHora((int) $b['id']);
              $dia  = Remitente::enviadosHoy((int) $b['id']);
            ?>
            <tr>
              <td>
                <b><?= e((string) $b['de_correo']) ?></b>
                <div class="pequeno suave"><?= e((string) ($b['de_nombre'] ?: $b['nombre'])) ?></div>
              </td>
              <td class="pequeno">
                <span class="mono"><?= e((string) $b['host'] . ':' . $b['puerto']) ?></span>
                <div><span class="chip chip-gris"><?= e(strtoupper((string) $b['seguridad'])) ?></span></div>
              </td>
              <td class="pequeno"><?= (int) $b['limite_hora'] ?>/hora · <?= (int) $b['limite_dia'] ?>/día</td>
              <td class="pequeno">
                <?= cr_numero($hora) ?> esta hora<br>
                <?= cr_numero($dia) ?> hoy
              </td>
              <td>
                <span class="chip <?= (int) $b['activo'] === 1 ? 'chip-neon' : 'chip-gris' ?>">
                  <?= (int) $b['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                </span>
                <?php if (!empty($b['ultimo_error'])): ?>
                  <div class="pequeno" style="color:var(--error);max-width:240px"><?= e(cr_recortar((string) $b['ultimo_error'], 90)) ?></div>
                <?php elseif (!empty($b['probado_en'])): ?>
                  <div class="pequeno suave">Probado <?= e(cr_fecha((string) $b['probado_en'])) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="acciones-fila">
                  <a class="btn btn-fantasma btn-peq" href="?editar=<?= (int) $b['id'] ?>">Editar</a>
                  <form method="post">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="probar">
                    <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                    <button class="btn btn-fantasma btn-peq">Probar</button>
                  </form>
                  <form method="post">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="borrar">
                    <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                    <button class="btn btn-fantasma btn-peq" style="color:var(--error)"
                            data-confirmar="¿Borrar el buzón <?= e((string) $b['de_correo']) ?>?">Borrar</button>
                  </form>
                </div>
                <details style="margin-top:8px">
                  <summary class="btn btn-fantasma btn-peq" style="list-style:none">Enviar prueba</summary>
                  <form method="post" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="enviar_prueba">
                    <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                    <input type="email" name="destino" class="campo" style="width:200px;padding:7px 10px"
                           placeholder="tu@correo.com" required>
                    <button class="btn btn-peq">Enviar</button>
                  </form>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- ======================= FORMULARIO ======================= -->
  <div class="tarjeta">
    <h3><?= $editar ? 'Editar buzón' : 'Añadir un buzón' ?></h3>
    <form method="post" autocomplete="off">
      <?= Seguridad::campoCsrf() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

      <div class="campo-grupo">
        <label class="etiqueta" for="de_correo">Dirección del remitente</label>
        <input type="email" id="de_correo" name="de_correo" class="campo" required
               placeholder="info@tudominio.com" value="<?= e((string) ($editar['de_correo'] ?? '')) ?>">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="de_nombre">Nombre que verá el destinatario</label>
        <input type="text" id="de_nombre" name="de_nombre" class="campo"
               placeholder="Tu empresa · lo que haces" value="<?= e((string) ($editar['de_nombre'] ?? '')) ?>">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="responder_a">Responder a (opcional)</label>
        <input type="email" id="responder_a" name="responder_a" class="campo"
               value="<?= e((string) ($editar['responder_a'] ?? '')) ?>">
      </div>

      <hr>

      <div class="campo-grupo">
        <label class="etiqueta" for="host">Servidor SMTP</label>
        <input type="text" id="host" name="host" class="campo mono" required
               placeholder="mail.tudominio.com" value="<?= e((string) ($editar['host'] ?? '')) ?>">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="campo-grupo">
          <label class="etiqueta" for="puerto">Puerto</label>
          <input type="number" id="puerto" name="puerto" class="campo" min="1" max="65535"
                 value="<?= (int) ($editar['puerto'] ?? 587) ?>">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="seguridad">Seguridad</label>
          <select id="seguridad" name="seguridad" class="campo">
            <?php foreach (['tls' => 'STARTTLS (puerto 587)', 'ssl' => 'SSL directo (puerto 465)', 'ninguna' => 'Sin cifrar (no recomendado)'] as $v => $t): ?>
              <option value="<?= e($v) ?>" <?= ($editar['seguridad'] ?? 'tls') === $v ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="usuario">Usuario SMTP</label>
        <input type="text" id="usuario" name="usuario" class="campo mono"
               placeholder="normalmente la misma dirección" value="<?= e((string) ($editar['usuario'] ?? '')) ?>">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="clave">Contraseña <?= $editar ? '<span class="suave">(déjala vacía para no cambiarla)</span>' : '' ?></label>
        <input type="password" id="clave" name="clave" class="campo" <?= $editar ? '' : 'required' ?> autocomplete="new-password">
      </div>

      <hr>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="campo-grupo">
          <label class="etiqueta" for="limite_hora">Máximo por hora</label>
          <input type="number" id="limite_hora" name="limite_hora" class="campo" min="1" max="5000"
                 value="<?= (int) ($editar['limite_hora'] ?? 40) ?>">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="limite_dia">Máximo por día</label>
          <input type="number" id="limite_dia" name="limite_dia" class="campo" min="1" max="50000"
                 value="<?= (int) ($editar['limite_dia'] ?? 200) ?>">
        </div>
      </div>
      <p class="pequeno suave" style="margin:-6px 0 14px">
        Empieza bajo: 20–30 por hora la primera semana. Subir demasiado rápido es lo que hace que un dominio acabe marcado como spam.
      </p>

      <?= interruptor_buzon($editar) ?>

      <button type="submit" class="btn btn-bloque" style="margin-top:18px">
        <?= $editar ? 'Guardar cambios' : 'Añadir buzón' ?>
      </button>
      <?php if ($editar): ?>
        <a class="btn btn-fantasma btn-bloque" style="margin-top:10px" href="remitentes.php">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="tarjeta">
    <h3>Antes de tu primera campaña</h3>
    <div class="estado-linea">
      <span><b>1. Configura SPF, DKIM y DMARC</b>
        <em>En cPanel → Correo electrónico → Autenticación de correo electrónico. Sin esto, la mitad de tus correos van a spam.</em></span>
    </div>
    <div class="estado-linea">
      <span><b>2. Envía una prueba</b>
        <em>Usa el botón «Enviar prueba» y comprueba que llega a la bandeja de entrada, no a la de spam.</em></span>
    </div>
    <div class="estado-linea">
      <span><b>3. Calienta el buzón</b>
        <em>Primera semana 20–30 al día, segunda 50, tercera 100. Sin prisa.</em></span>
    </div>
    <div class="estado-linea">
      <span><b>4. Usa un dominio secundario si puedes</b>
        <em>Así, si algo sale mal, tu dominio principal no queda afectado.</em></span>
    </div>
    <p class="pequeno suave" style="margin-top:14px">
      Las contraseñas se guardan cifradas con la clave de tu instalación: en la base de datos no aparecen en claro.
    </p>
  </div>
</div>

<?php
/** Interruptor de activación del buzón (función auxiliar de esta página). */
function interruptor_buzon(?array $editar): string
{
    $activo = $editar === null || (int) $editar['activo'] === 1;
    return '<label class="interruptor"><input type="checkbox" name="activo" value="1" ' . ($activo ? 'checked' : '')
        . '><span class="pista" aria-hidden="true"></span><span class="txt">Buzón activo (disponible para las campañas)</span></label>';
}

admin_pie();
