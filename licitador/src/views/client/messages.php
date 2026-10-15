<div class="page-head"><div><p class="eyebrow">WhatsApp</p><h1>Mensajes listos para enviar</h1></div></div>
<?php if (!$msgs): ?><div class="empty card"><div class="empty-ic" aria-hidden="true">◔</div><h3>No hay mensajes pendientes</h3><p class="muted">Cuando haya oportunidades nuevas y tengas activadas las alertas por WhatsApp, aparecerán aquí.</p><a class="btn btn-gold" href="<?= e(url('app/alertas')) ?>">Configurar alertas</a></div>
<?php else: foreach ($msgs as $m): ?>
<article class="card msg"><pre class="wa-text"><?= e($m['body']) ?></pre>
  <div class="btn-row"><a class="btn btn-gold btn-sm" href="<?= e($m['wa_link']) ?>" target="_blank" rel="noopener noreferrer">Abrir en WhatsApp</a>
  <form method="post" action="<?= e(url('app/mensajes/' . $m['id'])) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Marcar como enviado</button></form></div></article>
<?php endforeach; endif; ?>
