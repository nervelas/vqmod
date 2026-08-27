<?php
use App\Core\Ajustes;
use App\Core\Auth;

$portada = Ajustes::get('portada', '');
$iconosAmenidad = ['brillo','gota','arbol','rayo','escudo','salvavidas','maletin','estrella','casa','puerta','calendario','llave'];
?>
<section class="hero">
  <?php if ($portada !== '' && is_file(RUTA_BASE . '/uploads/galeria/' . $portada)): ?>
    <img class="hero-foto" src="<?= e(subida($portada, 'galeria')) ?>" alt="" loading="eager" fetchpriority="high">
  <?php endif; ?>
  <div class="contenedor">
    <div class="lema"><?= e(Ajustes::get('lema', 'Residencial privado')) ?></div>
    <h1><?= e(Ajustes::get('titular', Ajustes::get('nombre', 'Un residencial hecho para vivir tranquilo'))) ?></h1>
    <p><?= e(Ajustes::get('descripcion', 'Seguridad las 24 horas, áreas comunes cuidadas y una administración transparente al alcance de su teléfono.')) ?></p>
    <div class="acciones">
      <a class="btn btn-oro btn-lg" href="<?= e(url(Auth::invitado() ? '/acceso' : '/portal')) ?>">
        <?= ico('entrar', 19) ?> <?= Auth::invitado() ? 'Entrar al portal de residentes' : 'Ir a mi portal' ?>
      </a>
      <a class="btn btn-fantasma btn-lg" style="color:#E9EEE9;border-color:rgba(255,255,255,.28)" href="<?= e(url('/contacto')) ?>">
        <?= ico('chat', 19) ?> Contactar a la administración
      </a>
    </div>
  </div>
</section>

