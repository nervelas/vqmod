<?php use App\Core\Auth; ?>
<div class="pagina-cab">
  <div>
    <h1><?= e(App\Models\Alumno::nombre($alumno)) ?></h1>
    <p class="pagina-cab__sub">
      Código <strong><?= e($alumno['codigo']) ?></strong> ·
      <?= e($alumno['grupo'] ?? 'Sin grado asignado') ?> ·
      <span class="badge badge--<?= e(estado_badge((string)$alumno['estado'])) ?>"><?= e(ucfirst((string)$alumno['estado'])) ?></span>
    </p>
  </div>
  <div class="acciones">
    <a href="<?= e(url('alumnos/' . (int)$alumno['id'] . '/carne')) ?>" class="btn btn--linea" target="_blank" rel="noopener"><?= icono('recibo', 17) ?> Carné</a>
    <a href="<?= e(url('boleta/' . (int)$alumno['id'])) ?>" class="btn btn--linea" target="_blank" rel="noopener"><?= icono('notas', 17) ?> Boleta</a>
    <?php if (Auth::can('cobranza.ver')): ?>
      <a href="<?= e(url('cobranza/estado/' . (int)$alumno['id'])) ?>" class="btn btn--linea"><?= icono('dinero', 17) ?> Estado de cuenta</a>
    <?php endif; ?>
    <?php if (Auth::can('alumnos.editar')): ?>
      <a href="<?= e(url('alumnos/' . (int)$alumno['id'] . '/editar')) ?>" class="btn"><?= icono('editar', 17) ?> Editar</a>
    <?php endif; ?>
  </div>
</div>

