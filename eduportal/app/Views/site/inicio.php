<?php
use App\Core\Settings;
$heroImg = (string)Settings::get('sitio_hero_imagen', '');
$mision = (string)Settings::get('sitio_mision', '') ?: (string)($paginas['mision']['contenido'] ?? '');
$vision = (string)Settings::get('sitio_vision', '') ?: (string)($paginas['vision']['contenido'] ?? '');
$mapa = (string)Settings::get('sitio_mapa', '');
?>
<section class="hero">
  <?php if ($heroImg !== ''): ?>
    <img class="hero__img" src="<?= e(archivo_url($heroImg)) ?>" alt="" loading="eager">
  <?php endif; ?>
  <div class="hero__int">
    <span class="hero__etq"><?= icono('estrella', 15) ?> Ciclo escolar <?= e(date('Y')) ?></span>
    <h1><?= e(Settings::get('sitio_hero_titulo', 'Educamos para la vida')) ?></h1>
    <p><?= e(Settings::get('sitio_hero_texto', '')) ?></p>
    <div class="hero__acciones">
      <?php if (Settings::bool('sitio_inscripcion', true)): ?>
        <a href="<?= e(url('inscripcion')) ?>" class="btn btn--oro">Inscribirse en línea</a>
      <?php endif; ?>
      <a href="<?= e(url('ingresar')) ?>" class="btn btn--linea" style="color:#fff;border-color:rgba(255,255,255,.45)">
        Portal de padres <?= icono('flecha', 17) ?>
      </a>
    </div>
  </div>
</section>

<section class="seccion" id="niveles">
  <div class="seccion__int">
    <div class="seccion__cab">
      <span class="etq">Formación</span>
      <h2>Niveles académicos</h2>
      <p>Un recorrido educativo continuo, cuidado en cada etapa del desarrollo de sus hijos.</p>
    </div>
    <div class="rejilla rejilla--3">
      <?php if ($niveles === []): ?>
        <p class="txt-3">Los niveles se configuran desde el panel administrativo.</p>
      <?php endif; ?>
      <?php foreach ($niveles as $n): ?>
        <?php
        $gradosNivel = array_values(array_filter($grados, static fn($g) => (int)$g['nivel_id'] === (int)$n['id']));
        ?>
        <article class="tarjeta-nivel" data-animar>
          <div class="n"><?= icono('libro', 22) ?></div>
          <h3><?= e($n['nombre']) ?></h3>
          <p class="sm txt-2">
            <?php if ($gradosNivel !== []): ?>
              <?= e(implode(' · ', array_map(static fn($g) => (string)$g['nombre'], array_slice($gradosNivel, 0, 8)))) ?>
            <?php else: ?>
              Grados por definir.
            <?php endif; ?>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="seccion seccion--marfil" id="nosotros">
  <div class="seccion__int">
    <div class="rejilla rejilla--2">
      <div class="tarjeta" data-animar>
        <h3>Nuestra misión</h3>
        <div class="txt-2"><?= $mision !== '' ? strip_tags($mision, '<p><br><strong><em><ul><ol><li>') : '<p>Formar personas íntegras, críticas y solidarias.</p>' ?></div>
      </div>
      <div class="tarjeta" data-animar>
        <h3>Nuestra visión</h3>
        <div class="txt-2"><?= $vision !== '' ? strip_tags($vision, '<p><br><strong><em><ul><ol><li>') : '<p>Ser el colegio de referencia por su excelencia académica y humana.</p>' ?></div>
      </div>
    </div>
  </div>
</section>

