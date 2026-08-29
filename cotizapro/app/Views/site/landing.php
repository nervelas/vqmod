<?php
/** Landing de venta de la plataforma (administrable desde /super/landing). */
$g = static fn (string $k, string $d = ''): string => (string) ($s['landing_' . $k] ?? '') !== '' ? (string) $s['landing_' . $k] : $d;
$wa = preg_replace('/\D/', '', (string) ($s['whatsapp'] ?? '')) ?: '';
$waMsg = rawurlencode((string) ($s['whatsapp_message'] ?? 'Hola, me interesa el sistema de catálogo y cotizaciones. ¿Me comparten información?'));
$waUrl = $wa !== '' ? "https://wa.me/{$wa}?text={$waMsg}" : '#contacto';
$heroImg = !empty($s['hero_image']) ? upload($s['hero_image']) : url('/assets/img/industry/hero-planta.jpg');
$demoUrl = $demo ? url('/e/' . $demo['slug']) : url('/');
page('preloadImage', ['src' => url('/assets/img/industry/hero-planta-md.webp'),
    'srcset' => url('/assets/img/industry/hero-planta-sm.webp') . ' 640w, ' . url('/assets/img/industry/hero-planta-md.webp') . ' 1200w, ' . url('/assets/img/industry/hero-planta.webp') . ' 2200w']);
$problems = $blocks['problema'] ?? [];
$steps    = $blocks['paso'] ?? [];
$benefits = $blocks['beneficio'] ?? [];
$quotes   = $blocks['testimonio'] ?? [];
?>
<div class="sweep" aria-hidden="true"></div>

<header class="topbar">
  <div class="wrap topbar__in">
    <a class="brand" href="<?= e(url('/')) ?>">
      <span class="brand__mark" aria-hidden="true">CP</span>
      <span><?= e($s['platform_name'] ?? 'CotizaPro B2B') ?></span>
    </a>
    <button class="navtoggle" type="button" aria-expanded="false" aria-controls="mainnav" aria-label="Abrir menú"><span></span></button>
    <nav class="nav" id="mainnav" aria-label="Principal">
      <a href="#problema">El problema</a>
      <a href="#demo">Demo en vivo</a>
      <a href="#como">Cómo funciona</a>
      <a href="#planes">Planes</a>
      <a class="btn btn--accent btn--sm" href="<?= e($waUrl) ?>"<?= $wa !== '' ? ' target="_blank" rel="noopener"' : '' ?>>Quiero el mío <span class="arw" aria-hidden="true">&rarr;</span></a>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="hero__media">
    <picture>
      <source srcset="<?= e(url('/assets/img/industry/hero-planta-sm.webp')) ?> 640w, <?= e(url('/assets/img/industry/hero-planta-md.webp')) ?> 1200w, <?= e(url('/assets/img/industry/hero-planta.webp')) ?> 2200w" sizes="100vw" type="image/webp">
      <img src="<?= e($heroImg) ?>"
           srcset="<?= e(url('/assets/img/industry/hero-planta-sm.jpg')) ?> 640w, <?= e(url('/assets/img/industry/hero-planta-md.jpg')) ?> 1200w, <?= e(url('/assets/img/industry/hero-planta.jpg')) ?> 2200w"
           sizes="100vw" alt="Nave industrial con estanterías de repuestos" width="2200" height="1400" fetchpriority="high" decoding="async">
    </picture>
  </div>
  <div class="hero__scrim" aria-hidden="true"></div>
  <div class="hero__grid" aria-hidden="true"></div>
  <div class="wrap hero__in">
    <span class="kicker"><?= e($g('hero_kicker', 'Catálogo · Cotizador · Seguimiento')) ?></span>
    <h1 class="h-hero hero__title assemble">
      <?php foreach (explode('|', $g('hero_title', 'Su catálogo|cotiza solo.|Usted cierra.')) as $i => $line): ?>
        <span style="--d:<?= e(0.12 + $i * 0.13) ?>s"><?= e(trim($line)) ?></span>
      <?php endforeach; ?>
    </h1>
    <p class="hero__sub"><?= e($g('hero_sub', 'El sistema para empresas que no venden con tarjeta: catálogo técnico en línea, cotizador para el cliente y un tablero donde ninguna cotización se pierde.')) ?></p>
    <div class="hero__actions">
      <a class="btn btn--accent" href="<?= e($demoUrl) ?>">Ver la demo en vivo <span class="arw" aria-hidden="true">&rarr;</span></a>
      <a class="btn btn--light" href="#planes">Ver planes en Q</a>
    </div>
    <dl class="hero__meta">
      <div><dt>Empresas activas</dt><dd><span data-count="<?= e($counters['empresas']) ?>">0</span></dd></div>
      <div><dt>Productos publicados</dt><dd><span data-count="<?= e($counters['productos']) ?>">0</span></dd></div>
      <div><dt>Cotizaciones emitidas</dt><dd><span data-count="<?= e($counters['cotizaciones']) ?>">0</span></dd></div>
    </dl>
  </div>
