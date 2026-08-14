<?php
/** Global SEO settings. */
if (!defined('BASE_PATH')) { exit; }
$keys = ['seo_default_title','seo_default_description','seo_keywords','seo_og_image','analytics_head'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    foreach ($keys as $k) { if ($k==='seo_og_image') continue; Settings::set($k, post($k), 'seo'); }
    Settings::set('seo_og_image', post('seo_og_image'), 'seo');
    Auth::log('settings_seo','Actualizó SEO');
    flash('success','SEO guardado.');
    redirect('admin/index.php?page=seo');
}
admin_header('SEO');
?>
<div class="card" style="max-width:760px">
  <h2>SEO global del sitio</h2>
  <p class="muted">Valores por defecto. Cada página puede sobreescribir su propio título y descripción en <a href="<?= e(admin_url('pages')) ?>">Páginas</a>.</p>
  <form method="post" class="form">
    <?= Csrf::field() ?>
    <div class="form-group"><label>Título por defecto</label><input type="text" name="seo_default_title" value="<?= e(Settings::raw('seo_default_title')) ?>" maxlength="200"></div>
    <div class="form-group"><label>Meta description por defecto</label><textarea name="seo_default_description" rows="3" maxlength="300"><?= e(Settings::raw('seo_default_description')) ?></textarea></div>
    <div class="form-group"><label>Palabras clave</label><input type="text" name="seo_keywords" value="<?= e(Settings::raw('seo_keywords')) ?>"></div>
    <?= media_field('seo_og_image', Settings::raw('seo_og_image'), 'Imagen para compartir (Open Graph)') ?>
    <div class="form-group"><label>Código en &lt;head&gt; (analítica, verificación)</label><textarea name="analytics_head" rows="4" placeholder="<!-- Google Analytics, etc. -->"><?= e(Settings::raw('analytics_head')) ?></textarea>
      <small class="muted">Se inserta tal cual en el &lt;head&gt;. Sólo pega código de fuentes de confianza.</small></div>
    <div class="form-actions"><button class="btn btn--primary">Guardar SEO</button></div>
  </form>
  <p class="muted">Sitemap: <a href="<?= e(base_url('sitemap.xml')) ?>" target="_blank"><?= e(base_url('sitemap.xml')) ?></a> · Robots: <a href="<?= e(base_url('robots.txt')) ?>" target="_blank">robots.txt</a></p>
</div>
<?php admin_footer(); ?>
