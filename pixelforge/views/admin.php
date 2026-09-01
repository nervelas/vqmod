<?php
/** Panel de administración. */
$titulo = 'PixelForge — panel';
require PF_VIEWS . '/cabecera.php';
?>
<div class="envoltorio">
    <header class="barra">
        <div class="marca">
            <strong>PIXEL<em>FORGE</em></strong>
            <span>panel de administración</span>
        </div>
        <nav>
            <span class="chip"><?= Support::e($engine === 'none' ? 'sin GD/Imagick' : $engine) ?> · <?= Support::e($driver) ?> · <?= (int) $totalImagenes ?> imágenes</span>
            <a class="boton menudo" href="<?= Support::e($base) ?>/index.php">Volver al estudio</a>
            <a class="boton menudo" href="<?= Support::e($base) ?>/index.php?action=logout&amp;csrf=<?= Support::e($csrf) ?>">Salir</a>
        </nav>
    </header>

    <?php if ($mensaje !== '') : ?>
        <div class="aviso <?= Support::e($tipo) ?>"><?= Support::e($mensaje) ?></div>
    <?php endif; ?>

    <main class="estudio" style="grid-template-columns:1fr">
        <form method="post" action="<?= Support::e($base) ?>/admin/index.php">
            <input type="hidden" name="csrf" value="<?= Support::e($csrf) ?>">
            <input type="hidden" name="accion" value="guardar">

            <section class="panel">
                <div class="titulo-seccion">
                    <h2>Proveedores</h2>
                    <span class="etiqueta-mono">orden de respaldo</span>
                </div>
                <p class="ayuda" style="margin-bottom:16px">
                    Se intenta con el número 1; si falla o agota su cuota, se pasa automáticamente al siguiente.
                    Las API keys se guardan cifradas y nunca llegan al navegador.
                </p>
                <ul class="orden-proveedores">
                    <?php foreach ($proveedores as $p) : ?>
                        <li>
                            <div class="posicion">
                                <input type="number" name="orden_<?= Support::e($p['id']) ?>" value="<?= (int) $p['posicion'] ?>" min="1" max="99" aria-label="Orden de <?= Support::e($p['label']) ?>">
                            </div>
                            <div class="nombre">
                                <?= Support::e($p['label']) ?>
                                <div class="ayuda">
                                    <?= $p['requires_key'] ? 'Necesita API key gratuita.' : 'Sin API key: funciona de inmediato.' ?>
                                    <?= $p['negative'] ? ' Admite prompt negativo.' : ' No admite prompt negativo.' ?>
                                </div>
                            </div>
                            <label class="interruptor" style="flex:0 0 auto" for="activo_<?= Support::e($p['id']) ?>">
                                <input type="checkbox" id="activo_<?= Support::e($p['id']) ?>" name="activo_<?= Support::e($p['id']) ?>" value="1" <?= $p['enabled'] ? 'checked' : '' ?>>
                                <span class="pista"></span>
                                <span class="texto">Activo</span>
                            </label>
                            <div style="flex:1 1 220px">
                                <label for="modelo_<?= Support::e($p['id']) ?>">Modelo</label>
                                <input type="text" id="modelo_<?= Support::e($p['id']) ?>" name="modelo_<?= Support::e($p['id']) ?>" value="<?= Support::e($p['model']) ?>">
                            </div>
                            <div style="flex:1 1 240px">
                                <label for="key_<?= Support::e($p['id']) ?>">
                                    API key <?= $p['requires_key'] ? '' : '(opcional)' ?>
                                    <?= $p['mask'] !== '' ? ' — guardada: ' . Support::e($p['mask']) : '' ?>
                                </label>
                                <input type="password" id="key_<?= Support::e($p['id']) ?>" name="key_<?= Support::e($p['id']) ?>" autocomplete="off" placeholder="<?= $p['mask'] !== '' ? 'Déjalo vacío para conservarla' : 'Pega aquí tu key' ?>">
                                <?php if ($p['mask'] !== '') : ?>
                                    <label class="ayuda" style="letter-spacing:0;text-transform:none;margin-top:6px">
                                        <input type="checkbox" name="borrar_key_<?= Support::e($p['id']) ?>" value="1" style="width:auto;margin-right:6px">
                                        Borrar la key guardada
                                    </label>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="panel">
                <div class="titulo-seccion">
                    <h2>Generación</h2>
                    <span class="etiqueta-mono">valores por defecto</span>
                </div>
                <div class="campo">
                    <label for="realism_suffix">Sufijo de «Potenciar realismo»</label>
                    <textarea id="realism_suffix" name="realism_suffix" maxlength="600" style="min-height:96px"><?= Support::e($settings->get('realism_suffix')) ?></textarea>
                    <p class="ayuda">Solo se añade al prompt cuando el interruptor está activado en el estudio.</p>
                </div>
                <div class="campo dos-columnas">
                    <div>
                        <label for="default_format">Formato por defecto</label>
                        <select id="default_format" name="default_format">
                            <?php foreach (['png' => 'PNG', 'jpg' => 'JPG', 'webp' => 'WEBP'] as $valor => $etiqueta) : ?>
                                <option value="<?= $valor ?>" <?= $settings->get('default_format') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="rate_limit_hour">Límite de generaciones por hora (0 = sin límite)</label>
                        <input type="number" id="rate_limit_hour" name="rate_limit_hour" min="0" max="1000" value="<?= (int) $settings->int('rate_limit_hour') ?>">
                    </div>
                    <div>
                        <label for="default_width">Ancho por defecto</label>
                        <input type="number" id="default_width" name="default_width" min="64" max="4096" value="<?= (int) $settings->int('default_width') ?>">
                    </div>
                    <div>
                        <label for="default_height">Alto por defecto</label>
                        <input type="number" id="default_height" name="default_height" min="64" max="4096" value="<?= (int) $settings->int('default_height') ?>">
                    </div>
                    <div>
                        <label for="http_timeout">Tiempo máximo por petición (s)</label>
                        <input type="number" id="http_timeout" name="http_timeout" min="20" max="180" value="<?= (int) $settings->int('http_timeout') ?>">
                    </div>
                    <div>
                        <label for="http_retries">Reintentos por proveedor</label>
                        <input type="number" id="http_retries" name="http_retries" min="1" max="5" value="<?= (int) $settings->int('http_retries') ?>">
                    </div>
                    <div>
                        <label for="keep_history">Imágenes guardadas en el historial</label>
                        <input type="number" id="keep_history" name="keep_history" min="20" max="5000" value="<?= (int) $settings->int('keep_history') ?>">
                    </div>
                </div>
                <div class="campo">
                    <div class="grupo-opciones">
                        <label class="interruptor" for="pollinations_nologo">
                            <input type="checkbox" id="pollinations_nologo" name="pollinations_nologo" value="1" <?= $settings->bool('pollinations_nologo') ? 'checked' : '' ?>>
                            <span class="pista"></span>
                            <span class="texto">Pollinations sin marca<small>Pide la imagen sin logo cuando el proveedor lo permite.</small></span>
                        </label>
                        <label class="interruptor" for="pollinations_private">
                            <input type="checkbox" id="pollinations_private" name="pollinations_private" value="1" <?= $settings->bool('pollinations_private') ? 'checked' : '' ?>>
                            <span class="pista"></span>
                            <span class="texto">Pollinations en privado<small>Evita que la imagen aparezca en su galería pública.</small></span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="boton principal">Guardar ajustes</button>
            </section>
        </form>

        <section class="panel">
            <div class="titulo-seccion">
                <h2>Uso por proveedor</h2>
                <span class="etiqueta-mono">últimos 7 días</span>
            </div>
            <?php if (!$uso) : ?>
                <p class="ayuda">Todavía no hay generaciones registradas.</p>
            <?php else : ?>
                <table class="tabla">
                    <thead>
                        <tr><th>Día</th><?php foreach (array_keys(ProviderRegistry::catalog()) as $id) : ?><th><?= Support::e($id) ?></th><?php endforeach; ?><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uso as $dia => $porProveedor) : ?>
                            <tr>
                                <td><?= Support::e((string) $dia) ?></td>
                                <?php $total = 0; ?>
                                <?php foreach (array_keys(ProviderRegistry::catalog()) as $id) : ?>
                                    <?php $n = (int) ($porProveedor[$id] ?? 0); $total += $n; ?>
                                    <td><?= $n ?></td>
                                <?php endforeach; ?>
                                <td><strong><?= $total ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="titulo-seccion">
                <h2>Contraseña</h2>
                <span class="etiqueta-mono">acceso</span>
            </div>
            <form method="post" action="<?= Support::e($base) ?>/admin/index.php" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= Support::e($csrf) ?>">
                <input type="hidden" name="accion" value="password">
                <div class="campo dos-columnas">
                    <div>
                        <label for="password_actual">Contraseña actual</label>
                        <input type="password" id="password_actual" name="password_actual" required autocomplete="current-password">
                    </div>
                    <div>
                        <label for="password_nueva">Nueva contraseña</label>
                        <input type="password" id="password_nueva" name="password_nueva" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="password_repetir">Repite la nueva</label>
                        <input type="password" id="password_repetir" name="password_repetir" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="boton">Cambiar contraseña</button>
            </form>
        </section>

        <section class="panel">
            <div class="titulo-seccion">
                <h2>Servidor</h2>
                <span class="etiqueta-mono">diagnóstico</span>
            </div>
            <ul class="lista-chequeo">
                <?php foreach ($checks as $check) : ?>
                    <li>
                        <span class="<?= !empty($check['ok']) ? 'marca-ok' : 'marca-no' ?>"><?= !empty($check['ok']) ? '●' : '○' ?></span>
                        <span><strong><?= Support::e($check['label']) ?></strong> — <?= Support::e($check['detail']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="panel">
            <div class="titulo-seccion">
                <h2>Registro de errores</h2>
                <form method="post" action="<?= Support::e($base) ?>/admin/index.php">
                    <input type="hidden" name="csrf" value="<?= Support::e($csrf) ?>">
                    <input type="hidden" name="accion" value="limpiar_log">
                    <button type="submit" class="boton menudo peligro">Vaciar registro</button>
                </form>
            </div>
            <div class="registro"><?php
                if (!$log) {
                    echo 'Sin entradas por ahora.';
                } else {
                    foreach ($log as $linea) {
                        echo Support::e($linea) . "\n";
                    }
                }
            ?></div>
        </section>
    </main>
</div>
<?php require PF_VIEWS . '/pie.php'; ?>