<?php if ($galeria !== []): ?>
<section class="seccion" id="galeria">
  <div class="seccion__int">
    <div class="seccion__cab">
      <span class="etq">Vida escolar</span>
      <h2>Galería</h2>
    </div>
    <div class="galeria">
      <?php foreach ($galeria as $g): ?>
        <figure data-animar>
          <img src="<?= e(archivo_url($g['archivo'])) ?>" alt="<?= e($g['titulo'] ?? '') ?>" loading="lazy">
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($eventos !== []): ?>
<section class="seccion seccion--marfil" id="calendario">
  <div class="seccion__int">
    <div class="seccion__cab">
      <span class="etq">Agenda</span>
      <h2>Próximas actividades</h2>
    </div>
    <div class="tarjeta">
      <div class="linea-tiempo">
        <?php foreach (array_slice($eventos, 0, 8) as $ev): ?>
          <div class="linea-tiempo__item">
            <div class="linea-tiempo__fecha">
              <div class="d"><?= e(date('d', strtotime((string)$ev['fecha_inicio']))) ?></div>
              <div class="m"><?= e(mb_substr(mes_nombre((int)date('n', strtotime((string)$ev['fecha_inicio']))), 0, 3)) ?></div>
            </div>
            <div>
              <strong><?= e($ev['titulo']) ?></strong>
              <div class="sm txt-2"><?= e(recorta($ev['descripcion'] ?? '', 140)) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="mt-4"><a href="<?= e(url('calendario')) ?>" class="btn btn--linea btn--sm">Ver calendario completo</a></p>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="seccion" id="contacto">
  <div class="seccion__int">
    <div class="seccion__cab">
      <span class="etq">Estamos para servirle</span>
      <h2>Contáctenos</h2>
      <p>Escríbanos y nuestro equipo de admisiones le responderá a la brevedad.</p>
    </div>
    <div class="split">
      <div class="tarjeta">
        <form method="post" action="<?= e(url('contacto')) ?>">
          <?= csrf_field() ?>
          <div class="fila">
            <div class="campo">
              <label for="c-nombre">Nombre completo</label>
              <input type="text" id="c-nombre" name="nombre" required maxlength="160">
            </div>
            <div class="campo">
              <label for="c-telefono">Teléfono</label>
              <input type="tel" id="c-telefono" name="telefono" maxlength="40" inputmode="tel">
            </div>
          </div>
          <div class="campo">
            <label for="c-email">Correo electrónico</label>
            <input type="email" id="c-email" name="email" maxlength="160">
          </div>
          <div class="campo">
            <label for="c-mensaje">Mensaje</label>
            <textarea id="c-mensaje" name="mensaje" required minlength="10" maxlength="2000"></textarea>
          </div>
          <div class="campo">
            <label for="c-captcha">Verificación: ¿cuánto es <?= e($captcha) ?>?</label>
            <input type="text" id="c-captcha" name="captcha" required inputmode="numeric" maxlength="4" style="max-width:130px">
          </div>
          <button type="submit" class="btn">Enviar mensaje</button>
        </form>
      </div>
      <div class="col">
        <div class="tarjeta">
          <h3>Datos de contacto</h3>
          <p class="sm txt-2 flex" style="align-items:flex-start">
            <?= icono('pin', 18) ?><span><?= e(Settings::get('colegio_direccion', 'Dirección por definir')) ?></span>
          </p>
          <p class="sm txt-2 flex"><?= icono('telefono', 18) ?><span><?= e(Settings::get('colegio_telefono', '')) ?></span></p>
          <p class="sm txt-2 flex"><?= icono('correo', 18) ?><span><?= e(Settings::get('colegio_email', '')) ?></span></p>
          <?php $wa = (string)Settings::get('colegio_whatsapp', ''); ?>
          <?php if ($wa !== ''): ?>
            <a class="btn btn--linea btn--sm mt-3" target="_blank" rel="noopener"
               href="<?= e(wa_link($wa, 'Hola, deseo información sobre el colegio.')) ?>">
              <?= icono('whatsapp', 17) ?> Escribir por WhatsApp
            </a>
          <?php endif; ?>
        </div>
        <?php if ($mapa !== ''): ?>
          <div class="tarjeta tarjeta--plana">
            <iframe src="<?= e($mapa) ?>" title="Ubicación del colegio" loading="lazy"
                    style="width:100%;height:280px;border:0" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
