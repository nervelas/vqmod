<?php
/** Cabecera. Variable opcional: $navFull (bool, true en la home). */
require_once __DIR__ . '/_icons.php';
$navFull = !empty($navFull);
$hasDemos = !empty($cfg['demos']) && is_array($cfg['demos']);
$links = [['/#como', 'Cómo funciona'], ['/#planes', 'Planes'], ['/#incluye', 'Qué incluye']];
if ($hasDemos) { $links[] = ['/#ejemplos', 'Ejemplos']; }
$links[] = ['/#faq', 'Preguntas'];
$links[] = ['/#contacto', 'Contacto'];
?>
<header class="hdr">
  <div class="wrap">
    <a class="brand" href="/" aria-label="Servicom, inicio">
      <svg viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="bg1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f8ebc6"/><stop offset=".5" stop-color="#dcb96c"/><stop offset="1" stop-color="#b58a38"/></linearGradient></defs><rect x="2" y="2" width="60" height="60" rx="16" fill="#0a0a0c" stroke="url(#bg1)" stroke-opacity=".6" stroke-width="2"/><path d="M19 24c0-5 4-8 11-8 5 0 9 1.800 11 5l-5 3c-1-2-3.500-3-6-3-3 0-5 1-5 3 0 5 17 2 17 13 0 6-5 10-13 10-6 0-11-2.500-13-7l5-3c1.500 3 4.500 5 8 5 3.500 0 5.500-1.500 5.500-3.500C40 36 19 39 19 24z" fill="url(#bg1)"/></svg>
      <span>Servicom</span>
    </a>
<?php if ($navFull): ?>
    <nav class="nav" aria-label="Principal">
<?php foreach ($links as $l): ?>
      <a href="<?= e($l[0]) ?>"><?= e($l[1]) ?></a>
<?php endforeach; ?>
    </nav>
<?php endif; ?>
<?php if ($navFull): ?>
    <a class="btn btn--gold btn--sm hdr__cta" href="/crear">Crea tu web ahora</a>
    <button class="burger" type="button" aria-expanded="false" aria-controls="mnav" aria-label="Abrir menú"><span></span></button>
<?php else: ?>
    <a class="btn btn--gold btn--sm burger-alt" href="/crear">Crear mi web</a>
<?php endif; ?>
  </div>
</header>
<?php if ($navFull): ?>
<nav class="mnav" id="mnav" aria-label="Menú móvil">
<?php foreach ($links as $l): ?>
  <a href="<?= e($l[0]) ?>"><?= e($l[1]) ?></a>
<?php endforeach; ?>
  <a class="btn btn--gold" href="/crear">Crea tu web ahora</a>
</nav>
<?php endif; ?>
