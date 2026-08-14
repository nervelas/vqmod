<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('appearance.manage');

if (is_post()) {
    Security::requireCsrf();
    $themeId = int_input('theme_id');
    $scope   = str_input('scope', 'global'); // 'global' or 'league'
    $leagueId = int_input('league_id');
    $theme = Theme::find($themeId);
    if (!$theme) {
        flash('danger', 'Tema no válido.');
    } elseif ($scope === 'league' && $leagueId) {
        Database::q("UPDATE leagues SET theme_id = ? WHERE id = ?", [$themeId, $leagueId]);
        Audit::log('apply_theme', 'appearance', $leagueId, null, ['theme_id' => $themeId, 'scope' => 'league']);
        flash('success', 'Tema aplicado a la liga seleccionada.');
    } else {
        Settings::set('default_theme_id', (string)$themeId, 'general');
        Audit::log('apply_theme', 'appearance', null, null, ['theme_id' => $themeId, 'scope' => 'global']);
        flash('success', 'Tema aplicado como predeterminado de la plataforma.');
    }
    redirect(base_url('admin/appearance.php'));
}

$themes = Theme::all();
$currentGlobal = (int)Settings::get('default_theme_id', 1);
$leagues = Database::all("SELECT id, name, theme_id FROM leagues ORDER BY name");

$PAGE_TITLE = 'Apariencia';
$ACTIVE = 'appearance';
require 'partials/head.php';
?>
<div class="page-head">
    <h1>Apariencia</h1>
    <p>Elige uno de los 10 temas visuales. Los cambios de tema solo se realizan desde aquí; el público no puede modificarlos.</p>
</div>

<div class="card mb-3">
    <div class="form-row" style="align-items:end">
        <div class="field" style="margin-bottom:0">
            <label for="scope">Aplicar a</label>
            <select class="select" id="scope" onchange="document.getElementById('league-wrap').classList.toggle('hidden', this.value!=='league')">
                <option value="global">Toda la plataforma (predeterminado)</option>
                <option value="league"<?= $leagues ? '' : ' disabled' ?>>Una liga específica</option>
            </select>
        </div>
        <div class="field hidden" id="league-wrap" style="margin-bottom:0">
            <label for="league_id">Liga</label>
            <select class="select" id="league_id">
                <?php foreach ($leagues as $l): ?>
                    <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?><?= $l['theme_id'] ? ' · tema actual: ' . e(($themes[array_search($l['theme_id'], array_column($themes,'id'))]['name'] ?? '')) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <p class="help mt-1">Cada liga puede usar un tema distinto. Sin selección de liga, se cambia el tema predeterminado.</p>
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
                    <form method="post" data-apply-form style="display:inline">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="theme_id" value="<?= (int)$t['id'] ?>">
                        <input type="hidden" name="scope" class="apply-scope" value="global">
                        <input type="hidden" name="league_id" class="apply-league" value="">
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

<script>
// Wire apply forms to the current scope/league selection.
(function () {
    var scopeSel = document.getElementById('scope');
    var leagueSel = document.getElementById('league_id');
    document.querySelectorAll('[data-apply-form]').forEach(function (f) {
        f.addEventListener('submit', function () {
            f.querySelector('.apply-scope').value = scopeSel ? scopeSel.value : 'global';
            f.querySelector('.apply-league').value = (scopeSel && scopeSel.value === 'league' && leagueSel) ? leagueSel.value : '';
        });
    });
})();
</script>
<?php require 'partials/foot.php'; ?>
