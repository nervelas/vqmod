<?php
/**
 * Home pública. Variables: $cfg (wa_servicom, precio_info, precio_tienda, precio_tarjeta, pres_max_mb, demos[]).
 */
require_once __DIR__ . '/_icons.php';
$cfg      = isset($cfg) && is_array($cfg) ? $cfg : [];
$pInfo    = money_q($cfg['precio_info'] ?? 1250);
$pTienda  = money_q($cfg['precio_tienda'] ?? 1750);
$pTarjeta = money_q($cfg['precio_tarjeta'] ?? 750);
$waNum    = (string)($cfg['wa_servicom'] ?? '');
$presMb   = (int)($cfg['pres_max_mb'] ?? 10);
$demos    = [];
foreach ((array)($cfg['demos'] ?? []) as $d) {
    if (is_array($d) && !empty($d['url']) && preg_match('~^https?://~i', (string)$d['url'])) { $demos[] = $d; }
}
$comun = [
    ['spark','Diseño premium personalizado'],['globe','Dominio .com incluido'],['chat','Un idioma a tu elección (español o inglés)'],
    ['mail','10 correos corporativos'],['box','Hosting incluido'],['phone','Diseño responsive: celular, tablet y PC'],
    ['menu','Menú desplegable'],['slides','Slider / banner animado'],['form','Formulario de contacto'],
    ['wa','Chat de WhatsApp'],['share','Enlaces a tus redes sociales'],['image','Imágenes y videos ilimitados'],
    ['panel','Panel de administración'],['lock','Certificado SSL (candado seguro)'],['map','Mapa de ubicación'],
];
$tienda = [
    ['layout','Secciones y categorías ilimitadas'],['bell','Inventario con alertas de stock'],['search','Búsqueda de productos'],
    ['file','Descripciones de producto'],['users','Registro de clientes'],['cart','Carrito de compras'],
    ['mail','Pedidos directo a tu correo'],['bank','Pago por transferencia / depósito y contra entrega'],['tag','Tienda con WooCommerce'],
];
$faq = [
  ['¿Cuánto tarda?','Llenar tus datos toma unos 5 minutos y tu vista previa se arma en automático apenas terminas, para que la revises de inmediato. El dominio .com y tus correos quedan activos en un máximo de 2 días hábiles después de confirmar tu pago.'],
  ['¿Qué incluye mi plan?','Diseño premium, dominio .com, hosting, certificado SSL, 10 correos corporativos, formulario de contacto, chat de WhatsApp, mapa, redes sociales y panel de administración. La tienda suma carrito, inventario, pedidos a tu correo y más. Mira la lista completa en la sección “Qué incluye”.'],
  ['¿Cómo se paga?','El pago es únicamente por transferencia o depósito bancario. Cuando apruebas tu vista previa te mostramos los datos de la cuenta, subes tu comprobante y nosotros lo revisamos. El extra de tarjeta es para que tu tienda cobre con tarjeta; se activa con Visanet/Epay y requiere tu afiliación.'],
  ['¿Cómo funcionan el dominio .com y los correos?','Si ya tienes un dominio .com lo conectamos; si no, registramos el que nos pidas. Puedes tener hasta 10 correos como ventas@tunegocio.com. Dominio y correos quedan listos en un máximo de 2 días hábiles.'],
  ['¿Qué presentación puedo subir?','Un PDF, PowerPoint (.pptx) o Word (.docx) de hasta ' . $presMb . ' MB. Con solo ese archivo creamos tu web completa (logo, colores, textos y fotos). Lo que falte lo completas después desde tu panel. Si tu archivo es .ppt, .doc, Keynote o Canva, guárdalo como PDF y súbelo de nuevo.'],
  ['¿Puedo hacer cambios después?','Sí. Antes de pagar puedes editar tus datos y regenerar la vista previa las veces que necesites. Ya publicada, tu sitio incluye un panel de administración para cambiar textos, fotos, servicios o productos cuando quieras.'],
  ['¿Cómo es la renovación?','El plan es anual: el precio incluye dominio, hosting, SSL y correos por un año. Antes del vencimiento te escribimos para coordinar la renovación; no hay cobros automáticos.'],
  ['¿Qué pasa con la privacidad de mi presentación?','Tu archivo se usa solo para crear tu web, se procesa con inteligencia artificial y se elimina al finalizar. No se comparte ni se publica. Subirla es opcional: siempre puedes llenar los datos a mano.'],
];
$waMsg = 'Hola Servicom, quiero información sobre “Tu web en 5 minutos”.';
?>
<link rel="stylesheet" href="<?= e(asset('css/home.css')) ?>">
<?php $navFull = true; include __DIR__ . '/_header.php'; ?>
<main id="main">

