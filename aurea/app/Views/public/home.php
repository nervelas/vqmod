<?php
use Aurea\Core\Brand;
use Aurea\Core\Util;
$biz = (string)setting('business_name', '');
$heroImg = (string)setting('hero_image', '');
$prof = \Aurea\Services\PresetService::all()[(string)setting('profession', 'otro')]['label'] ?? '';
$headline = (string)setting('hero_headline', '');
$byCat = [];
foreach ($services as $s) { $byCat[(string)($s['cat_name'] ?? '')][] = $s; }
$sn = 0;
$wa = Brand::waUrl(__('Hola, quisiera agendar una %s.', mb_strtolower(term('appt'))));
?>
<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow reveal"><?= e($prof !== '' && $prof !== 'Otro' ? $prof : $biz) ?></span>
    <h1 class="reveal"><?= e($headline) ?></h1>
    <p class="lead reveal"><?= e((string)setting('hero_subtitle', '')) ?></p>
    <div class="actions reveal">
      <a class="btn btn-gold" href="<?= e(url('/reservar')) ?>"><?= e(__('Reservar %s', mb_strtolower(term('appt')))) ?></a>
      <a class="btn btn-line" href="#servicios"><?= e(__('Ver servicios')) ?></a>
    </div>
    <div class="meta">
      <span class="rule" aria-hidden="true"></span>
      <span><b><?= e(__('60 segundos')) ?></b> · <?= e(__('desde tu celular')) ?></span>
      <span><b><?= e(__('WhatsApp')) ?></b> · <?= e(__('confirmación inmediata')) ?></span>
      <?php if ($stats['n'] > 0): ?><span><b>★ <?= e(number_format($stats['avg'], 1)) ?></b> · <?= e(__('%d reseñas', $stats['n'])) ?></span><?php endif; ?>
    </div>
  </div>
  <div class="hero-art grain" aria-hidden="<?= $heroImg !== '' ? 'false' : 'true' ?>">
    <?php if ($heroImg !== ''): ?>
      <img class="photo" src="<?= e(url('uploads/' . rawurlencode($heroImg))) ?>" alt="<?= e($biz) ?>" fetchpriority="high">
    <?php else: include __DIR__ . '/../partials/hero_art.php'; endif; ?>
    <div class="caption"><span><?= e($biz) ?></span></div>
  </div>
</section>

<section class="block" id="servicios">
  <div class="wrap">
    <div class="sec-head">
      <span class="eyebrow"><?= e(__('Servicios')) ?></span>
      <h2 class="reveal"><?= e(__('Elige cómo podemos ayudarte')) ?></h2>
      <span class="rule" aria-hidden="true"></span>
    </div>
    <?php if (!$services): ?>
      <div class="empty"><h3><?= e(__('Pronto publicaremos nuestros servicios')) ?></h3><p><?= e(__('Mientras tanto, escríbenos y con gusto te atendemos.')) ?></p></div>
    <?php endif; ?>
    <?php foreach ($byCat as $cat => $list): ?>
      <?php if ($cat !== '' && count($byCat) > 1): ?><h3 class="cat-title"><?= e($cat) ?></h3><?php endif; ?>
      <ul class="svc-list">
        <?php foreach ($list as $s): $sn++; ?>
          <li class="reveal">
            <span class="n"><?= sprintf('%02d', $sn) ?></span>
            <div>
              <h3><?= e($s['name']) ?></h3>
              <?php if ($s['description']): ?><p><?= e($s['description']) ?></p><?php endif; ?>
              <div class="tail">
                <span><?= e((int)$s['duration_min']) ?> min</span>
                <span class="badge"><?= e(['presencial' => __('Presencial'), 'virtual' => __('Virtual'), 'domicilio' => __('A domicilio')][$s['modality']] ?? '') ?></span>
                <?php if ((int)$s['capacity'] > 1): ?><span class="badge badge-gold"><?= e(__('Sesión grupal')) ?></span><?php endif; ?>
                <?php if (setting('show_prices', '1') === '1' && (float)$s['price'] > 0): ?><span class="price"><?= e(money($s['price'])) ?></span><?php elseif ((float)$s['price'] <= 0 && setting('show_prices', '1') === '1'): ?><span class="price"><?= e(__('Sin costo')) ?></span><?php endif; ?>
                <a href="<?= e(url('/reservar?servicio=' . (int)$s['id'])) ?>"><?= e(__('Reservar')) ?> →</a>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </div>
</section>

<section class="block dark grain">
  <div class="wrap">
    <div class="sec-head"><span class="eyebrow"><?= e(__('Cómo funciona')) ?></span><h2><?= e(__('Reservar toma menos de un minuto')) ?></h2></div>
    <div class="steps3">
      <div class="s reveal"><span class="rule" aria-hidden="true"></span><div class="num">01</div><h3><?= e(__('Elige')) ?></h3><p><?= e(__('Selecciona el servicio y, si lo deseas, tu %s de preferencia.', mb_strtolower(term('professional')))) ?></p></div>
      <div class="s reveal"><span class="rule" aria-hidden="true"></span><div class="num">02</div><h3><?= e(__('Agenda')) ?></h3><p><?= e(__('Ve los horarios disponibles en tiempo real y escoge el que mejor te quede.')) ?></p></div>
      <div class="s reveal"><span class="rule" aria-hidden="true"></span><div class="num">03</div><h3><?= e(__('Recibe tu confirmación')) ?></h3><p><?= e(__('Te enviamos los detalles y recordatorios. Cambiar o cancelar es cuestión de un toque.')) ?></p></div>
    </div>
  </div>
