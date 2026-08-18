<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('content.manage');

/* ---- Handle POST (save settings) --------------------------------------- */
if (is_post()) {
    Security::requireCsrf();

    // General / Marca
    Settings::setMany([
        'site_name'    => str_input('site_name'),
        'site_tagline' => str_input('site_tagline'),
    ], 'general');

    // Image uploads: logo + favicon
    try {
        $logo    = Upload::image('logo', 'media');
        $favicon = Upload::image('favicon', 'media');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect(base_url('admin/content.php'));
    }
    if (post('remove_logo') && Settings::get('logo')) {
        Upload::delete(Settings::get('logo'));
        Settings::set('logo', '', 'general');
    }
    if ($logo) {
        $old = Settings::get('logo');
        if ($old) { Upload::delete($old); }
        Settings::set('logo', $logo, 'general');
    }
    if (post('remove_favicon') && Settings::get('favicon')) {
        Upload::delete(Settings::get('favicon'));
        Settings::set('favicon', '', 'general');
    }
    if ($favicon) {
        $old = Settings::get('favicon');
        if ($old) { Upload::delete($old); }
        Settings::set('favicon', $favicon, 'general');
    }

    // Contenido
    Settings::setMany([
        'hero_title'    => str_input('hero_title'),
        'hero_subtitle' => str_input('hero_subtitle'),
        'footer_text'   => str_input('footer_text'),
        'institutional' => (string)post('institutional', ''),
        'contact_email' => str_input('contact_email'),
    ], 'content');

    // SEO
    Settings::setMany([
        'seo_title'       => str_input('seo_title'),
        'seo_description' => str_input('seo_description'),
        'seo_keywords'    => str_input('seo_keywords'),
    ], 'seo');

    // Módulos (visibilidad)
    Settings::setMany([
        'module_news'       => post('module_news') ? '1' : '0',
        'module_scorers'    => post('module_scorers') ? '1' : '0',
        'module_discipline' => post('module_discipline') ? '1' : '0',
        'module_rules'      => post('module_rules') ? '1' : '0',
    ], 'modules');

    Audit::log('update', 'content', null, null, ['saved' => true]);
    flash('success', 'Contenido y configuración guardados correctamente.');
    redirect(base_url('admin/content.php'));
}

$PAGE_TITLE = 'Contenido y SEO';
$ACTIVE = 'content';

$logo    = Settings::get('logo');
$favicon = Settings::get('favicon');

require 'partials/head.php';
?>
<div class="page-head">
    <h1>Contenido y SEO</h1>
    <p>Configura la marca, los textos del sitio, el SEO y la visibilidad de los módulos.</p>
</div>

<form method="post" enctype="multipart/form-data">
    <?= Security::csrfField() ?>

    <div class="card card-pad-lg mt-3">
        <h3>General / Marca</h3>
        <div class="form-row">
            <div class="field">
                <label for="site_name">Nombre del sitio</label>
                <input class="input" id="site_name" name="site_name" value="<?= e(Settings::get('site_name', '')) ?>">
            </div>
            <div class="field">
                <label for="site_tagline">Eslogan</label>
                <input class="input" id="site_tagline" name="site_tagline" value="<?= e(Settings::get('site_tagline', '')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="logo">Logo (JPG, PNG, WEBP)</label>
                <input class="input" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
                <?php if ($logo): ?>
                    <div class="mt-1 flex items-center gap-1">
                        <img src="<?= e(base_url($logo)) ?>" alt="" style="max-height:60px;border-radius:8px">
                        <label class="help"><input type="checkbox" name="remove_logo" value="1"> Eliminar</label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="favicon">Favicon (JPG, PNG, WEBP)</label>
                <input class="input" type="file" id="favicon" name="favicon" accept=".jpg,.jpeg,.png,.webp">
                <?php if ($favicon): ?>
                    <div class="mt-1 flex items-center gap-1">
                        <img src="<?= e(base_url($favicon)) ?>" alt="" style="max-height:40px;border-radius:8px">
                        <label class="help"><input type="checkbox" name="remove_favicon" value="1"> Eliminar</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card card-pad-lg mt-3">
        <h3>Contenido</h3>
        <div class="form-row">
            <div class="field">
                <label for="hero_title">Título principal (hero)</label>
                <input class="input" id="hero_title" name="hero_title" value="<?= e(Settings::get('hero_title', '')) ?>">
            </div>
            <div class="field">
                <label for="hero_subtitle">Subtítulo (hero)</label>
                <input class="input" id="hero_subtitle" name="hero_subtitle" value="<?= e(Settings::get('hero_subtitle', '')) ?>">
            </div>
        </div>
        <div class="field">
            <label for="institutional">Texto institucional</label>
            <textarea class="textarea" id="institutional" name="institutional" rows="6"><?= e(Settings::get('institutional', '')) ?></textarea>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="contact_email">Correo de contacto</label>
                <input class="input" type="email" id="contact_email" name="contact_email" value="<?= e(Settings::get('contact_email', '')) ?>">
            </div>
            <div class="field">
                <label for="footer_text">Texto del pie de página</label>
                <input class="input" id="footer_text" name="footer_text" value="<?= e(Settings::get('footer_text', '')) ?>">
            </div>
        </div>
    </div>

    <div class="card card-pad-lg mt-3">
        <h3>SEO</h3>
        <div class="field">
            <label for="seo_title">Título SEO</label>
            <input class="input" id="seo_title" name="seo_title" value="<?= e(Settings::get('seo_title', '')) ?>">
        </div>
        <div class="field">
            <label for="seo_description">Descripción SEO</label>
            <textarea class="textarea" id="seo_description" name="seo_description" rows="3"><?= e(Settings::get('seo_description', '')) ?></textarea>
        </div>
        <div class="field">
            <label for="seo_keywords">Palabras clave SEO</label>
            <input class="input" id="seo_keywords" name="seo_keywords" value="<?= e(Settings::get('seo_keywords', '')) ?>" placeholder="separadas, por, comas">
        </div>
    </div>

    <div class="card card-pad-lg mt-3">
        <h3>Módulos (visibilidad)</h3>
        <p class="muted" style="font-size:.85rem;margin-top:-.5rem">Activa o desactiva secciones del sitio público.</p>
        <div class="check-grid">
            <label class="check-item"><input type="checkbox" name="module_news" value="1"<?= checked(Settings::bool('module_news')) ?>> Noticias</label>
            <label class="check-item"><input type="checkbox" name="module_scorers" value="1"<?= checked(Settings::bool('module_scorers')) ?>> Goleadores</label>
            <label class="check-item"><input type="checkbox" name="module_discipline" value="1"<?= checked(Settings::bool('module_discipline')) ?>> Disciplina</label>
            <label class="check-item"><input type="checkbox" name="module_rules" value="1"<?= checked(Settings::bool('module_rules')) ?>> Reglamento</label>
        </div>
    </div>

    <div class="page-actions mt-3">
        <button class="btn" type="submit">Guardar cambios</button>
    </div>
</form>
<?php require 'partials/foot.php'; ?>