<section class="hero" aria-labelledby="h-hero">
  <div class="hero__orb o1" data-par="0.12" aria-hidden="true"></div>
  <div class="hero__orb o2" data-par="-0.08" aria-hidden="true"></div>
  <div class="wrap hero__grid">
    <div class="hero__copy">
      <span class="eyebrow">Tu web en 5 minutos</span>
      <h1 id="h-hero">Tu negocio, con una web <span class="gold-text">de lujo</span>, lista hoy.</h1>
      <p class="hero__lead">Llena tus datos o sube la presentación de tu negocio. Nosotros armamos tu vista previa con dominio .com, correos corporativos y hosting incluidos.</p>
      <div class="hero__cta">
        <a class="btn btn--gold" id="cta-hero" href="/crear">Crea tu web ahora <?= ico('arrow') ?></a>
        <a class="btn btn--ghost" href="#como">Cómo funciona</a>
      </div>
      <ul class="trust" aria-label="Incluido en todos los planes">
        <li><?= ico('globe') ?>Dominio .com</li><li><?= ico('mail') ?>10 correos</li><li><?= ico('lock') ?>SSL</li><li><?= ico('wa') ?>WhatsApp</li>
      </ul>
    </div>

    <div class="mock" aria-hidden="true">
      <div class="win">
        <div class="win__bar"><i></i><i></i><i></i><span>tunegocio.com</span></div>
        <div class="win__body">
          <div class="m-hdr a1"><b class="m-logo"></b><span class="m-nav"><i></i><i></i><i></i></span><b class="m-pill"></b></div>
          <div class="m-banner a2"><div class="m-t1"></div><div class="m-t2"></div><div class="m-t3"></div><b class="m-cta"></b></div>
          <div class="m-cards">
            <div class="m-card a3"><span><?= ico('spark') ?></span><i></i><i></i></div>
            <div class="m-card a4"><span><?= ico('shield') ?></span><i></i><i></i></div>
            <div class="m-card a5"><span><?= ico('heart') ?></span><i></i><i></i></div>
          </div>
          <div class="m-strip a6"><i></i><i></i><i></i><i></i></div>
        </div>
        <div class="m-wa a7"><?= ico('wa') ?></div>
      </div>
      <div class="m-done a8"><?= ico('check') ?> Vista previa lista</div>
    </div>
  </div>
</section>

<section class="stats" aria-label="En números">
  <div class="wrap stats__g">
    <div class="stat rv"><b class="num" data-count="5">5</b><span>minutos para llenar tus datos</span></div>
    <div class="stat rv d1"><b class="num" data-count="10">10</b><span>correos corporativos incluidos</span></div>
    <div class="stat rv d2"><b class="num" data-count="2">2</b><span>días hábiles para dominio y correos</span></div>
    <div class="stat rv d3"><b class="num">∞</b><span>imágenes y videos en tu web</span></div>
  </div>
</section>

<section class="sec" id="como" aria-labelledby="h-como">
  <div class="wrap">
    <header class="sec__head rv"><span class="eyebrow">Cómo funciona</span><h2 id="h-como">Tres pasos y tu web está en línea</h2></header>
    <ol class="how">
      <li class="how__i rv"><span class="how__n">1</span><span class="how__ic"><?= ico('upload') ?></span><div><h3>Llena tus datos o sube tu presentación</h3><p>Responde unas preguntas sencillas desde tu teléfono, o sube solo un PDF con tu negocio y nosotros creamos toda tu web. <a href="/guia-presentacion">¿Qué debe llevar?</a></p></div></li>
      <li class="how__i rv d1"><span class="how__n">2</span><span class="how__ic"><?= ico('eye') ?></span><div><h3>Revisa tu vista previa</h3><p>Armamos tu sitio y te enviamos un enlace privado para verlo en tu celular o computadora. ¿Algo por ajustar? Edita y regenera.</p></div></li>
      <li class="how__i rv d2"><span class="how__n">3</span><span class="how__ic"><?= ico('card') ?></span><div><h3>Paga y publica</h3><p>Pagas por transferencia o depósito, subes tu comprobante y publicamos tu web con tu dominio .com y tus correos.</p></div></li>
    </ol>
  </div>
</section>

