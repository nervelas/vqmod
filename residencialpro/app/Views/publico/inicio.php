<?php
use App\Core\Ajustes;

$nombre = Ajustes::get('nombre', 'ResidencialPro');
$ciudad = Ajustes::get('ciudad', 'Guatemala');
$foto   = static fn(string $n): string => url('/assets/img/sitio/' . $n);

/** Fotografía de portada administrable: si hay galería, manda la galería. */
$portada = $galeria[0]['archivo'] ?? '';
$hayPortadaPropia = $portada !== '' && is_file(RUTA_BASE . '/uploads/galeria/' . $portada);
?>

<!-- ═══════════════════════ PORTADA ═══════════════════════ -->
<section class="portada">
  <div class="portada-foto">
    <?php if ($hayPortadaPropia): ?>
      <img src="<?= e(subida($portada, 'galeria')) ?>" alt="" width="2000" height="1125" fetchpriority="high">
    <?php else: ?>
      <picture>
        <source type="image/webp" srcset="<?= e($foto('portada-700.webp')) ?> 700w, <?= e($foto('portada-900.webp')) ?> 900w, <?= e($foto('portada-1200.webp')) ?> 1200w, <?= e($foto('portada.webp')) ?> 1800w" sizes="100vw">
        <img src="<?= e($foto('portada-1200.jpg')) ?>" alt="" width="1800" height="1013" fetchpriority="high" decoding="async">
      </picture>
    <?php endif; ?>
  </div>

  <div class="portada-rejilla">
    <div class="portada-texto">
      <span class="lema"><?= e($ciudad) ?> · Residencial privado</span>
      <h1>Vivir tranquilo <em>empieza</em> por estar bien administrado.</h1>
      <p class="entrada">
        <?= e(recortar(Ajustes::get('descripcion',
            'Cuotas al día, garita con control de acceso, áreas comunes reservables y comunicación directa con la administración. Todo en un solo lugar, disponible desde su teléfono.'), 210)) ?>
      </p>
      <div class="portada-ctas">
        <a class="btn btn-oro btn-lg" href="<?= e(url('/acceso')) ?>"><?= ico('entrar', 18) ?> Entrar al portal</a>
        <a class="btn btn-claro btn-lg" href="#administracion">Ver cómo se administra</a>
      </div>
    </div>

    <!-- Pieza de interfaz real, no una captura: siempre nítida. -->
    <div class="maqueta" aria-hidden="true">
      <div class="maqueta-barra">
        <span class="n">Estado de cuenta · Casa A-12</span>
        <span class="luces"><i></i><i></i><i></i></span>
      </div>
      <div class="maqueta-cuerpo">
        <div class="kpi ok">
          <span class="kpi-et">Saldo al día de hoy</span>
          <span class="kpi-valor">Q 0.00</span>
          <span class="kpi-nota"><span class="punto"></span> Vivienda solvente</span>
        </div>
        <table class="tabla">
          <tbody>
            <tr><td>Cuota de mantenimiento</td><td class="d"><span class="chip ok">Pagado</span></td></tr>
            <tr><td>Agua potable</td><td class="d"><span class="chip ok">Pagado</span></td></tr>
            <tr><td>Salón de eventos · 05/09</td><td class="d"><span class="chip oro">Reservado</span></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="portada-cifras">
    <div class="interior">
      <div class="cifra"><div class="v" data-contador="<?= (int) ($cifras['viviendas'] ?? 0) ?>">0</div><div class="e">Viviendas</div></div>
      <div class="cifra"><div class="v" data-contador="<?= (int) ($cifras['residentes'] ?? 0) ?>">0</div><div class="e">Residentes registrados</div></div>
      <div class="cifra"><div class="v" data-contador="<?= (int) ($cifras['accesos'] ?? 0) ?>">0</div><div class="e">Accesos en 30 días</div></div>
      <div class="cifra"><div class="v">24/7</div><div class="e">Garita con bitácora</div></div>
    </div>
  </div>
</section>

