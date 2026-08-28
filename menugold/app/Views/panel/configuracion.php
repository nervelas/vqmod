<?php
/** @var array $horarios, $zonas, $temas, $smtp, $impresora */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Configuración');
View::set('subtitulo', 'Todo lo que define cómo se ve y funciona tu menú');
$s = (string)($r['simbolo'] ?? 'Q');
$dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$modos = explode(',', (string)$r['modos_pedido']);
$pagos = explode(',', (string)$r['metodos_pago']);
$idiomas = explode(',', (string)$r['idiomas']);
$propinas = \MenuGold\Models\Restaurant::propinas($r);
?>
<div class="pestanas" role="tablist">
  <?php foreach ([
    'general'  => ['Datos del negocio', 'store'],
    'marca'    => ['Identidad visual', 'palette'],
    'pedidos'  => ['Pedidos y pagos', 'cart'],
    'horarios' => ['Horarios', 'clock'],
    'entrega'  => ['Entrega a domicilio', 'map'],
    'avanzado' => ['Correo e impresión', 'mail'],
  ] as $k => $v): ?>
    <button class="pestana" type="button" role="tab" data-pestana="<?= e($k) ?>"
            aria-selected="<?= $k === 'general' ? 'true' : 'false' ?>"><?= icon($v[1], 'ico-sm') ?> <?= e($v[0]) ?></button>
  <?php endforeach; ?>
</div>

<!-- ============================== GENERAL ============================== -->
<section data-panel="general">
  <form method="post" action="<?= e(url('panel/configuracion')) ?>">
    <?= csrf_field() ?>
    <div class="rejilla rejilla--2">
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('store') ?> Datos del restaurante</h2></div>
        <div class="campo-p"><label for="nombre">Nombre *</label>
          <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e((string)$r['nombre']) ?>"></div>
        <div class="campo-p"><label for="eslogan">Eslogan</label>
          <input type="text" id="eslogan" name="eslogan" maxlength="180" value="<?= e((string)$r['eslogan']) ?>"
                 placeholder="Ej. Cocina de autor · Antigua Guatemala"></div>
        <div class="campo-p"><label for="descripcion">Descripción</label>
          <textarea id="descripcion" name="descripcion" maxlength="1200"><?= e((string)$r['descripcion']) ?></textarea></div>
        <div class="campo-p"><label for="direccion">Dirección</label>
          <input type="text" id="direccion" name="direccion" maxlength="255" value="<?= e((string)$r['direccion']) ?>"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="mapa_lat">Latitud</label>
            <input type="text" id="mapa_lat" name="mapa_lat" value="<?= e((string)($r['mapa_lat'] ?? '')) ?>" placeholder="14.5619"></div>
          <div class="campo-p"><label for="mapa_lng">Longitud</label>
            <input type="text" id="mapa_lng" name="mapa_lng" value="<?= e((string)($r['mapa_lng'] ?? '')) ?>" placeholder="-90.7343"></div>
        </div>
        <p class="ayuda-p" style="margin-top:-8px">Abre Google Maps, haz clic derecho sobre tu local y copia las coordenadas.</p>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('phone') ?> Contacto y redes</h2></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="telefono">Teléfono</label>
            <input type="tel" id="telefono" name="telefono" maxlength="30" value="<?= e((string)$r['telefono']) ?>"></div>
          <div class="campo-p"><label for="whatsapp">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e((string)$r['whatsapp']) ?>" placeholder="50212345678">
            <p class="ayuda-p">Con código de país, sin signos.</p></div>
        </div>
        <div class="campo-p"><label for="email">Correo del restaurante</label>
          <input type="email" id="email" name="email" maxlength="190" value="<?= e((string)$r['email']) ?>"></div>
        <div class="campo-p"><label for="facebook">Facebook</label>
          <input type="url" id="facebook" name="facebook" maxlength="190" value="<?= e((string)$r['facebook']) ?>"></div>
        <div class="campo-p"><label for="instagram">Instagram</label>
          <input type="url" id="instagram" name="instagram" maxlength="190" value="<?= e((string)$r['instagram']) ?>"></div>
        <div class="campo-p"><label for="tiktok">TikTok</label>
          <input type="url" id="tiktok" name="tiktok" maxlength="190" value="<?= e((string)$r['tiktok']) ?>"></div>
        <div class="campo-p"><label for="google_reviews">Enlace para reseñas de Google</label>
          <input type="url" id="google_reviews" name="google_reviews" maxlength="255" value="<?= e((string)$r['google_reviews']) ?>">
          <p class="ayuda-p">Al terminar su pedido invitamos al cliente a dejarte una reseña aquí.</p></div>
      </div>
    </div>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('globe') ?> Presentación e idiomas</h2></div>
      <div class="fila-campos">
        <div class="campo-p"><label for="mensaje_bienvenida">Mensaje de bienvenida</label>
          <input type="text" id="mensaje_bienvenida" name="mensaje_bienvenida" maxlength="255" value="<?= e((string)$r['mensaje_bienvenida']) ?>"></div>
        <div class="campo-p"><label for="mensaje_pie">Mensaje de despedida</label>
          <input type="text" id="mensaje_pie" name="mensaje_pie" maxlength="255" value="<?= e((string)$r['mensaje_pie']) ?>"></div>
      </div>
      <div class="fila-campos">
        <div class="campo-p"><label for="idioma">Idioma principal</label>
          <select id="idioma" name="idioma">
            <option value="es" <?= $r['idioma'] === 'es' ? 'selected' : '' ?>>Español</option>
            <option value="en" <?= $r['idioma'] === 'en' ? 'selected' : '' ?>>Inglés</option>
          </select></div>
        <div class="campo-p"><label>Idiomas disponibles en el menú</label>
          <div class="pastillas-sel">
            <label class="pastilla-sel"><input type="checkbox" name="idiomas[]" value="es" <?= in_array('es', $idiomas, true) ? 'checked' : '' ?>>Español</label>
            <label class="pastilla-sel"><input type="checkbox" name="idiomas[]" value="en" <?= in_array('en', $idiomas, true) ? 'checked' : '' ?>>Inglés</label>
          </div></div>
      </div>
      <div class="fila-campos">
        <div class="campo-p"><label for="seo_title">Título para buscadores</label>
          <input type="text" id="seo_title" name="seo_title" maxlength="190" value="<?= e((string)$r['seo_title']) ?>"></div>
        <div class="campo-p"><label for="seo_desc">Descripción para buscadores</label>
          <input type="text" id="seo_desc" name="seo_desc" maxlength="255" value="<?= e((string)$r['seo_desc']) ?>"></div>
      </div>
    </div>

    <div class="tarjeta-p" style="text-align:right">
      <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar cambios</button>
    </div>
  </form>
