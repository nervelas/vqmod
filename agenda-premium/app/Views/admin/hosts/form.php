<?php
/** @var array $h @var ?int $id @var ?string $error @var array $users @var array $schedules @var string $photo @var string $ics_url */
use App\Core\Str;

$v = static fn (string $k, $d = '') => $h[$k] ?? $d;
$phone = static fn (string $k): string => Str::phoneDisplay((string) ($h[$k] ?? '')) ?: (string) ($h[$k] ?? '');
$action = $id ? url('/admin/anfitriones/' . $id . '/editar') : url('/admin/anfitriones/nuevo');
$sw = static function (string $name, string $label, bool $on): string {
    $i = 'sw-' . $name;
    return '<div class="switch-row"><label class="switch"><input type="checkbox" id="' . e($i) . '" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '><span></span></label><label for="' . e($i) . '">' . e($label) . '</label></div>';
};
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <p class="p2-crumb"><a href="<?= e(url('/admin/anfitriones')) ?>"><?= icon('chevron-left') ?> Anfitriones</a></p>
      <h1 class="page-title"><?= $id ? 'Editar anfitrión' : 'Nuevo anfitrión' ?></h1>
      <p class="page-sub">Estos datos aparecen en la página de reserva y en los correos a tus clientes.</p>
    </div>
  </header>
  <?php if (!empty($error)) : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>

  <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="stack p2-form-narrow" autocomplete="off">
    <?= csrf_field() ?>
    <section class="card"><div class="card-body stack">
      <h2 class="p2-h3">Perfil</h2>
      <div class="p2-photo-row">
        <?php if ($photo !== '') : ?><img class="p2-photo p2-photo-lg" src="<?= e($photo) ?>" alt="Foto actual de <?= e($v('name')) ?>" width="96" height="96"><?php else : ?><span class="avatar p2-photo p2-photo-lg" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $v('name', '?'), 0, 1)) ?: '?') ?></span><?php endif; ?>
        <div class="field">
          <label for="h-photo">Foto</label>
          <input class="input" type="file" id="h-photo" name="photo" accept="image/jpeg,image/png,image/webp">
          <p class="hint">JPG, PNG o WebP de hasta 4 MB. Se recorta en círculo.</p>
          <?php if ($photo !== '') : ?><?= $sw('remove_photo', 'Quitar la foto actual', false) ?><?php endif; ?>
        </div>
      </div>
      <div class="form-grid">
        <div class="field"><label for="h-name">Nombre completo</label><input class="input" type="text" id="h-name" name="name" maxlength="120" required value="<?= e($v('name')) ?>"></div>
        <div class="field"><label for="h-title">Cargo o especialidad</label><input class="input" type="text" id="h-title" name="title" maxlength="160" value="<?= e($v('title')) ?>" placeholder="Ej. Psicóloga clínica"></div>
        <div class="field">
          <label for="h-slug">Enlace del perfil</label>
          <input class="input mono" type="text" id="h-slug" name="slug" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($v('slug')) ?>" placeholder="se-genera-solo">
          <p class="hint">Solo minúsculas, números y guiones.</p>
        </div>
        <div class="field">
          <label for="h-color">Color en el calendario</label>
          <input class="p2-colorinput" type="color" id="h-color" name="color" value="<?= e($v('color', '#C9A050')) ?>">
        </div>
      </div>
      <div class="field"><label for="h-bio">Presentación</label><textarea class="textarea" id="h-bio" name="bio" rows="4" maxlength="3000" placeholder="Unas líneas sobre su experiencia."><?= e($v('bio')) ?></textarea></div>
    </div></section>

    <section class="card"><div class="card-body stack">
      <h2 class="p2-h3">Contacto</h2>
      <div class="form-grid">
        <div class="field"><label for="h-email">Correo</label><input class="input" type="email" id="h-email" name="email" maxlength="190" value="<?= e($v('email')) ?>"></div>
        <div class="field"><label for="h-phone">Teléfono</label><input class="input mono" type="tel" id="h-phone" name="phone" maxlength="30" value="<?= e($phone('phone')) ?>" placeholder="+502 5555 1234"></div>
        <div class="field"><label for="h-wa">WhatsApp</label><input class="input mono" type="tel" id="h-wa" name="whatsapp" maxlength="30" value="<?= e($phone('whatsapp')) ?>" placeholder="+502 5555 1234"></div>
      </div>
    </div></section>

    <section class="card"><div class="card-body stack">
      <h2 class="p2-h3">Agenda</h2>
      <div class="form-grid">
        <div class="field"><label for="h-tz">Zona horaria</label><?= \App\Core\View::partial('admin/schedules/tzselect', ['name' => 'timezone', 'value' => (string) $v('timezone', 'America/Guatemala'), 'id' => 'h-tz']) ?></div>
        <div class="field">
          <label for="h-sched">Horario de atención</label>
          <select class="select" id="h-sched" name="schedule_id">
            <option value="">Usar el horario predeterminado</option>
            <?php foreach ($schedules as $s) : ?><option value="<?= (int) $s['id'] ?>"<?= sel($v('schedule_id'), $s['id']) ?>><?= e($s['name']) ?> · <?= e($s['timezone']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="h-user">Usuario del panel</label>
          <select class="select" id="h-user" name="user_id">
            <option value="">Sin usuario vinculado</option>
            <?php foreach ($users as $u) : ?><option value="<?= (int) $u['id'] ?>"<?= sel($v('user_id'), $u['id']) ?>><?= e($u['name']) ?> · <?= e($u['email']) ?></option><?php endforeach; ?>
          </select>
          <p class="hint">Con un usuario vinculado, esta persona entra al panel y solo ve su propia agenda.</p>
        </div>
        <div class="field"><label for="h-order">Orden</label><input class="input" type="number" id="h-order" name="sort_order" min="0" max="100000" value="<?= (int) $v('sort_order', 0) ?>"><p class="hint">Número menor aparece primero.</p></div>
      </div>
      <?= $sw('public_profile', 'Mostrar su perfil en la página pública', (int) $v('public_profile', 1) === 1) ?>
      <?= $sw('active', 'Anfitrión activo (recibe citas)', (int) $v('active', 1) === 1) ?>
    </div></section>

    <?php if ($id && $ics_url !== '') : ?>
      <section class="card"><div class="card-body stack">
        <h2 class="p2-h3">Calendario propio (feed ICS)</h2>
        <p class="muted">Suscribe este enlace en Google Calendar, Outlook o Apple Calendar para ver las citas de <?= e($v('name')) ?> junto a su agenda personal. Es privado: quien tenga el enlace puede ver las citas, así que no lo publiques.</p>
        <div class="input-group">
          <input class="input mono" type="text" readonly id="ics-url" value="<?= e($ics_url) ?>" aria-label="Enlace del calendario">
          <button class="btn btn-outline" type="button" data-copy="#ics-url"><?= icon('copy') ?> Copiar</button>
        </div>
        <details class="p2-more"><summary>¿Cómo lo agrego?</summary>
          <ul class="p2-steps">
            <li><strong>Google Calendar:</strong> «Otros calendarios» → «Desde URL» y pega el enlace.</li>
            <li><strong>Outlook:</strong> «Agregar calendario» → «Suscribirse desde la web».</li>
            <li><strong>Apple Calendar:</strong> «Archivo» → «Nueva suscripción a calendario».</li>
          </ul>
          <p class="hint">Marcas de sus respectivos dueños; sin afiliación. Los calendarios externos se actualizan cada cierto tiempo, no al instante.</p>
        </details>
        <p><button class="btn btn-ghost btn-sm text-err" type="submit" formaction="<?= e(url('/admin/anfitriones/' . $id . '/token')) ?>" formenctype="application/x-www-form-urlencoded" data-confirm="¿Generar un enlace nuevo? El actual dejará de funcionar y tendrás que volver a suscribirlo."><?= icon('refresh') ?> Generar un enlace nuevo</button></p>
      </div></section>
    <?php endif; ?>

    <div class="form-actions p2-sticky">
      <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?> <?= $id ? 'Guardar cambios' : 'Agregar anfitrión' ?></button>
      <a class="btn btn-ghost btn-lg" href="<?= e(url('/admin/anfitriones')) ?>">Cancelar</a>
    </div>
  </form>
</div>
