<div class="pagina-cab">
  <div><h1>Avisos y comunicados</h1><p class="pagina-cab__sub">Publique información dirigida a toda la comunidad educativa</p></div>
  <div class="acciones"><a href="<?= e(url('avisos/nuevo')) ?>" class="btn"><?= icono('mas', 17) ?> Nuevo aviso</a></div>
</div>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Título</th><th>Destinatario</th><th>Publicación</th><th class="cen">Lecturas</th><th class="cen">Estado</th><th class="cen">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($avisos as $a): ?>
      <tr>
        <td><a href="<?= e(url('avisos/' . (int)$a['id'])) ?>"><strong><?= e($a['titulo']) ?></strong></a>
          <div class="xs txt-3">Por <?= e($a['autor'] ?? 'Sistema') ?></div></td>
        <td class="sm txt-2"><?= e(ucfirst((string)$a['destino'])) ?><?= $a['destino_rol'] ? ' · ' . e(rol_nombre((string)$a['destino_rol'])) : '' ?></td>
        <td class="sm"><?= e(fecha_hora($a['publicar_en'] ?? $a['creado_en'])) ?>
          <?php if (!empty($a['caduca_en'])): ?><div class="xs txt-3">Caduca <?= e(fecha((string)$a['caduca_en'])) ?></div><?php endif; ?></td>
        <td class="cen"><?= (int)$a['lecturas'] ?></td>
        <td class="cen"><span class="badge badge--<?= (int)$a['activo'] === 1 ? 'ok' : 'mute' ?>"><?= (int)$a['activo'] === 1 ? 'Activo' : 'Oculto' ?></span></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <a class="btn btn--fantasma btn--sm" href="<?= e(url('avisos/' . (int)$a['id'] . '/editar')) ?>"><?= icono('editar', 16) ?></a>
            <form method="post" action="<?= e(url('avisos/' . (int)$a['id'] . '/eliminar')) ?>"
                  data-confirmar="¿Eliminar este aviso?" style="display:inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($avisos === []): ?>
      <tr><td colspan="6" class="tabla__vacio"><?= icono('aviso', 40) ?><p>Aún no hay avisos publicados.</p></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
