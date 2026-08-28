<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Respaldos disponibles</h3>
      <form method="post">
        <?= csrf() ?>
        <input type="hidden" name="accion" value="crear">
        <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Crear respaldo ahora</button>
      </form>
    </div>
    <?php if ($archivos === []): ?>
      <div class="vacio"><?= ico('guardar', 44) ?>
        <h3>Todavía no hay respaldos</h3>
        <p>Genere el primero con el botón de arriba. Se conservan los 12 más recientes.</p>
      </div>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla apilar">
          <thead><tr><th>Archivo</th><th class="c">Fecha</th><th class="d">Tamaño</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($archivos as $f): ?>
              <tr>
                <td data-et="Archivo" class="fuerte"><?= e($f['nombre']) ?></td>
                <td data-et="Fecha" class="c texto-3"><?= e(date('d/m/Y H:i', (int) $f['fecha'])) ?></td>
                <td data-et="Tamaño" class="d num"><?= e(number_format($f['tamano'] / 1024, 0)) ?> KB</td>
                <td data-et="" class="d nowrap">
                  <a class="btn btn-sm btn-claro" href="<?= e(url('/admin/respaldos', ['descargar' => $f['nombre']])) ?>"><?= ico('descargar', 15) ?> Descargar</a>
                  <form method="post" style="display:inline"
                        data-confirmar="El archivo se eliminará del servidor."
                        data-confirmar-titulo="¿Eliminar el respaldo?" data-confirmar-boton="Sí, eliminar">
                    <?= csrf() ?>
                    <input type="hidden" name="accion" value="borrar">
                    <input type="hidden" name="archivo" value="<?= e($f['nombre']) ?>">
                    <button class="btn btn-sm btn-fantasma" type="submit" aria-label="Eliminar"><?= ico('basura', 15) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <div class="columna">
    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Cómo restaurar</h3></div>
      <div class="tarjeta-cuerpo">
        <ol style="padding-left:18px;font-size:.92rem;line-height:1.8;color:var(--texto-2)">
          <li>Descargue el archivo <code>.sql.gz</code> y descomprímalo.</li>
          <li>Entre a cPanel &rarr; <strong>phpMyAdmin</strong>.</li>
          <li>Seleccione la base de datos del sistema.</li>
          <li>Use la pestaña <strong>Importar</strong> y suba el archivo <code>.sql</code>.</li>
        </ol>
        <div class="aviso-caja alerta"><?= ico('alerta', 19) ?>
          <div>Restaurar reemplaza toda la información actual. Genere un respaldo antes de hacerlo.</div>
        </div>
      </div>
    </article>

    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Respaldo automático</h3>
        <span class="chip <?= $automatico ? 'ok' : 'neutro' ?>"><?= $automatico ? 'Activo' : 'Desactivado' ?></span>
      </div>
      <div class="tarjeta-cuerpo">
        <p class="texto-2" style="font-size:.9rem">
          Con la tarea programada configurada, el sistema crea un respaldo cada semana automáticamente.
        </p>
        <a class="btn btn-claro btn-sm" href="<?= e(url('/admin/ajustes', ['seccion' => 'notificaciones'])) ?>">
          <?= ico('ajustes', 15) ?> Configurar el cron
        </a>
      </div>
    </article>
  </div>
</div>
