<div class="pagina-cab">
  <div><h1>Respaldo y tareas programadas</h1><p class="pagina-cab__sub">Copias de seguridad de la base de datos y configuración del cron</p></div>
  <div class="acciones"><a href="<?= e(url('configuracion')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<div class="split">
  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Respaldos</h2>
        <form method="post" action="<?= e(url('configuracion/respaldo')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn--sm"><?= icono('respaldo', 15) ?> Generar ahora</button>
        </form>
      </div>
      <?php if ($archivos === []): ?>
        <div class="vacio sm"><?= icono('respaldo', 40) ?><p>Aún no hay respaldos generados.</p></div>
      <?php else: ?>
        <div class="tabla-env" tabindex="0">
          <table class="tabla" style="min-width:auto">
            <thead><tr><th>Archivo</th><th>Fecha</th><th class="num">Tamaño</th><th class="cen"></th></tr></thead>
            <tbody>
            <?php foreach ($archivos as $a): ?>
              <tr>
                <td class="sm"><?= e($a['nombre']) ?></td>
                <td class="sm"><?= e($a['fecha']) ?></td>
                <td class="num sm"><?= e(number_format($a['tamano'] / 1024, 0)) ?> KB</td>
                <td class="cen"><a class="btn btn--fantasma btn--sm"
                     href="<?= e(url('configuracion/respaldo/' . rawurlencode($a['nombre']))) ?>" aria-label="Descargar respaldo"><?= icono('descargar', 15) ?></a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <p class="sm txt-3 mt-3">Se conservan los 12 respaldos más recientes en <code>/storage/backups/</code>,
         una carpeta protegida y sin acceso público.</p>
    </div>
  </div>

  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Tareas programadas (cron)</h2></div>
      <p class="sm txt-2">Configure en cPanel un cron cada 15 minutos con este comando:</p>
      <pre style="background:var(--superficie-2);border:1px solid var(--borde);border-radius:10px;padding:12px;
                  overflow-x:auto;font-size:.8rem"><code>*/15 * * * * curl -s "<?= e($cronUrl) ?>?token=<?= e($cronToken) ?>"</code></pre>
      <ul class="sm txt-2">
        <li>Recalcula la mora de los cargos vencidos.</li>
        <li>Envía los recordatorios de pago por correo y notificación.</li>
        <li>Genera el respaldo semanal automático los domingos.</li>
        <li>Depura intentos de acceso y notificaciones antiguas.</li>
      </ul>
      <form method="post" action="<?= e(url('configuracion/cron-token')) ?>"
            data-confirmar="Se generará un token nuevo y deberá actualizar el cron en cPanel. ¿Continuar?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--linea btn--sm"><?= icono('escudo', 15) ?> Regenerar token</button>
      </form>
    </div>
  </div>
</div>
