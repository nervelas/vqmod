<?php
declare(strict_types=1);
/** Centro de SEO: metadatos globales, datos del negocio y herramientas. */

$fields = [
  'basico' => ['SEO general', 'seo', [
      'seo_default_title'       => ['label' => 'Título por defecto', 'type' => 'text', 'max' => 200, 'full' => true, 'hint' => 'Se usa cuando una página no tiene título propio. Ideal: 50–60 caracteres.'],
      'seo_default_description' => ['label' => 'Meta descripción por defecto', 'type' => 'textarea', 'max' => 320, 'full' => true, 'hint' => 'Ideal: 140–160 caracteres. Debe invitar a hacer clic.'],
      'seo_default_keywords'    => ['label' => 'Palabras clave principales', 'type' => 'text', 'max' => 320, 'full' => true, 'hint' => 'Separadas por comas. Ej. diseño de páginas web Guatemala, tiendas virtuales Guatemala.'],
      'seo_separator'           => ['label' => 'Separador del título', 'type' => 'text', 'max' => 5, 'hint' => 'Se muestra entre el título de la página y el nombre del sitio.'],
      'seo_og_image'            => ['label' => 'Imagen para redes sociales', 'type' => 'media', 'full' => true, 'hint' => 'Se muestra al compartir el sitio. Recomendado 1200×630 px.'],
      'seo_robots'              => ['label' => 'Directiva robots por defecto', 'type' => 'select', 'options' => [
          'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' => 'Indexar todo (recomendado)',
          'index, follow' => 'Indexar y seguir enlaces',
          'noindex, nofollow' => 'No indexar el sitio (solo para pruebas)',
      ], 'full' => true],
  ]],
  'negocio' => ['Datos del negocio (Schema.org)', 'escudo', [
      'schema_type'        => ['label' => 'Tipo de negocio', 'type' => 'select', 'options' => [
          'ProfessionalService' => 'Servicio profesional', 'LocalBusiness' => 'Negocio local',
          'Organization' => 'Organización', 'WebDesignCompany' => 'Empresa de diseño web',
      ], 'hint' => 'Define cómo entiende Google su negocio.'],
      'schema_price_range' => ['label' => 'Rango de precios', 'type' => 'text', 'max' => 20, 'hint' => 'Ej. $, $$ o $$$.'],
      'schema_hours'       => ['label' => 'Horario en formato Schema', 'type' => 'text', 'max' => 120, 'hint' => 'Ej. Mo-Fr 08:00-18:00'],
      'schema_lat'         => ['label' => 'Latitud', 'type' => 'text', 'max' => 30, 'hint' => 'Opcional. Ej. 14.6349'],
      'schema_lng'         => ['label' => 'Longitud', 'type' => 'text', 'max' => 30, 'hint' => 'Opcional. Ej. -90.5069'],
      'seo_geo_region'     => ['label' => 'Código de región', 'type' => 'text', 'max' => 10, 'hint' => 'GT para Guatemala.'],
  ]],
  'herramientas' => ['Google y herramientas', 'grafica', [
      'google_analytics'    => ['label' => 'ID de Google Analytics / Tag Manager', 'type' => 'text', 'max' => 40, 'full' => true, 'hint' => 'Ej. G-XXXXXXXXXX o GTM-XXXXXXX. Déjelo vacío para no cargar scripts externos.'],
      'google_verification' => ['label' => 'Código de verificación de Search Console', 'type' => 'text', 'max' => 120, 'full' => true, 'hint' => 'Solo el valor del atributo content de la etiqueta que le da Google.'],
  ]],
];

$tab = get('tab', 'basico');
if (!isset($fields[$tab])) { $tab = 'basico'; }

if (is_post()) {
    Csrf::verify();
    $saveTab = post('tab', $tab);
    $set = $fields[$saveTab][2] ?? [];
    $values = [];
    foreach ($set as $key => $f) {
        if (($f['type'] ?? '') === 'media') {
            $v = post($key);
            $up = $_FILES['upload_' . $key] ?? null;
            if (is_array($up) && ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $res = Media::upload($up);
                if ($res['ok']) { $v = (string) $res['path']; }
            }
            $values[$key] = $v;
            continue;
        }
        $values[$key] = post($key);
    }
    Settings::setMany($values, 'seo');
    Settings::flush();
    flash('Configuración SEO guardada.');
    redirect('admin/index.php?p=seo&tab=' . $saveTab);
}

