<?php
/** Enlace vencido o eliminado (410). Variables: $cfg (opcional). */
require_once __DIR__ . '/_icons.php';
$cfg = isset($cfg) && is_array($cfg) ? $cfg : [];
$waNum = (string)($cfg['wa_servicom'] ?? '');
?>
<?php include __DIR__ . '/_header.php'; ?>
<main id="main" class="simple">
  <div class="wrap">
    <section class="card panel" aria-labelledby="gn-t">
      <span class="ico-big"><?= ico('clock') ?></span>
      <h1 id="gn-t">Este enlace ya no está disponible</h1>
      <p>Por seguridad, los borradores y vistas previas se eliminan pasado un tiempo. Puedes empezar de nuevo en menos de 5 minutos.</p>
      <div class="acts">
        <a class="btn btn--gold" href="/crear">Crear mi web ahora</a>
<?php if ($waNum !== ''): ?>
        <a class="btn btn--wa" href="<?= e(wa_link($waNum, 'Hola, mi enlace ya no funciona y quiero recuperar mi web.')) ?>" target="_blank" rel="noopener"><?= ico('wa') ?>Necesito ayuda<span class="sr"> (se abre en una pestaña nueva)</span></a>
<?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php include __DIR__ . '/_footer.php'; ?>