<!-- ═══════════════════════ EL RESIDENCIAL ═══════════════════════ -->
<section class="banda" id="residencial">
  <div class="contenedor">
    <div class="alterna">
      <div class="alterna-texto" data-surge>
        <div class="seccion-tit" style="margin-bottom:0">
          <span class="lema">01 — El residencial</span>
          <h2>Un lugar pensado para vivir, no solo para llegar a dormir.</h2>
          <p>Calles arboladas, áreas verdes con mantenimiento propio y un acceso controlado
             las veinticuatro horas. Cada vivienda pertenece a una fase y una calle, con su
             cuota, su coeficiente y su historial claramente identificados.</p>
        </div>
        <ul class="lista-limpia puntos-lista">
          <li><span class="marca"><?= ico('check', 14) ?></span><span><b>Acceso controlado.</b> Todo visitante queda registrado con nombre, documento y placa.</span></li>
          <li><span class="marca"><?= ico('check', 14) ?></span><span><b>Áreas comunes reservables.</b> Se apartan desde el teléfono y se cobran en el estado de cuenta.</span></li>
          <li><span class="marca"><?= ico('check', 14) ?></span><span><b>Cuentas transparentes.</b> Cada residente ve lo que debe, lo que pagó y por qué.</span></li>
        </ul>
      </div>
      <div class="lamina cotas" data-surge="1">
        <picture>
          <source type="image/webp" srcset="<?= e($foto('residencial-900.webp')) ?> 900w, <?= e($foto('residencial.webp')) ?> 1600w" sizes="(max-width: 1180px) 100vw, 55vw">
          <img src="<?= e($foto('residencial.jpg')) ?>" alt="Vivienda del residencial con jardín y piscina" width="1600" height="1067" loading="lazy" decoding="async">
        </picture>
        <div class="lamina-sobre">
          <div class="fila-entre">
            <div>
              <div class="mayus">Fase A · Calle Los Cipreses</div>
              <div style="font-weight:600;margin-top:2px">24 viviendas · 100 % ocupadas</div>
            </div>
            <span class="chip ok"><?= ico('check', 13) ?> Al día</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════ AMENIDADES ═══════════════════════ -->
<section class="banda papel" id="amenidades">
  <div class="contenedor">
    <div class="seccion-tit" data-surge>
      <span class="lema">02 — Amenidades</span>
      <h2>Espacios comunes que se reservan en dos toques.</h2>
      <p>Piscina, casa club, salón social y áreas verdes. Cada área tiene su horario, su
         cupo y sus reglas; el sistema evita choques de horario y bloquea la reserva si la
         vivienda tiene saldo pendiente.</p>
    </div>

    <div class="mosaico" data-surge="1">
      <figure class="m-alto">
        <picture>
          <source type="image/webp" srcset="<?= e($foto('piscina-640.webp')) ?> 640w, <?= e($foto('piscina.webp')) ?> 1000w" sizes="(max-width: 1180px) 100vw, 42vw">
          <img src="<?= e($foto('piscina.jpg')) ?>" alt="Piscina del residencial rodeada de palmeras" width="1000" height="750" loading="lazy" decoding="async">
        </picture>
        <figcaption>Piscina y solárium</figcaption>
      </figure>
      <figure class="m-ancho">
        <picture>
          <source type="image/webp" srcset="<?= e($foto('aerea-760.webp')) ?> 760w, <?= e($foto('aerea.webp')) ?> 1280w" sizes="(max-width: 1180px) 100vw, 56vw">
          <img src="<?= e($foto('aerea.jpg')) ?>" alt="Vista aérea de las áreas verdes y la piscina" width="1280" height="720" loading="lazy" decoding="async">
        </picture>
        <figcaption>Áreas verdes</figcaption>
      </figure>
      <figure class="m-medio">
        <picture>
          <source type="image/webp" srcset="<?= e($foto('salon-600.webp')) ?> 600w, <?= e($foto('salon.webp')) ?> 900w" sizes="(max-width: 1180px) 100vw, 30vw">
          <img src="<?= e($foto('salon.jpg')) ?>" alt="Salón social del residencial" width="900" height="600" loading="lazy" decoding="async">
        </picture>
        <figcaption>Salón social</figcaption>
      </figure>
      <figure class="m-chico">
        <picture>
          <source type="image/webp" srcset="<?= e($foto('terraza-860.webp')) ?> 860w, <?= e($foto('terraza.webp')) ?> 1500w" sizes="(max-width: 1180px) 100vw, 24vw">
          <img src="<?= e($foto('terraza.jpg')) ?>" alt="Terraza común al atardecer" width="1500" height="1000" loading="lazy" decoding="async">
        </picture>
        <figcaption>Terraza</figcaption>
      </figure>
    </div>

    <?php if ($amenidades !== []): ?>
      <div class="modulos mt-3" data-surge="2">
        <?php foreach (array_slice($amenidades, 0, 6) as $i => $am): ?>
          <article class="modulo">
            <span class="ico"><?= ico((string) ($am['icono'] ?? 'estrella'), 18) ?></span>
            <span class="n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <h3><?= e($am['titulo']) ?></h3>
            <p><?= e(recortar((string) ($am['detalle'] ?? ''), 130)) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════════════ ADMINISTRACIÓN ═══════════════════════ -->