// Diagnostico rapido
$pages = Content::pages();
$issues = [];
foreach ($pages as $p) {
    $t = (string) ($p['meta_title'] ?: $p['title']);
    $d = (string) $p['meta_description'];
    if (mb_strlen($t) > 65) { $issues[] = ['warn', 'La página «' . $p['title'] . '» tiene un título de ' . mb_strlen($t) . ' caracteres (recomendado ≤ 60).', admin_url('paginas', ['action' => 'edit', 'id' => $p['id']])]; }
    if ($d === '') { $issues[] = ['error', 'La página «' . $p['title'] . '» no tiene meta descripción.', admin_url('paginas', ['action' => 'edit', 'id' => $p['id']])]; }
    elseif (mb_strlen($d) > 165) { $issues[] = ['warn', 'La meta descripción de «' . $p['title'] . '» tiene ' . mb_strlen($d) . ' caracteres (recomendado ≤ 160).', admin_url('paginas', ['action' => 'edit', 'id' => $p['id']])]; }
}
foreach (Content::services() as $s) {
    if ((string) $s['meta_description'] === '') { $issues[] = ['error', 'El servicio «' . $s['title'] . '» no tiene meta descripción.', admin_url('servicios', ['action' => 'edit', 'id' => $s['id']])]; }
}

admin_header('SEO y buscadores', 'seo');
?>
<div class="tabs">
  <?php foreach ($fields as $key => [$label, $ic, $_]): ?>
    <a class="<?= $key === $tab ? 'is-active' : '' ?>" href="<?= e(admin_url('seo', ['tab' => $key])) ?>"><?= icon($ic, 16) ?><?= e($label) ?></a>
  <?php endforeach; ?>
  <a href="<?= e(admin_url('paginas')) ?>"><?= icon('web', 16) ?>SEO por página</a>
</div>

<form class="panel" method="post" enctype="multipart/form-data" action="<?= e(admin_url('seo', ['tab' => $tab])) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="panel__head"><h2><?= icon($fields[$tab][1], 19) ?><?= e($fields[$tab][0]) ?></h2></div>
  <div class="panel__body">
    <div class="form-grid">
      <?php foreach ($fields[$tab][2] as $key => $f): admin_field($key, $f, Settings::get($key)); endforeach; ?>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit"><?= icon('check', 17) ?><span>Guardar cambios</span></button>
  </div>
</form>

<div class="panel">
  <div class="panel__head"><h2><?= icon('seo', 19) ?>Diagnóstico SEO</h2></div>
  <div class="panel__body">
    <?php if ($issues === []): ?>
      <div class="notice notice--ok"><?= icon('check', 19) ?><span>Todas las páginas y servicios tienen su título y su meta descripción en las medidas recomendadas.</span></div>
    <?php else: ?>
      <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.55rem">
        <?php foreach (array_slice($issues, 0, 15) as [$level, $text, $link]): ?>
          <li style="display:flex;gap:.6rem;align-items:flex-start;font-size:.9rem">
            <span style="color:<?= $level === 'error' ? 'var(--a-danger)' : 'var(--a-warn)' ?>;margin-top:.15rem"><?= icon('cerrar', 16) ?></span>
            <span><?= e($text) ?> — <a href="<?= e($link) ?>">corregir</a></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel__head"><h2><?= icon('documento', 19) ?>Archivos para buscadores</h2></div>
  <div class="panel__body">
    <p class="panel__hint">Estos archivos se generan solos con el contenido publicado. Envíe el sitemap a Google Search Console.</p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.9rem">
      <a class="btn btn--light btn--sm" target="_blank" rel="noopener" href="<?= e(base('sitemap.xml')) ?>"><?= icon('documento', 15) ?><span>Ver sitemap.xml</span></a>
      <a class="btn btn--light btn--sm" target="_blank" rel="noopener" href="<?= e(base('robots.txt')) ?>"><?= icon('escudo', 15) ?><span>Ver robots.txt</span></a>
      <a class="btn btn--light btn--sm" target="_blank" rel="noopener" href="https://search.google.com/search-console"><?= icon('seo', 15) ?><span>Abrir Search Console</span></a>
      <a class="btn btn--light btn--sm" target="_blank" rel="noopener" href="https://pagespeed.web.dev/"><?= icon('velocidad', 15) ?><span>Medir velocidad (PageSpeed)</span></a>
      <a class="btn btn--light btn--sm" target="_blank" rel="noopener" href="https://search.google.com/test/rich-results"><?= icon('estrella', 15) ?><span>Probar resultados enriquecidos</span></a>
    </div>
  </div>
</div>
<?php admin_pickers(); admin_footer(); ?>
