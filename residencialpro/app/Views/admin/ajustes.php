<?php
$temas = [
  'verde-oro'   => ['Petróleo y Barro', '#0E4C5A', '#B94E27'],
  'grafito'     => ['Pizarra y Cobre',  '#2E3A40', '#A9622A'],
  'azul-marino' => ['Índigo y Arena',   '#263A63', '#A8681F'],
  'terracota'   => ['Bosque y Terracota', '#24503C', '#B2502C'],
  'borgona'     => ['Borgoña y Lino',   '#56202C', '#9A6118'],
  'negro-oro'   => ['Basalto y Ámbar',  '#1F2224', '#9E6A12'],
  'purpura'     => ['Oliva y Hueso',    '#3B4429', '#A55B24'],
  'azul-real'   => ['Tinta y Cobalto',  '#17324D', '#A65A2E'],
  'oceano'      => ['Océano y Coral',   '#14444F', '#B4453F'],
];
$v = static fn(string $k, string $d = '') => (string) ($a[$k] ?? $d);
$secciones = ['general' => 'Identidad', 'cobros' => 'Cobros y mora', 'mensajes' => 'Correo y WhatsApp', 'notificaciones' => 'Notificaciones y respaldo'];
?>
<div class="btn-grupo mb-3">
  <?php foreach ($secciones as $k => $et): ?>
    <a href="<?= e(url('/admin/ajustes', ['seccion' => $k])) ?>" class="<?= $seccion === $k ? 'is-activo' : '' ?>"><?= e($et) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($seccion === 'general'): ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf() ?>
    <input type="hidden" name="grupo" value="general">
    <div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Identidad del residencial</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campos">
            <div class="campo campo-ancho"><label for="nombre">Nombre del condominio *</label>
              <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e($v('nombre')) ?>"></div>
            <div class="campo campo-ancho"><label for="lema">Lema</label>
              <input type="text" id="lema" name="lema" maxlength="120" value="<?= e($v('lema')) ?>"></div>
            <div class="campo"><label for="nit">NIT</label>
              <input type="text" id="nit" name="nit" maxlength="30" value="<?= e($v('nit')) ?>"></div>
            <div class="campo"><label for="telefono">Teléfono</label>
              <input type="tel" id="telefono" name="telefono" maxlength="40" value="<?= e($v('telefono')) ?>"></div>
            <div class="campo"><label for="whatsapp">WhatsApp</label>
              <input type="tel" id="whatsapp" name="whatsapp" maxlength="40" value="<?= e($v('whatsapp')) ?>"></div>
            <div class="campo"><label for="correo">Correo de la administración</label>
              <input type="email" id="correo" name="correo" maxlength="160" value="<?= e($v('correo')) ?>"></div>
            <div class="campo campo-ancho"><label for="direccion">Dirección</label>
              <input type="text" id="direccion" name="direccion" maxlength="255" value="<?= e($v('direccion')) ?>"></div>
            <div class="campo"><label for="ciudad">Ciudad</label>
              <input type="text" id="ciudad" name="ciudad" maxlength="120" value="<?= e($v('ciudad', 'Ciudad de Guatemala')) ?>"></div>
            <div class="campo"><label for="moneda_simbolo">Símbolo de moneda</label>
              <input type="text" id="moneda_simbolo" name="moneda_simbolo" maxlength="4" value="<?= e($v('moneda_simbolo', 'Q')) ?>"></div>
            <div class="campo"><label for="pais_codigo">Código de país (WhatsApp)</label>
              <input type="text" id="pais_codigo" name="pais_codigo" maxlength="5" value="<?= e($v('pais_codigo', '502')) ?>"></div>
          </div>
          <hr>
          <h4>Firma en los documentos</h4>
          <div class="campos">
            <div class="campo"><label for="firma_nombre">Nombre de quien firma</label>
              <input type="text" id="firma_nombre" name="firma_nombre" maxlength="140" value="<?= e($v('firma_nombre')) ?>"></div>
            <div class="campo"><label for="firma_cargo">Cargo</label>
              <input type="text" id="firma_cargo" name="firma_cargo" maxlength="120" value="<?= e($v('firma_cargo', 'Administración del residencial')) ?>"></div>
            <div class="campo campo-ancho"><label for="firma_archivo">Imagen de la firma (PNG con fondo transparente)</label>
              <input type="file" id="firma_archivo" name="firma_archivo" accept="image/png,image/jpeg">
              <?php if ($v('firma_archivo') !== ''): ?>
                <img src="<?= e(subida($v('firma_archivo'), 'logos')) ?>" alt="Firma" style="margin-top:10px;max-height:70px">
              <?php endif; ?>
            </div>
            <div class="campo campo-ancho"><label for="reglamento">Reglamento interno (PDF)</label>
              <input type="file" id="reglamento" name="reglamento" accept="application/pdf">
              <?php if ($v('reglamento') !== ''): ?>
                <span class="ayuda">Ya hay un reglamento cargado.
                  <a href="<?= e(url('/archivo/documentos/' . $v('reglamento'))) ?>" target="_blank" rel="noopener">Verlo</a>.</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar identidad</button>
        </div>
      </div>

      <div class="columna">
        <article class="tarjeta">
          <div class="tarjeta-cab"><h3>Logotipo</h3></div>
          <div class="tarjeta-cuerpo centrado">
            <?php if ($v('logo') !== ''): ?>
              <img src="<?= e(subida($v('logo'), 'logos')) ?>" alt="Logotipo" style="max-height:110px;border-radius:var(--r-sm)">
            <?php else: ?>
              <div class="escudo" style="width:96px;height:96px;margin:0 auto;border-radius:20px;display:grid;place-items:center;background:linear-gradient(135deg,var(--arcilla-3),var(--arcilla));color:#fff">
                <?= ico('casa', 46) ?>
              </div>
            <?php endif; ?>
            <div class="campo mt-2">
              <label for="logo" class="solo-lectores">Logotipo</label>
              <input type="file" id="logo" name="logo" accept="image/*">
              <span class="ayuda">Al subirlo se regeneran automáticamente los iconos de la aplicación instalable.</span>
            </div>
          </div>
        </article>

        <article class="tarjeta">
          <div class="tarjeta-cab"><h3>Tema visual</h3></div>
          <div class="tarjeta-cuerpo">
            <div class="temas mb-2">
              <?php foreach ($temas as $k => [$nombre, $c1, $c2]): ?>
                <button type="button" class="tema-op <?= $v('tema', 'verde-oro') === $k ? 'is-activo' : '' ?>"
                        data-tema-op="<?= e($k) ?>" title="<?= e($nombre) ?>" aria-label="Tema <?= e($nombre) ?>">
                  <i style="background:<?= e($c1) ?>"></i>
                  <i style="background:<?= e($c2) ?>;left:auto;right:0;width:40%"></i>
                </button>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="tema" id="tema-valor" value="<?= e($v('tema', 'verde-oro')) ?>">
            <div class="campos">
              <div class="campo"><label for="color_primario">Color principal</label>
                <input type="color" id="color_primario" name="color_primario" value="<?= e($v('color_primario', '#0E4C5A')) ?>"></div>
              <div class="campo"><label for="color_acento">Color de acento</label>
                <input type="color" id="color_acento" name="color_acento" value="<?= e($v('color_acento', '#B94E27')) ?>"></div>
            </div>
            <span class="ayuda">Los colores se usan en los documentos PDF, los correos y la aplicación instalable.</span>
          </div>
        </article>
      </div>
    </div>
  </form>