</section>

<?php if (count($profs) > 0): ?>
<section class="block" id="equipo">
  <div class="wrap">
    <div class="sec-head"><span class="eyebrow"><?= e(term('professionals')) ?></span><h2 class="reveal"><?= e(__('Conoce a quienes te atenderán')) ?></h2><span class="rule" aria-hidden="true"></span></div>
    <div class="team-grid">
      <?php foreach (array_slice($profs, 0, 6) as $p): ?>
        <a class="person reveal" href="<?= e(url('/profesional/' . $p['slug'])) ?>">
          <div class="ph"><?php if ($p['photo']): ?><img src="<?= e(url('uploads/' . rawurlencode($p['photo']))) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="400" height="500"><?php else: ?><span class="init" aria-hidden="true"><?= e(name_initial($p['name'])) ?></span><?php endif; ?></div>
          <div class="tx"><h3><?= e($p['name']) ?></h3><span class="t"><?= e($p['title']) ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (count($profs) > 6): ?><p style="margin-top:28px"><a href="<?= e(url('/equipo')) ?>"><?= e(__('Ver todo el equipo')) ?> →</a></p><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($reviews): ?>
<section class="block" style="padding-top:0">
  <div class="wrap">
    <div class="sec-head"><span class="eyebrow"><?= e(__('Opiniones')) ?></span><h2><?= e(__('Lo que dicen quienes nos visitan')) ?></h2></div>
    <div class="reviews">
      <?php foreach ($reviews as $r): ?>
        <figure class="review reveal" style="margin:0">
          <div class="stars" aria-label="<?= e(__('%d de 5 estrellas', (int)$r['rating'])) ?>"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
          <blockquote>“<?= e($r['comment']) ?>”</blockquote>
          <cite><?= e(explode(' ', trim($r['client_name']))[0]) ?> · <?= e($r['prof_name']) ?></cite>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="block dark grain" id="contacto">
  <div class="wrap contact-grid">
    <div>
      <span class="eyebrow"><?= e(__('Contacto')) ?></span>
      <h2 style="font-size:clamp(2rem,4.4vw,3.2rem);margin-top:12px"><?= e(__('Te esperamos')) ?></h2>
      <p style="color:var(--on-dark-muted);max-width:30em"><?= e(__('Agenda en línea o escríbenos; respondemos con gusto.')) ?></p>
      <div class="actions" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:22px">
        <a class="btn btn-gold" href="<?= e(url('/reservar')) ?>"><?= e(__('Reservar %s', mb_strtolower(term('appt')))) ?></a>
        <?php if ($wa !== ''): ?><a class="btn btn-line" style="color:var(--on-dark)" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(__('Escribir por WhatsApp')) ?></a><?php endif; ?>
      </div>
    </div>
    <dl>
      <?php if ($locs): foreach ($locs as $l): ?>
        <dt><?= e($l['name']) ?></dt>
        <dd><?= e(trim($l['address'] . ($l['city'] ? ', ' . $l['city'] : ''))) ?><?php if (Util::safeUrl($l['map_url']) !== ''): ?> · <a href="<?= e($l['map_url']) ?>" target="_blank" rel="noopener"><?= e(__('Ver mapa')) ?></a><?php endif; ?></dd>
      <?php endforeach; elseif (setting('business_address', '') !== ''): ?>
        <dt><?= e(__('Dirección')) ?></dt>
        <dd><?= e((string)setting('business_address')) ?><?php if (Util::safeUrl((string)setting('business_map_url', '')) !== ''): ?> · <a href="<?= e((string)setting('business_map_url')) ?>" target="_blank" rel="noopener"><?= e(__('Ver mapa')) ?></a><?php endif; ?></dd>
      <?php endif; ?>
      <?php if (setting('business_phone', '') !== ''): ?><dt><?= e(__('Teléfono')) ?></dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)setting('business_phone'))) ?>"><?= e((string)setting('business_phone')) ?></a></dd><?php endif; ?>
      <?php if (setting('business_email', '') !== ''): ?><dt><?= e(__('Correo')) ?></dt><dd><a href="mailto:<?= e((string)setting('business_email')) ?>"><?= e((string)setting('business_email')) ?></a></dd><?php endif; ?>
      <?php $dn = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado']; $rows = []; foreach ($hours as $wd => $bl) { if ($bl) { $rows[] = ucfirst($dn[$wd]) . ': ' . implode(' y ', array_map(static fn($b) => sprintf('%d:%02d–%d:%02d', intdiv($b[0], 60), $b[0] % 60, intdiv($b[1], 60), $b[1] % 60), $bl)); } } ?>
      <?php if ($rows): ?><dt><?= e(__('Horario de atención')) ?></dt><dd><?= implode('<br>', array_map('e', $rows)) ?></dd><?php endif; ?>
    </dl>
  </div>
</section>