</section>

<!-- ============================== MARCA ============================== -->
<section data-panel="marca" hidden>
  <form method="post" action="<?= e(url('panel/configuracion/marca')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="rejilla rejilla--2">
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('palette') ?> Tema del menú</h2></div>
        <div class="temas-rejilla">
          <?php foreach ($temas as $k => $t): ?>
            <label class="tema-opcion">
              <input type="radio" name="tema" value="<?= e($k) ?>" <?= $r['tema'] === $k ? 'checked' : '' ?>>
              <span class="tema-opcion__muestra">
                <span style="background:<?= e($t[1]) ?>"></span>
                <span style="background:<?= e($t[2]) ?>"></span>
                <span style="background:<?= e($t[3]) ?>"></span>
              </span>
              <span class="tema-opcion__nombre"><?= e($t[0]) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="fila-campos" style="margin-top:16px">
          <div class="campo-p"><label for="color_primario">Color de acento</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="color" id="color_primario" name="color_primario" value="<?= e((string)$r['color_primario']) ?>"
                     style="width:52px;height:42px;padding:3px;border-radius:9px;border:1px solid var(--p-borde-2)">
              <input type="text" value="<?= e((string)$r['color_primario']) ?>" readonly id="colorPrimarioTexto" class="entrada mono">
            </div></div>
          <div class="campo-p"><label for="color_fondo">Color de fondo</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="color" id="color_fondo" name="color_fondo" value="<?= e((string)$r['color_fondo']) ?>"
                     style="width:52px;height:42px;padding:3px;border-radius:9px;border:1px solid var(--p-borde-2)">
              <input type="text" value="<?= e((string)$r['color_fondo']) ?>" readonly id="colorFondoTexto" class="entrada mono">
            </div></div>
        </div>

        <label class="etiqueta-campo" style="margin-top:8px">Tipografía del menú</label>
        <div class="pastillas-sel">
          <?php foreach (['clasica' => 'Clásica (Playfair + Inter)', 'moderna' => 'Moderna (Inter)', 'editorial' => 'Editorial (serif)'] as $k => $v): ?>
            <label class="pastilla-sel">
              <input type="radio" name="tipografia" value="<?= e($k) ?>" <?= $r['tipografia'] === $k ? 'checked' : '' ?>><?= e($v) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('image') ?> Logo y fotos</h2></div>
        <?php foreach ([
          ['logo', 'Logo', 'Cuadrado, con fondo transparente o sólido. También genera el ícono de la app.'],
          ['portada', 'Foto de portada', 'Horizontal y bien iluminada. Es lo primero que ve el cliente.'],
          ['og_image', 'Imagen para redes', 'La que se muestra al compartir tu menú (1200 × 630 px).'],
        ] as $img): ?>
          <label class="etiqueta-campo"><?= e($img[1]) ?></label>
          <div class="previa-foto" id="previa_<?= e($img[0]) ?>" style="<?= empty($r[$img[0]]) ? 'display:none' : '' ?>;margin-bottom:10px">
            <img src="<?= e(!empty($r[$img[0]]) ? uploaded((string)$r[$img[0]]) : '') ?>" alt="">
            <button class="previa-foto__quitar" type="button" data-quitar-previa="#previa_<?= e($img[0]) ?>"
                    data-campo="#campo_<?= e($img[0]) ?>" data-marca="#quitar_<?= e($img[0]) ?>" aria-label="Quitar"><?= icon('x') ?></button>
          </div>
          <input type="hidden" name="quitar_<?= e($img[0]) ?>" id="quitar_<?= e($img[0]) ?>" value="0">
          <label class="subir-foto" style="margin-bottom:16px">
            <input type="file" id="campo_<?= e($img[0]) ?>" name="<?= e($img[0]) ?>" accept="image/jpeg,image/png,image/webp"
                   data-previsualizar="#previa_<?= e($img[0]) ?>">
            <?= icon('upload') ?><span class="subir-foto__texto"><?= e($img[2]) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="tarjeta-p" style="text-align:right">
      <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar identidad</button>
    </div>
  </form>
