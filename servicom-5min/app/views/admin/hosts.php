<?php /** Vars: $rows $counts $defaultId $webs */
$csrf = '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; ?>
<h1>Hostings</h1>
<p class="mut">Carpeta de webs por defecto: <code><?= e($webs) ?></code> (distinta de <code>public_html</code>). Cada web vive en una subcarpeta propia.</p>
<?php foreach ($rows as $h): ?>
<div class="card"><b><?= e($h['name']) ?></b> <span class="tag"><?= e($h['kind']) ?></span> <?= (int) $h['id'] === $defaultId ? '<span class="tag vista_lista">predeterminado</span>' : '' ?> <?= $h['active'] ? '' : '<span class="tag vencida">inactivo</span>' ?>
<span class="mut"> · <?= (int) ($counts[(int) $h['id']] ?? 0) ?> webs</span>
<div class="actions" style="margin:.6rem 0"><form method="post" action="/admin/hostings"><?= $csrf ?><input type="hidden" name="accion" value="predeterminado"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>"><button class="btn sec">Hacer predeterminado</button></form>
<form method="post" action="/admin/hostings"><?= $csrf ?><input type="hidden" name="accion" value="activar"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>"><input type="hidden" name="valor" value="<?= $h['active'] ? '0' : '1' ?>"><button class="btn sec"><?= $h['active'] ? 'Desactivar' : 'Activar' ?></button></form></div>
<details><summary>Editar</summary><?php include __DIR__ . '/_hostform.php'; ?></details></div>
<?php endforeach; ?>
<div class="card"><h2 style="margin-top:0">Agregar hosting</h2><?php $h = ['id' => 0, 'name' => '', 'kind' => 'cpanel', 'cfg' => ['domain_root' => '', 'webs_path' => '', 'host' => 'localhost', 'port' => 2083, 'verify_ssl' => true]]; include __DIR__ . '/_hostform.php'; ?></div>
