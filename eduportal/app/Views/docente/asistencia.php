<?php
$alumnos = $datos['alumnos'];
$registro = $datos['registro'];
$etiquetas = ['presente' => ['P', 'p'], 'ausente' => ['A', 'a'], 'tarde' => ['T', 't'], 'justificado' => ['J', 'j']];
?>
<div class="pagina-cab">
  <div><h1>Pase de lista</h1>
    <p class="pagina-cab__sub"><?= e($seccion['etiqueta']) ?> · <?= e(dia_nombre($fecha)) ?> <?= e(fecha($fecha)) ?> · <?= count($alumnos) ?> alumnos</p></div>
  <div class="acciones">
    <form method="get" class="flex" style="gap:8px">
      <input type="date" name="fecha" value="<?= e($fecha) ?>" max="<?= e(hoy()) ?>" data-auto-envio aria-label="Fecha">
    </form>
    <a href="<?= e(url('asistencia')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a>
  </div>
</div>

<div class="flex flex--envuelve mb-4" style="gap:6px">
  <span class="sm txt-3">Marcar todos como:</span>
  <?php foreach (['presente' => 'Presentes', 'ausente' => 'Ausentes'] as $k => $v): ?>
    <button type="button" class="btn btn--linea btn--sm" data-marcar-todos="<?= e($k) ?>"><?= e($v) ?></button>
  <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('asistencia/' . (int)$seccion['id'])) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
  <div class="lista-asistencia">
    <?php foreach ($alumnos as $al): $actual = $registro[(int)$al['id']]['estado'] ?? 'presente'; ?>
      <div class="asis-fila">
        <?php if (!empty($al['foto'])): ?>
          <img class="avatar" src="<?= e(archivo_url($al['foto'])) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="avatar iniciales"><?= e(mb_strtoupper(mb_substr($al['nombres'], 0, 1) . mb_substr($al['apellidos'], 0, 1))) ?></span>
        <?php endif; ?>
        <div class="asis-fila__nombre"><?= e($al['nombre_completo']) ?>
          <div class="xs txt-3"><?= e($al['codigo']) ?></div></div>
        <div class="asis-opciones" role="radiogroup" aria-label="Asistencia de <?= e($al['nombre_completo']) ?>">
          <?php foreach ($etiquetas as $valor => [$letra, $clase]): ?>
            <input type="radio" id="a<?= (int)$al['id'] ?>-<?= e($valor) ?>" name="estado[<?= (int)$al['id'] ?>]"
                   value="<?= e($valor) ?>" <?= $actual === $valor ? 'checked' : '' ?>>
            <label class="<?= e($clase) ?>" for="a<?= (int)$al['id'] ?>-<?= e($valor) ?>"><?= e(ucfirst($valor)) ?></label>
          <?php endforeach; ?>
        </div>
        <input type="text" name="nota[<?= (int)$al['id'] ?>]" maxlength="180" placeholder="Observación (opcional)"
               value="<?= e($registro[(int)$al['id']]['nota'] ?? '') ?>" style="flex:1 1 180px;min-width:150px"
               aria-label="Observación para <?= e($al['nombre_completo']) ?>">
      </div>
    <?php endforeach; ?>
    <?php if ($alumnos === []): ?>
      <div class="tarjeta vacio"><?= icono('alumnos', 44) ?><p>El grupo no tiene alumnos inscritos.</p></div>
    <?php endif; ?>
  </div>
  <?php if ($alumnos !== []): ?>
    <button type="submit" class="btn mt-4"><?= icono('check', 17) ?> Guardar asistencia</button>
  <?php endif; ?>
</form>
