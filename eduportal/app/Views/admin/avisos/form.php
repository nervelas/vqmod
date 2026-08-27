<?php $esNuevo = empty($aviso['id']); ?>
<div class="pagina-cab">
  <div><h1><?= e($titulo) ?></h1><p class="pagina-cab__sub">Defina el contenido y a quién va dirigido</p></div>
  <div class="acciones"><a href="<?= e(url('avisos')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<form method="post" enctype="multipart/form-data"
      action="<?= e(url($esNuevo ? 'avisos' : 'avisos/' . (int)$aviso['id'])) ?>">
  <?= csrf_field() ?>
  <div class="split">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Contenido</h2></div>
      <div class="campo">
        <label for="av-titulo">Título <span class="oro">*</span></label>
        <input type="text" id="av-titulo" name="titulo" required maxlength="180" value="<?= e($aviso['titulo'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="av-contenido">Mensaje <span class="oro">*</span></label>
        <textarea id="av-contenido" name="contenido" required minlength="5" maxlength="20000" style="min-height:220px"><?= e($aviso['contenido'] ?? '') ?></textarea>
        <p class="ayuda">Puede usar etiquetas simples: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;a&gt;.</p>
      </div>
      <div class="fila">
        <div class="campo">
          <label for="av-imagen">Imagen destacada</label>
          <input type="file" id="av-imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="campo">
          <label for="av-adjunto">Archivo adjunto</label>
          <input type="file" id="av-adjunto" name="adjunto" accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.csv">
        </div>
      </div>
    </div>

    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Destinatarios</h2></div>
        <div class="campo">
          <label for="av-destino">Dirigido a <span class="oro">*</span></label>
          <select id="av-destino" name="destino" required>
            <?php foreach (['todos' => 'Todo el colegio', 'nivel' => 'Un nivel', 'grado' => 'Un grado',
                            'seccion' => 'Una sección', 'alumno' => 'Un alumno', 'rol' => 'Un rol'] as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= ($aviso['destino'] ?? 'todos') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="av-destino-id">Nivel, grado o sección</label>
          <select id="av-destino-id" name="destino_id">
            <option value="">—</option>
            <optgroup label="Niveles">
              <?php foreach ($niveles as $n): ?>
                <option value="<?= (int)$n['id'] ?>" <?= ($aviso['destino'] ?? '') === 'nivel' && (int)($aviso['destino_id'] ?? 0) === (int)$n['id'] ? 'selected' : '' ?>><?= e($n['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Grados">
              <?php foreach ($grados as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= ($aviso['destino'] ?? '') === 'grado' && (int)($aviso['destino_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Secciones">
              <?php foreach ($secciones as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= ($aviso['destino'] ?? '') === 'seccion' && (int)($aviso['destino_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['etiqueta']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          </select>
          <p class="ayuda">Si eligió "un alumno", escriba su ID numérico en este campo desde la ficha del alumno.</p>
        </div>
        <div class="campo">
          <label for="av-rol">Rol destinatario</label>
          <select id="av-rol" name="destino_rol">
            <option value="">—</option>
            <?php foreach (['padre', 'docente', 'secretaria', 'superadmin'] as $r): ?>
              <option value="<?= e($r) ?>" <?= ($aviso['destino_rol'] ?? '') === $r ? 'selected' : '' ?>><?= e(rol_nombre($r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Publicación</h2></div>
        <div class="campo">
          <label for="av-pub">Publicar desde</label>
          <input type="datetime-local" id="av-pub" name="publicar_en"
                 value="<?= e(!empty($aviso['publicar_en']) ? date('Y-m-d\TH:i', strtotime((string)$aviso['publicar_en'])) : date('Y-m-d\TH:i')) ?>">
        </div>
        <div class="campo">
          <label for="av-cad">Caduca el</label>
          <input type="datetime-local" id="av-cad" name="caduca_en"
                 value="<?= e(!empty($aviso['caduca_en']) ? date('Y-m-d\TH:i', strtotime((string)$aviso['caduca_en'])) : '') ?>">
        </div>
        <label class="check"><input type="checkbox" name="activo" value="1" <?= (int)($aviso['activo'] ?? 1) === 1 ? 'checked' : '' ?>> Aviso visible</label>
      </div>

      <button type="submit" class="btn btn--bloque"><?= icono('check', 17) ?> Publicar aviso</button>
    </div>
  </div>
</form>
