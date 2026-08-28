<?php use MenuGold\Core\View; View::set('titulo', 'Sin conexión · MenúGold'); ?>
<main style="min-height:100vh;display:grid;place-items:center;padding:32px 22px;text-align:center">
  <div style="max-width:420px">
    <div style="width:96px;height:96px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;
                border:2px solid var(--acento);color:var(--acento)">
      <?= icon('globe', 'ico-lg') ?>
    </div>
    <h1 style="font-family:var(--fuente-titulo);font-size:30px;margin:0 0 10px">Sin conexión</h1>
    <p style="color:var(--texto-suave);margin:0 0 22px">
      Parece que te quedaste sin internet. El menú que ya viste sigue disponible;
      para pedir necesitamos volver a conectarnos.
    </p>
    <button class="btn btn--oro" type="button" onclick="location.reload()">
      <?= icon('refresh') ?> Reintentar
    </button>
  </div>
</main>