<section class="seccion" id="amenidades">
  <div class="contenedor">
    <div class="seccion-tit">
      <div class="lema">La vida aquí</div>
      <h2>Amenidades del residencial</h2>
      <p>Espacios pensados para la convivencia, el descanso y la seguridad de cada familia.</p>
    </div>
    <?php if ($amenidades === []): ?>
      <div class="rejilla rejilla-4">
        <?php foreach ([
          ['escudo', 'Seguridad 24/7', 'Garita con control de accesos, cámaras y rondas permanentes.'],
          ['arbol',  'Áreas verdes',   'Jardines y senderos con mantenimiento constante todo el año.'],
          ['salvavidas', 'Piscina y club', 'Espacios recreativos para toda la familia, disponibles por reserva.'],
          ['brillo', 'Salón de eventos', 'Ideal para celebraciones; se reserva desde el portal del residente.'],
        ] as [$ic, $t, $d]): ?>
          <article class="amenidad">
            <div class="aro"><?= ico($ic, 26) ?></div>
            <h3><?= e($t) ?></h3>
            <p><?= e($d) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="rejilla rejilla-4">
        <?php foreach ($amenidades as $i => $a): ?>
          <article class="amenidad">
            <div class="aro"><?= ico($a['icono'] ?: $iconosAmenidad[$i % count($iconosAmenidad)], 26) ?></div>
            <h3><?= e($a['titulo']) ?></h3>
            <p><?= e($a['detalle']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($galeria !== []): ?>
<section class="seccion alterna" id="galeria">
  <div class="contenedor">
    <div class="seccion-tit">
      <div class="lema">Nuestro entorno</div>
      <h2>Galería</h2>
    </div>
    <div class="galeria">
      <?php foreach ($galeria as $g): ?>
        <figure>
          <img src="<?= e(subida($g['archivo'], 'galeria')) ?>" alt="<?= e($g['titulo'] ?? '') ?>" loading="lazy">
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="seccion" id="residentes">
  <div class="contenedor">
    <div class="rejilla rejilla-2" style="align-items:center;gap:44px">
      <div>
        <div class="lema" style="color:var(--acento-3);text-transform:uppercase;letter-spacing:.18em;font-size:.72rem;font-weight:700;margin-bottom:10px">Portal del residente</div>
        <h2>Su residencial, en su teléfono</h2>
        <p class="texto-2">Consulte su estado de cuenta, pague su cuota, autorice visitas con código QR, reserve las áreas
          comunes y reciba los avisos de la administración. Todo desde el mismo lugar, sin llamadas ni filas.</p>
        <ul class="lista-limpia mt-2">
          <?php foreach ([
            ['billetera', 'Estado de cuenta al día, con recibo digital verificable.'],
            ['qr', 'Sus visitas entran en segundos con un código QR de un solo uso.'],
            ['calendario', 'Reserva del salón, la piscina o la churrasquera en línea.'],
            ['megafono', 'Avisos, asambleas y votaciones oficiales del residencial.'],
          ] as [$ic, $t]): ?>
            <li class="item-lista" style="border:0;padding:7px 0">
              <span style="color:var(--acento-3)"><?= ico($ic, 20) ?></span>
              <span class="crecer"><?= e($t) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <a class="btn btn-oro btn-lg mt-2" href="<?= e(url('/acceso')) ?>"><?= ico('entrar', 19) ?> Ingresar al portal</a>
      </div>
      <div class="tarjeta" style="background:linear-gradient(150deg,var(--marca),var(--marca-3));border:0">
        <div class="tarjeta-cuerpo" style="color:#E9EEE9">
          <div class="mayus" style="color:rgba(233,238,233,.6)">Ejemplo de estado de cuenta</div>
          <div style="font-family:var(--f-titulo);font-size:2.4rem;color:var(--acento-2);margin:8px 0 2px">Q0.00</div>
          <p style="color:rgba(233,238,233,.7);font-size:.9rem;margin-bottom:18px">Vivienda solvente · sin saldo pendiente</p>
          <div style="display:flex;flex-direction:column;gap:9px">
            <?php foreach ([['Cuota de mantenimiento','Pagada'],['Fondo de reserva','Pagada'],['Reserva del salón','Confirmada']] as [$c, $s]): ?>
              <div class="fila-entre" style="background:rgba(255,255,255,.06);padding:11px 14px;border-radius:12px">
                <span style="font-size:.9rem"><?= e($c) ?></span>
                <span class="chip ok"><?= e($s) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($eventos !== []): ?>
<section class="seccion alterna">
  <div class="contenedor">
    <div class="seccion-tit"><div class="lema">Agenda</div><h2>Próximas actividades</h2></div>
    <div class="rejilla rejilla-3">
      <?php foreach ($eventos as $ev): ?>
        <article class="tarjeta tarjeta-flota">
          <div class="tarjeta-cuerpo">
            <span class="chip oro"><?= e(fecha((string) $ev['inicio'])) ?></span>
            <h3 class="mt-1" style="font-family:var(--f-texto);font-size:1.05rem"><?= e($ev['titulo']) ?></h3>
            <p class="texto-3" style="font-size:.88rem;margin:0"><?= e(recortar((string) $ev['detalle'], 120)) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="seccion" id="ubicacion">
  <div class="contenedor">
    <div class="rejilla rejilla-2" style="gap:34px;align-items:center">
      <div>
        <div class="lema" style="color:var(--acento-3);text-transform:uppercase;letter-spacing:.18em;font-size:.72rem;font-weight:700;margin-bottom:10px">Ubicación</div>
        <h2>¿Dónde estamos?</h2>
        <p class="texto-2"><?= e(Ajustes::get('direccion', 'Consulte con la administración la dirección exacta del residencial.')) ?></p>
        <div class="fila envolver mt-2">
          <?php if (Ajustes::get('telefono', '') !== ''): ?>
            <a class="btn btn-claro" href="tel:<?= e(preg_replace('/\D+/', '', Ajustes::get('telefono'))) ?>"><?= ico('telefono', 18) ?> <?= e(Ajustes::get('telefono')) ?></a>
          <?php endif; ?>
          <?php if (Ajustes::get('whatsapp', '') !== ''): ?>
            <a class="btn btn-claro" target="_blank" rel="noopener" href="<?= e(whatsapp(Ajustes::get('whatsapp'), 'Buen día, deseo información del residencial.')) ?>"><?= ico('chat', 18) ?> WhatsApp</a>
          <?php endif; ?>
          <a class="btn btn-oro" href="<?= e(url('/contacto')) ?>"><?= ico('correo', 18) ?> Escribirnos</a>
        </div>
      </div>
      <div class="tarjeta">
        <div class="tarjeta-cuerpo">
          <h3 style="font-family:var(--f-texto);font-size:1.05rem">Horarios de la administración</h3>
          <table class="tabla">
            <tbody>
              <tr><td>Lunes a viernes</td><td class="d fuerte"><?= e(Ajustes::get('horario_semana', '8:00 a 17:00')) ?></td></tr>
              <tr><td>Sábados</td><td class="d fuerte"><?= e(Ajustes::get('horario_sabado', '8:00 a 12:00')) ?></td></tr>
              <tr><td>Garita</td><td class="d fuerte">24 horas</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>
