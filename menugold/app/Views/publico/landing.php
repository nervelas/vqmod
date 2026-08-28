<?php
/** @var array $planes, $captcha; array|null $demo */
use MenuGold\Core\Setting;
use MenuGold\Core\View;
use MenuGold\Models\Restaurant;

$marca   = (string)Setting::plat('nombre_plataforma', 'MenúGold');
$heroT   = (string)Setting::plat('hero_titulo', 'Tu carta, convertida en experiencia');
$heroS   = (string)Setting::plat('hero_subtitulo', 'Menú QR elegante, pedidos que llegan solos a la cocina y control total desde tu celular.');
$cta     = (string)Setting::plat('cta_texto', 'Quiero mi menú digital');
$imagen  = (string)Setting::plat('landing_imagen', '');
$urlDemo = $demo ? Restaurant::urlMenu($demo) : '';
?>
<main id="contenido">

<!-- ================= PORTADA ================= -->
<section class="hero">
  <div class="hero__int">
    <div>
      <span class="hero__eyebrow"><?= icon('sparkles') ?> Menús QR con pedidos</span>
      <h1><?= e($heroT) ?></h1>
      <p class="hero__texto"><?= e($heroS) ?></p>
      <div class="hero__acciones">
        <a class="btn btn--oro" href="#contacto"><?= icon('sparkles') ?> <?= e($cta) ?></a>
        <?php if ($urlDemo): ?>
          <a class="btn btn--linea" href="<?= e($urlDemo) ?>" target="_blank" rel="noopener">
            <?= icon('eye') ?> Ver una demostración
          </a>
        <?php endif; ?>
      </div>
      <div class="hero__prueba">
        <span><?= icon('check-circle') ?> Sin comisiones por pedido</span>
        <span><?= icon('check-circle') ?> Listo en 24 horas</span>
        <span><?= icon('check-circle') ?> Cambia precios desde tu celular</span>
      </div>
    </div>

    <div style="position:relative">
      <div class="telefono">
        <div class="telefono__pantalla">
          <?php if ($imagen): ?>
            <img src="<?= e(uploaded($imagen)) ?>" alt="Vista del menú digital en un celular" width="300" height="620">
          <?php else: ?>
            <div class="telefono__demo">
              <div class="telefono__cab">
                <div class="telefono__logo">LT</div>
                <p class="telefono__nombre">La Terraza Gold</p>
                <span class="telefono__estado">● Abierto ahora</span>
              </div>
              <?php foreach ([
                ['C', 'Ceviche del chef', 'Corvina fresca, leche de tigre', 'Q145.00'],
                ['L', 'Lomito Wellington', 'Res premium en hojaldre', 'Q285.00'],
                ['C', 'Crème brûlée', 'Vainilla de Alta Verapaz', 'Q82.00'],
              ] as $p): ?>
                <div class="telefono__plato">
                  <span class="telefono__foto"><?= e($p[0]) ?></span>
                  <div>
                    <strong><?= e($p[1]) ?></strong>
                    <small><?= e($p[2]) ?></small>
                    <span class="telefono__precio"><?= e($p[3]) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
              <div class="telefono__barra"><span>3 · Ver mi pedido</span><span>Q512.00</span></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($urlDemo): ?>
        <div class="qr-flotante">
          <img src="<?= e(url('qr-demo.png')) ?>" alt="Código QR de la demostración" width="82" height="82" loading="lazy">
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ================= BENEFICIOS ================= -->
<section class="seccion-pub seccion-pub--alt" id="beneficios">
  <div class="seccion-pub__int">
    <div class="seccion-pub__cab revelar">
      <h2>Todo lo que tu restaurante necesita</h2>
      <p>Sin apps que instalar, sin comisiones por pedido y sin depender de nadie para cambiar un precio.</p>
    </div>
    <div class="tarjetas-pub">
      <?php foreach ([
        ['qr', 'Menú QR de lujo', 'Tus platillos con fotos grandes, descripciones que dan hambre y precios siempre al día. Se ve impecable en cualquier celular.'],
        ['chef', 'Pedidos directo a cocina', 'El cliente escanea el QR de su mesa, pide y la comanda aparece al instante en la pantalla de la cocina. Sin equivocaciones.'],
        ['table', 'Mesas y caja bajo control', 'Ve qué mesas están ocupadas, quién pidió la cuenta, divide el pago y cobra. Todo desde una tablet o tu celular.'],
        ['zap', 'Cambios en un toque', 'Se acabó el pescado del día: lo marcas agotado y desaparece del menú al instante. Sin reimprimir nada.'],
        ['moto', 'Para llevar y domicilio', 'Recibe pedidos con dirección, zona de entrega y costo de envío. O deja que lleguen directo a tu WhatsApp.'],
        ['chart', 'Reportes que sí usas', 'Ventas del día, ticket promedio, horas pico, qué se vende y qué no. Exportables a PDF y Excel.'],
        ['percent', 'Promociones y cupones', '2x1 los martes, 20% en entradas, códigos de descuento con fecha. Se aplican solos al pedir.'],
        ['shield', 'Seguro de verdad', 'Cada QR de mesa va firmado. Los precios se calculan en el servidor. Todo cambio queda registrado con usuario, IP y fecha.'],
      ] as $b): ?>
        <article class="tarjeta-pub revelar">
          <div class="tarjeta-pub__icono"><?= icon($b[0]) ?></div>
          <h3><?= e($b[1]) ?></h3>
          <p><?= e($b[2]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= CÓMO FUNCIONA ================= -->
<section class="seccion-pub" id="como">
  <div class="seccion-pub__int">
    <div class="seccion-pub__cab revelar">
      <h2>Así de simple</h2>
      <p>De la carta de papel a tu menú digital funcionando, en menos de un día.</p>
    </div>
    <div class="pasos-pub">
      <?php foreach ([
        ['Nos das tu carta', 'Nosotros la subimos, o la cargas tú desde un Excel en cinco minutos.'],
        ['Eliges cómo se ve', 'Ocho temas premium, tu logo, tus colores y tu tipografía. Queda con tu identidad.'],
        ['Imprimes tus QR', 'Descarga el PDF listo para imprimir: tarjetas de mesa, tent cards o stickers.'],
        ['Empiezas a recibir pedidos', 'Tus clientes escanean y piden. Tú lo ves todo desde tu celular.'],
      ] as $p): ?>
        <div class="paso-pub revelar">
          <h3><?= e($p[0]) ?></h3>
          <p><?= e($p[1]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= TESTIMONIO ================= -->
<section class="seccion-pub seccion-pub--alt">
  <div class="cita revelar">
    <div class="cita__estrellas" aria-label="5 de 5 estrellas">
      <?php for ($i = 0; $i < 5; $i++) echo icon('star'); ?>
    </div>
    <blockquote>
      «Dejamos de reimprimir cartas cada vez que subía un precio.
      Ahora el mesero atiende más mesas y la cocina no vuelve a preguntar qué pidió el cliente.»
    </blockquote>
    <div class="cita__autor">Mariana Solís</div>
    <div class="cita__cargo">Propietaria · La Terraza Gold, Antigua Guatemala</div>
  </div>
</section>

<!-- ================= PLANES ================= -->
<section class="seccion-pub" id="planes">
  <div class="seccion-pub__int">
    <div class="seccion-pub__cab revelar">
      <h2>Planes claros, sin sorpresas</h2>
      <p>Sin comisión por pedido. Sin contrato forzoso. Cancelas cuando quieras.</p>
    </div>
    <div class="planes-pub">
      <?php foreach ($planes as $p): ?>
        <article class="plan-pub revelar <?= (int)$p['destacado'] === 1 ? 'plan-pub--destacado' : '' ?>">
          <?php if ((int)$p['destacado'] === 1): ?>
            <span class="plan-pub__cinta">El más elegido</span>
          <?php endif; ?>
          <h3><?= e((string)$p['nombre']) ?></h3>
          <p class="plan-pub__desc"><?= e((string)$p['descripcion']) ?></p>
          <div class="plan-pub__precio">
            <b>Q<?= e(number_format((float)$p['precio_mensual'], 0)) ?></b><span>/ mes</span>
          </div>
          <?php if ((float)$p['precio_anual'] > 0): ?>
            <p class="plan-pub__anual">o Q<?= e(number_format((float)$p['precio_anual'], 0)) ?> al año (dos meses gratis)</p>
          <?php else: ?><p class="plan-pub__anual">&nbsp;</p><?php endif; ?>

          <ul class="plan-pub__lista">
            <?php foreach ((array)$p['caracteristicas'] as $c): ?>
              <li><?= icon('check') ?><span><?= e((string)$c) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="btn <?= (int)$p['destacado'] === 1 ? 'btn--oro' : 'btn--linea' ?> btn--bloque"
             href="#contacto" data-plan="<?= e((string)$p['nombre']) ?>">
            Quiero el plan <?= e((string)$p['nombre']) ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="centro" style="margin-top:26px;color:var(--texto-tenue);font-size:14px">
      ¿Tienes varias sucursales o necesitas algo distinto? Escríbenos y lo armamos a tu medida.
    </p>
  </div>
</section>

<!-- ================= CONTACTO ================= -->
<section class="seccion-pub seccion-pub--alt" id="contacto">
  <div class="seccion-pub__int">
    <div class="contacto-caja">
      <div class="contacto-info revelar">
        <h3>Hablemos de tu restaurante</h3>
        <p>
          Cuéntanos qué necesitas y te mostramos cómo se vería tu menú.
          Sin compromiso y sin llamadas eternas.
        </p>
        <ul>
          <li><?= icon('clock') ?><span>Te respondemos el mismo día hábil.</span></li>
          <li><?= icon('utensils') ?><span>Nosotros subimos tu carta completa la primera vez.</span></li>
          <li><?= icon('qr') ?><span>Te entregamos los QR listos para imprimir.</span></li>
          <li><?= icon('shield') ?><span>Tus datos son tuyos. Puedes descargarlos cuando quieras.</span></li>
        </ul>
        <?php
        $wa = preg_replace('/\D/', '', (string)Setting::plat('whatsapp', '')) ?? '';
        $mail = (string)Setting::plat('email_contacto', '');
        ?>
        <?php if ($wa || $mail): ?>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
            <?php if ($wa): ?>
              <a class="btn btn--wa" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">
                <?= icon('whatsapp') ?> WhatsApp
              </a>
            <?php endif; ?>
            <?php if ($mail): ?>
              <a class="btn btn--linea" href="mailto:<?= e($mail) ?>"><?= icon('mail') ?> <?= e($mail) ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <form class="contacto-form revelar" method="post" action="<?= e(url('contacto')) ?>" novalidate>
        <?= csrf_field() ?>
        <div style="position:absolute;left:-9999px" aria-hidden="true">
          <label>No llenar<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="campo">
          <label for="cnNombre">Tu nombre *</label>
          <input type="text" id="cnNombre" name="nombre" required maxlength="120" value="<?= old('nombre') ?>" autocomplete="name">
        </div>
        <div class="campo-fila">
          <div class="campo">
            <label for="cnEmail">Correo *</label>
            <input type="email" id="cnEmail" name="email" required maxlength="190" value="<?= old('email') ?>" autocomplete="email">
          </div>
          <div class="campo">
            <label for="cnTel">Teléfono / WhatsApp</label>
            <input type="tel" id="cnTel" name="telefono" maxlength="30" value="<?= old('telefono') ?>" autocomplete="tel">
          </div>
        </div>
        <div class="campo-fila">
          <div class="campo">
            <label for="cnRest">Nombre del restaurante</label>
            <input type="text" id="cnRest" name="restaurante" maxlength="120" value="<?= old('restaurante') ?>">
          </div>
          <div class="campo">
            <label for="cnPlan">Plan de interés</label>
            <select id="cnPlan" name="plan">
              <option value="">Todavía no lo sé</option>
              <?php foreach ($planes as $p): ?>
                <option value="<?= e((string)$p['nombre']) ?>"><?= e((string)$p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="campo">
          <label for="cnMensaje">¿Qué necesitas? *</label>
          <textarea id="cnMensaje" name="mensaje" required maxlength="2000" rows="4"
                    placeholder="Ej. tengo 14 mesas y quiero que los clientes pidan desde el QR"><?= old('mensaje') ?></textarea>
        </div>
        <div class="campo">
          <label for="cnCaptcha">Para confirmar que eres humano: ¿cuánto es <?= e($captcha['pregunta']) ?>?</label>
          <input type="number" id="cnCaptcha" name="captcha" required inputmode="numeric" style="max-width:150px">
        </div>
        <button class="btn btn--oro btn--bloque" type="submit"><?= icon('send') ?> Enviar mi solicitud</button>
        <p class="campo__ayuda centro" style="margin-top:12px">
          Solo usamos tus datos para contactarte. Nunca los compartimos.
        </p>
      </form>
    </div>
  </div>
</section>

<!-- ================= PIE ================= -->
<footer class="pie-pub">
  <div class="pie-pub__int">
    <div>
      <div class="cab-pub__marca" style="margin-bottom:12px"><?= e($marca) ?></div>
      <p style="color:var(--texto-tenue);font-size:14px;max-width:280px;line-height:1.6">
        <?= e((string)Setting::plat('eslogan', '')) ?>
      </p>
      <div style="display:flex;gap:9px;margin-top:16px">
        <?php foreach (['facebook' => 'globe', 'instagram' => 'image'] as $red => $ic): ?>
          <?php $u = (string)Setting::plat($red, ''); if ($u): ?>
            <a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($red)) ?>"
               style="width:40px;height:40px;border-radius:50%;border:1px solid var(--borde);display:grid;place-items:center">
              <?= icon($ic) ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <h4>Producto</h4>
      <a href="#beneficios">Beneficios</a>
      <a href="#como">Cómo funciona</a>
      <a href="#planes">Planes y precios</a>
      <?php if ($urlDemo): ?><a href="<?= e($urlDemo) ?>" target="_blank" rel="noopener">Ver demostración</a><?php endif; ?>
    </div>
    <div>
      <h4>Cuenta</h4>
      <a href="<?= e(url('ingresar')) ?>">Ingresar al panel</a>
      <a href="<?= e(url('recuperar')) ?>">Recuperar contraseña</a>
      <a href="#contacto">Soporte</a>
    </div>
    <div>
      <h4>Contacto</h4>
      <?php if ($mail): ?><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a><?php endif; ?>
      <?php $tel = (string)Setting::plat('telefono', ''); if ($tel): ?>
        <a href="tel:<?= e(preg_replace('/\s/', '', $tel)) ?>"><?= e($tel) ?></a>
      <?php endif; ?>
      <?php $dir = (string)Setting::plat('direccion', ''); if ($dir): ?>
        <span style="display:block;padding:5px 0;font-size:14px;color:var(--texto-suave)"><?= e($dir) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="pie-pub__abajo">
    © <?= date('Y') ?> <?= e($marca) ?>. Todos los derechos reservados.
  </div>
</footer>
</main>

<?php View::start('scripts'); ?>
<script nonce="<?= e(\MenuGold\Core\Security::nonce()) ?>">
(function () {
  // Preselecciona el plan al venir desde una tarjeta
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-plan]');
    if (!a) return;
    var s = document.getElementById('cnPlan');
    if (s) {
      for (var i = 0; i < s.options.length; i++) {
        if (s.options[i].value === a.dataset.plan) { s.selectedIndex = i; break; }
      }
    }
  });

  // Muestra los avisos del servidor como tostadas
  var flashes = <?= json_encode(array_values($flashes ?? []), JSON_UNESCAPED_UNICODE) ?>;
  flashes.forEach(function (f) {
    var cont = document.getElementById('tostadas');
    if (!cont) return;
    var el = document.createElement('div');
    el.className = 'tostada tostada--' + (f.tipo === 'exito' ? 'ok' : (f.tipo === 'error' ? 'error' : 'aviso'));
    el.textContent = f.texto;
    cont.appendChild(el);
    setTimeout(function () { el.remove(); }, 6000);
  });
  if (flashes.length) {
    var c = document.getElementById('contacto');
    if (c) window.scrollTo({ top: c.getBoundingClientRect().top + window.pageYOffset - 76, behavior: 'smooth' });
  }
})();
</script>
<?php View::stop(); ?>
<?php unset($_SESSION['_old']); ?>