<section class="banda tinta" id="administracion">
  <div class="contenedor">
    <div class="seccion-tit" data-surge>
      <span class="lema">03 — Administración</span>
      <h2>Así se administra este residencial.</h2>
      <p>Cobranza, morosidad, garita, reservas y comunicación funcionan sobre un mismo
         sistema. Nada se lleva en cuadernos ni en hojas sueltas: cada movimiento queda
         registrado, con fecha, responsable y respaldo.</p>
    </div>

    <div class="alterna invertida" style="margin-bottom:clamp(40px,6vw,80px)">
      <div class="alterna-texto" data-surge>
        <h3 style="font-size:clamp(1.35rem,1.1rem + 1vw,1.8rem);margin-bottom:14px">Cuotas y cobranza sin perseguir a nadie</h3>
        <p>Los cargos del mes se generan solos. El residente recibe su recordatorio cinco
           días antes, reporta el pago desde el teléfono con la foto de su boleta y la
           administración lo aprueba en un clic. El recibo en PDF sale firmado y con código
           de verificación.</p>
        <ul class="lista-limpia puntos-lista">
          <li><span class="marca"><?= ico('check', 14) ?></span><span>Mora automática, fija o por porcentaje mensual.</span></li>
          <li><span class="marca"><?= ico('check', 14) ?></span><span>Pagos parciales y saldo a favor aplicados al cargo más antiguo.</span></li>
          <li><span class="marca"><?= ico('check', 14) ?></span><span>Estado de cuenta y constancia de solvencia en PDF.</span></li>
        </ul>
      </div>

      <!-- Segunda pieza de interfaz real: reporte de morosidad. -->
      <div class="tarjeta tarjeta-flota cotas" data-surge="1">
        <div class="tarjeta-cab">
          <div>
            <span class="mayus">Reporte</span>
            <h3 style="margin-top:2px">Morosidad por antigüedad</h3>
          </div>
          <span class="chip info">Agosto</span>
        </div>
        <div class="tarjeta-cuerpo">
          <div class="rejilla-4 mb-3">
            <div class="kpi ok"><span class="kpi-et">Al día</span><span class="kpi-valor">48</span></div>
            <div class="kpi aviso"><span class="kpi-et">1–30 días</span><span class="kpi-valor">07</span></div>
            <div class="kpi oro"><span class="kpi-et">31–90</span><span class="kpi-valor">04</span></div>
            <div class="kpi grave"><span class="kpi-et">+90 días</span><span class="kpi-valor">01</span></div>
          </div>
          <table class="tabla">
            <thead><tr><th>Vivienda</th><th>Antigüedad</th><th class="d">Saldo</th></tr></thead>
            <tbody>
              <tr><td class="fuerte">B-14</td><td><span class="chip temprana">22 días</span></td><td class="d num">Q 1,066.00</td></tr>
              <tr><td class="fuerte">A-07</td><td><span class="chip media">63 días</span></td><td class="d num">Q 2,318.40</td></tr>
              <tr><td class="fuerte">C-03</td><td><span class="chip alta">118 días</span></td><td class="d num">Q 4,905.75</td></tr>
            </tbody>
          </table>
          <p class="ayuda mt-2">A los 60 días se emite carta de cobro; a los 90, restricción de servicios visible en garita.</p>
        </div>
      </div>
    </div>

    <div class="alterna">
      <div class="alterna-texto" data-surge>
        <h3 style="font-size:clamp(1.35rem,1.1rem + 1vw,1.8rem);margin-bottom:14px">Garita: dos segundos por visita</h3>
        <p>El residente preinscribe la visita y el sistema genera un código QR firmado, con
           vigencia y un solo uso. El guardia lo escanea, la pluma sube y el residente recibe
           el aviso de que su visita llegó. Sin llamadas, sin listas en papel.</p>
        <ul class="lista-limpia puntos-lista">
          <li><span class="marca"><?= ico('check', 14) ?></span><span>Código de seis dígitos como alternativa al QR.</span></li>
          <li><span class="marca"><?= ico('check', 14) ?></span><span>Bitácora de turno con relevo firmado y botón de pánico.</span></li>
          <li><span class="marca"><?= ico('check', 14) ?></span><span>Funciona sin conexión y sincroniza al recuperar la señal.</span></li>
        </ul>
      </div>
      <div class="lamina cotas" data-surge="1">
        <picture>
          <source type="image/webp" srcset="<?= e($foto('seguridad-900.webp')) ?> 900w, <?= e($foto('seguridad.webp')) ?> 1600w" sizes="(max-width: 1180px) 100vw, 55vw">
          <img src="<?= e($foto('seguridad.jpg')) ?>" alt="Acceso al residencial iluminado al anochecer" width="1600" height="1120" loading="lazy" decoding="async">
        </picture>
        <div class="lamina-sobre">
          <div class="fila" style="gap:14px">
            <span class="avatar" style="background:var(--ok)"><?= ico('check', 18) ?></span>
            <div class="crecer">
              <div class="fuerte">Visita autorizada · Casa A-12</div>
              <div class="meta">Código RP1 verificado · un solo uso</div>
            </div>
            <span class="placa">P 429 BQR</span>
          </div>
        </div>
      </div>
    </div>

    <div class="modulos mt-3" data-surge="2" style="border-color:color-mix(in srgb,#fff 12%,transparent);background:color-mix(in srgb,#fff 12%,transparent)">
      <?php
      $mods = [
        ['casa',       'Residentes y viviendas',  'Propietarios, inquilinos, vehículos, mascotas y personal doméstico autorizado.'],
        ['calendario', 'Reservas de áreas',       'Calendario visual, cupo, depósito y cobro integrado al estado de cuenta.'],
        ['megafono',   'Avisos y asambleas',      'Comunicados por fase o calle, confirmación de lectura y votaciones con acta.'],
        ['grafica',    'Informes y presupuesto',  'Ingresos, egresos, flujo de caja y presupuesto contra ejecutado, exportables.'],
      ];
      foreach ($mods as $i => [$ic, $t, $d]): ?>
        <article class="modulo" style="background:transparent">
          <span class="ico" style="background:color-mix(in srgb,#fff 12%,transparent);color:var(--arcilla-3)"><?= ico($ic, 18) ?></span>
          <h3 style="color:#fff"><?= e($t) ?></h3>
          <p style="color:color-mix(in srgb,#fff 70%,transparent)"><?= e($d) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════ GALERÍA ═══════════════════════ -->