</section>

<!-- ============================== PEDIDOS ============================== -->
<section data-panel="pedidos" hidden>
  <form method="post" action="<?= e(url('panel/configuracion')) ?>">
    <?= csrf_field() ?>
    <?php
    // Campos que también viajan en este formulario para no perderlos
    foreach (['nombre','eslogan','descripcion','telefono','whatsapp','email','direccion','mapa_lat','mapa_lng',
              'facebook','instagram','tiktok','google_reviews','mensaje_bienvenida','mensaje_pie','seo_title','seo_desc','idioma'] as $c): ?>
      <input type="hidden" name="<?= e($c) ?>" value="<?= e((string)($r[$c] ?? '')) ?>">
    <?php endforeach;
    foreach ($idiomas as $l): ?><input type="hidden" name="idiomas[]" value="<?= e($l) ?>"><?php endforeach; ?>

    <div class="rejilla rejilla--2">
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('cart') ?> Cómo pueden pedir</h2></div>
        <div style="display:grid;gap:8px">
          <?php foreach ([
            'consulta' => ['Solo consultar el menú', 'El cliente ve la carta pero no puede pedir en línea.'],
            'mesa'     => ['Pedido en mesa (QR por mesa)', 'El pedido llega a la cocina con el número de mesa.'],
            'llevar'   => ['Para llevar', 'El cliente pide y pasa a recoger.'],
            'delivery' => ['A domicilio', 'Con dirección, zona de entrega y costo de envío.'],
            'whatsapp' => ['Enviar por WhatsApp', 'El pedido se arma y se manda como mensaje. Sin panel de cocina.'],
          ] as $k => $v): ?>
            <label class="casilla" style="align-items:flex-start;padding:12px;border:1px solid var(--p-borde);border-radius:11px">
              <input type="checkbox" name="modos_pedido[]" value="<?= e($k) ?>" <?= in_array($k, $modos, true) ? 'checked' : '' ?>>
              <span><strong style="display:block"><?= e($v[0]) ?></strong>
                <small style="color:var(--p-tenue)"><?= e($v[1]) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>

        <label class="etiqueta-campo" style="margin-top:16px">Métodos de pago que aceptas</label>
        <div class="pastillas-sel">
          <?php foreach (['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta en mesa', 'transferencia' => 'Transferencia', 'link' => 'Pago en línea'] as $k => $v): ?>
            <label class="pastilla-sel">
              <input type="checkbox" name="metodos_pago[]" value="<?= e($k) ?>" <?= in_array($k, $pagos, true) ? 'checked' : '' ?>><?= e($v) ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="campo-p" style="margin-top:14px"><label for="datos_bancarios">Datos bancarios (para transferencias)</label>
          <textarea id="datos_bancarios" name="datos_bancarios" maxlength="800" placeholder="Banco, número de cuenta, nombre y NIT"><?= e((string)$r['datos_bancarios']) ?></textarea></div>
        <div class="campo-p"><label for="link_pago">Enlace de pago en línea</label>
          <input type="url" id="link_pago" name="link_pago" maxlength="255" value="<?= e((string)$r['link_pago']) ?>"></div>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('money') ?> Precios e impuestos</h2></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="moneda">Moneda</label>
            <input type="text" id="moneda" name="moneda" maxlength="6" value="<?= e((string)$r['moneda']) ?>"></div>
          <div class="campo-p"><label for="simbolo">Símbolo</label>
            <input type="text" id="simbolo" name="simbolo" maxlength="4" value="<?= e((string)$r['simbolo']) ?>"></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="impuesto_pct">Impuesto (%)</label>
            <input type="number" id="impuesto_pct" name="impuesto_pct" step="0.01" min="0" max="50"
                   value="<?= e((string)$r['impuesto_pct']) ?>" inputmode="decimal"></div>
          <div class="campo-p"><label for="pedido_minimo">Pedido mínimo</label>
            <div class="grupo-prefijo"><span><?= e($s) ?></span>
              <input type="number" id="pedido_minimo" name="pedido_minimo" step="0.01" min="0"
                     value="<?= e((string)$r['pedido_minimo']) ?>" inputmode="decimal"></div></div>
        </div>
        <label class="interruptor" style="margin-bottom:14px">
          <input type="checkbox" name="impuesto_incluido" value="1" <?= (int)$r['impuesto_incluido'] === 1 ? 'checked' : '' ?>>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Los precios ya incluyen impuesto</span>
        </label>

        <label class="etiqueta-campo">Propinas sugeridas (%)</label>
        <div class="fila-campos">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="campo-p"><input type="number" name="propinas[]" min="0" max="100"
                 value="<?= e((string)($propinas[$i] ?? '')) ?>" inputmode="numeric" aria-label="Propina <?= $i + 1 ?>"></div>
          <?php endfor; ?>
        </div>
        <p class="ayuda-p" style="margin-top:-8px">Usa 0 para la opción «Sin propina». Deja vacío para no mostrar esa opción.</p>

        <div class="campo-p" style="margin-top:8px"><label for="tiempo_prep_min">Tiempo estimado de preparación (min)</label>
          <input type="number" id="tiempo_prep_min" name="tiempo_prep_min" min="1" max="180"
                 value="<?= e((string)$r['tiempo_prep_min']) ?>" inputmode="numeric"></div>

        <label class="interruptor" style="margin-bottom:10px">
          <input type="checkbox" name="notas_activas" value="1" <?= (int)$r['notas_activas'] === 1 ? 'checked' : '' ?>>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Permitir notas del cliente por platillo</span>
        </label>

        <div class="campo-p"><label for="abierto_modo">Estado del restaurante</label>
          <select id="abierto_modo" name="abierto_modo">
            <option value="auto" <?= $r['abierto_modo'] === 'auto' ? 'selected' : '' ?>>Automático según horario</option>
            <option value="abierto" <?= $r['abierto_modo'] === 'abierto' ? 'selected' : '' ?>>Forzar abierto</option>
            <option value="cerrado" <?= $r['abierto_modo'] === 'cerrado' ? 'selected' : '' ?>>Forzar cerrado</option>
          </select>
          <p class="ayuda-p">Útil si cierras por un evento privado o un día festivo.</p></div>
      </div>
    </div>
    <div class="tarjeta-p" style="text-align:right">
      <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
    </div>
  </form>
