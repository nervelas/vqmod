<div class="wrap">
  <div class="page-head"><span class="eyebrow"><?= e($p['title']) ?></span><h1><?= e($p['name']) ?></h1><span class="rule" aria-hidden="true"></span></div>
  <div class="profile" style="margin-bottom:90px">
    <div class="ph"><?php if ($p['photo']): ?><img src="<?= e(url('uploads/' . rawurlencode($p['photo']))) ?>" alt="<?= e($p['name']) ?>" width="640" height="800"><?php else: ?><span class="init" aria-hidden="true"><?= e(name_initial($p['name'])) ?></span><?php endif; ?></div>
    <div>
      <?php if ($p['specialties']): ?><ul class="tags"><?php foreach (array_filter(array_map('trim', explode(',', $p['specialties']))) as $t): ?><li class="badge badge-gold"><?= e($t) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($p['bio']): ?><div class="prose"><p><?= e($p['bio']) ?></p></div><?php endif; ?>
      <a class="btn btn-gold" href="<?= e(url('/reservar?profesional=' . (int)$p['id'])) ?>"><?= e(__('Reservar con %s', $p['name'])) ?></a>
      <?php if ($svc): ?>
        <h2 style="font-size:1.7rem;margin-top:48px"><?= e(__('Servicios')) ?></h2>
        <ul class="svc-list"><?php foreach ($svc as $i => $s): $price = $s['price_override'] !== null ? $s['price_override'] : $s['price']; ?>
          <li><span class="n"><?= sprintf('%02d', $i + 1) ?></span><div><h3><?= e($s['name']) ?></h3><div class="tail"><span><?= e((int)$s['duration_min']) ?> min</span><?php if (setting('show_prices', '1') === '1'): ?><span class="price"><?= e((float)$price > 0 ? money($price) : __('Sin costo')) ?></span><?php endif; ?><a href="<?= e(url('/reservar?servicio=' . (int)$s['id'] . '&profesional=' . (int)$p['id'])) ?>"><?= e(__('Reservar')) ?> →</a></div></div></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($reviews): ?>
        <h2 style="font-size:1.7rem;margin-top:48px"><?= e(__('Opiniones')) ?></h2>
        <div class="reviews"><?php foreach ($reviews as $r): ?><figure class="review" style="margin:0"><div class="stars"><?= str_repeat('★', (int)$r['rating']) ?></div><?php if ($r['comment']): ?><blockquote>“<?= e($r['comment']) ?>”</blockquote><?php endif; ?><cite><?= e(explode(' ', trim($r['client_name']))[0]) ?></cite></figure><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