<?php if ($galeria !== []): ?>
<section class="banda" id="galeria">
  <div class="contenedor">
    <div class="seccion-tit" data-surge>
      <span class="lema">Galería</span>
      <h2>El residencial por dentro.</h2>
    </div>
    <div class="galeria" data-surge="1">
      <?php foreach ($galeria as $g): ?>
        <figure style="margin:0" class="lamina">
          <img src="<?= e(subida($g['archivo'], 'galeria')) ?>" alt="<?= e($g['titulo'] ?? '') ?>"
               width="600" height="450" loading="lazy" decoding="async">
          <?php if (!empty($g['titulo'])): ?>
            <figcaption class="mayus" style="padding:10px 2px 0"><?= e($g['titulo']) ?></figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════ LA ADMINISTRACIÓN ═══════════════════════ -->
<section class="banda papel" id="administracion-equipo">
  <div class="contenedor">
    <div class="seccion-tit" data-surge>
      <span class="lema">04 — Quién responde</span>
      <h2>Detrás del sistema hay personas con nombre.</h2>
      <p>Toda solicitud tiene un responsable y un tiempo de respuesta. Estos son los
         contactos del residencial; el resto de gestiones se atiende desde el portal.</p>
    </div>
    <div class="voces" data-surge="1">
      <?php
      $equipo = [
        ['persona-1', 'Administración', 'Cuotas, pagos, constancias y reservas de áreas comunes.', 'Lunes a viernes, 8:00 a 17:00'],
        ['persona-4', 'Junta directiva', 'Presupuesto anual, asambleas, reglamento y obras del residencial.', 'Sesión mensual, acta publicada'],
        ['persona-3', 'Seguridad', 'Garita, control de visitas, rondas y atención de emergencias.', 'Turno permanente, 24 horas'],
      ];
      foreach ($equipo as [$img, $cargo, $texto, $horario]): ?>
        <article class="voz">
          <span class="mayus"><?= e($cargo) ?></span>
          <blockquote><?= e($texto) ?></blockquote>
          <figure>
            <img src="<?= e($foto($img . '-200.jpg')) ?>" alt="" width="44" height="44" loading="lazy" decoding="async">
            <div>
              <div class="quien"><?= e($cargo) ?> del residencial</div>
              <div class="donde"><?= e($horario) ?></div>
            </div>
          </figure>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════ UBICACIÓN Y EVENTOS ═══════════════════════ -->