</section>

<!-- ============================== HORARIOS ============================== -->
<section data-panel="horarios" hidden>
  <form method="post" action="<?= e(url('panel/configuracion/horarios')) ?>">
    <?= csrf_field() ?>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('clock') ?> Horario de atención</h2></div>
      <?php foreach ($dias as $i => $d): ?>
        <?php $h = $horarios[$i]; ?>
        <div class="entre" style="padding:11px 0;border-bottom:1px solid var(--p-borde);flex-wrap:wrap;gap:10px">
          <strong style="min-width:96px"><?= e($d) ?></strong>
          <div style="display:flex;gap:8px;align-items:center;flex:1;min-width:220px">
            <input class="entrada" type="time" name="abre[<?= $i ?>]" value="<?= e(substr((string)$h['abre'], 0, 5)) ?>"
                   aria-label="Hora de apertura <?= e($d) ?>" style="max-width:130px">
            <span style="color:var(--p-tenue)">a</span>
            <input class="entrada" type="time" name="cierra[<?= $i ?>]" value="<?= e(substr((string)$h['cierra'], 0, 5)) ?>"
                   aria-label="Hora de cierre <?= e($d) ?>" style="max-width:130px">
          </div>
          <label class="casilla">
            <input type="checkbox" name="cerrado[]" value="<?= $i ?>" <?= (int)$h['cerrado'] === 1 ? 'checked' : '' ?>>
            <span>Cerrado</span>
          </label>
        </div>
      <?php endforeach; ?>
      <p class="ayuda-p" style="margin-top:12px">
        Si cierras después de medianoche, pon por ejemplo de 18:00 a 02:00: el sistema lo entiende.
      </p>
    </div>
    <div class="tarjeta-p" style="text-align:right">
      <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar horarios</button>
    </div>
  </form>