<div class="split">
  <div class="col">
    <?php if ($cuenta !== null): ?>
      <div class="rejilla rejilla--3">
        <div class="kpi"><div class="kpi__etq">Saldo pendiente</div>
          <div class="kpi__valor"><?= e(moneda($cuenta['saldo'])) ?></div></div>
        <div class="kpi"><div class="kpi__etq">Vencido</div>
          <div class="kpi__valor" style="color:<?= $cuenta['vencido'] > 0 ? 'var(--bad)' : 'inherit' ?>"><?= e(moneda($cuenta['vencido'])) ?></div></div>
        <div class="kpi"><div class="kpi__etq">Pagado en el ciclo</div>
          <div class="kpi__valor"><?= e(moneda($cuenta['pagado'])) ?></div></div>
      </div>
    <?php endif; ?>

    <div class="tarjeta tarjeta--plana">
      <div class="tarjeta__cab"><h2>Calificaciones del ciclo</h2></div>
      <div class="tabla-env" tabindex="0" style="border:0">
        <table class="tabla">
          <thead>
            <tr><th>Materia</th>
              <?php foreach ($boleta['periodos'] as $p): ?><th class="cen"><?= e($p['nombre']) ?></th><?php endforeach; ?>
              <th class="cen">Promedio</th></tr>
          </thead>
          <tbody>
            <?php foreach ($boleta['materias'] as $m): ?>
              <tr>
                <td><?= e($m['materia']) ?><div class="xs txt-3"><?= e($m['docente'] ?? '') ?></div></td>
                <?php foreach ($boleta['periodos'] as $p): ?>
                  <?php $n = $m['periodos'][(int)$p['id']] ?? null; ?>
                  <td class="cen <?= $n ? e(nota_clase((float)$n['total'])) : '' ?>">
                    <?= $n && $n['total'] !== null ? e(number_format((float)$n['total'], 2)) : '—' ?>
                  </td>
                <?php endforeach; ?>
                <td class="cen negrita <?= $m['promedio'] !== null ? e(nota_clase((float)$m['promedio'])) : '' ?>">
                  <?= $m['promedio'] !== null ? e(number_format((float)$m['promedio'], 2)) : '—' ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($boleta['materias'] === []): ?>
              <tr><td colspan="<?= count($boleta['periodos']) + 2 ?>" class="tabla__vacio">Sin materias asignadas al grado.</td></tr>
            <?php else: ?>
              <tr><td class="negrita">Promedio general</td>
                <td colspan="<?= count($boleta['periodos']) ?>"></td>
                <td class="cen negrita <?= e(nota_clase((float)$boleta['promedio'])) ?>"><?= e(number_format((float)$boleta['promedio'], 2)) ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Encargados</h2>
        <?php if (Auth::can('encargados.editar') && count($encargados) < 3): ?>
          <button type="button" class="btn btn--sm" data-modal="modal-encargado"
                  data-valores='{"encargado_id":"","nombre":"","parentesco":"","telefono":"","email":"","dpi":""}'>
            <?= icono('mas', 15) ?> Agregar
          </button>
        <?php endif; ?>
      </div>
      <?php if ($encargados === []): ?>
        <div class="vacio sm"><?= icono('usuarios', 40) ?><p>No hay encargados registrados.</p></div>
      <?php else: ?>
        <div class="pila">
          <?php foreach ($encargados as $en): ?>
            <div class="cargo-fila">
              <div class="cargo-fila__desc">
                <strong><?= e($en['nombre']) ?></strong>
                <?php if ((int)$en['principal'] === 1): ?><span class="badge badge--oro">Principal</span><?php endif; ?>
                <?php if (empty($en['user_id'])): ?><span class="badge badge--mute">Sin acceso</span><?php endif; ?>
                <div class="sm txt-2">
                  <?= e($en['parentesco'] ?? '') ?>
                  <?php if (!empty($en['telefono'])): ?> · <?= e($en['telefono']) ?><?php endif; ?>
                  <?php if (!empty($en['email'])): ?> · <?= e($en['email']) ?><?php endif; ?>
                </div>
              </div>
              <div class="flex" style="gap:4px">
                <?php if (!empty($en['telefono'])): ?>
                  <a class="btn btn--fantasma btn--sm" target="_blank" rel="noopener"
                     href="<?= e(wa_link((string)$en['telefono'], 'Estimado/a ' . $en['nombre'] . ', le escribimos del colegio.')) ?>"
                     title="WhatsApp"><?= icono('whatsapp', 16) ?></a>
                <?php endif; ?>
                <?php if (Auth::can('encargados.editar')): ?>
                  <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="modal-encargado"
                    data-valores='<?= e(json_encode([
                        'encargado_id' => (string)$en['id'], 'nombre' => $en['nombre'],
                        'parentesco' => $en['parentesco'], 'telefono' => $en['telefono'],
                        'email' => $en['email'], 'dpi' => $en['dpi'],
                        'principal' => (string)$en['principal'],
                    ], JSON_UNESCAPED_UNICODE)) ?>' title="Editar"><?= icono('editar', 16) ?></button>
                  <form method="post" action="<?= e(url('encargado/' . (int)$en['id'] . '/eliminar')) ?>"
                        data-confirmar="¿Eliminar a este encargado?" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--fantasma btn--sm" title="Eliminar"><?= icono('borrar', 16) ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col">
    <div class="tarjeta cen">
      <?php if (!empty($alumno['foto'])): ?>
        <img class="avatar avatar--xl" style="margin:0 auto" src="<?= e(archivo_url($alumno['foto'])) ?>" alt="">
      <?php else: ?>
        <span class="avatar avatar--xl iniciales" style="margin:0 auto;font-size:2rem">
          <?= e(mb_strtoupper(mb_substr($alumno['nombres'], 0, 1) . mb_substr($alumno['apellidos'], 0, 1))) ?></span>
      <?php endif; ?>
      <h3 class="mt-3 mb-0"><?= e(App\Models\Alumno::nombre($alumno)) ?></h3>
      <p class="sm txt-2"><?= e($alumno['grupo'] ?? '') ?></p>
      <img class="mt-3" style="width:132px;margin:0 auto;border-radius:10px"
           src="<?= e(url('alumnos/' . (int)$alumno['id'] . '/qr')) ?>" alt="Código QR del alumno" loading="lazy">
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h3>Información</h3></div>
      <dl class="sm" style="margin:0">
        <div class="flex flex--sep"><dt class="txt-3">Nacimiento</dt><dd style="margin:0"><?= e(fecha($alumno['fecha_nacimiento'] ?? '') ?: '—') ?></dd></div>
        <div class="flex flex--sep"><dt class="txt-3">DPI / CUI</dt><dd style="margin:0"><?= e($alumno['dpi'] ?? '—') ?></dd></div>
        <div class="flex flex--sep"><dt class="txt-3">Partida</dt><dd style="margin:0"><?= e($alumno['partida'] ?? '—') ?></dd></div>
        <div class="flex flex--sep"><dt class="txt-3">Beca</dt><dd style="margin:0"><?= e(number_format((float)($alumno['beca_pct'] ?? 0), 2)) ?>%</dd></div>
        <div class="flex flex--sep"><dt class="txt-3">Emergencia</dt><dd style="margin:0"><?= e($alumno['emergencia_tel'] ?? '—') ?></dd></div>
      </dl>
      <?php if (!empty($alumno['alergias'])): ?>
        <div class="aviso aviso--warn mt-3"><?= icono('escudo', 18) ?><span><?= e($alumno['alergias']) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h3>Historial académico</h3></div>
      <?php if ($historial === []): ?>
        <p class="sm txt-3">Sin inscripciones registradas.</p>
      <?php else: ?>
        <div class="pila sm">
          <?php foreach ($historial as $h): ?>
            <div class="flex flex--sep">
              <span><strong><?= e($h['ciclo']) ?></strong> · <?= e($h['grado'] . ' ' . $h['seccion']) ?></span>
              <span class="badge badge--<?= e(estado_badge((string)$h['estado'])) ?>"><?= e(ucfirst((string)$h['estado'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h3>Documentos</h3></div>
      <?php if ($documentos === []): ?>
        <p class="sm txt-3">Sin documentos adjuntos.</p>
      <?php else: ?>
        <div class="pila sm">
          <?php foreach ($documentos as $d): ?>
            <div class="flex flex--sep">
              <a href="<?= e(archivo_url($d['archivo'])) ?>" target="_blank" rel="noopener" class="truncar"><?= e($d['nombre']) ?></a>
              <?php if (Auth::can('alumnos.editar')): ?>
                <form method="post" action="<?= e(url('documento/' . (int)$d['id'] . '/eliminar')) ?>"
                      data-confirmar="¿Eliminar este documento?" style="display:inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 15) ?></button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (Auth::can('alumnos.editar')): ?>
        <form method="post" enctype="multipart/form-data" class="mt-3"
              action="<?= e(url('alumnos/' . (int)$alumno['id'] . '/documento')) ?>">
          <?= csrf_field() ?>
          <div class="campo">
            <label for="doc-nombre">Nombre del documento</label>
            <input type="text" id="doc-nombre" name="nombre" maxlength="160" placeholder="Partida de nacimiento">
          </div>
          <div class="campo">
            <label for="doc-archivo">Archivo</label>
            <input type="file" id="doc-archivo" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.csv">
          </div>
          <button type="submit" class="btn btn--linea btn--sm btn--bloque"><?= icono('subir', 15) ?> Adjuntar</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (App\Core\Auth::is('superadmin')): ?>
      <form method="post" action="<?= e(url('alumnos/' . (int)$alumno['id'] . '/eliminar')) ?>"
            data-confirmar="Esta acción no se puede deshacer. ¿Eliminar al alumno?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--peligro btn--bloque"><?= icono('borrar', 17) ?> Eliminar alumno</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (Auth::can('encargados.editar')): ?>
<div class="modal" id="modal-encargado" aria-hidden="true" role="dialog" aria-label="Encargado">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('alumnos/' . (int)$alumno['id'] . '/encargado')) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab">
        <h3>Encargado</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button>
      </div>
      <div class="modal__cuerpo">
        <input type="hidden" name="encargado_id" value="">
        <div class="campo">
          <label for="en-nombre">Nombre completo <span class="oro">*</span></label>
          <input type="text" id="en-nombre" name="nombre" required maxlength="140">
        </div>
        <div class="fila">
          <div class="campo">
            <label for="en-parentesco">Parentesco</label>
            <input type="text" id="en-parentesco" name="parentesco" maxlength="40" placeholder="Madre, padre, tío…">
          </div>
          <div class="campo">
            <label for="en-telefono">Teléfono</label>
            <input type="tel" id="en-telefono" name="telefono" maxlength="40">
          </div>
        </div>
        <div class="fila">
          <div class="campo">
            <label for="en-email">Correo electrónico</label>
            <input type="email" id="en-email" name="email" maxlength="160">
          </div>
          <div class="campo">
            <label for="en-dpi">DPI</label>
            <input type="text" id="en-dpi" name="dpi" maxlength="30">
          </div>
        </div>
        <label class="check"><input type="checkbox" name="principal" value="1"> Es el encargado principal</label>
        <label class="check"><input type="checkbox" name="crear_acceso" value="1" checked>
          Crear acceso al portal y enviar la contraseña por correo</label>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Guardar encargado</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
