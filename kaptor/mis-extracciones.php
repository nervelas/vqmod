<?php
/** Kaptor - Historial de extracciones del usuario que ha iniciado sesión. */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

// Kaptor es privado: sin sesión no se entra.
cr_exigir_sesion();

if (!Auth::autenticado()) {
    cr_flash('info', 'Inicia sesión para ver tus extracciones.');
    cr_redirigir('login.php');
}

$usuarioId = Auth::id();

// --- Borrar todo el historial -------------------------------------------
// Solo lo suyo, nunca lo de otro usuario, y solo por POST con el token de
// la sesion: asi un enlace de fuera no puede vaciarle el historial a nadie.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) cr_post('accion', '') === 'vaciar') {
    Seguridad::exigirCsrf();

    $ids = array_map(
        static fn(array $f): int => (int) $f['id'],
        BD::todos('SELECT `id` FROM `cr_escaneos` WHERE `usuario_id` = ?', [$usuarioId])
    );

    if ($ids === []) {
        cr_flash('info', 'No había nada que borrar.');
    } else {
        // De mil en mil, que un historial largo no reviente la consulta.
        foreach (array_chunk($ids, 1000) as $tanda) {
            $marcas = implode(',', array_fill(0, count($tanda), '?'));
            foreach (['cr_correos', 'cr_telefonos', 'cr_cola', 'cr_sitios'] as $tabla) {
                BD::ejecutar("DELETE FROM `{$tabla}` WHERE `escaneo_id` IN ({$marcas})", $tanda);
            }
            BD::ejecutar("DELETE FROM `cr_escaneos` WHERE `id` IN ({$marcas}) AND `usuario_id` = ?",
                         array_merge($tanda, [$usuarioId]));
        }
        cr_flash('exito', count($ids) === 1
            ? 'Se borró la extracción y todo lo que tenía dentro.'
            : 'Se borraron ' . cr_numero(count($ids)) . ' extracciones y todo lo que tenían dentro.');
    }

    cr_redirigir('mis-extracciones.php');
}

$pagina    = max(1, (int) cr_get('p', 1));
$porPagina = 20;
$desplaz   = ($pagina - 1) * $porPagina;

$total = (int) BD::valor('SELECT COUNT(*) FROM `cr_escaneos` WHERE `usuario_id` = ?', [$usuarioId], 0);
$lista = BD::todos(
    'SELECT * FROM `cr_escaneos` WHERE `usuario_id` = ? ORDER BY `inicio` DESC LIMIT ' . $porPagina . ' OFFSET ' . $desplaz,
    [$usuarioId]
);
$paginas = max(1, (int) ceil($total / $porPagina));

cr_cabecera(['titulo' => 'Mis extracciones', 'activo' => 'mias']);
?>
<section class="seccion">
  <div class="contenedor">
    <div class="resultados-cab">
      <div>
        <h1 style="font-size:clamp(1.6rem,4vw,2.2rem)">Mis extracciones</h1>
        <p class="sub"><?= cr_numero($total) ?> extracción<?= $total === 1 ? '' : 'es' ?> guardada<?= $total === 1 ? '' : 's' ?></p>
      </div>
      <div class="cab-acciones">
        <a class="btn btn-peq" href="<?= e(cr_url('index.php')) ?>">Nueva extracción</a>
        <?php if ($lista): ?>
          <form method="post" id="form-vaciar" class="form-vaciar">
            <?= Seguridad::campoCsrf() ?>
            <input type="hidden" name="accion" value="vaciar">
            <button type="submit" class="btn btn-peligro btn-peq" id="btn-vaciar">
              <svg viewBox="0 0 256 256" fill="currentColor" aria-hidden="true" width="16" height="16"><path d="M216,48H176V40a24,24,0,0,0-24-24H104A24,24,0,0,0,80,40v8H40a8,8,0,0,0,0,16h8V208a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V64h8a8,8,0,0,0,0-16ZM96,40a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96Zm96,168H64V64H192ZM112,104v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Zm48,0v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Z"/></svg>
              Borrar todo el historial
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$lista): ?>
      <div class="tarjeta vacio">
        <p><b>Todavia no has hecho ninguna extracción.</b></p>
        <p class="pequeno">Pega un enlace en la portada y empieza en un clic.</p>
        <a class="btn" style="margin-top:14px" href="<?= e(cr_url('index.php')) ?>">Ir a la portada</a>
      </div>
    <?php else: ?>
      <div class="tabla-caja">
        <div class="tabla-scroll">
          <table class="tabla">
            <thead>
              <tr>
                <th>Sitio analizado</th>
                <th>Fecha</th>
                <th>Modo</th>
                <th>Páginas</th>
                <th>Correos</th>
                <th>Estado</th>
                <th>Descargar</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $esc): ?>
              <tr>
                <td>
                  <span class="celda-correo"><?= e(cr_recortar((string) $esc['host'], 34)) ?></span>
                  <div class="celda-url"><?= e(cr_recortar((string) $esc['url_origen'], 58)) ?></div>
                </td>
                <td class="pequeno"><?= e(cr_fecha((string) $esc['inicio'])) ?></td>
                <td><span class="chip chip-gris"><?= (int) $esc['profundo'] === 1 ? 'Profundo' : 'Una página' ?></span></td>
                <td><?= cr_numero((int) $esc['paginas_ok']) ?></td>
                <td><b class="oro"><?= cr_numero((int) $esc['correos']) ?></b></td>
                <td>
                  <?php $est = (string) $esc['estado']; ?>
                  <span class="chip <?= $est === 'completado' ? 'chip-neon' : ($est === 'error' ? 'chip-rojo' : 'chip-gris') ?>">
                    <?= e(ucfirst($est)) ?>
                  </span>
                </td>
                <td>
                  <?php if ((int) $esc['correos'] > 0): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <?php foreach (['txt' => 'TXT', 'csv' => 'CSV', 'xlsx' => 'Excel'] as $f => $et): ?>
                        <form method="post" action="<?= e(cr_url('api/exportar.php')) ?>" style="display:inline">
                          <?= Seguridad::campoCsrf() ?>
                          <input type="hidden" name="escaneo_id" value="<?= (int) $esc['id'] ?>">
                          <input type="hidden" name="formato" value="<?= e($f) ?>">
                          <input type="hidden" name="datos" value="<?= $f === 'xlsx' ? 'todo' : 'correos' ?>">
                          <button type="submit" class="btn btn-fantasma btn-peq"><?= e($et) ?></button>
                        </form>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span class="suave pequeno">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($paginas > 1): ?>
        <nav class="paginacion" aria-label="Páginas">
          <?php for ($i = 1; $i <= $paginas; $i++): ?>
            <?php if ($i === $pagina): ?>
              <span class="actual"><?= $i ?></span>
            <?php else: ?>
              <a href="?p=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<script>
  (function () {
    var f = document.getElementById('form-vaciar');
    if (!f) { return; }
    f.addEventListener('submit', function (e) {
      if (!window.confirm('Se borrará TODO tu historial de extracciones, con sus correos y teléfonos.\n\nEsto no se puede deshacer. \u00bfSeguro?')) {
        e.preventDefault();
      }
    });
  })();
</script>
<?php cr_pie(false); ?>