<?php elseif ($seccion === 'cobros'): ?>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="grupo" value="cobros">
    <div class="rejilla rejilla-2">
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Mora y recargos</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campos">
            <div class="campo"><label for="mora_tipo">Tipo de recargo</label>
              <select id="mora_tipo" name="mora_tipo">
                <option value="porcentaje" <?= $v('mora_tipo', 'porcentaje') === 'porcentaje' ? 'selected' : '' ?>>Porcentaje mensual</option>
                <option value="fijo" <?= $v('mora_tipo') === 'fijo' ? 'selected' : '' ?>>Monto fijo mensual</option>
                <option value="ninguna" <?= $v('mora_tipo') === 'ninguna' ? 'selected' : '' ?>>Sin recargo</option>
              </select>
              <span class="ayuda">Se aplica a los conceptos que no definan su propia mora.</span></div>
            <div class="campo"><label for="mora_valor">Valor</label>
              <input type="number" id="mora_valor" name="mora_valor" step="0.01" min="0" value="<?= e($v('mora_valor', '2')) ?>"></div>
            <div class="campo"><label for="mora_dias_gracia">Días de gracia</label>
              <input type="number" id="mora_dias_gracia" name="mora_dias_gracia" min="0" max="30" value="<?= e($v('mora_dias_gracia', '0')) ?>"></div>
            <div class="campo"><label for="mora_tope_porcentaje">Tope de mora (% del saldo)</label>
              <input type="number" id="mora_tope_porcentaje" name="mora_tope_porcentaje" min="0" max="500" value="<?= e($v('mora_tope_porcentaje', '100')) ?>"></div>
          </div>
          <label class="marca-check">
            <input type="checkbox" name="generacion_automatica" value="1" <?= ($a['generacion_automatica'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Generar automáticamente los cargos del mes (el día 1, mediante el cron)</span>
          </label>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Cobranza y escalamiento</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campos">
            <div class="campo"><label for="recordatorio_previo_dias">Recordar X días antes de vencer</label>
              <input type="number" id="recordatorio_previo_dias" name="recordatorio_previo_dias" min="0" max="30" value="<?= e($v('recordatorio_previo_dias', '5')) ?>"></div>
            <div class="campo"><label for="recordatorio_cada_dias">Insistir cada X días en mora</label>
              <input type="number" id="recordatorio_cada_dias" name="recordatorio_cada_dias" min="1" max="60" value="<?= e($v('recordatorio_cada_dias', '7')) ?>"></div>
            <div class="campo"><label for="carta_dias">Carta de cobro a los X días</label>
              <input type="number" id="carta_dias" name="carta_dias" min="0" max="365" value="<?= e($v('carta_dias', '60')) ?>"></div>
            <div class="campo"><label for="carta_plazo_dias">Plazo que da la carta (días)</label>
              <input type="number" id="carta_plazo_dias" name="carta_plazo_dias" min="1" max="90" value="<?= e($v('carta_plazo_dias', '15')) ?>"></div>
            <div class="campo campo-ancho"><label for="corte_dias">Marcar "restricción de servicios" a los X días</label>
              <input type="number" id="corte_dias" name="corte_dias" min="0" max="365" value="<?= e($v('corte_dias', '90')) ?>">
              <span class="ayuda">Escriba 0 para desactivar esta marca por completo.</span></div>
          </div>
          <label class="marca-check">
            <input type="checkbox" name="mostrar_restriccion_garita" value="1" <?= ($a['mostrar_restriccion_garita'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Mostrar la restricción en la pantalla de garita</span>
          </label>
          <div class="campo mt-2"><label for="carta_texto">Texto de la carta de cobro</label>
            <textarea id="carta_texto" name="carta_texto" rows="4" maxlength="2000"><?= e($v('carta_texto')) ?></textarea></div>
        </div>
      </div>

      <div class="tarjeta" style="grid-column:1/-1">
        <div class="tarjeta-cab"><h3>Formas de pago</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campos">
            <div class="campo campo-ancho"><label for="cuenta_deposito">Datos bancarios para depósitos</label>
              <textarea id="cuenta_deposito" name="cuenta_deposito" rows="2" maxlength="500"><?= e($v('cuenta_deposito')) ?></textarea>
              <span class="ayuda">Aparecen en el estado de cuenta, en los correos y en el portal.</span></div>
            <div class="campo"><label for="enlace_pago">Enlace de pago en línea</label>
              <input type="url" id="enlace_pago" name="enlace_pago" maxlength="255" value="<?= e($v('enlace_pago')) ?>"
                     placeholder="https://pagos.recurrente.com/...">
              <span class="ayuda">Recurrente, Pagadito u otro. Si lo deja vacío, el botón se oculta.</span></div>
            <div class="campo"><label for="recibo_prefijo">Prefijo de los recibos</label>
              <input type="text" id="recibo_prefijo" name="recibo_prefijo" maxlength="10" value="<?= e($v('recibo_prefijo')) ?>" placeholder="REC-"></div>
          </div>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar configuración de cobros</button>
        </div>
      </div>
    </div>
  </form>

<?php elseif ($seccion === 'mensajes'): ?>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="grupo" value="mensajes">
    <div class="rejilla rejilla-2">
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Servidor de correo (SMTP)</h3></div>
        <div class="tarjeta-cuerpo">
          <label class="marca-check mb-2">
            <input type="checkbox" name="correo_activo" value="1" <?= ($a['correo_activo'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Enviar correos desde el sistema</span>
          </label>
          <div class="campos">
            <div class="campo campo-ancho"><label for="smtp_host">Servidor SMTP</label>
              <input type="text" id="smtp_host" name="smtp_host" maxlength="160" value="<?= e($v('smtp_host')) ?>" placeholder="mail.sudominio.com">
              <span class="ayuda">Si lo deja vacío se usa la función mail() del hosting.</span></div>
            <div class="campo"><label for="smtp_puerto">Puerto</label>
              <input type="number" id="smtp_puerto" name="smtp_puerto" value="<?= e($v('smtp_puerto', '587')) ?>"></div>
            <div class="campo"><label for="smtp_seguridad">Seguridad</label>
              <select id="smtp_seguridad" name="smtp_seguridad">
                <option value="tls" <?= $v('smtp_seguridad', 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                <option value="ssl" <?= $v('smtp_seguridad') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                <option value="ninguna" <?= $v('smtp_seguridad') === 'ninguna' ? 'selected' : '' ?>>Sin cifrado (25)</option>
              </select></div>
            <div class="campo"><label for="smtp_usuario">Usuario</label>
              <input type="text" id="smtp_usuario" name="smtp_usuario" maxlength="160" value="<?= e($v('smtp_usuario')) ?>" autocomplete="off"></div>
            <div class="campo"><label for="smtp_clave">Contraseña</label>
              <input type="password" id="smtp_clave" name="smtp_clave" maxlength="160" autocomplete="new-password"
                     placeholder="<?= $v('smtp_clave') !== '' ? 'Guardada — escriba para cambiarla' : '' ?>"></div>
            <div class="campo"><label for="smtp_de_correo">Remitente</label>
              <input type="email" id="smtp_de_correo" name="smtp_de_correo" maxlength="160" value="<?= e($v('smtp_de_correo')) ?>"></div>
            <div class="campo"><label for="smtp_de_nombre">Nombre del remitente</label>
              <input type="text" id="smtp_de_nombre" name="smtp_de_nombre" maxlength="140" value="<?= e($v('smtp_de_nombre')) ?>"></div>
            <div class="campo campo-ancho"><label for="correo_pie">Pie de los correos</label>
              <input type="text" id="correo_pie" name="correo_pie" maxlength="255" value="<?= e($v('correo_pie')) ?>"></div>
          </div>
          <div class="fila envolver mt-2" style="gap:8px">
            <button class="btn btn-claro btn-sm" type="submit" name="accion" value="probar_smtp"><?= ico('rayo', 15) ?> Probar conexión</button>
            <input type="email" name="correo_prueba" placeholder="correo@destino.com" style="max-width:230px">
            <button class="btn btn-claro btn-sm" type="submit" name="accion" value="correo_prueba"><?= ico('enviar', 15) ?> Enviar prueba</button>
          </div>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Plantillas de WhatsApp</h3></div>
        <div class="tarjeta-cuerpo">
          <p class="texto-3" style="font-size:.86rem">
            Variables disponibles: <code>{residente}</code> <code>{casa}</code> <code>{saldo}</code>
            <code>{vence}</code> <code>{monto}</code> <code>{recibo}</code> <code>{visitante}</code>
            <code>{condominio}</code> <code>{enlace}</code>
          </p>
          <div class="campo"><label for="wa_recordatorio">Recordatorio de cobro</label>
            <textarea id="wa_recordatorio" name="wa_recordatorio" rows="3" maxlength="900"><?= e($v('wa_recordatorio')) ?></textarea></div>
          <div class="campo"><label for="wa_recibo">Confirmación de pago</label>
            <textarea id="wa_recibo" name="wa_recibo" rows="2" maxlength="900"><?= e($v('wa_recibo')) ?></textarea></div>
          <div class="campo"><label for="wa_visita">Aviso de visita en garita</label>
            <textarea id="wa_visita" name="wa_visita" rows="2" maxlength="900"><?= e($v('wa_visita')) ?></textarea></div>
          <label class="marca-check">
            <input type="checkbox" name="avisar_visita" value="1" <?= ($a['avisar_visita'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Avisar al residente cuando su visita llegue a la garita</span>
          </label>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar</button>
        </div>
      </div>
    </div>
  </form>

<?php else: ?>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="grupo" value="notificaciones">
    <div class="rejilla rejilla-2">
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Notificaciones push</h3></div>
        <div class="tarjeta-cuerpo">
          <?php if (!$pushOk): ?>
            <div class="aviso-caja alerta"><?= ico('alerta', 19) ?>
              <div>Su servidor no tiene las extensiones necesarias (openssl y curl) para enviar notificaciones push.
                El resto del sistema funciona con normalidad.</div>
            </div>
          <?php elseif ($v('vapid_publica') === ''): ?>
            <div class="aviso-caja info mb-2"><?= ico('info', 19) ?>
              <div>Aún no se han generado las claves de notificaciones. Genérelas una sola vez;
                después los residentes podrán activarlas desde su portal.</div>
            </div>
            <button class="btn btn-oro" type="submit" name="accion" value="generar_vapid"><?= ico('llave', 17) ?> Generar claves</button>
          <?php else: ?>
            <div class="aviso-caja ok mb-2"><?= ico('checkCirculo', 19) ?>
              <div>Las notificaciones push están configuradas y activas.</div>
            </div>
            <div class="campo">
              <label for="vapid">Clave pública (VAPID)</label>
              <input type="text" id="vapid" value="<?= e($v('vapid_publica')) ?>" readonly>
            </div>
            <button class="btn btn-claro btn-sm" type="submit" name="accion" value="generar_vapid"
><?= ico('refrescar', 15) ?> Regenerar claves</button>
            <span class="ayuda">Al regenerarlas, los residentes deberán volver a activar las notificaciones.</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Respaldos y tarea programada</h3></div>
        <div class="tarjeta-cuerpo">
          <label class="marca-check mb-2">
            <input type="checkbox" name="respaldo_automatico" value="1" <?= ($a['respaldo_automatico'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Crear un respaldo automático cada semana</span>
          </label>
          <p class="texto-2" style="font-size:.9rem">
            Para que funcionen los recordatorios de cobro, la mora y los respaldos automáticos,
            configure esta línea en cPanel &rarr; <em>Trabajos cron</em>, cada 15 minutos:
          </p>
          <pre class="desplaza" data-etiqueta="Línea de cron" style="background:var(--cal-2);padding:13px;border-radius:var(--r-sm);overflow:auto;font-size:.78rem">*/15 * * * * curl -s "<?= e(\App\Core\Url::absoluta('/cron/run.php')) ?>?token=<?= e($cronToken) ?>" &gt;/dev/null 2&gt;&amp;1</pre>
          <button class="btn btn-claro btn-sm" type="button"
                  data-copiar='*/15 * * * * curl -s "<?= e(\App\Core\Url::absoluta('/cron/run.php')) ?>?token=<?= e($cronToken) ?>" >/dev/null 2>&1'>
            <?= ico('archivo', 15) ?> Copiar la línea
          </button>
          <div class="fila-fin mt-3">
            <a class="btn btn-claro" href="<?= e(url('/admin/respaldos')) ?>"><?= ico('guardar', 16) ?> Ver respaldos</a>
            <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar</button>
          </div>
        </div>
      </div>
    </div>
  </form>
<?php endif; ?>

<script<?= nonce() ?>>
document.querySelectorAll('[data-tema-op]').forEach(function (b) {
  b.addEventListener('click', function () {
    var campo = document.getElementById('tema-valor');
    if (campo) campo.value = b.dataset.temaOp;
  });
});
</script>
