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

    if ($accion === 'borrar') {
        $enUso = (int) BD::valor('SELECT COUNT(*) FROM `cr_campanas` WHERE `plantilla_id` = ?', [$id], 0);
        if ($enUso > 0) {
            cr_flash('error', 'No se puede borrar: la usan ' . $enUso . ' campaña(s).');
        } else {
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
