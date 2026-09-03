<?php
declare(strict_types=1);
/** Selector de los 8 temas visuales, con vista previa y edición de colores. */

$themes  = Theme::all();
$current = Settings::get('theme_active', 'obsidiana');
$editKey = preg_replace('/[^a-z0-9_-]/', '', get('editar', '')) ?: '';

if (is_post()) {
    Csrf::verify();
    $op = post('op');

    if ($op === 'activate') {
        $key = preg_replace('/[^a-z0-9_-]/', '', post('theme_key')) ?: '';
        if ($key !== '' && Database::first('SELECT id FROM themes WHERE theme_key = :k', ['k' => $key]) !== null) {
            Settings::set('theme_active', $key, 'tema');
            Settings::flush();
            flash('Tema activado. Abra el sitio para verlo aplicado.');
        }
        redirect('admin/index.php?p=temas');
    }

    if ($op === 'colors') {
        $key = preg_replace('/[^a-z0-9_-]/', '', post('theme_key')) ?: '';
        $row = Database::first('SELECT * FROM themes WHERE theme_key = :k', ['k' => $key]);
        if ($row !== null) {
            $palette = json_field($row['palette']);
            foreach (['bg', 'bg-alt', 'surface', 'surface-2', 'text', 'muted', 'accent', 'accent-2', 'accent-ink'] as $slot) {
                $v = post('c_' . str_replace('-', '_', $slot));
                if (preg_match('/^#[0-9a-f]{3,8}$/i', $v) === 1) {
                    $palette[$slot] = strtolower($v);
                }
            }
            $radius = post('c_radius');
            if (preg_match('/^\d{1,3}px$/', $radius) === 1) { $palette['radius'] = $radius; }
            Database::update('themes', ['palette' => json_encode($palette, JSON_UNESCAPED_SLASHES)], 'theme_key = :k', ['k' => $key]);
            flash('Colores del tema «' . $row['name'] . '» actualizados.');
        }
        redirect('admin/index.php?p=temas&editar=' . $key);
    }

    if ($op === 'restore') {
        $key = preg_replace('/[^a-z0-9_-]/', '', post('theme_key')) ?: '';
        foreach (Theme::defaults() as $d) {
            if ($d['theme_key'] === $key) {
                Database::update('themes', [
                    'palette' => json_encode($d['palette'], JSON_UNESCAPED_SLASHES),
                    'fonts'   => json_encode($d['fonts'], JSON_UNESCAPED_SLASHES),
                ], 'theme_key = :k', ['k' => $key]);
                flash('Tema restaurado a sus colores originales.');
            }
        }
        redirect('admin/index.php?p=temas&editar=' . $key);
    }
}

admin_header('Temas visuales', 'temas');
?>
<div class="row-help">
  <?= icon('chispa', 16) ?> El sitio incluye <strong>8 temas completos</strong> (4 oscuros y 4 claros), cada uno con su propia paleta y tipografías.
  Pulse <strong>Vista previa</strong> para verlo en el sitio sin activarlo, y <strong>Activar</strong> para dejarlo como tema oficial.
</div>

<div class="theme-grid">
  <?php foreach ($themes as $t):
      $p = is_array($t['palette']) ? $t['palette'] : [];
      $f = is_array($t['fonts']) ? $t['fonts'] : []; ?>
    <div class="theme-card<?= $t['theme_key'] === $current ? ' is-active' : '' ?>">
      <div class="theme-card__prev" style="background:<?= e($p['bg'] ?? '#000') ?>;color:<?= e($p['text'] ?? '#fff') ?>">
        <div>
          <b><?= e($t['name']) ?></b>
          <small style="display:block;color:<?= e($p['muted'] ?? '#888') ?>"><?= $t['mode'] === 'dark' ? 'Tema oscuro' : 'Tema claro' ?></small>
        </div>
        <div class="theme-card__chips">
          <i style="background:<?= e($p['accent'] ?? '#fff') ?>"></i>
          <i style="background:<?= e($p['accent-2'] ?? '#fff') ?>"></i>
          <i style="background:<?= e($p['surface'] ?? '#111') ?>;border:1px solid <?= e($p['muted'] ?? '#555') ?>"></i>
        </div>
      </div>
      <div class="theme-card__body">
        <strong><?= e($t['name']) ?><?= $t['theme_key'] === $current ? ' <span class="pill pill--on">Activo</span>' : '' ?></strong>
        <p><?= e($t['description']) ?></p>
        <p style="font-size:.72rem;color:var(--a-muted);margin-bottom:.7rem">
          <?= e(str_replace("'", '', (string) ($f['display'] ?? ''))) ?> + <?= e(str_replace("'", '', (string) ($f['body'] ?? ''))) ?>
        </p>
        <div class="theme-card__actions">
          <?php if ($t['theme_key'] !== $current): ?>
            <form method="post" style="display:contents">
              <?= Csrf::field() ?>
              <input type="hidden" name="op" value="activate">
              <input type="hidden" name="theme_key" value="<?= e($t['theme_key']) ?>">
              <button class="btn btn--sm" type="submit"><?= icon('check', 15) ?><span>Activar</span></button>
            </form>
          <?php endif; ?>
          <a class="btn btn--light btn--sm" target="_blank" rel="noopener" href="<?= e(base('?preview_theme=' . $t['theme_key'])) ?>"><?= icon('web', 15) ?><span>Vista previa</span></a>
          <a class="btn btn--light btn--sm" href="<?= e(admin_url('temas', ['editar' => $t['theme_key']])) ?>"><?= icon('diseno', 15) ?><span>Colores</span></a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php