<section class="sec sec--alt" id="planes" aria-labelledby="h-planes">
  <div class="wrap">
    <header class="sec__head rv"><span class="eyebrow">Planes y precios</span><h2 id="h-planes">Un precio claro, por año</h2><p>Todo incluido: dominio, hosting, SSL y correos. Sin sorpresas.</p></header>
    <div class="plans">
      <article class="plan rv">
        <h3>Página informativa</h3>
        <p class="plan__d">Para presentar tu negocio, tus servicios y que te contacten.</p>
        <p class="price"><b><?= e($pInfo) ?></b><span>/año</span></p>
        <ul class="plan__l">
          <li><?= ico('check') ?>Diseño premium y responsive</li>
          <li><?= ico('check') ?>Dominio .com y 10 correos</li>
          <li><?= ico('check') ?>Hosting y certificado SSL</li>
          <li><?= ico('check') ?>WhatsApp, formulario y mapa</li>
          <li><?= ico('check') ?>Panel de administración</li>
        </ul>
        <a class="btn btn--ghost btn--block" href="/crear?plan=info">Elegir informativa</a>
      </article>
      <article class="plan plan--hot rv d1">
        <span class="plan__tag">Más completa</span>
        <h3>Tienda virtual</h3>
        <p class="plan__d">Vende en línea con carrito, inventario y pedidos a tu correo.</p>
        <p class="price"><b><?= e($pTienda) ?></b><span>/año</span></p>
        <ul class="plan__l">
          <li><?= ico('check') ?>Todo lo de la página informativa</li>
          <li><?= ico('check') ?>Productos y categorías ilimitados</li>
          <li><?= ico('check') ?>Carrito y registro de clientes</li>
          <li><?= ico('check') ?>Inventario con alertas de stock</li>
          <li><?= ico('check') ?>Pago por transferencia y contra entrega</li>
        </ul>
        <a class="btn btn--gold btn--block" href="/crear?plan=tienda">Elegir tienda virtual</a>
      </article>
      <article class="plan plan--extra rv d2">
        <span class="plan__ico"><?= ico('card') ?></span>
        <div>
          <h3>Extra: pago con tarjeta</h3>
          <p class="price price--s"><b>+<?= e($pTarjeta) ?></b><span>/año</span></p>
          <p class="plan__d">Cobra con tarjeta en tu web. Se activa con Visanet/Epay y requiere tu afiliación.</p>
        </div>
      </article>
    </div>
  </div>
</section>

<section class="sec" id="incluye" aria-labelledby="h-inc">
  <div class="wrap">
    <header class="sec__head rv"><span class="eyebrow">Qué incluye cada plan</span><h2 id="h-inc">Todo lo que tu web necesita</h2></header>
    <h3 class="inc__t rv">En ambos planes</h3>
    <ul class="inc">
<?php foreach ($comun as $i => $it): ?>
      <li class="rv"><span><?= ico($it[0]) ?></span><?= e($it[1]) ?></li>
<?php endforeach; ?>
    </ul>
    <h3 class="inc__t inc__t--g rv">Además, en la Tienda virtual</h3>
    <ul class="inc inc--g">
<?php foreach ($tienda as $it): ?>
      <li class="rv"><span><?= ico($it[0]) ?></span><?= e($it[1]) ?></li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<?php if ($demos): ?>
<section class="sec sec--alt" id="ejemplos" aria-labelledby="h-ej">
  <div class="wrap">
    <header class="sec__head rv"><span class="eyebrow">Ejemplos</span><h2 id="h-ej">Mira cómo se ve</h2><p>Webs de demostración hechas con el mismo sistema.</p></header>
    <div class="demos">
<?php foreach ($demos as $i => $d): ?>
      <a class="demo rv" href="<?= e($d['url']) ?>" target="_blank" rel="noopener">
        <div class="demo__art dm<?= $i % 5 ?>" aria-hidden="true"><div class="da-bar"><i></i><i></i><i></i></div><div class="da-b"><b></b><b></b></div><div class="da-c"><i></i><i></i><i></i></div></div>
        <div class="demo__t"><h3><?= e($d['nombre'] ?? 'Ejemplo') ?></h3><?php if (!empty($d['rubro'])): ?><span><?= e($d['rubro']) ?></span><?php endif; ?></div>
        <span class="demo__go">Ver ejemplo <?= ico('arrow') ?></span>
        <span class="sr">(se abre en una pestaña nueva)</span>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec<?= $demos ? '' : ' sec--alt' ?>" id="faq" aria-labelledby="h-faq">
  <div class="wrap wrap--narrow">
    <header class="sec__head rv"><span class="eyebrow">Preguntas frecuentes</span><h2 id="h-faq">Lo que más nos preguntan</h2></header>
    <div class="faq">
<?php foreach ($faq as $q): ?>
      <details class="rv"><summary><span><?= e($q[0]) ?></span><?= ico('plus') ?></summary><p><?= e($q[1]) ?></p></details>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec cta" id="contacto" aria-labelledby="h-ct">
  <div class="wrap">
    <div class="cta__box rv">
      <span class="eyebrow">Contacto</span>
      <h2 id="h-ct">¿Listo para empezar o con dudas?</h2>
      <p>Crea tu web ahora o escríbenos por WhatsApp. Te respondemos personalmente.</p>
      <div class="cta__b">
        <a class="btn btn--gold" href="/crear">Crea tu web ahora <?= ico('arrow') ?></a>
<?php if ($waNum !== ''): ?>
        <a class="btn btn--wa" href="<?= e(wa_link($waNum, $waMsg)) ?>" target="_blank" rel="noopener"><?= ico('wa') ?>Escríbenos por WhatsApp<span class="sr"> (se abre en una pestaña nueva)</span></a>
<?php endif; ?>
      </div>
    </div>
  </div>
</section>
</main>
<?php include __DIR__ . '/_footer.php'; ?>
<a class="fab" id="fab" href="/crear">Crea tu web ahora <?= ico('arrow') ?></a>
