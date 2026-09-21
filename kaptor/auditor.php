<?php
/**
 * Kaptor - Auditor web.
 *
 * Se pega una dirección (o una lista entera salida del extractor) y Kaptor
 * revisa cada sitio en seis frentes: velocidad, celular, Google, seguridad,
 * contacto y visibilidad en las IA. De cada uno sale un informe con la marca
 * del usuario, listo para enviárselo al dueño del negocio.
 *
 * Esta página solo arma el formulario y la lista de resultados: el trabajo lo
 * hace api/auditar.php por fases, y el informe vive en informe.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

cr_exigir_sesion();

$activo = Ajustes::activo('auditor_activo', true);
$sinPsi = trim(Ajustes::obtener('psi_clave')) === '' && Ajustes::activo('psi_activo', true);

// Últimas auditorías del usuario, para volver a un informe sin buscarlo.
$historial = Auditor::historial(Auth::id(), 12);

// Se puede llegar desde otra página con las direcciones ya puestas.
$precargado = trim((string) ($_GET['sitios'] ?? ''));

cr_cabecera([
    'titulo'      => 'Auditor web',
    'descripcion' => 'Analiza cualquier sitio web y genera un informe con tu marca.',
    'activo'      => 'auditor',
    'css'         => ['auditor.css'],
]);
?>

<section class="seccion-auditor">
  <div class="contenedor">

    <header class="aud-intro">
      <p class="etiqueta-seccion"><span class="punto"></span> Diagnóstico técnico</p>
      <h1><?= cr_titulo_brillo('Audita cualquier web y entrega el informe') ?></h1>
      <p class="sub">
        Pega una dirección y Kaptor revisa velocidad, celular, Google, seguridad,
        contacto y visibilidad en las inteligencias artificiales. Sale un informe
        con tu marca, listo para enviar.
      </p>
    </header>

    <?php if (!$activo): ?>
      <div class="aviso aviso-mal">El auditor está desactivado en los ajustes del panel.</div>
    <?php endif; ?>

    <div class="aud-cuadro">
      <form id="form-auditor" autocomplete="off" novalidate>
        <?= Seguridad::campoCsrf() ?>

        <div class="paso-bloque">
          <span class="paso-n">01</span>
          <div class="paso-cuerpo">
            <label for="sitios" class="paso-titulo">¿Qué sitios quieres revisar?</label>
            <textarea id="sitios" name="sitios" rows="4" spellcheck="false"
                      placeholder="colegio.edu.gt&#10;otraempresa.com&#10;&#10;Una por línea. Puedes pegar la lista completa que sacaste con el extractor."><?= e($precargado) ?></textarea>
            <p class="paso-nota">Hasta <?= e((string) Ajustes::entero('auditor_max_lote', 50, 1, 300)) ?> sitios por tanda. No hace falta escribir «https://».</p>
          </div>
        </div>

        <div class="paso-bloque">
          <span class="paso-n">02</span>
          <div class="paso-cuerpo">
            <label for="rivales" class="paso-titulo">
              Compáralo con su competencia
              <span class="opcional">opcional</span>
            </label>
            <textarea id="rivales" name="rivales" rows="2" spellcheck="false"
                      placeholder="competidor1.com&#10;competidor2.com"></textarea>
            <p class="paso-nota">
              Hasta tres. El informe enseñará lado a lado quién va ganando en cada área:
              es lo que cierra la venta. Solo funciona cuando auditas <strong>un</strong> sitio.
            </p>
          </div>
        </div>

        <div class="aud-acciones">
          <button type="submit" class="btn btn-oro btn-grande" id="btn-auditar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
              <path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="5.2"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/>
            </svg>
            Auditar
          </button>
          <button type="button" class="btn btn-fantasma oculto" id="btn-parar">Detener</button>
        </div>

        <?php if ($sinPsi): ?>
          <p class="aud-consejo">
            <strong>Consejo:</strong> añade tu clave gratuita de Google en
            <?php if (Auth::esAdmin()): ?><a href="<?= e(cr_url('admin/ajustes.php#auditor')) ?>">Ajustes</a><?php else: ?>Ajustes<?php endif; ?>
            y cada informe traerá además la nota oficial de velocidad de Google. Son 25.000 consultas gratis al día.
          </p>
        <?php endif; ?>
      </form>
    </div>

    <!-- Progreso y resultados ------------------------------------------- -->
    <div id="aud-panel" class="aud-panel oculto">
      <div class="aud-panel-cab">
        <h2 id="aud-titulo">Analizando…</h2>
        <p id="aud-aviso" class="aud-aviso oculto"></p>
      </div>
      <div id="aud-lista" class="aud-lista"></div>
    </div>

    <!-- Historial --------------------------------------------------------- -->
    <?php if ($historial): ?>
      <section class="aud-historial" id="aud-historial">
        <h2>Auditorías recientes</h2>
        <div class="aud-lista">
          <?php foreach ($historial as $a):
            $nota = $a['nota'] !== null ? (int) $a['nota'] : null;
            $color = $nota !== null ? Informe::color($nota) : 'gris';
          ?>
            <article class="aud-fila">
              <div class="aud-nota aud-<?= e($color) ?>">
                <?= $nota !== null ? e((string) $nota) : '—' ?>
              </div>
              <div class="aud-datos">
                <p class="aud-host"><?= e($a['host']) ?></p>
                <p class="aud-meta">
                  <?= e($a['titulo'] ?: 'Sin título') ?> ·
                  <?= e(cr_fecha($a["creado"], false)) ?>
                </p>
              </div>
              <div class="aud-botones">
                <?php if ($a['estado'] === 'listo'): ?>
                  <a class="btn btn-fino" href="<?= e(cr_url('informe.php?id=' . (int) $a['id'])) ?>">Ver informe</a>
                <?php else: ?>
                  <span class="aud-estado"><?= e($a['estado'] === 'error' ? 'No se pudo' : 'Sin terminar') ?></span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </div>
</section>

<script>
window.CR_AUDITOR = { api: <?= json_encode(cr_url('api/auditar.php')) ?> };
</script>
<script src="<?= e(cr_url('assets/js/auditor.js')) ?>?v=<?= e(CR_VERSION) ?>" defer></script>

<?php cr_pie(false); ?>
