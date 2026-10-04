<?php
/** Privacidad y términos. */
use App\Controllers\Admin\A4Controller;

$queryBase = $q !== '' ? ['q' => $q] : [];
?>
<div class="page p4-page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Privacidad y términos</h1>
      <p class="page-sub">Los textos que aceptan tus clientes al reservar, cuánto tiempo conservas sus datos y la evidencia de su consentimiento.</p>
    </div>
    <div class="page-actions">
      <span class="badge badge-gold" title="Cada vez que cambias un texto sube la versión y los clientes aceptan la nueva">Versión <?= (int) $version ?></span>
    </div>
  </div>

  <div class="alert alert-warn p4-legal-warning" role="note">
    <?= icon('alert') ?>
    <div><strong>Estas plantillas son genéricas y deben ser revisadas por un abogado antes de publicarse.</strong>
    <span class="muted">Agenda Premium no ofrece asesoría legal. Adáptalas a tu actividad, a tu profesión y a la ley vigente en Guatemala.</span></div>
  </div>

  <form class="stack" method="post" action="<?= e(url('/admin/legal')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php foreach ($docs as $doc => $d) : $key = $d['key']; $err = $errors[$key] ?? ''; ?>
      <section class="card" aria-labelledby="h-<?= e($doc) ?>">
        <div class="card-head row row-between row-wrap">
          <h2 id="h-<?= e($doc) ?>" class="serif"><?= e($d['label']) ?></h2>
          <div class="row row-wrap gap-2">
            <?php if ($isDefault[$doc]) : ?><span class="badge badge-warn">Plantilla sin guardar</span><?php else : ?><span class="badge badge-ok">Guardado</span><?php endif; ?>
            <button class="btn btn-outline btn-sm" type="submit" formaction="<?= e(url('/admin/legal/restaurar')) ?>" formnovalidate name="doc" value="<?= e($doc) ?>" data-confirm="¿Restaurar la plantilla genérica de «<?= e($d['label']) ?>»? Se reemplaza el texto guardado y los cambios sin guardar se pierden."><?= icon('undo') ?>Restaurar plantilla</button>
          </div>
        </div>
        <div class="card-body">
          <div class="field">
            <label for="f-<?= e($key) ?>" class="sr-only"><?= e($d['label']) ?></label>
            <textarea class="textarea p4-legal-text" id="f-<?= e($key) ?>" name="<?= e($key) ?>" rows="14" maxlength="40000" data-count-target="c-<?= e($doc) ?>"<?= $err !== '' ? ' aria-invalid="true" aria-describedby="f-' . e($key) . '-err"' : '' ?>><?= e($texts[$doc]) ?></textarea>
            <p class="hint">Formato: <span class="mono">**negrita**</span>, <span class="mono">*cursiva*</span>, párrafos separados por una línea en blanco y enlaces <span class="mono">[texto](https://…)</span>. <span id="c-<?= e($doc) ?>" class="mono nowrap" data-count></span></p>
            <?php if ($err !== '') : ?><p class="error" id="f-<?= e($key) ?>-err" role="alert"><?= e($err) ?></p><?php endif; ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="form-actions">
      <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?>Guardar textos</button>
      <button class="btn btn-outline" type="submit" formaction="<?= e(url('/admin/legal/restaurar')) ?>" formnovalidate name="doc" value="all" data-confirm="¿Restaurar las tres plantillas genéricas? Se reemplazan todos los textos guardados."><?= icon('undo') ?>Restaurar las tres plantillas</button>
    </div>
  </form>

  <div class="grid cols-2 mt-4">
    <section class="card" aria-labelledby="h-ret">
      <div class="card-head"><h2 id="h-ret" class="serif">Retención de datos</h2></div>
      <form class="card-body stack" method="post" action="<?= e(url('/admin/legal/retencion')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="f-retention_months">Borrar datos inactivos después de (meses)</label>
          <input class="input" type="number" id="f-retention_months" name="retention_months" min="0" max="120" value="<?= e($monthsOld) ?>" aria-describedby="ret-hint<?= isset($errors['retention_months']) ? ' ret-err' : '' ?>">
          <p class="hint" id="ret-hint">0 = no borrar nunca. Si pones, por ejemplo, 36, cada día se eliminan las citas y los clientes sin actividad desde hace más de 3 años.</p>
          <?php if (isset($errors['retention_months'])) : ?><p class="error" id="ret-err" role="alert"><?= e($errors['retention_months']) ?></p><?php endif; ?>
        </div>
        <?php if ($months > 0) : ?>
          <div class="alert alert-info" role="status">Con la política actual (<?= (int) $months ?> meses), hoy se borrarían unas <strong><?= number_format($affected) ?></strong> cita<?= $affected === 1 ? '' : 's' ?> concluida<?= $affected === 1 ? '' : 's' ?>.</div>
        <?php endif; ?>
        <div class="form-actions"><button class="btn btn-gold" type="submit">Guardar política</button></div>
      </form>
    </section>

    <section class="card" aria-labelledby="h-cook">
      <div class="card-head"><h2 id="h-cook" class="serif">Cookies y datos de una persona</h2></div>
      <div class="card-body stack">
        <p class="muted">Tu agenda usa <strong>solo cookies estrictamente necesarias</strong> (la sesión del panel). La analítica es propia, no coloca cookies y no comparte datos con terceros. Por eso no se muestra un banner de aceptación de cookies de seguimiento.</p>
        <form class="stack" method="get" action="<?= e(url('/admin/clientes')) ?>">
          <div class="field">
            <label for="f-persona">Buscar a una persona para exportar o eliminar sus datos</label>
            <div class="input-group">
              <input class="input" type="search" id="f-persona" name="q" placeholder="Nombre, correo o teléfono" maxlength="120">
              <button class="btn btn-outline" type="submit"><?= icon('search') ?>Buscar</button>
            </div>
            <p class="hint">En la ficha de cada cliente encuentras los botones para exportar o eliminar sus datos personales.</p>
          </div>
        </form>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/clientes')) ?>"><?= icon('users') ?>Ir a clientes</a>
      </div>
    </section>
  </div>

  <section class="card mt-4" aria-labelledby="h-cons">
    <div class="card-head row row-between row-wrap">
      <h2 id="h-cons" class="serif">Registro de consentimientos</h2>
      <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/legal/consentimientos.csv', $queryBase)) ?>"><?= icon('download') ?>Exportar CSV</a>
    </div>
    <div class="card-body stack">
      <form class="row row-wrap gap-2" method="get" action="<?= e(url('/admin/legal')) ?>">
        <label class="sr-only" for="f-cq">Buscar por correo</label>
        <input class="input" type="search" id="f-cq" name="q" value="<?= e($q) ?>" placeholder="Buscar por correo" maxlength="120">
        <button class="btn btn-ghost" type="submit"><?= icon('search') ?>Buscar</button>
        <?php if ($q !== '') : ?><a class="btn btn-ghost" href="<?= e(url('/admin/legal')) ?>#h-cons">Limpiar</a><?php endif; ?>
      </form>
      <?php if (!$consents) : ?>
        <div class="empty">
          <?= icon('shield') ?>
          <p class="empty-title"><?= $q !== '' ? 'Sin resultados' : 'Aún no hay consentimientos' ?></p>
          <p class="empty-text"><?= $q !== '' ? 'Prueba con otra parte del correo.' : 'Cuando alguien reserve y acepte tus términos, quedará registrado aquí con fecha, versión y huella del texto.' ?></p>
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table class="table table-sm">
            <thead><tr><th scope="col">Fecha</th><th scope="col">Correo</th><th scope="col">Documento</th><th scope="col">Versión</th><th scope="col">IP truncada</th></tr></thead>
            <tbody>
            <?php foreach ($consents as $c) : ?>
              <tr>
                <td class="mono nowrap"><?= e(A4Controller::local($c['created_at'])) ?></td>
                <td><?= e($c['email'] ?: '—') ?></td>
                <td><?= e($docLabels[$c['document']] ?? $c['document']) ?></td>
                <td class="mono">v<?= (int) $c['version'] ?></td>
                <td class="mono"><?= e($c['ip_trunc'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1) : ?>
          <nav class="pagination" aria-label="Páginas de consentimientos">
            <?php if ($page > 1) : ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/legal', $queryBase + ['pagina' => $page - 1])) ?>#h-cons"><?= icon('chevron-left') ?>Anterior</a><?php endif; ?>
            <span class="muted">Página <?= (int) $page ?> de <?= (int) $pages ?> · <?= number_format($total) ?> registros</span>
            <?php if ($page < $pages) : ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/legal', $queryBase + ['pagina' => $page + 1])) ?>#h-cons">Siguiente<?= icon('chevron-right') ?></a><?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