$edit = null;
foreach ($themes as $t) { if ($t['theme_key'] === $editKey) { $edit = $t; } }
if ($edit !== null):
    $p = is_array($edit['palette']) ? $edit['palette'] : [];
    $slots = [
        'bg' => 'Fondo principal', 'bg-alt' => 'Fondo alterno', 'surface' => 'Superficie de tarjetas',
        'surface-2' => 'Superficie secundaria', 'text' => 'Texto principal', 'muted' => 'Texto secundario',
        'accent' => 'Color de acento', 'accent-2' => 'Acento secundario', 'accent-ink' => 'Texto sobre el acento',
    ];
?>
<form class="panel" method="post" id="colores" action="<?= e(admin_url('temas', ['editar' => $editKey])) ?>#colores" style="margin-top:1.4rem">
  <?= Csrf::field() ?>
  <input type="hidden" name="op" value="colors">
  <input type="hidden" name="theme_key" value="<?= e($editKey) ?>">
  <div class="panel__head">
    <h2><?= icon('diseno', 19) ?>Colores del tema «<?= e($edit['name']) ?>»</h2>
    <a class="btn btn--light btn--sm" href="<?= e(admin_url('temas')) ?>"><?= icon('cerrar', 15) ?><span>Cerrar</span></a>
  </div>
  <div class="panel__body">
    <p class="panel__hint" style="margin-bottom:1rem">Ajuste la paleta de este tema. Si algo no le convence, puede restaurar los colores originales.</p>
    <div class="form-grid">
      <?php foreach ($slots as $slot => $label): ?>
        <div class="f">
          <label for="c_<?= e(str_replace('-', '_', $slot)) ?>"><?= e($label) ?></label>
          <div style="display:flex;gap:.5rem;align-items:center">
            <input type="color" id="c_<?= e(str_replace('-', '_', $slot)) ?>" name="c_<?= e(str_replace('-', '_', $slot)) ?>"
                   value="<?= e(preg_match('/^#[0-9a-f]{6}$/i', (string) ($p[$slot] ?? '')) ? $p[$slot] : '#000000') ?>">
            <code style="font-size:.8rem;color:var(--a-muted)"><?= e((string) ($p[$slot] ?? '')) ?></code>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="f">
        <label for="c_radius">Redondeo de esquinas</label>
        <input type="text" id="c_radius" name="c_radius" value="<?= e((string) ($p['radius'] ?? '18px')) ?>" placeholder="18px">
        <span class="hint">Escriba un valor en píxeles, por ejemplo 4px, 18px o 28px.</span>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit"><?= icon('check', 17) ?><span>Guardar colores</span></button>
    <button class="btn btn--light" type="submit" name="op" value="restore" data-confirm="¿Restaurar los colores originales de este tema?"><?= icon('flecha-arriba', 17) ?><span>Restaurar originales</span></button>
    <a class="btn btn--light" target="_blank" rel="noopener" href="<?= e(base('?preview_theme=' . $editKey)) ?>"><?= icon('web', 17) ?><span>Ver resultado</span></a>
  </div>
</form>
<?php endif; ?>
<?php admin_footer(); ?>
