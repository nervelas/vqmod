<div style="min-height:100dvh;display:grid;place-items:center;padding:24px;background:linear-gradient(160deg,var(--petroleo-3),var(--petroleo))">
  <div style="text-align:center;max-width:460px;color:#E9EEE9">
    <div style="width:84px;height:84px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;background:rgba(201,169,97,.16);color:var(--arcilla-3)">
      <?= ico('rayo', 40) ?>
    </div>
    <h1 style="color:#fff;font-size:1.9rem">Sin conexión</h1>
    <p style="color:rgba(233,238,233,.78)">No pudimos conectarnos con el servidor del residencial. Revise su señal o su red Wi-Fi.</p>
    <p style="color:color-mix(in srgb, #fff 76%, transparent);font-size:.88rem">Si está en la garita, puede seguir registrando ingresos: quedarán guardados en el dispositivo y se enviarán solos cuando vuelva la conexión.</p>
    <div class="fila" style="justify-content:center;margin-top:24px">
      <button class="btn btn-oro" data-recargar><?= ico('refrescar', 18) ?> Reintentar</button>
      <a class="btn btn-fantasma" style="color:#D9E0DA;border-color:rgba(255,255,255,.24)" href="<?= e(url('/garita')) ?>">Ir a la garita</a>
    </div>
  </div>
</div>
