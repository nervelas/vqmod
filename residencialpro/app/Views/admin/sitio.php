<?php $v = static fn(string $k, string $d = '') => (string) ($a[$k] ?? $d); ?>
<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,360px)">
  <div class="columna">
    <form method="post" enctype="multipart/form-data">
      <?= csrf() ?>
      <input type="hidden" name="accion" value="contenido">
      <div class="tarjeta">
        <div class="tarjeta-cab">
          <h3>Contenido de la portada</h3>
          <a class="btn btn-sm btn-claro" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= ico('ojo', 15) ?> Ver el sitio</a>
        </div>
        <div class="tarjeta-cuerpo">
          <div class="campos">
            <div class="campo campo-ancho"><label for="titular">Titular principal</label>
              <input type="text" id="titular" name="titular" maxlength="190" value="<?= e($v('titular')) ?>"
                     placeholder="Vivir en Los Cipreses es vivir en calma"></div>
            <div class="campo campo-ancho"><label for="lema">Lema (arriba del titular)</label>
              <input type="text" id="lema" name="lema" maxlength="120" value="<?= e($v('lema')) ?>"></div>
            <div class="campo campo-ancho"><label for="descripcion">Descripción</label>
              <textarea id="descripcion" name="descripcion" rows="3" maxlength="600"><?= e($v('descripcion')) ?></textarea></div>
            <div class="campo"><label for="horario_semana">Horario de lunes a viernes</label>
              <input type="text" id="horario_semana" name="horario_semana" maxlength="60" value="<?= e($v('horario_semana', '8:00 a 17:00')) ?>"></div>
            <div class="campo"><label for="horario_sabado">Horario de sábado</label>
              <input type="text" id="horario_sabado" name="horario_sabado" maxlength="60" value="<?= e($v('horario_sabado', '8:00 a 12:00')) ?>"></div>
            <div class="campo campo-ancho"><label for="portada">Fotografía de portada</label>
              <input type="file" id="portada" name="portada" accept="image/*" data-previa="#previa-portada">
              <?php if ($v('portada') !== ''): ?>
                <img id="previa-portada" src="<?= e(subida($v('portada'), 'galeria')) ?>" alt="Fotografía de portada"
                     style="margin-top:10px;border-radius:var(--r-sm);max-height:160px">
              <?php else: ?>
                <img id="previa-portada" src="<?= e(url('/assets/img/vacio.svg')) ?>" alt="" hidden
                     style="margin-top:10px;border-radius:var(--r-sm);max-height:160px">
              <?php endif; ?></div>
          </div>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar contenido</button>
        </div>
      </div>
    </form>

    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Galería</h3><span class="chip oro"><?= count($galeria) ?> imágenes</span></div>
      <div class="tarjeta-cuerpo">
        <?php if ($galeria !== []): ?>
          <div class="galeria mb-3">
            <?php foreach ($galeria as $g): ?>
              <figure><img src="<?= e(subida($g['archivo'], 'galeria')) ?>" alt="<?= e($g['titulo'] ?? '') ?>" loading="lazy"></figure>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="fila envolver" style="gap:10px">
          <?= csrf() ?>
          <input type="hidden" name="accion" value="galeria">
          <label class="solo-lectores" for="gal-titulo">Título de la imagen</label>
          <input type="text" id="gal-titulo" name="titulo" placeholder="Título (opcional)" maxlength="140" class="crecer" style="min-width:180px">
          <label class="solo-lectores" for="gal-imagen">Imagen para la galería</label>
          <input type="file" id="gal-imagen" name="imagen" accept="image/*" required style="max-width:260px">
          <button class="btn btn-oro btn-sm" type="submit"><?= ico('subir', 15) ?> Agregar</button>
        </form>
      </div>
    </article>

    <?php if ($contactos !== []): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab"><h3>Mensajes recibidos desde el sitio</h3></div>
        <div class="tarjeta-cuerpo compacto">
          <ul class="lista-limpia">
            <?php foreach ($contactos as $c): ?>
              <li class="item-lista">
                <span class="avatar sm"><?= e(iniciales((string) $c['nombre'])) ?></span>
                <div class="crecer">
                  <b><?= e($c['nombre']) ?></b>
                  <div class="meta"><?= e($c['correo'] ?? '') ?> <?= !empty($c['telefono']) ? '· ' . e($c['telefono']) : '' ?> · <?= e(hace((string) $c['creado_en'])) ?></div>
                  <p style="margin:5px 0 0;font-size:.9rem;color:var(--texto-2)"><?= nl2br(e((string) $c['mensaje'])) ?></p>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </article>
    <?php endif; ?>
  </div>

  <form method="post" style="align-self:start">
    <?= csrf() ?>
    <input type="hidden" name="accion" value="amenidad">
    <input type="hidden" name="id" id="am-id" value="0">
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Amenidades</h3></div>
      <div class="tarjeta-cuerpo compacto">
        <?php if ($amenidades !== []): ?>
          <ul class="lista-limpia mb-2">
            <?php foreach ($amenidades as $am): ?>
              <li class="item-lista" style="padding:9px 0">
                <span style="color:var(--arcilla)"><?= ico((string) $am['icono'], 19) ?></span>
                <div class="crecer"><b style="font-size:.92rem"><?= e($am['titulo']) ?></b>
                  <div class="meta"><?= e(recortar((string) $am['detalle'], 44)) ?></div></div>
                <button class="btn btn-sm btn-fantasma" type="button" aria-label="Editar amenidad"
                        data-amenidad="<?= e(json_encode($am, JSON_UNESCAPED_UNICODE)) ?>"><?= ico('editar', 14) ?></button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="campo"><label for="am-titulo">Título</label>
          <input type="text" id="am-titulo" name="titulo" maxlength="140" placeholder="Seguridad 24 horas"></div>
        <div class="campo"><label for="am-detalle">Detalle</label>
          <input type="text" id="am-detalle" name="detalle" maxlength="255"></div>
        <div class="campo"><label for="am-icono">Icono</label>
          <select id="am-icono" name="icono">
            <?php foreach (['escudo' => 'Escudo', 'arbol' => 'Árbol', 'salvavidas' => 'Piscina', 'brillo' => 'Salón',
                            'estrella' => 'Cancha', 'gota' => 'Agua', 'rayo' => 'Energía', 'carro' => 'Parqueo',
                            'maletin' => 'Servicios', 'casa' => 'Vivienda'] as $k => $et): ?>
              <option value="<?= e($k) ?>"><?= e($et) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="campo"><label for="am-orden">Orden</label>
          <input type="number" id="am-orden" name="orden" min="0" value="<?= count($amenidades) + 1 ?>"></div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Guardar amenidad</button>
      </div>
    </div>
  </form>
</div>
<script<?= nonce() ?>>
document.querySelectorAll('[data-amenidad]').forEach(function (b) {
  b.addEventListener('click', function () {
    var am = JSON.parse(b.dataset.amenidad);
    document.getElementById('am-id').value = am.id;
    document.getElementById('am-titulo').value = am.titulo || '';
    document.getElementById('am-detalle').value = am.detalle || '';
    document.getElementById('am-icono').value = am.icono || 'brillo';
    document.getElementById('am-orden').value = am.orden || 0;
  });
});
</script>
