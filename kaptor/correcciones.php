<?php
/**
 * Kaptor - El archivo de correcciones de una auditoría.
 *
 * El informe dice qué está mal; esto entrega lo que hay que pegar para
 * arreglarlo, ya escrito con los datos de ese sitio y solo con lo que de
 * verdad le falta.
 *
 * Con ?bajar=1 sale el paquete entero como archivo de texto, para adjuntarlo
 * a un correo o mandarlo por WhatsApp.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

cr_exigir_sesion();

$id   = (int) ($_GET['id'] ?? 0);
$fila = $id > 0 ? Auditor::porId($id) : null;

if ($fila && !Auth::esAdmin() && (int) $fila['usuario_id'] !== Auth::id()) { $fila = null; }

if (!$fila || $fila['estado'] !== 'listo') {
    http_response_code(404);
    cr_cabecera(['titulo' => 'Correcciones no disponibles', 'css' => ['auditor.css']]);
    echo '<section class="seccion-auditor"><div class="contenedor"><div class="aviso aviso-error"><span>'
       . 'Esa auditoría no existe o no llegó a terminar.'
       . '</span></div><p><a class="btn btn-fantasma" href="' . e(cr_url('auditor.php')) . '">Volver al auditor</a></p></div></section>';
    cr_pie(false);
    exit;
}

$hallazgos = Informe::hallazgosDe($fila);
$datos     = Informe::datosDe($fila);
$paquete   = Correcciones::armar($fila, $hallazgos, $datos);
$marca     = Ajustes::obtener('sitio_nombre', 'Kaptor');

// Descarga del paquete entero en un archivo.
if (($_GET['bajar'] ?? '') !== '') {
    Exportador::descargar(
        Correcciones::comoTexto($paquete, $fila, $marca),
        'correcciones-' . cr_slug((string) $fila['host']) . '-' . date('Y-m-d') . '.txt',
        'text/plain; charset=utf-8'
    );
}

cr_cabecera([
    'titulo'      => 'Correcciones de ' . $fila['host'],
    'descripcion' => 'Lo que hay que cambiar en ' . $fila['host'] . ', listo para pegar.',
    'activo'      => 'auditor',
    'css'         => ['auditor.css'],
]);
?>

<section class="seccion-informe">
  <div class="contenedor contenedor-informe">

    <div class="inf-acciones no-imprimir">
      <a class="btn btn-fantasma btn-fino" href="<?= e(cr_url('informe.php?id=' . (int) $fila['id'])) ?>">← Volver al informe</a>
      <div class="inf-acciones-der">
        <a class="btn btn-oro btn-fino" href="<?= e(cr_url('correcciones.php?id=' . (int) $fila['id'] . '&bajar=1')) ?>">
          Descargar todo en un archivo
        </a>
      </div>
    </div>

    <header class="inf-cabecera">
      <div class="inf-marca">
        <?php $logo = cr_logo_url(); ?>
        <?php if ($logo !== ''): ?>
          <img src="<?= e($logo) ?>" alt="<?= e($marca) ?>" class="inf-logo">
        <?php else: ?>
          <?= cr_logo_svg(38) ?>
        <?php endif; ?>
        <div>
          <p class="inf-marca-nombre"><?= e($marca) ?></p>
          <p class="inf-marca-lema">Correcciones para <?= e((string) $fila['host']) ?></p>
        </div>
      </div>
      <p class="inf-fecha"><?= e(date('d/m/Y')) ?></p>
    </header>

    <section class="corr-intro">
      <h1>Lo que hay que cambiar</h1>
      <p class="inf-resumen"><?= e($paquete['resumen']) ?></p>
      <div class="aviso aviso-info">
        <span>
          <b>Antes de tocar nada, haz una copia de seguridad.</b> Aquí solo aparece lo que le falta
          a <?= e((string) $fila['host']) ?>: lo que ya tiene bien no se toca.
        </span>
      </div>
    </section>

    <?php if (!Correcciones::hayAlgo($paquete)): ?>
      <div class="vacio"><p><b>No hay nada que corregir.</b></p>
        <p class="pequeno">El sitio pasó todas las comprobaciones que tienen arreglo conocido.</p></div>
    <?php endif; ?>

    <!-- 1. El .htaccess --------------------------------------------------- -->
    <?php if ($paquete['htaccess'] !== ''): ?>
      <section class="corr-bloque">
        <div class="corr-cab">
          <span class="corr-n">1</span>
          <div>
            <h2>Archivo <code>.htaccess</code></h2>
            <p class="inf-sub">Se pega en el hosting y no toca el sitio. Es lo que más arregla con menos trabajo.</p>
          </div>
        </div>
        <div class="corr-codigo">
          <button type="button" class="btn btn-fantasma btn-peq corr-copiar" data-copiar="htaccess">Copiar</button>
          <pre id="htaccess"><code><?= e($paquete['htaccess']) ?></code></pre>
        </div>
      </section>
    <?php endif; ?>

    <!-- 2. El código ------------------------------------------------------- -->
    <?php if ($paquete['bloques']): ?>
      <section class="corr-bloque">
        <div class="corr-cab">
          <span class="corr-n">2</span>
          <div>
            <h2>Código para pegar en las páginas</h2>
            <p class="inf-sub">Cada trozo dice dónde va. Ya están rellenos con los datos de este sitio.</p>
          </div>
        </div>

        <?php foreach ($paquete['bloques'] as $i => $b): ?>
          <article class="corr-item">
            <h3><?= e($b['titulo']) ?></h3>
            <p class="corr-donde"><b>Dónde va:</b> <?= e($b['donde']) ?></p>
            <div class="corr-codigo">
              <button type="button" class="btn btn-fantasma btn-peq corr-copiar" data-copiar="bloque-<?= (int) $i ?>">Copiar</button>
              <pre id="bloque-<?= (int) $i ?>"><code><?= e($b['codigo']) ?></code></pre>
            </div>
            <?php if ($b['nota'] !== ''): ?>
              <p class="corr-nota"><?= e($b['nota']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <!-- 3. Lo que es trabajo ------------------------------------------------ -->
    <?php if ($paquete['manual']): ?>
      <section class="corr-bloque">
        <div class="corr-cab">
          <span class="corr-n">3</span>
          <div>
            <h2>Lo que no se arregla pegando código</h2>
            <p class="inf-sub">Aquí no hay atajo: es trabajo. Y es donde está tu presupuesto.</p>
          </div>
        </div>
        <ul class="corr-manual">
          <?php foreach ($paquete['manual'] as $m): ?>
            <li>
              <h3><?= e($m['titulo']) ?></h3>
              <p><?= e($m['pasos']) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

  </div>
</section>

<script>
(function () {
  Array.prototype.forEach.call(document.querySelectorAll('.corr-copiar'), function (b) {
    b.addEventListener('click', function () {
      var caja = document.getElementById(b.getAttribute('data-copiar'));
      if (!caja) { return; }
      var txt = caja.textContent || '';
      var listo = function () {
        var antes = b.textContent;
        b.textContent = 'Copiado';
        setTimeout(function () { b.textContent = antes; }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(listo, function () { window.prompt('Copia el código:', txt); });
      } else {
        window.prompt('Copia el código:', txt);
      }
    });
  });
})();
</script>

<?php cr_pie(false); ?>
