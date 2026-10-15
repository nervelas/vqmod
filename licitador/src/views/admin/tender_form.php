<?php $t = $t ?? null; $g = fn($k, $d = '') => $t[$k] ?? $d; ?>
<p><a class="back" href="<?= e(url('admin/concursos')) ?>">← Concursos</a></p>
<div class="page-head"><div><p class="eyebrow"><?= $t ? e($t['ext_id']) . ($t['is_demo'] ? ' · DEMO' : '') . ' · ' . (int) ($nmatch ?? 0) . ' coincidencias' : 'Nuevo' ?></p><h1><?= $t ? 'Editar concurso' : 'Nuevo concurso' ?></h1></div></div>
<form method="post" action="<?= e(url('admin/concursos/guardar')) ?>" class="card form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $g('id', 0) ?>">
  <?= f_input('title', 'Título', $g('title'), 'text', 'required maxlength="400"') ?>
  <?= f_area('description', 'Descripción', $g('description'), 4) ?>
  <div class="two-col">
  <?= f_input('entity', 'Entidad compradora', $g('entity')) ?>
  <?= f_select('department', 'Departamento', array_combine(array_merge(['Nacional'], deptos()), array_merge(['Nacional'], deptos())), $g('department', 'Nacional')) ?>
  <?= f_select('modality', 'Modalidad', modalidades(), $g('modality', 'cotizacion')) ?>
  <?= f_select('category', 'Categoría', categorias(), $g('category', 'otros')) ?>
  <?= f_input('amount', 'Monto estimado (Q)', $g('amount') !== '' ? (float) $g('amount') : '', 'text', 'inputmode="decimal"') ?>
  <?= f_input('publish_date', 'Fecha de publicación', $g('publish_date', date('Y-m-d')), 'date') ?>
  <?= f_input('deadline', 'Fecha límite', $g('deadline') ? date('Y-m-d\TH:i', strtotime($g('deadline'))) : '', 'datetime-local') ?>
  <?= f_select('status', 'Estado', ['abierto' => 'Abierto', 'adjudicado' => 'Adjudicado', 'cerrado' => 'Cerrado', 'desierto' => 'Desierto'], $g('status', 'abierto')) ?>
  <?= f_input('awarded_amount', 'Monto final adjudicado (Q) — alimenta los precios de referencia', $g('awarded_amount') !== '' ? (float) $g('awarded_amount') : '', 'text', 'inputmode="decimal"') ?>
  <?= f_input('awarded_to', 'Adjudicado a', $g('awarded_to')) ?>
  </div>
  <?= f_input('url', 'Enlace oficial a GUATECOMPRAS', $g('url'), 'url') ?>
  <?= f_area('requirements', 'Documentos y requisitos (uno por línea)', $g('requirements'), 6) ?>
  <div class="btn-row"><button class="btn btn-gold">Guardar</button></div>
</form>
<?php if ($t): ?><form method="post" action="<?= e(url('admin/concursos/' . $t['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar este concurso y sus coincidencias?"><?= csrf_field() ?><button class="btn btn-danger btn-sm">Eliminar concurso</button></form><?php endif; ?>
