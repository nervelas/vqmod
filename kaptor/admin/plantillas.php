<?php
/** Kaptor - Plantillas de los mensajes. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion = (string) cr_post('accion');
    $id     = (int) cr_post('id', 0);

    if ($accion === 'guardar') {
        $datos = [
            'nombre'      => mb_substr(trim((string) cr_post('nombre')) ?: 'Plantilla', 0, 160),
            'asunto'      => mb_substr(trim((string) cr_post('asunto')), 0, 300),
            'cuerpo'      => (string) cr_post('cuerpo'),
            'actualizado' => date('Y-m-d H:i:s'),
        ];
        if ($datos['asunto'] === '' || trim($datos['cuerpo']) === '') {
            cr_flash('error', 'El asunto y el cuerpo no pueden estar vacíos.');
        } elseif ($id > 0) {
            BD::actualizar('cr_plantillas', $datos, '`id` = ?', [$id]);
            cr_flash('exito', 'Plantilla guardada.');
        } else {
            $datos['creado'] = $datos['actualizado'];
            $id = BD::insertar('cr_plantillas', $datos);
            cr_flash('exito', 'Plantilla creada.');
        }
        cr_redirigir('admin/plantillas.php?editar=' . $id);
    }

    // --- Enviar una prueba a un correo --------------------------------------
    if ($accion === 'enviar_prueba') {
        $p = $id > 0 ? BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [$id]) : null;
        if (!$p) {
            cr_flash('error', 'Guarda la plantilla antes de enviar una prueba.');
            cr_redirigir('admin/plantillas.php');
        }
        $destino = (string) cr_post('destino');
        $r = Campana::enviarPrueba($p, $destino);
        cr_flash($r['ok'] ? 'exito' : 'error', $r['ok']
            ? ('Prueba enviada a ' . $destino . ' desde ' . $r['buzon']
               . ($r['adjuntos'] ? ' con ' . $r['adjuntos'] . ' adjunto(s)' : '')
               . '. Mira la bandeja de entrada y la de spam.')
            : ($r['error'] ?? 'Error.'));
        cr_redirigir('admin/plantillas.php?editar=' . $id);
    }

    // --- Adjuntar un archivo ------------------------------------------------
    if ($accion === 'adjuntar') {
        $p = $id > 0 ? BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [$id]) : null;
        if (!$p) {
            cr_flash('error', 'Guarda la plantilla antes de adjuntarle archivos.');
            cr_redirigir('admin/plantillas.php');
        }

        $yaHay = Adjuntos::deLaPlantilla($p);
        $r = Adjuntos::subir($_FILES['archivo'] ?? [], $yaHay);

        if (!$r['ok']) {
            cr_flash('error', (string) $r['error']);
        } else {
            $yaHay[] = $r['ficha'];
            BD::actualizar('cr_plantillas', [
                'adjuntos'    => json_encode($yaHay, JSON_UNESCAPED_UNICODE),
                'actualizado' => date('Y-m-d H:i:s'),
            ], '`id` = ?', [$id]);
            cr_flash('exito', 'Archivo adjuntado: ' . $r['ficha']['nombre']
                . ' (' . Adjuntos::enMegas((int) $r['ficha']['peso']) . ').');
        }
        cr_redirigir('admin/plantillas.php?editar=' . $id);
    }

    // --- Quitar un archivo --------------------------------------------------
    if ($accion === 'quitar_adjunto') {
        $p = $id > 0 ? BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [$id]) : null;
        if ($p) {
            $cual  = (string) cr_post('archivo', '');
            $quedan = [];
            foreach (Adjuntos::deLaPlantilla($p) as $f) {
                if (($f['archivo'] ?? '') === $cual) { Adjuntos::borrar($f); continue; }
                $quedan[] = $f;
            }
            BD::actualizar('cr_plantillas', [
                'adjuntos'    => $quedan ? json_encode($quedan, JSON_UNESCAPED_UNICODE) : null,
                'actualizado' => date('Y-m-d H:i:s'),
            ], '`id` = ?', [$id]);
            cr_flash('exito', 'Archivo quitado.');
        }
        cr_redirigir('admin/plantillas.php?editar=' . $id);
    }

    if ($accion === 'borrar') {
        $enUso = (int) BD::valor('SELECT COUNT(*) FROM `cr_campanas` WHERE `plantilla_id` = ?', [$id], 0);
        if ($enUso > 0) {
            cr_flash('error', 'No se puede borrar: la usan ' . $enUso . ' campaña(s).');
        } else {
            // Los archivos se van con la plantilla; si no, quedan ahí para siempre.
            $p = BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [$id]);
            if ($p) {
                foreach (Adjuntos::deLaPlantilla($p) as $f) { Adjuntos::borrar($f); }
            }
            BD::ejecutar('DELETE FROM `cr_plantillas` WHERE `id` = ?', [$id]);
            cr_flash('exito', 'Plantilla eliminada.');
        }
    }
    cr_redirigir('admin/plantillas.php');
}

$editar = (int) cr_get('editar', 0) > 0
    ? BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [(int) cr_get('editar')])
    : null;

$plantillas = BD::todos('SELECT * FROM `cr_plantillas` ORDER BY `id` DESC');

$ejemplo = "Hola {{nombre}},\n\n"
    . "Soy {{remitente}}. Trabajamos con centros educativos como {{centro}} para "
    . "simplificar la gestión de notas, asistencia y comunicación con las familias.\n\n"
    . "¿Te vendría bien una demostración de 15 minutos esta semana?\n\n"
    . "Un saludo,\n{{firma}}";

admin_cabecera(['titulo' => 'Plantillas', 'activo' => 'plantillas.php']);
?>

<div class="rejilla rejilla-2" style="align-items:start">

  <div class="tarjeta" style="grid-column:1 / -1">
    <h3>Plantillas guardadas (<?= cr_numero(count($plantillas)) ?>)</h3>
    <?php if (!$plantillas): ?>
      <div class="vacio"><p>Todavía no hay plantillas. Crea la primera abajo.</p></div>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tabla panel-tabla">
          <thead><tr><th>Nombre</th><th>Asunto</th><th>Actualizada</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($plantillas as $p): ?>
            <tr>
              <td><b><?= e((string) $p['nombre']) ?></b></td>
              <td class="pequeno"><?= e(cr_recortar((string) $p['asunto'], 60)) ?></td>
              <td class="pequeno suave"><?= e(cr_fecha((string) $p['actualizado'])) ?></td>
              <td>
                <div class="acciones-fila">
                  <a class="btn btn-fantasma btn-peq" href="?editar=<?= (int) $p['id'] ?>">Editar</a>
                  <form method="post">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="borrar">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-fantasma btn-peq" style="color:var(--error)"
                            data-confirmar="¿Borrar la plantilla «<?= e((string) $p['nombre']) ?>»?">Borrar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h3><?= $editar ? 'Editar plantilla' : 'Nueva plantilla' ?></h3>
    <form method="post">
      <?= Seguridad::campoCsrf() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

      <div class="campo-grupo">
        <label class="etiqueta" for="nombre">Nombre interno</label>
        <input type="text" id="nombre" name="nombre" class="campo" required
               value="<?= e((string) ($editar['nombre'] ?? '')) ?>" placeholder="Presentación a colegios">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="asunto">Asunto</label>
        <input type="text" id="asunto" name="asunto" class="campo" required maxlength="300"
               value="<?= e((string) ($editar['asunto'] ?? '')) ?>"
               placeholder="Una idea para {{centro}}">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="cuerpo">Mensaje</label>
        <textarea id="cuerpo" name="cuerpo" class="campo" rows="14"
                  placeholder="<?= e($ejemplo) ?>"><?= e((string) ($editar['cuerpo'] ?? '')) ?></textarea>
        <p class="pequeno suave" style="margin-top:8px">
          Puedes escribir texto normal (los saltos de línea se respetan) o HTML.
          El pie con el enlace de baja se añade solo.
        </p>
      </div>
      <button class="btn btn-bloque"><?= $editar ? 'Guardar cambios' : 'Crear plantilla' ?></button>
      <?php if ($editar): ?>
        <a class="btn btn-fantasma btn-bloque" style="margin-top:10px" href="plantillas.php">Nueva plantilla</a>
      <?php endif; ?>
    </form>

    <?php if ($editar): ?>
      <!-- ===================== Vista previa ===================== -->
      <h3 style="margin:26px 0 4px">Así va a llegar</h3>
      <p class="pequeno suave">
        El correo de verdad, con las variables ya sustituidas y su pie de baja.
        <b>Asunto:</b> <?= e(Campana::vistaPrevia($editar)['asunto']) ?>
      </p>
      <div class="previa-marco">
        <iframe src="vista.php?plantilla=<?= (int) $editar['id'] ?>&amp;v=<?= e((string) strtotime((string) ($editar['actualizado'] ?? 'now'))) ?>"
                title="Vista previa del correo" loading="lazy"></iframe>
      </div>
      <form method="post" class="previa-prueba">
        <?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="enviar_prueba">
        <input type="hidden" name="id" value="<?= (int) $editar['id'] ?>">
        <label class="etiqueta" for="destino_prueba">Enviar una prueba a:</label>
        <div class="previa-prueba-fila">
          <input type="email" id="destino_prueba" name="destino" class="campo" required
                 placeholder="tucorreo@tudominio.com"
                 value="<?= e((string) (Auth::usuario()['email'] ?? '')) ?>">
          <button class="btn btn-peq">Enviar prueba</button>
        </div>
        <p class="pequeno suave" style="margin-top:6px">
          Sale del primer buzón activo, con el mismo asunto, los mismos adjuntos y el
          mismo pie que el envío de verdad. No cuenta como envío.
        </p>
      </form>

      <div class="acciones-fila" style="margin-top:10px">
        <a class="btn btn-fantasma btn-peq" target="_blank" rel="noopener"
           href="vista.php?plantilla=<?= (int) $editar['id'] ?>">Abrir en una pestaña</a>
        <button type="button" class="btn btn-fantasma btn-peq" onclick="
          var m=this.closest('.tarjeta').querySelector('.previa-marco');
          m.classList.toggle('previa-movil');
          this.textContent = m.classList.contains('previa-movil') ? 'Ver en ordenador' : 'Ver en teléfono';
        ">Ver en teléfono</button>
      </div>
    <?php endif; ?>

    <!-- ===================== Archivos adjuntos ===================== -->
    <h3 style="margin:26px 0 4px">Archivos adjuntos</h3>
    <?php if (!$editar): ?>
      <p class="pequeno suave">Guarda la plantilla y aquí podrás adjuntarle archivos.</p>
    <?php else:
      $fichas = Adjuntos::deLaPlantilla($editar);
      $suma   = Adjuntos::peso($fichas); ?>

      <p class="pequeno suave">
        Viajan con cada correo. Hasta <?= Adjuntos::MAX_ARCHIVOS ?> archivos y
        <?= e(Adjuntos::enMegas(Mensaje::MAX_TOTAL)) ?> entre todos.
      </p>

      <?php if ($fichas): ?>
        <?php foreach ($fichas as $f): ?>
          <div class="estado-linea">
            <span>
              <b><?= e((string) $f['nombre']) ?></b>
              <em><?= e(Adjuntos::enMegas((int) $f['peso'])) ?> · <?= e((string) $f['tipo']) ?></em>
            </span>
            <form method="post" style="margin:0" onsubmit="return confirm('¿Quitar este archivo?')">
              <?= Seguridad::campoCsrf() ?>
              <input type="hidden" name="accion" value="quitar_adjunto">
              <input type="hidden" name="id" value="<?= (int) $editar['id'] ?>">
              <input type="hidden" name="archivo" value="<?= e((string) $f['archivo']) ?>">
              <button class="btn btn-fantasma btn-peq" style="color:var(--error)">Quitar</button>
            </form>
          </div>
        <?php endforeach; ?>
        <p class="pequeno suave" style="margin:10px 0 0">
          Pesan <b><?= e(Adjuntos::enMegas($suma)) ?></b> en total.
        </p>
      <?php endif; ?>

      <?php if (count($fichas) < Adjuntos::MAX_ARCHIVOS): ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:14px">
          <?= Seguridad::campoCsrf() ?>
          <input type="hidden" name="accion" value="adjuntar">
          <input type="hidden" name="id" value="<?= (int) $editar['id'] ?>">
          <input type="file" name="archivo" class="campo" required
                 accept=".<?= e(implode(',.', array_keys(Mensaje::TIPOS))) ?>">
          <button class="btn btn-fantasma btn-bloque" style="margin-top:10px">Adjuntar archivo</button>
        </form>
      <?php endif; ?>

      <div class="aviso aviso-info" style="margin-top:16px">
        <span><b>Antes de adjuntar, piénsalo:</b> en un envío en frío un adjunto <b>baja la
          entrega</b> —los filtros desconfían de un archivo que nadie pidió, y el correo pesa
          más—. Para una presentación comercial entra mucho mejor <b>subir el PDF a tu web y
          poner el enlace en el mensaje</b>. Si aun así lo quieres adjuntar, aquí está.</span>
      </div>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h3>Variables disponibles</h3>
    <p class="pequeno suave">Se sustituyen por los datos de cada contacto al enviar.</p>
    <?php
    $vars = [
        '{{nombre}}'    => 'Nombre del contacto; si no hay, el del centro',
        '{{centro}}'    => 'Colegio, empresa u organización',
        '{{correo}}'    => 'Su dirección de correo',
        '{{dominio}}'   => 'El dominio de su correo',
        '{{telefono}}'  => 'Teléfono o WhatsApp si se importó',
        '{{remitente}}' => 'Nombre del buzón que envía',
        '{{firma}}'     => 'Lo mismo, para cerrar el mensaje',
        '{{sitio}}'     => 'Nombre de tu sitio',
        '{{fecha}}'     => 'Fecha del envío',
        '{{baja}}'      => 'Enlace de baja (ya va en el pie)',
    ];
    foreach ($vars as $v => $d):
    ?>
      <div class="estado-linea">
        <span><b class="mono oro"><?= e($v) ?></b><em><?= e($d) ?></em></span>
      </div>
    <?php endforeach; ?>

    <h3 style="margin-top:22px">Consejos que sí funcionan</h3>
    <div class="estado-linea"><span><b>Asunto corto y concreto</b><em>Entre 30 y 50 caracteres. Nada de MAYÚSCULAS ni signos de exclamación.</em></span></div>
    <div class="estado-linea"><span><b>Mensaje breve</b><em>De 80 a 120 palabras. Una sola pregunta al final.</em></span></div>
    <div class="estado-linea"><span><b>Pocos enlaces</b><em>Uno o dos como mucho. Muchos enlaces disparan los filtros de spam.</em></span></div>
    <div class="estado-linea"><span><b>Sin imágenes pesadas</b><em>Un correo de solo texto entra en la bandeja mucho más a menudo.</em></span></div>
  </div>
</div>

<?php admin_pie(); ?>
