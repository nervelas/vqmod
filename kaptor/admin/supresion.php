<?php
/** Kaptor - Lista de supresión: direcciones que nunca reciben nada. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion = (string) cr_post('accion');

    if ($accion === 'agregar') {
        $n = Supresion::importar((string) cr_post('correos'), 'manual');
        cr_flash($n > 0 ? 'exito' : 'error',
            $n > 0 ? ($n . ' dirección(es) añadidas a la supresión.') : 'No se encontró ninguna dirección válida.');
    } elseif ($accion === 'quitar') {
        Supresion::quitar((string) cr_post('correo'));
        cr_flash('exito', 'Dirección retirada de la lista.');
    }
    cr_redirigir('admin/supresion.php');
}

$buscar = trim((string) cr_get('q', ''));
$pagina = max(1, (int) cr_get('p', 1));
$porPag = 50;

$where = $buscar !== '' ? ' WHERE `correo` LIKE ?' : '';
$params = $buscar !== '' ? ['%' . $buscar . '%'] : [];

$total   = (int) BD::valor('SELECT COUNT(*) FROM `cr_supresion`' . $where, $params, 0);
$paginas = max(1, (int) ceil($total / $porPag));
$pagina  = min($pagina, $paginas);

$filas = BD::todos(
    'SELECT * FROM `cr_supresion`' . $where . ' ORDER BY `creado` DESC LIMIT ' . $porPag . ' OFFSET ' . (($pagina - 1) * $porPag),
    $params
);

$porMotivo = [];
foreach (BD::todos('SELECT `motivo`, COUNT(*) AS n FROM `cr_supresion` GROUP BY `motivo`') as $f) {
    $porMotivo[(string) $f['motivo']] = (int) $f['n'];
}

admin_cabecera(['titulo' => 'Lista de supresión', 'activo' => 'supresion.php']);
?>

<div class="aviso aviso-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
    <path d="M12 3 4 6v6c0 5 3.4 9.4 8 10 4.6-.6 8-5 8-10V6Z"/>
  </svg>
  <span>
    Estas direcciones quedan excluidas de <b>todas</b> las campañas, para siempre.
    Se añaden solas cuando alguien se da de baja o cuando un correo rebota de forma definitiva.
    Respetar esta lista es lo que mantiene limpia la reputación de tu dominio.
  </span>
</div>

<div class="rejilla rejilla-4" style="margin-bottom:20px">
  <div class="metrica"><b><?= cr_numero($total) ?></b><span>Total suprimidas</span></div>
  <div class="metrica"><b><?= cr_numero($porMotivo['baja'] ?? 0) ?></b><span>Bajas voluntarias</span></div>
  <div class="metrica"><b><?= cr_numero($porMotivo['rebote'] ?? 0) ?></b><span>Rebotes</span></div>
  <div class="metrica"><b><?= cr_numero(($porMotivo['manual'] ?? 0) + ($porMotivo['importada'] ?? 0)) ?></b><span>Añadidas a mano</span></div>
</div>

<div class="rejilla rejilla-2" style="align-items:start">
  <div class="tarjeta" style="grid-column:1 / -1">
    <div class="resultados-cab">
      <h3 style="margin:0">Direcciones suprimidas</h3>
      <form method="get" class="buscador" style="min-width:240px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="search" name="q" class="campo" placeholder="Buscar dirección…" value="<?= e($buscar) ?>">
      </form>
    </div>

    <?php if (!$filas): ?>
      <div class="vacio"><p>La lista está vacía. Es buena señal.</p></div>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tabla panel-tabla">
          <thead><tr><th>Dirección</th><th>Motivo</th><th>Detalle</th><th>Fecha</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td><span class="celda-correo"><?= e((string) $f['correo']) ?></span></td>
              <td>
                <?php $m = (string) $f['motivo']; ?>
                <span class="chip <?= $m === 'baja' ? 'chip-neon' : ($m === 'rebote' ? 'chip-rojo' : 'chip-gris') ?>">
                  <?= e(ucfirst($m)) ?>
                </span>
              </td>
              <td class="pequeno suave"><?= e(cr_recortar((string) ($f['detalle'] ?? ''), 60)) ?: '—' ?></td>
              <td class="pequeno"><?= e(cr_fecha((string) $f['creado'])) ?></td>
              <td>
                <form method="post">
                  <?= Seguridad::campoCsrf() ?>
                  <input type="hidden" name="accion" value="quitar">
                  <input type="hidden" name="correo" value="<?= e((string) $f['correo']) ?>">
                  <button class="btn btn-fantasma btn-peq"
                          data-confirmar="¿Seguro? Volverá a poder recibir correos.">Quitar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($paginas > 1): ?>
        <nav class="paginacion">
          <?php
          $base = '?q=' . rawurlencode($buscar) . '&p=';
          $desde = max(1, $pagina - 3); $hasta = min($paginas, $pagina + 3);
          for ($i = $desde; $i <= $hasta; $i++) {
              echo $i === $pagina ? '<span class="actual">' . $i . '</span>'
                                  : '<a href="' . e($base . $i) . '">' . $i . '</a>';
          }
          ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h3>Añadir direcciones</h3>
    <form method="post">
      <?= Seguridad::campoCsrf() ?>
      <input type="hidden" name="accion" value="agregar">
      <div class="campo-grupo">
        <label class="etiqueta" for="correos">Una por línea (o separadas por comas)</label>
        <textarea id="correos" name="correos" class="campo mono" rows="8" required
                  placeholder="alguien@colegio.gt&#10;otro@colegio.gt"></textarea>
      </div>
      <button class="btn btn-bloque">Añadir a la supresión</button>
    </form>
  </div>

  <div class="tarjeta">
    <h3>Cuándo se añade sola</h3>
    <div class="estado-linea"><span><b>Baja</b><em>Alguien pulsa «Darse de baja» en un correo tuyo.</em></span></div>
    <div class="estado-linea"><span><b>Rebote</b><em>El servidor del destinatario responde que la dirección no existe (error 5xx).</em></span></div>
    <div class="estado-linea"><span><b>Manual</b><em>Alguien te responde pidiendo que no le escribas y lo añades tú.</em></span></div>
    <p class="pequeno suave" style="margin-top:14px">
      Consejo: si cambias de herramienta algún día, exporta esta lista y cárgala en la nueva. Nunca se empieza de cero con las bajas.
    </p>
  </div>
</div>

<?php admin_pie(); ?>
