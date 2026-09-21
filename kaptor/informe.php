<?php
/**
 * Kaptor - El informe de auditoría.
 *
 * Es la pieza que se entrega al cliente, así que manda la presentación: nota
 * grande arriba, las tres cosas urgentes, la comparativa con la competencia y
 * el detalle por áreas.
 *
 * Se abre de dos maneras:
 *   ?id=123      dentro de la sesión, para quien hizo la auditoría.
 *   ?t=<token>   con el enlace para compartir, sin sesión, para enseñárselo al
 *                dueño del sitio. El token es aleatorio de 32 caracteres y solo
 *                muestra el análisis de una web pública.
 *
 * El PDF sale de imprimir la propia página: las reglas de @media print dejan
 * el documento listo, y así no hace falta ninguna librería en el hosting.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

$token = trim((string) ($_GET['t'] ?? ''));
$id    = (int) ($_GET['id'] ?? 0);

$fila = null;
$compartido = false;

if ($token !== '') {
    $fila = Auditor::porToken($token);
    $compartido = true;
} elseif ($id > 0) {
    cr_exigir_sesion();
    $fila = Auditor::porId($id);
    // Cada quien ve lo suyo; el administrador ve todo.
    if ($fila && !Auth::esAdmin() && (int) $fila['usuario_id'] !== Auth::id()) { $fila = null; }
}

if (!$fila || $fila['estado'] !== 'listo') {
    http_response_code(404);
    cr_cabecera(['titulo' => 'Informe no disponible']);
    echo '<section class="seccion-depurar"><div class="contenedor"><div class="aviso aviso-mal">'
       . 'Ese informe no existe, todavía no ha terminado o no se pudo completar.'
       . '</div><p><a class="btn btn-fantasma" href="' . e(cr_url('auditor.php')) . '">Volver al auditor</a></p></div></section>';
    cr_pie(false);
    exit;
}

$hallazgos = Informe::hallazgosDe($fila);
$datos     = Informe::datosDe($fila);
$nota      = (int) $fila['nota'];
$areas     = Informe::areasDe($fila);
$modo      = (string) ($fila['modo'] ?? 'completo');
$global    = (int) ($areas['_global'] ?? $nota);
unset($areas['_global']);

// El camino al 100 %: cada arreglo con los puntos que devuelve. En los modos a
// fondo solo cuenta su área; en el completo, todo.
$plan = Seo::plan($hallazgos, Informe::areasDeModo($modo));
$recuento  = Informe::recuento($hallazgos);
$urgentes  = Informe::problemas($hallazgos, 3);
$criticos  = Informe::criticos($hallazgos);
$problemas = Informe::problemas($hallazgos);
$porArea   = Informe::porArea($hallazgos);

// Competencia, si la hubo.
$lote     = (string) $fila['lote'];
$hermanas = $lote !== '' ? Auditor::porLote($lote) : [];
$rivales  = array_values(array_filter($hermanas, static fn($h) => $h['papel'] === 'competidor' && $h['estado'] === 'listo'));
$comparativa = $rivales ? Informe::comparativa($fila, $rivales) : [];

$marca    = Ajustes::obtener('sitio_nombre', 'Kaptor');
$lema     = Ajustes::obtener('informe_lema', 'Diagnóstico técnico de tu sitio web');
$contacto = trim(Ajustes::obtener('informe_contacto'));
$cta      = trim(Ajustes::obtener('informe_cta'));
$logo     = cr_logo_url();

cr_cabecera([
    'titulo'      => 'Informe de ' . $fila['host'],
    'descripcion' => 'Diagnóstico técnico de ' . $fila['host'],
    'activo'      => 'auditor',
    'clase'       => 'pagina-informe',
    'css'         => ['auditor.css'],
]);
?>

<section class="seccion-informe">
  <div class="contenedor contenedor-informe">

    <!-- Barra de acciones: no sale en el papel --------------------------- -->
    <div class="inf-acciones no-imprimir">
      <?php if (!$compartido): ?>
        <a class="btn btn-fantasma btn-fino" href="<?= e(cr_url('auditor.php')) ?>">← Auditor</a>
      <?php endif; ?>
      <div class="inf-acciones-der">
        <?php if (!$compartido): ?>
          <a class="btn btn-fantasma btn-fino" href="<?= e(cr_url('correcciones.php?id=' . (int) $fila['id'])) ?>">
            Generar correcciones
          </a>
          <button type="button" class="btn btn-fantasma btn-fino" id="btn-enlace"
                  data-enlace="<?= e(cr_url('informe.php?t=' . $fila['token'])) ?>">
            Copiar enlace para el cliente
          </button>
        <?php endif; ?>
        <button type="button" class="btn btn-oro btn-fino" onclick="window.print()">Descargar PDF</button>
      </div>
    </div>

    <!-- Encabezado del documento ------------------------------------------ -->
    <header class="inf-cabecera">
      <div class="inf-marca">
        <?php if ($logo !== ''): ?>
          <img src="<?= e($logo) ?>" alt="<?= e($marca) ?>" class="inf-logo">
        <?php else: ?>
          <?= cr_logo_svg(38) ?>
        <?php endif; ?>
        <div>
          <p class="inf-marca-nombre"><?= e($marca) ?></p>
          <p class="inf-marca-lema"><?= e($lema) ?></p>
        </div>
      </div>
      <p class="inf-fecha">Informe del <?= e(cr_fecha($fila['creado'], false)) ?></p>
    </header>

    <!-- Aviso grave: va antes que nada, incluso antes de la nota --------- -->
    <?php if ($criticos): ?>
      <section class="inf-alarma" role="alert">
        <span class="inf-alarma-icono" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3.2 1.8 20.8h20.4L12 3.2Z"/><path d="M12 9.6v4.6"/><path d="M12 17.4h.01"/>
          </svg>
        </span>
        <div>
          <h2><?= count($criticos) === 1 ? 'Atención: hay un problema grave' : 'Atención: hay ' . e((string) count($criticos)) . ' problemas graves' ?></h2>
          <ul>
            <?php foreach ($criticos as $c): ?>
              <li><strong><?= e($c['titulo']) ?></strong><?= $c['valor'] !== '' ? ' — ' . e($c['valor']) : '' ?></li>
            <?php endforeach; ?>
          </ul>
          <p class="inf-alarma-nota">
            Hasta que esto se resuelva, cualquier otra mejora del sitio no va a notarse.
          </p>
        </div>
      </section>
    <?php endif; ?>

    <!-- Nota global -------------------------------------------------------- -->
    <section class="inf-veredicto">
      <div class="inf-nota-caja inf-<?= e(Informe::color($nota)) ?>">
        <?= cr_anillo_nota($nota) ?>
        <p class="inf-nota-etiqueta"><?= e(Informe::etiqueta($nota)) ?></p>
      </div>
      <div class="inf-veredicto-txt">
        <p class="inf-sitio"><?= e($fila['host']) ?><?php if ($modo !== 'completo'): ?>
          <span class="inf-modo"><?= e(Informe::nombreNota($modo)) ?></span>
        <?php endif; ?></p>
        <h1><?= e($fila['titulo'] ?: $fila['host']) ?></h1>
        <p class="inf-resumen"><?= e(Informe::veredicto($nota, $hallazgos)) ?></p>
        <ul class="inf-recuento">
          <li><strong class="n-mal"><?= e((string) $recuento['mal']) ?></strong> por corregir</li>
          <li><strong class="n-aviso"><?= e((string) $recuento['aviso']) ?></strong> mejorables</li>
          <li><strong class="n-bien"><?= e((string) $recuento['bien']) ?></strong> correctos</li>
          <?php if ($modo !== 'completo'): ?>
            <li><strong><?= e((string) $global) ?></strong> nota global del sitio</li>
          <?php endif; ?>
        </ul>
      </div>
    </section>

    <!-- Notas por área ------------------------------------------------------ -->
    <section class="inf-areas">
      <?php foreach (Chequeos::AREAS as $clave => $info):
        $n = (int) ($areas[$clave] ?? 0); ?>
        <div class="inf-area inf-<?= e(Informe::color($n)) ?>">
          <span class="inf-area-icono"><?= cr_icono_area($clave) ?></span>
          <p class="inf-area-nombre"><?= e($info['nombre']) ?></p>
          <p class="inf-area-nota"><?= e((string) $n) ?><small>/100</small></p>
          <div class="inf-barra"><span style="width:<?= e((string) max(2, $n)) ?>%"></span></div>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- Lo urgente ----------------------------------------------------------- -->
    <?php if ($urgentes): ?>
      <section class="inf-urgente">
        <h2>Lo primero que hay que arreglar</h2>
        <p class="inf-sub">De todo lo encontrado, estas tres cosas son las que más le están costando al negocio.</p>
        <ol class="inf-urgente-lista">
          <?php foreach ($urgentes as $i => $h): ?>
            <li>
              <span class="inf-orden"><?= e((string) ($i + 1)) ?></span>
              <div>
                <h3><?= e($h['titulo']) ?></h3>
                <?php if ($h['cuesta'] !== ''): ?><p class="inf-cuesta"><?= e($h['cuesta']) ?></p><?php endif; ?>
                <?php if ($h['arreglo'] !== ''): ?><p class="inf-arreglo"><strong>Solución:</strong> <?= e($h['arreglo']) ?></p><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>

    <!-- El camino al 100 % ------------------------------------------------- -->
    <?php if ($plan): $faltan = array_sum(array_column($plan, 'puntos')); ?>
      <section class="inf-plan">
        <h2>Qué hay que cambiar para llegar al 100 %</h2>
        <p class="inf-sub">
          Cada arreglo con los puntos que devuelve. Están ordenados por lo que más suben la nota,
          así que haciendo los de arriba se avanza más con menos trabajo.
        </p>

        <div class="plan-barra" aria-hidden="true">
          <span class="plan-hecho" style="width:<?= e((string) max(1, min(100, $nota))) ?>%"></span>
        </div>
        <p class="plan-cuenta">
          <strong><?= e((string) $nota) ?></strong> ahora
          <span>+</span>
          <strong><?= e(number_format($faltan, 1, ',', '.')) ?></strong> que se pueden recuperar
          <span>=</span>
          <strong class="plan-meta">100</strong>
        </p>

        <ol class="plan-lista">
          <?php foreach ($plan as $paso): ?>
            <li class="plan-paso est-<?= e($paso['estado']) ?><?= $paso['critico'] ? ' plan-critico' : '' ?>">
              <span class="plan-puntos">+<?= e(rtrim(rtrim(number_format($paso['puntos'], 1, ',', '.'), '0'), ',')) ?></span>
              <div class="plan-cuerpo">
                <p class="plan-titulo"><?= e($paso['titulo']) ?></p>
                <?php if ($paso['arreglo'] !== ''): ?>
                  <p class="plan-arreglo"><?= e($paso['arreglo']) ?></p>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>

    <!-- Comparativa ------------------------------------------------------------ -->
    <?php if ($comparativa): ?>
      <section class="inf-comparativa">
        <h2>Frente a su competencia</h2>
        <p class="inf-sub">La misma revisión aplicada a <?= e((string) count($rivales)) ?>
          <?= count($rivales) === 1 ? 'competidor' : 'competidores' ?>, área por área.</p>
        <div class="tabla-envoltorio">
          <table class="inf-tabla">
            <thead>
              <tr>
                <th>Área</th>
                <th class="col-mia"><?= e($fila['host']) ?></th>
                <?php foreach ($rivales as $r): ?><th><?= e($r['host']) ?></th><?php endforeach; ?>
                <th>Resultado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($comparativa as $c): ?>
                <tr>
                  <td class="td-area"><?= e($c['nombre']) ?></td>
                  <td class="col-mia"><span class="pastilla p-<?= e(Informe::color($c['mia'])) ?>"><?= e((string) $c['mia']) ?></span></td>
                  <?php foreach ($c['rivales'] as $r): ?>
                    <td><span class="pastilla p-<?= e(Informe::color($r['nota'])) ?>"><?= e((string) $r['nota']) ?></span></td>
                  <?php endforeach; ?>
                  <td class="td-veredicto <?= $c['gana'] ? 'gana' : 'pierde' ?>">
                    <?= $c['gana'] ? 'Va por delante' : 'Va por detrás' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <!-- Detalle por área --------------------------------------------------------- -->
    <section class="inf-detalle">
      <h2>Revisión completa</h2>
      <p class="inf-sub"><?= e((string) count($hallazgos)) ?> comprobaciones agrupadas por área.</p>

      <?php foreach ($porArea as $area => $lista):
        $info = Chequeos::AREAS[$area];
        $n = (int) ($areas[$area] ?? 0); ?>
        <div class="inf-grupo">
          <div class="inf-grupo-cab">
            <span class="inf-area-icono"><?= cr_icono_area($area) ?></span>
            <h3><?= e($info['nombre']) ?></h3>
            <span class="pastilla p-<?= e(Informe::color($n)) ?>"><?= e((string) $n) ?>/100</span>
          </div>
          <ul class="inf-items">
            <?php foreach ($lista as $h): ?>
              <li class="inf-item est-<?= e($h['estado']) ?>">
                <span class="inf-marca-estado" aria-hidden="true"></span>
                <div class="inf-item-cuerpo">
                  <p class="inf-item-titulo">
                    <?= e($h['titulo']) ?>
                    <?php if ($h['valor'] !== ''): ?><span class="inf-valor"><?= e($h['valor']) ?></span><?php endif; ?>
                  </p>
                  <?php if ($h['estado'] !== Chequeos::BIEN && $h['cuesta'] !== ''): ?>
                    <p class="inf-item-cuesta"><?= e($h['cuesta']) ?></p>
                  <?php endif; ?>
                  <?php if ($h['estado'] !== Chequeos::BIEN && $h['arreglo'] !== ''): ?>
                    <p class="inf-item-arreglo"><?= e($h['arreglo']) ?></p>
                  <?php endif; ?>
                </div>
                <span class="solo-lectores"><?= e(match ($h['estado']) {
                    Chequeos::BIEN => 'Correcto', Chequeos::AVISO => 'Mejorable', default => 'Por corregir',
                }) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- Datos técnicos ------------------------------------------------------------- -->
    <section class="inf-tecnico">
      <h2>Datos medidos</h2>
      <dl class="inf-dl">
        <div><dt>Dirección analizada</dt><dd><?= e((string) ($datos['url'] ?? $fila['url'])) ?></dd></div>
        <?php if (!empty($datos['tiempos']['ttfb'])): ?>
          <div><dt>Respuesta del servidor</dt><dd><?= e((string) $datos['tiempos']['ttfb']) ?> ms</dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['bytes'])): ?>
          <div><dt>Peso del documento</dt><dd><?= e(number_format(((int) $datos['bytes']) / 1024, 0, ',', '.')) ?> KB</dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['version'])): ?>
          <div><dt>Protocolo</dt><dd>HTTP/<?= e((string) $datos['version']) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['cert']['vence'])): ?>
          <div><dt>Certificado</dt><dd><?= e((string) $datos['cert']['emisor']) ?> · vence el <?= e(cr_fecha($datos['cert']['vence'], false)) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['psi']['rendimiento'])): ?>
          <div><dt>Nota de Google (celular)</dt><dd><?= e((string) $datos['psi']['rendimiento']) ?>/100</dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['paginas'])): ?>
          <div><dt>Páginas recorridas</dt><dd><?= e((string) count($datos['paginas'])) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['vinculos']['revisados'])): ?>
          <div><dt>Enlaces comprobados</dt><dd><?= e((string) (int) $datos['vinculos']['revisados']) ?>
            <span class="suave">(<?= e((string) (int) $datos['vinculos']['internos']) ?> internos)</span></dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['archivos_js'])): ?>
          <div><dt>Archivos de código abiertos</dt><dd><?= e((string) count($datos['archivos_js'])) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['virustotal']['motores'])): ?>
          <div><dt>Motores antivirus</dt><dd><?= e((string) (int) $datos['virustotal']['detectan']) ?>
            de <?= e((string) (int) $datos['virustotal']['motores']) ?> lo marcan</dd></div>
        <?php endif; ?>
        <?php if (!empty($datos['psi']['real']['lcp'])): ?>
          <div><dt>Usuarios reales · carga</dt><dd><?= e(number_format(((int) $datos['psi']['real']['lcp']) / 1000, 1, ',', '.')) ?> s</dd></div>
        <?php endif; ?>
      </dl>
      <?php if (!empty($datos['psi_aviso'])): ?>
        <p class="inf-nota-pie no-imprimir"><?= e((string) $datos['psi_aviso']) ?></p>
      <?php endif; ?>
    </section>

    <!-- Cierre ---------------------------------------------------------------------- -->
    <?php if ($cta !== '' || $contacto !== ''): ?>
      <section class="inf-cierre">
        <?php if ($cta !== ''): ?><p class="inf-cta"><?= e($cta) ?></p><?php endif; ?>
        <?php if ($contacto !== ''): ?><p class="inf-contacto"><?= nl2br(e($contacto)) ?></p><?php endif; ?>
      </section>
    <?php endif; ?>

    <footer class="inf-pie">
      <p><?= e($marca) ?> · Informe generado el <?= e(cr_fecha($fila['creado'])) ?></p>
      <p class="inf-pie-nota">
        Las mediciones se tomaron en el momento indicado sobre la página de inicio del sitio.
        Los valores de velocidad pueden variar según la conexión y la hora.
        La búsqueda de código malicioso se hace desde fuera, sobre lo que el sitio sirve
        al público: detecta lo que llega al navegador del visitante, pero no puede ver los
        archivos del servidor. No encontrar nada aquí no equivale a un certificado de
        que el sitio esté limpio por dentro.
      </p>
    </footer>

  </div>
</section>

<script>
(function () {
  var b = document.getElementById('btn-enlace');
  if (!b) { return; }
  b.addEventListener('click', function () {
    var url = b.getAttribute('data-enlace') || '';
    var listo = function () {
      var antes = b.textContent;
      b.textContent = 'Enlace copiado';
      setTimeout(function () { b.textContent = antes; }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(listo, function () { window.prompt('Copia el enlace:', url); });
    } else {
      window.prompt('Copia el enlace:', url);
    }
  });
})();
</script>

<?php cr_pie(false); ?>
