<div class="rejilla" style="grid-template-columns:minmax(0,380px) minmax(0,1fr)">
  <form method="post" enctype="multipart/form-data" style="align-self:start">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Reportar algo</h3></div>
      <div class="tarjeta-cuerpo">
        <p class="texto-2" style="font-size:.9rem">Una lámpara fundida, una fuga, un portón que falla: cuéntenos y le damos seguimiento.</p>
        <div class="campo">
          <label for="titulo">¿Qué ocurre? *</label>
          <input type="text" id="titulo" name="titulo" required maxlength="190" placeholder="Lámpara fundida frente a mi casa">
        </div>
        <div class="campo">
          <label for="categoria">Tipo</label>
          <select id="categoria" name="categoria">
            <?php foreach (['general' => 'General', 'alumbrado' => 'Alumbrado', 'agua' => 'Agua', 'seguridad' => 'Seguridad',
                            'jardineria' => 'Jardinería', 'limpieza' => 'Limpieza', 'ruido' => 'Ruido o convivencia',
                            'infraestructura' => 'Infraestructura'] as $k => $et): ?>
              <option value="<?= e($k) ?>"><?= e($et) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="ubicacion">¿Dónde?</label>
          <input type="text" id="ubicacion" name="ubicacion" maxlength="190" placeholder="Calle de los Cipreses, frente a A-14">
        </div>
        <div class="campo">
          <label for="prioridad">Urgencia</label>
          <select id="prioridad" name="prioridad">
            <option value="baja">Baja — puede esperar</option>
            <option value="media" selected>Media</option>
            <option value="alta">Alta — requiere atención pronta</option>
          </select>
        </div>
        <div class="campo">
          <label for="descripcion">Cuéntenos más *</label>
          <textarea id="descripcion" name="descripcion" rows="4" required minlength="10" maxlength="2000"></textarea>
        </div>
        <div class="campo">
          <label for="foto">Fotografía</label>
          <input type="file" id="foto" name="foto" accept="image/*" capture="environment" data-previa="#previa-inc">
          <img id="previa-inc" src="<?= e(url('/assets/img/vacio.svg')) ?>" alt="" hidden style="margin-top:10px;border-radius:var(--r-sm);max-height:160px">
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-bloque" type="submit"><?= ico('enviar', 17) ?> Enviar reporte</button>
      </div>
    </div>
  </form>

  <div class="columna">
    <?php if ($incidencias === []): ?>
      <div class="tarjeta"><div class="vacio"><?= ico('llave_inglesa', 44) ?>
        <h3>Sin reportes</h3><p>Cuando reporte algo, aquí verá el avance.</p></div></div>
    <?php endif; ?>
    <?php foreach ($incidencias as $i): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab">
          <div>
            <h3 style="margin:0;font-size:1.02rem"><?= e($i['titulo']) ?></h3>
            <div class="texto-3" style="font-size:.8rem">
              <?= e(ucfirst((string) $i['categoria'])) ?> · <?= e(hace((string) $i['creado_en'])) ?>
            </div>
          </div>
          <span class="chip <?= e(estadoBadge((string) $i['estado'])) ?>"><?= e(ucfirst((string) $i['estado'])) ?></span>
        </div>
        <div class="tarjeta-cuerpo compacto">
          <p class="texto-2" style="font-size:.92rem;margin-bottom:8px"><?= nl2br(e((string) $i['descripcion'])) ?></p>
          <?php if (!empty($i['foto'])): ?>
            <img src="<?= e(url('/archivo/incidencias/' . $i['foto'])) ?>" alt="" style="border-radius:var(--r-sm);max-height:200px">
          <?php endif; ?>
          <?php if (!empty($i['resolucion'])): ?>
            <div class="aviso-caja ok mt-2" style="font-size:.88rem">
              <?= ico('checkCirculo', 18) ?><div><strong>Respuesta de la administración</strong><?= e($i['resolucion']) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