</section>

<!-- ============================== ENTREGA ============================== -->
<section data-panel="entrega" hidden>
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab">
      <h2 class="tarjeta-p__titulo"><?= icon('map') ?> Zonas de entrega</h2>
      <button class="bt bt--sm bt--oro" type="button" data-modal="modalZonaEntrega" data-limpiar="1"><?= icon('plus', 'ico-sm') ?> Nueva zona</button>
    </div>
    <?php if (!$zonas): ?>
      <p style="color:var(--p-tenue);margin:0">
        Sin zonas de entrega. Agrega al menos una para habilitar el modo «A domicilio».
      </p>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla" style="min-width:auto">
          <thead><tr><th>Zona</th><th class="num">Costo</th><th class="num">Mínimo</th><th class="num">Tiempo</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($zonas as $z): ?>
              <tr>
                <td><strong><?= e((string)$z['nombre']) ?></strong></td>
                <td class="num"><?= e(money($z['costo'], $s)) ?></td>
                <td class="num"><?= (float)$z['minimo'] > 0 ? e(money($z['minimo'], $s)) : '—' ?></td>
                <td class="num"><?= (int)$z['tiempo_min'] ?> min</td>
                <td><span class="insignia insignia--<?= (int)$z['activo'] === 1 ? 'exito' : 'peligro' ?>">
                    <?= (int)$z['activo'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                <td class="tabla__acciones">
                  <button class="bt bt--sm bt--suave" type="button" data-modal="modalZonaEntrega" data-titulo="Editar zona"
                          data-rellenar='<?= e(json_encode([
                              'id' => (int)$z['id'], 'nombre' => $z['nombre'], 'costo' => (float)$z['costo'],
                              'minimo' => (float)$z['minimo'], 'tiempo_min' => (int)$z['tiempo_min'],
                              'activo' => (int)$z['activo'],
                          ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
                  <button class="bt bt--sm bt--suave" type="button" data-borrar-zona-entrega="<?= (int)$z['id'] ?>"
                          data-nombre="<?= e((string)$z['nombre']) ?>"><?= icon('trash', 'ico-sm') ?></button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ============================== AVANZADO ============================== -->
<section data-panel="avanzado" hidden>
  <form method="post" action="<?= e(url('panel/configuracion/smtp')) ?>">
    <?= csrf_field() ?>
    <div class="rejilla rejilla--2">
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('mail') ?> Correo saliente (SMTP)</h2></div>
        <p class="ayuda-p" style="margin-top:0">
          Se usa para recuperar contraseñas y avisos. Si lo dejas vacío, se usa la configuración de la plataforma.
        </p>
        <div class="fila-campos">
          <div class="campo-p"><label for="smtp_host">Servidor</label>
            <input type="text" id="smtp_host" name="smtp_host" maxlength="190" value="<?= e($smtp['host']) ?>" placeholder="mail.tudominio.com"></div>
          <div class="campo-p"><label for="smtp_puerto">Puerto</label>
            <input type="number" id="smtp_puerto" name="smtp_puerto" min="1" max="65535" value="<?= e($smtp['puerto']) ?>"></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="smtp_usuario">Usuario</label>
            <input type="text" id="smtp_usuario" name="smtp_usuario" maxlength="190" value="<?= e($smtp['usuario']) ?>" autocomplete="off"></div>
          <div class="campo-p"><label for="smtp_clave">Contraseña</label>
            <input type="password" id="smtp_clave" name="smtp_clave" maxlength="190" autocomplete="new-password"
                   placeholder="<?= $smtp['tiene_clave'] ? '•••••••• (guardada)' : '' ?>">
            <p class="ayuda-p">Déjala vacía para conservar la actual.</p></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="smtp_seguridad">Seguridad</label>
            <select id="smtp_seguridad" name="smtp_seguridad">
              <option value="tls" <?= $smtp['seguridad'] === 'tls' ? 'selected' : '' ?>>TLS (recomendado)</option>
              <option value="ssl" <?= $smtp['seguridad'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
              <option value="" <?= $smtp['seguridad'] === '' ? 'selected' : '' ?>>Ninguna</option>
            </select></div>
          <div class="campo-p"><label for="smtp_desde">Correo remitente</label>
            <input type="email" id="smtp_desde" name="smtp_desde" maxlength="190" value="<?= e($smtp['desde']) ?>"></div>
        </div>
        <div class="campo-p"><label for="smtp_nombre">Nombre del remitente</label>
          <input type="text" id="smtp_nombre" name="smtp_nombre" maxlength="120" value="<?= e($smtp['nombre']) ?>"></div>

        <div style="display:flex;gap:8px;align-items:flex-end;margin-top:6px">
          <div class="campo-p crece" style="margin:0"><label for="correoPrueba">Enviar prueba a</label>
            <input type="email" id="correoPrueba" maxlength="190" placeholder="tu@correo.com"></div>
          <button class="bt bt--linea" type="button" id="btnProbarCorreo"><?= icon('send') ?> Probar</button>
        </div>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('printer') ?> Impresión de tickets</h2></div>
        <div class="campo-p"><label for="impresora_ancho">Ancho del papel</label>
          <select id="impresora_ancho" name="impresora_ancho">
            <option value="80" <?= $impresora['ancho'] === '80' ? 'selected' : '' ?>>80 mm (estándar)</option>
            <option value="58" <?= $impresora['ancho'] === '58' ? 'selected' : '' ?>>58 mm (compacta)</option>
          </select></div>
        <div class="campo-p"><label for="impresora_copias">Copias por ticket</label>
          <input type="number" id="impresora_copias" name="impresora_copias" min="1" max="4" value="<?= (int)$impresora['copias'] ?>"></div>
        <div class="campo-p"><label for="impresora_encabezado">Texto extra en el ticket</label>
          <input type="text" id="impresora_encabezado" name="impresora_encabezado" maxlength="190"
                 value="<?= e($impresora['encabezado']) ?>" placeholder="Ej. NIT 1234567-8"></div>
        <label class="interruptor">
          <input type="checkbox" name="impresora_auto" value="1" <?= (int)$impresora['auto'] === 1 ? 'checked' : '' ?>>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Abrir el ticket automáticamente al cobrar</span>
        </label>
        <p class="ayuda-p">
          El ticket se genera como PDF con el ancho exacto de tu papel. Se imprime desde el navegador
          con cualquier impresora térmica conectada.
        </p>
      </div>
    </div>
    <div class="tarjeta-p" style="text-align:right">
      <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
    </div>
  </form>
</section>

<!-- ============ Modal zona de entrega ============ -->
<div class="modal-p" id="modalZonaEntrega" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(460px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/configuracion/entrega')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <input type="hidden" name="accion" value="guardar">
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Zona de entrega</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="zeNombre">Nombre de la zona *</label>
          <input type="text" id="zeNombre" name="nombre" required maxlength="120" placeholder="Ej. Centro de Antigua"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="zeCosto">Costo de envío</label>
            <div class="grupo-prefijo"><span><?= e($s) ?></span>
              <input type="number" id="zeCosto" name="costo" step="0.01" min="0" value="0" inputmode="decimal"></div></div>
          <div class="campo-p"><label for="zeMin">Pedido mínimo</label>
            <div class="grupo-prefijo"><span><?= e($s) ?></span>
              <input type="number" id="zeMin" name="minimo" step="0.01" min="0" value="0" inputmode="decimal"></div></div>
        </div>
        <div class="campo-p"><label for="zeTiempo">Tiempo estimado (minutos)</label>
          <input type="number" id="zeTiempo" name="tiempo_min" min="0" max="240" value="30" inputmode="numeric"></div>
        <label class="interruptor"><input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span><span class="interruptor__texto">Zona activa</span></label>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;

  // Pestañas
  function mostrar(clave) {
    M.$$('[data-pestana]').forEach(function (b) { b.setAttribute('aria-selected', String(b.dataset.pestana === clave)); });
    M.$$('[data-panel]').forEach(function (s) { s.hidden = s.dataset.panel !== clave; });
    try { localStorage.setItem('mg_cfg_tab', clave); } catch (e) {}
  }
  M.$$('[data-pestana]').forEach(function (b) {
    b.addEventListener('click', function () { mostrar(b.dataset.pestana); });
  });
  try {
    var g = localStorage.getItem('mg_cfg_tab');
    if (g && document.querySelector('[data-panel="' + g + '"]')) mostrar(g);
  } catch (e) {}

  // Colores
  [['color_primario', 'colorPrimarioTexto'], ['color_fondo', 'colorFondoTexto']].forEach(function (par) {
    var i = document.getElementById(par[0]), t = document.getElementById(par[1]);
    if (i && t) i.addEventListener('input', function () { t.value = i.value.toUpperCase(); });
  });

  // Quitar imagen -> marca el campo oculto
  M.$$('[data-quitar-previa]').forEach(function (b) {
    b.addEventListener('click', function () {
      var m = document.querySelector(b.dataset.marca);
      if (m) m.value = '1';
    });
  });

  // Probar correo
  var bp = document.getElementById('btnProbarCorreo');
  if (bp) bp.addEventListener('click', function () {
    var para = document.getElementById('correoPrueba').value.trim();
    if (!para) { M.avisar('Escribe un correo para la prueba.', 'aviso'); return; }
    bp.disabled = true;
    M.pedir('panel/configuracion/probar-correo', { para: para }).then(function (r) {
      bp.disabled = false;
      M.avisar(r.ok ? r.mensaje : r.error, r.ok ? 'ok' : 'error');
    });
  });

  // Borrar zona de entrega
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-borrar-zona-entrega]');
    if (!b) return;
    M.confirmar('Se eliminará la zona "' + b.dataset.nombre + '".', 'Eliminar zona', 'Sí, eliminar').then(function (ok) {
      if (!ok) return;
      M.pedir('panel/configuracion/entrega', { accion: 'borrar', id: Number(b.dataset.borrarZonaEntrega) })
        .then(function (r) { if (r.ok) location.reload(); else M.avisar(r.error, 'error'); });
    });
  });
})();
</script>
<?php View::stop(); ?>
