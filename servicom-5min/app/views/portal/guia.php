<?php
/** Guía: qué debe incluir la presentación (PDF) del cliente. Variables: $secciones, $cfg. */
require_once __DIR__ . '/_icons.php';
?>
<?php include __DIR__ . '/_header.php'; ?>
<main id="main" class="simple">
  <div class="wrap">
    <section class="card panel panel--guia" aria-labelledby="g-t">
      <span class="ico-big"><?= ico('file') ?></span>
      <h1 id="g-t">Qué poner en tu presentación</h1>
      <p>Lo único que necesitas subir es <b>un archivo PDF</b> con la información de tu negocio. Con eso creamos tu web completa: diseño, colores de tu logo, textos y fotos. No necesitas saber de diseño.</p>
<?php foreach ($secciones as $s): ?>
      <div class="guia__blk">
        <h2><?= e($s['t']) ?></h2>
        <p class="guia__n"><?= e($s['n']) ?></p>
        <ul>
<?php foreach ($s['i'] as $it): ?>
          <li><b><?= e($it[0]) ?></b><span><?= e($it[1]) ?></span></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endforeach; ?>
      <div class="guia__blk guia__tips">
        <h2>Consejos</h2>
        <ul>
          <li><span>Puedes hacerlo en Word, Google Docs, PowerPoint o Canva y guardarlo como <b>PDF</b>.</span></li>
          <li><span>Pon el logo y las fotos <b>dentro del mismo documento</b>: las usamos en tu web.</span></li>
          <li><span>Si algo no lo tienes (por ejemplo el WhatsApp), no pasa nada: tu web se crea igual y en tu panel verás <b>qué falta, con un botón para completarlo</b>.</span></li>
          <li><span>Nunca inventamos teléfonos, direcciones ni cifras. Lo que no venga en tu archivo queda como pendiente para que lo escribas tú.</span></li>
        </ul>
      </div>
      <div class="acts">
        <a class="btn btn--ghost" href="/guia-presentacion/plantilla" download>Descargar plantilla para completar</a>
        <a class="btn btn--ghost" href="/assets/ejemplos/ejemplo-presentacion-servicios-legales.pdf" target="_blank" rel="noopener">Ver un PDF de ejemplo (servicios legales)</a>
        <a class="btn btn--gold" href="/crear">Subir mi PDF y crear mi web</a>
      </div>
    </section>
  </div>
</main>
<?php include __DIR__ . '/_footer.php'; ?>
