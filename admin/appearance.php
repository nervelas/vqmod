<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('appearance.manage');

if (is_post()) {
    Security::requireCsrf();
    $themeId = int_input('theme_id');
    $theme = Theme::find($themeId);
    if (!$theme) {
        flash('danger', 'Tema no válido.');
    } else {
        // Single-league mode: the chosen theme is the platform theme. Apply it
        // as the default and to the league itself so it always takes effect.
        Settings::set('default_theme_id', (string)$themeId, 'general');
        $lid = the_league_id();
        if ($lid) { Database::q("UPDATE leagues SET theme_id = ? WHERE id = ?", [$themeId, $lid]); }
        Audit::log('apply_theme', 'appearance', $lid, null, ['theme_id' => $themeId]);
        flash('success', 'Tema aplicado a la plataforma.');
    }
    redirect(base_url('admin/appearance.php'));
}

$themes = Theme::all();
$currentGlobal = (int)Settings::get('default_theme_id', 1);

$PAGE_TITLE = 'Apariencia';
$ACTIVE = 'appearance';
require 'partials/head.php';
?>
<div class="page-head">
    <h1>Apariencia</h1>
    <p>Elige uno de los 10 temas visuales. Los cambios de tema solo se realizan desde aquí; el público no puede modificarlos.</p>
</div>

<div class="theme-grid">
    <?php foreach ($themes as $t):
        $vars = Theme::styleAttr($t);
        $isActive = (int)$t['id'] === $currentGlobal;
    ?>
        <div class="theme-card <?= $isActive ? 'active' : '' ?> reveal">
            <div class="theme-swatches">
                <span style="background:<?= e($t['color_bg']) ?>"></span>
                <span style="background:<?= e($t['color_primary']) ?>"></span>
                <span style="background:<?= e($t['color_secondary']) ?>"></span>
                <span style="background:<?= e($t['color_accent']) ?>"></span>
            </div>
            <div class="theme-card-body">
                <div class="flex justify-between items-center">
                    <h4><?= e($t['name']) ?></h4>
                    <?php if ($isActive): ?><span class="badge badge-success">Activo</span><?php endif; ?>
                </div>
                <div class="t-style"><?= e($t['style']) ?></div>
                <div class="color-chips">
                    <?php foreach (['color_bg','color_primary','color_secondary','color_accent'] as $c): ?>
                        <span class="color-chip" title="<?= e($t[$c]) ?>" style="background:<?= e($t[$c]) ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="theme-card-actions mt-2">
                    <button class="btn btn-sm btn-ghost" type="button" data-theme-preview-btn data-theme-vars="<?= e($vars) ?>">Previsualizar</button>
                    <form method="post" style="display:inline">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="theme_id" value="<?= (int)$t['id'] ?>">
                        <button class="btn btn-sm" type="submit">Aplicar</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="mt-4">Previsualización</h3>
<p class="muted" style="margin-top:-.5rem">Pulsa "Previsualizar" en cualquier tema para ver aquí cómo se verían los componentes. Luego "Aplicar" o cancela sin guardar.</p>
<div id="theme-preview-box" class="theme-preview" style="<?= e(Theme::styleAttr(Theme::find($currentGlobal) ?: $themes[0])) ?>">
    <div class="tp-hero">Hero de la liga · Torneo Apertura</div>
    <div class="tp-row mb-2">
        <button class="btn btn-sm" type="button">Botón primario</button>
        <button class="btn btn-sm btn-accent" type="button">Acento</button>
        <button class="btn btn-sm btn-ghost" type="button">Ghost</button>
        <span class="badge">Jornada 1</span>
        <span class="badge badge-accent">Goleador</span>
    </div>
    <div class="card mb-2" style="padding:1rem">
        <strong>Tarjeta de contenido</strong>
        <p class="muted" style="margin:.3rem 0 0">Texto secundario con contraste garantizado.</p>
    </div>
    <div class="table-wrap" style="margin-bottom:1rem">
        <table class="data" style="min-width:auto">
            <thead><tr><th>#</th><th>Equipo</th><th class="num">PTS</th></tr></thead>
            <tbody>
                <tr><td>1</td><td>Equipo Alfa</td><td class="num pts">27</td></tr>
                <tr><td>2</td><td>Equipo Beta</td><td class="num pts">24</td></tr>
            </tbody>
        </table>
    </div>
    <div class="field" style="margin:0"><label>Campo de formulario</label><input class="input" placeholder="Escribe aquí…"></div>
</div>

<?php require 'partials/foot.php'; ?>
