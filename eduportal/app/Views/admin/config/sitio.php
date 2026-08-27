<div class="pagina-cab">
  <div><h1>Sitio web público</h1><p class="pagina-cab__sub">Portada, contenidos, galería y SEO</p></div>
  <div class="acciones">
    <a href="<?= e(url('/')) ?>" class="btn btn--linea" target="_blank" rel="noopener"><?= icono('ver', 17) ?> Ver sitio</a>
    <a href="<?= e(url('configuracion')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a>
  </div>
</div>

<form method="post" enctype="multipart/form-data" action="<?= e(url('configuracion/sitio')) ?>">
  <?= csrf_field() ?>
  <div class="split">
    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Portada</h2></div>
        <div class="campo">
          <label for="st-titulo">Título principal</label>
          <input type="text" id="st-titulo" name="sitio_hero_titulo" maxlength="180" value="<?= e($cfg['sitio_hero_titulo'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="st-texto">Texto de apoyo</label>
          <textarea id="st-texto" name="sitio_hero_texto" maxlength="500"><?= e($cfg['sitio_hero_texto'] ?? '') ?></textarea>
        </div>
        <div class="campo">
          <label for="st-img">Imagen de portada</label>
          <?php if (!empty($cfg['sitio_hero_imagen'])): ?>
            <img src="<?= e(archivo_url($cfg['sitio_hero_imagen'])) ?>" alt="" style="max-height:120px;border-radius:10px;margin-bottom:8px">
          <?php endif; ?>
          <input type="file" id="st-img" name="sitio_hero_imagen" accept="image/jpeg,image/png,image/webp">
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Misión y visión</h2></div>
        <div class="campo">
          <label for="st-mision">Misión</label>
          <textarea id="st-mision" name="sitio_mision" maxlength="3000"><?= e($cfg['sitio_mision'] ?? '') ?></textarea>
        </div>
        <div class="campo">
          <label for="st-vision">Visión</label>
          <textarea id="st-vision" name="sitio_vision" maxlength="3000"><?= e($cfg['sitio_vision'] ?? '') ?></textarea>
        </div>
        <div class="campo">
          <label for="st-mapa">Enlace del mapa (Google Maps embed)</label>
          <input type="url" id="st-mapa" name="sitio_mapa" maxlength="1000" value="<?= e($cfg['sitio_mapa'] ?? '') ?>"
                 placeholder="https://www.google.com/maps/embed?pb=...">
        </div>
      </div>
    </div>

    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>SEO</h2></div>
        <div class="campo">
          <label for="st-seo-t">Título (title)</label>
          <input type="text" id="st-seo-t" name="seo_title" maxlength="180" value="<?= e($cfg['seo_title'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="st-seo-d">Descripción (meta description)</label>
          <textarea id="st-seo-d" name="seo_description" maxlength="300"><?= e($cfg['seo_description'] ?? '') ?></textarea>
        </div>
        <div class="campo">
          <label for="st-og">Imagen para redes sociales (OG image)</label>
          <input type="file" id="st-og" name="seo_og" accept="image/jpeg,image/png,image/webp">
        </div>
        <p class="sm txt-3">Mapa del sitio: <a href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener">sitemap.xml</a></p>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Visibilidad</h2></div>
        <label class="check"><input type="checkbox" name="sitio_activo" value="1"
          <?= ($cfg['sitio_activo'] ?? '1') === '1' ? 'checked' : '' ?>> Sitio público visible</label>
        <label class="check"><input type="checkbox" name="sitio_inscripcion" value="1"
          <?= ($cfg['sitio_inscripcion'] ?? '1') === '1' ? 'checked' : '' ?>> Permitir pre-inscripción en línea</label>
      </div>

      <button type="submit" class="btn btn--bloque"><?= icono('check', 17) ?> Guardar sitio</button>
    </div>
  </div>
</form>

<div class="split mt-5">
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Galería</h2></div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('configuracion/sitio/galeria')) ?>" class="mb-4">
      <?= csrf_field() ?>
      <div class="fila">
        <div class="campo"><label for="gal-titulo">Título</label>
          <input type="text" id="gal-titulo" name="titulo" maxlength="160"></div>
        <div class="campo"><label for="gal-orden">Orden</label>
          <input type="number" id="gal-orden" name="orden" min="0" max="99" value="0"></div>
      </div>
      <div class="campo"><label for="gal-img">Imagen</label>
        <input type="file" id="gal-img" name="imagen" required accept="image/jpeg,image/png,image/webp"></div>
      <button type="submit" class="btn btn--linea"><?= icono('subir', 17) ?> Agregar imagen</button>
    </form>
    <div class="galeria">
      <?php foreach ($galeria as $g): ?>
        <figure style="position:relative">
          <img src="<?= e(archivo_url($g['archivo'])) ?>" alt="<?= e($g['titulo'] ?? '') ?>" loading="lazy">
          <form method="post" action="<?= e(url('configuracion/sitio/galeria/' . (int)$g['id'] . '/eliminar')) ?>"
                data-confirmar="¿Eliminar esta imagen?" style="position:absolute;top:6px;right:6px">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--peligro btn--sm" aria-label="Eliminar"><?= icono('borrar', 14) ?></button>
          </form>
        </figure>
      <?php endforeach; ?>
      <?php if ($galeria === []): ?><p class="sm txt-3">Sin imágenes en la galería.</p><?php endif; ?>
    </div>
  </div>

  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Páginas de contenido</h2></div>
    <form method="post" action="<?= e(url('configuracion/sitio/pagina')) ?>">
      <?= csrf_field() ?>
      <div class="fila">
        <div class="campo"><label for="pg-slug">Identificador <span class="oro">*</span></label>
          <input type="text" id="pg-slug" name="slug" required maxlength="60" pattern="[a-z0-9-]{2,60}" placeholder="mision"></div>
        <div class="campo"><label for="pg-titulo">Título <span class="oro">*</span></label>
          <input type="text" id="pg-titulo" name="titulo" required maxlength="160"></div>
      </div>
      <div class="campo"><label for="pg-contenido">Contenido</label>
        <textarea id="pg-contenido" name="contenido" maxlength="20000"></textarea></div>
      <label class="check"><input type="checkbox" name="activo" value="1" checked> Página activa</label>
      <button type="submit" class="btn btn--linea"><?= icono('check', 17) ?> Guardar página</button>
    </form>
    <div class="tabla-env mt-4">
      <table class="tabla" style="min-width:auto">
        <thead><tr><th>Identificador</th><th>Título</th><th class="cen">Activa</th></tr></thead>
        <tbody>
        <?php foreach ($paginas as $p): ?>
          <tr><td class="sm"><code><?= e($p['slug']) ?></code></td><td class="sm"><?= e($p['titulo']) ?></td>
            <td class="cen"><?= (int)$p['activo'] === 1 ? 'Sí' : 'No' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
