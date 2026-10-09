<?php
/** 404/500 amables. Variables: $code (404|500|…), $cfg (opcional). Nunca muestra detalles técnicos. */
require_once __DIR__ . '/_icons.php';
$cfg = isset($cfg) && is_array($cfg) ? $cfg : [];
$code = (int)($code ?? 500);
$nf = $code === 404;
$waNum = (string)($cfg['wa_servicom'] ?? '');
?>
<?php include __DIR__ . '/_header.php'; ?>
<main id="main" class="simple">
  <div class="wrap">
    <section class="card panel" aria-labelledby="er-t">
      <span class="ico-big"><?= ico($nf ? 'search' : 'alert') ?></span>
      <h1 id="er-t"><?= $nf ? 'No encontramos esta página' : 'Algo no salió como esperábamos' ?></h1>
      <p><?= $nf ? 'El enlace puede estar incompleto o la página ya no existe. Vuelve al inicio o crea tu web desde aquí.' : 'Ya estamos al tanto. Inténtalo de nuevo en unos minutos; tus datos guardados no se pierden.' ?></p>
      <div class="acts">
        <a class="btn btn--gold" href="/">Ir al inicio</a>
        <a class="btn btn--ghost" href="/crear">Crear mi web</a>
<?php if (!$nf && $waNum !== ''): ?>
        <a class="btn btn--wa" href="<?= e(wa_link($waNum, 'Hola, tuve un problema en el portal.')) ?>" target="_blank" rel="noopener"><?= ico('wa') ?>Escríbenos</a>
<?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php include __DIR__ . '/_footer.php'; ?>