<section class="banda" id="ubicacion">
  <div class="contenedor">
    <div class="alterna invertida">
      <div class="alterna-texto" data-surge>
        <div class="seccion-tit" style="margin-bottom:22px">
          <span class="lema">05 — Ubicación</span>
          <h2>Cómo llegar.</h2>
        </div>
        <table class="tabla" style="max-width:440px">
          <tbody>
            <?php if (Ajustes::get('direccion', '') !== ''): ?>
              <tr><td class="texto-3" style="width:34%">Dirección</td><td class="fuerte"><?= e(Ajustes::get('direccion')) ?></td></tr>
            <?php endif; ?>
            <tr><td class="texto-3">Ciudad</td><td><?= e($ciudad) ?></td></tr>
            <?php if (Ajustes::get('telefono', '') !== ''): ?>
              <tr><td class="texto-3">Garita</td><td><a href="tel:<?= e(preg_replace('/\s+/', '', Ajustes::get('telefono'))) ?>"><?= e(Ajustes::get('telefono')) ?></a></td></tr>
            <?php endif; ?>
            <?php if (Ajustes::get('correo', '') !== ''): ?>
              <tr><td class="texto-3">Correo</td><td><a href="mailto:<?= e(Ajustes::get('correo')) ?>"><?= e(Ajustes::get('correo')) ?></a></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <div class="fila-fin" style="justify-content:flex-start;margin-top:24px">
          <a class="btn btn-oro" href="<?= e(url('/contacto')) ?>"><?= ico('enviar', 17) ?> Escribir a la administración</a>
          <a class="btn btn-claro" href="<?= e(url('/reglamento')) ?>"><?= ico('libro', 17) ?> Reglamento</a>
        </div>

        <?php if ($eventos !== []): ?>
          <div class="mt-3">
            <span class="mayus">Próximas actividades</span>
            <ul class="lista-limpia mt-1">
              <?php foreach ($eventos as $ev): ?>
                <li class="item-lista">
                  <span class="chip oro"><?= e(date('d/m', (int) strtotime((string) $ev['inicio']))) ?></span>
                  <div class="crecer">
                    <b><?= e($ev['titulo']) ?></b>
                    <div class="meta"><?= e(hora(date('H:i:s', (int) strtotime((string) $ev['inicio'])))) ?><?= !empty($ev['lugar']) ? ' · ' . e($ev['lugar']) : '' ?></div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>

      <div class="lamina cotas" data-surge="1">
        <picture>
          <source type="image/webp" srcset="<?= e($foto('casa-club-900.webp')) ?> 900w, <?= e($foto('casa-club.webp')) ?> 1600w" sizes="(max-width: 1180px) 100vw, 55vw">
          <img src="<?= e($foto('casa-club.jpg')) ?>" alt="Casa club del residencial" width="1600" height="1067" loading="lazy" decoding="async">
        </picture>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════ CIERRE ═══════════════════════ -->
<section class="cierre">
  <picture>
    <source type="image/webp" srcset="<?= e($foto('interior-800.webp')) ?> 800w, <?= e($foto('interior.webp')) ?> 1400w" sizes="100vw">
    <img src="<?= e($foto('interior.jpg')) ?>" alt="" width="1400" height="933" loading="lazy" decoding="async">
  </picture>
  <div class="contenedor">
    <div class="interior">
      <span class="lema" style="color:var(--arcilla-3);display:inline-flex;align-items:center;gap:10px;margin-bottom:16px">Portal del residente</span>
      <h2>Su estado de cuenta, sus visitas y sus reservas, en el teléfono.</h2>
      <p>Consulte lo que debe, reporte un pago con la foto de su boleta, preinscriba a sus
         visitas y aparte el salón sin llamar a nadie. Se instala como aplicación desde el
         propio navegador.</p>
      <div class="fila-fin" style="justify-content:flex-start;margin-top:30px">
        <a class="btn btn-oro btn-lg" href="<?= e(url('/acceso')) ?>"><?= ico('entrar', 18) ?> Entrar al portal</a>
        <a class="btn btn-claro btn-lg" href="<?= e(url('/verificar')) ?>"
           style="background:color-mix(in srgb,#fff 12%,transparent);border-color:color-mix(in srgb,#fff 32%,transparent);color:#fff">
          <?= ico('qr', 18) ?> Verificar un recibo
        </a>
      </div>
    </div>
  </div>
</section>
