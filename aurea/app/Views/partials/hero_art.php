<?php use Aurea\Core\Brand; ?>
<svg class="art" viewBox="0 0 600 800" preserveAspectRatio="xMidYMid slice" role="img" aria-label="<?= e(__('Ilustración decorativa')) ?>" focusable="false">
  <defs>
    <linearGradient id="gg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8C6A2B"/><stop offset=".5" stop-color="#B8924A"/><stop offset="1" stop-color="#E9D29A"/></linearGradient>
    <radialGradient id="bg" cx=".5" cy=".42" r=".75"><stop offset="0" stop-color="#2a2316"/><stop offset=".55" stop-color="#14110b"/><stop offset="1" stop-color="#0B0A08"/></radialGradient>
    <radialGradient id="glow" cx=".5" cy=".45" r=".5"><stop offset="0" stop-color="#E9D29A" stop-opacity=".22"/><stop offset="1" stop-color="#E9D29A" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="600" height="800" fill="url(#bg)"/>
  <rect width="600" height="800" fill="url(#glow)"/>
  <g stroke="url(#gg)" fill="none" stroke-width="1" opacity=".5">
    <?php for ($i = 0; $i < 25; $i++): $a = deg2rad(180 + $i * 7.5); ?>
      <line x1="300" y1="390" x2="<?= round(300 + cos($a) * 520, 1) ?>" y2="<?= round(390 + sin($a) * 520, 1) ?>"/>
    <?php endfor; ?>
  </g>
  <g stroke="url(#gg)" fill="none">
    <path d="M110 790V390a190 190 0 0 1 380 0v400" stroke-width="1.4"/>
    <path d="M132 790V390a168 168 0 0 1 336 0v400" stroke-width=".9" opacity=".75"/>
    <path d="M154 790V390a146 146 0 0 1 292 0v400" stroke-width=".7" opacity=".5"/>
  </g>
  <text x="300" y="470" text-anchor="middle" font-family="Bodoni Moda,Georgia,serif" font-size="250" font-weight="500" fill="url(#gg)"><?= e(Brand::initial()) ?></text>
  <g fill="#E9D29A" opacity=".8"><circle cx="60" cy="120" r="1.6"/><circle cx="520" cy="90" r="1.2"/><circle cx="548" cy="260" r="1.6"/><circle cx="40" cy="330" r="1.2"/><circle cx="470" cy="40" r="1"/></g>
  <g stroke="url(#gg)" stroke-width=".8" opacity=".6"><path d="M40 740h520"/><path d="M40 752h520" opacity=".5"/></g>
</svg>