</section>

<section class="section blueprint" id="problema">
  <svg class="tracer" viewBox="0 0 1200 600" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0 40 H420 V300 H820 V560 H1200"/>
  </svg>
  <div class="wrap">
    <div class="section__head reveal">
      <div>
        <span class="secnum">01/</span>
        <h2 class="h2" style="margin-top:12px"><?= e($g('problem_title', 'Cotizar por WhatsApp le está costando ventas.')) ?></h2>
      </div>
      <p class="lead" style="max-width:38ch;margin:0"><?= e($g('problem_body', 'El cliente pregunta por un repuesto, el vendedor busca precios, contesta tres días después y nadie vuelve a saber de esa cotización.')) ?></p>
    </div>
    <div class="problem reveal" data-d="1">
      <?php
      $defaults = [
        ['Se pierde el hilo', 'La cotización queda enterrada en un chat. Nadie sabe si el cliente la aprobó, la rechazó o simplemente la olvidó.'],
        ['Precios distintos', 'Cada vendedor arma su cotización en Excel con su propio formato y su propio criterio de descuento.'],
        ['Sin catálogo', 'El cliente no puede ver qué vende usted: tiene que preguntar por cada código, uno por uno.'],
        ['Cero medición', 'No sabe cuánto cotizó este mes, cuánto ganó, ni por qué perdió las que perdió.'],
      ];
      $items = $problems ?: array_map(static fn ($d) => ['title' => $d[0], 'body' => $d[1]], $defaults);
      foreach ($items as $p): ?>
        <div>
          <span class="problem__x" aria-hidden="true">&times;</span>
          <h3><?= e($p['title']) ?></h3>
          <p><?= e($p['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--tight" id="demo" style="background:var(--paper-2)">
  <div class="wrap">
    <div class="section__head reveal">
      <div>
        <span class="secnum">02/</span>
        <h2 class="h2" style="margin-top:12px">Demo en vivo, no capturas de pantalla.</h2>
      </div>
      <a class="btn btn--ghost" href="<?= e($demoUrl) ?>">Abrir <?= e($demo['name'] ?? 'la demo') ?> <span class="arw" aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="grid12" style="align-items:center">
      <div style="grid-column:span 7" class="reveal">
        <div class="demoframe">
          <div class="demoframe__bar" aria-hidden="true">
            <span class="demoframe__dot"></span><span class="demoframe__dot"></span><span class="demoframe__dot"></span>
            <span class="demoframe__url"><?= e(preg_replace('#^https?://#', '', $demoUrl)) ?></span>
          </div>
          <div class="demoframe__body">
            <picture>
              <source srcset="<?= e(url('/assets/img/industry/demo-catalogo.webp')) ?>" type="image/webp">
              <img src="<?= e(url('/assets/img/industry/demo-catalogo.jpg')) ?>" alt="Vista del catálogo técnico de la empresa demostrativa" width="1200" height="764" loading="lazy" decoding="async">
            </picture>
          </div>
        </div>
      </div>
      <div style="grid-column:span 5" class="reveal" data-d="2">
        <div class="stack">
          <div class="cota">Lo que verá</div>
          <ul style="list-style:none;padding:0;display:grid;gap:14px">
            <?php foreach ([
              ['Catálogo técnico real', 'Códigos, medidas, materiales y fichas técnicas en PDF.'],
              ['Carrito de cotización', 'El visitante arma su lista, pone cantidades y notas, y la envía.'],
              ['Seguimiento público', 'El cliente abre su enlace y ve el estado de su cotización.'],
              ['Tablero del vendedor', 'Kanban con semáforo de días sin seguimiento.'],
            ] as $i => $row): ?>
              <li style="display:flex;gap:14px;align-items:flex-start;padding-bottom:14px;border-bottom:1px solid var(--line)">
                <span class="secnum" style="flex:none;padding-top:3px"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?>/</span>
                <span><b style="font-family:var(--f-display);letter-spacing:-.01em"><?= e($row[0]) ?></b><br><span class="small muted"><?= e($row[1]) ?></span></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <a class="linkarrow" href="<?= e($demoUrl . '/catalogo') ?>">Entrar al catálogo de la demo <span aria-hidden="true">&rarr;</span></a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section blueprint" id="como">
  <div class="wrap">
    <div class="section__head reveal">
      <div>
        <span class="secnum">03/</span>
        <h2 class="h2" style="margin-top:12px"><?= e($g('steps_title', 'Funcionando en tres pasos.')) ?></h2>
      </div>
    </div>
    <div class="steps3 reveal" data-d="1">
      <?php
      $defSteps = [
        ['Suba su catálogo', 'Importe el CSV que ya exportó de WooCommerce o use la plantilla de Excel: categorías, códigos, medidas y fotos.'],
        ['El cliente cotiza solo', 'Busca por código o medida, arma su lista y envía la solicitud con sus datos. Sin registro, sin fricción.'],
        ['Usted cierra la venta', 'Recibe la solicitud en su tablero, ajusta precios, genera el PDF y lo envía por correo y WhatsApp en un clic.'],
      ];
      $items = $steps ?: array_map(static fn ($d) => ['title' => $d[0], 'body' => $d[1]], $defSteps);
      foreach (array_slice($items, 0, 3) as $st): ?>
        <div>
          <h3><?= e($st['title']) ?></h3>
          <p><?= e($st['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="counters reveal" style="margin-top:clamp(40px,5vw,72px)" data-d="2">
      <?php foreach ([
        ['Tiempo de respuesta', 'De días a', 20, ' min'],
        ['Cotizaciones sin rastro', 'Ahora', 0, ''],
        ['Instalación', 'En', 15, ' min'],
        ['Empresas por instalación', 'Hasta', 99, '+'],
      ] as $row): ?>
        <dl class="counter">
          <dt><?= e($row[0]) ?></dt>
          <dd><small style="font-size:.34em;letter-spacing:.14em;text-transform:uppercase"><?= e($row[1]) ?></small><br><span data-count="<?= e($row[2]) ?>" data-suffix="<?= e($row[3]) ?>">0</span></dd>
        </dl>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section on-dark" style="background:var(--ink);color:#fff">
  <div class="wrap">
    <div class="section__head reveal">
      <div>
        <span class="secnum">04/</span>
        <h2 class="h2" style="margin-top:12px;color:#fff">Todo lo que trae, sin módulos que comprar aparte.</h2>
      </div>
    </div>
    <div class="grid12 reveal" data-d="1">
      <?php
      $defBen = [
        ['Catálogo con atributos técnicos', 'Defina material, medida, norma o aplicación. El cliente filtra como en un catálogo de fabricante.'],
        ['Precios que usted controla', 'Ocúltelos, muéstrelos a todos o solo a clientes. Listas de precio por tipo de cliente.'],
        ['PDF de lujo con su marca', 'Su logo, sus colores, su firma y un QR que lleva al seguimiento en línea.'],
        ['Kanban con semáforo', 'Verde, amarillo y rojo según los días sin contactar al cliente. Nada se enfría.'],
        ['Recordatorios automáticos', 'El sistema le avisa cuando una cotización lleva días sin respuesta.'],
        ['Reportes que sí usa', 'Monto cotizado, ganado, conversión, ranking de vendedores y motivos de pérdida.'],
      ];
      $items = $benefits ?: array_map(static fn ($d) => ['title' => $d[0], 'body' => $d[1]], $defBen);
      foreach ($items as $i => $b): ?>
        <div style="grid-column:span 4;border-top:1px solid rgba(255,255,255,.2);padding-top:20px">
          <span class="secnum"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?>/</span>
          <h3 class="h3" style="color:#fff;margin:10px 0 8px"><?= e($b['title']) ?></h3>
          <p style="color:rgba(255,255,255,.66);font-size:.9375rem;margin:0"><?= e($b['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section blueprint" id="planes">
  <div class="wrap">
    <div class="section__head reveal">
      <div>
        <span class="secnum">05/</span>
        <h2 class="h2" style="margin-top:12px"><?= e($g('plans_title', 'Planes en quetzales, sin sorpresas.')) ?></h2>
      </div>
      <p class="lead" style="max-width:34ch;margin:0"><?= e($g('plans_sub', 'Una sola instalación en su propio hosting. Sin comisiones por cotización.')) ?></p>
    </div>
    <div class="plans reveal" data-d="1">
      <?php foreach ($plans as $p): $feat = \App\Models\Plan::features($p); ?>
        <div class="plan<?= $p['highlight'] ? ' plan--hot' : '' ?>">
          <span class="plan__name"><?= e($p['name']) ?></span>
          <?php if ($p['tagline']): ?><p class="small muted" style="margin:6px 0 0"><?= e($p['tagline']) ?></p><?php endif; ?>
          <p class="plan__price">Q<?= e(number_format((float) $p['price_month'], 0)) ?><small>/mes</small></p>
          <p class="small muted" style="margin:0">
            <?= (int) $p['max_products'] > 0 ? e(number_format((int) $p['max_products'])) . ' productos' : 'Productos ilimitados' ?> ·
            <?= (int) $p['max_users'] > 0 ? e($p['max_users']) . ' usuarios' : 'Usuarios ilimitados' ?>
          </p>
          <ul>
            <?php foreach ($feat as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
          </ul>
          <a class="btn <?= $p['highlight'] ? 'btn--accent' : 'btn--ghost' ?> btn--block" href="<?= e($waUrl) ?>"<?= $wa !== '' ? ' target="_blank" rel="noopener"' : '' ?>>Quiero el plan <?= e($p['name']) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($quotes): ?>
<section class="section section--tight" style="background:var(--paper-2)">
  <div class="wrap">
    <div class="section__head reveal">
      <div><span class="secnum">06/</span><h2 class="h2" style="margin-top:12px">Quienes ya dejaron el Excel.</h2></div>
    </div>
    <div class="grid12 reveal" data-d="1">
      <?php foreach (array_slice($quotes, 0, 3) as $q): ?>
        <div style="grid-column:span 4">
          <div class="quotecard">
            <p><?= e($q['body']) ?></p>
            <footer>
              <span class="avatarbubble" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $q['title'], 0, 1))) ?></span>
              <span><b><?= e($q['title']) ?></b><br><span><?= e($q['subtitle']) ?></span></span>
            </footer>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section on-dark" id="contacto" style="background:var(--ink);color:#fff;position:relative;overflow:hidden">
  <img src="<?= e(url('/assets/img/industry/plano-tecnico-md.jpg')) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async"
       style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.28">
  <div class="wrap" style="position:relative;z-index:2">
    <div class="grid12" style="align-items:start">
      <div style="grid-column:span 6" class="reveal">
        <span class="secnum">07/</span>
        <h2 class="h1" style="color:#fff;margin:14px 0 18px;max-width:16ch"><?= e($g('cta_title', 'Su catálogo puede estar en línea esta semana.')) ?></h2>
        <p style="color:rgba(255,255,255,.72);max-width:46ch"><?= e($g('cta_body', 'Escríbanos por WhatsApp y le mostramos el sistema con productos de su propio catálogo.')) ?></p>
        <div class="flex flex-wrap" style="margin-top:26px">
          <a class="btn btn--accent" href="<?= e($waUrl) ?>"<?= $wa !== '' ? ' target="_blank" rel="noopener"' : '' ?>>Escribir por WhatsApp <span class="arw" aria-hidden="true">&rarr;</span></a>
          <?php if (!empty($s['contact_email'])): ?>
            <a class="btn btn--ghost" style="--fg:#fff;--bd:rgba(255,255,255,.3)" href="mailto:<?= e($s['contact_email']) ?>"><?= e($s['contact_email']) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <div style="grid-column:span 5/-1" class="reveal" data-d="2">
        <?= \App\Core\View::partial('partials/flash', get_defined_vars()) ?>
        <form class="panelbox panelbox--dark" method="post" action="<?= e(url('/contacto')) ?>" novalidate>
          <?= csrf_field() ?>
          <p class="kicker" style="color:rgba(255,255,255,.6)">Solicitar demostración</p>
          <div class="row-2" style="margin-top:18px">
            <div class="field"><label for="c_name">Su nombre</label><input class="input" id="c_name" name="name" required maxlength="120" autocomplete="name"></div>
            <div class="field"><label for="c_company">Empresa</label><input class="input" id="c_company" name="company" maxlength="160" autocomplete="organization"></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="c_email">Correo</label><input class="input" id="c_email" name="email" type="email" required maxlength="150" autocomplete="email"></div>
            <div class="field"><label for="c_phone">Teléfono</label><input class="input" id="c_phone" name="phone" type="tel" maxlength="40" autocomplete="tel"></div>
          </div>
          <div class="field"><label for="c_msg">¿Qué vende su empresa?</label><textarea class="textarea" id="c_msg" name="message" rows="3" maxlength="1500"></textarea></div>
          <div class="field">
            <label for="c_captcha">Verificación</label>
            <div class="captchabox">
              <strong aria-hidden="true"><?= e($captcha['question']) ?> =</strong>
              <input class="input" id="c_captcha" name="captcha" inputmode="numeric" required aria-label="Resultado de <?= e($captcha['question']) ?>">
              <input type="hidden" name="captcha_stamp" value="<?= e($captcha['stamp']) ?>">
            </div>
          </div>
          <button class="btn btn--accent btn--block" type="submit">Enviar solicitud <span class="arw" aria-hidden="true">&rarr;</span></button>
        </form>
      </div>
    </div>
  </div>
</section>

<footer class="footer" style="background:#141618">
  <div class="wrap">
    <div class="footer__bottom" style="margin-top:0;padding-top:0;border:0">
      <span>&copy; <?= date('Y') ?> <?= e($s['platform_name'] ?? 'CotizaPro B2B') ?>. Hecho para empresas que venden por cotización.</span>
      <span class="flex" style="gap:18px">
        <a href="<?= e(url('/entrar')) ?>">Acceso</a>
        <a href="<?= e($demoUrl) ?>">Demo</a>
        <?php if ($wa !== ''): ?><a href="<?= e($waUrl) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      </span>
    </div>
  </div>
</footer>
