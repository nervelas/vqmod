<?php
/** Estudio principal. */
$titulo = 'PixelForge — estudio';
require PF_VIEWS . '/cabecera.php';

$activos = array_values(array_filter($providers, static fn (array $p): bool => !empty($p['enabled'])));
$formatoDefecto = $settings->get('default_format');
$config = [
    'base' => $base,
    'csrf' => $csrf,
    'formato' => $formatoDefecto,
    'proveedores' => $providers,
    'webp' => (bool) $webp,
];
?>
<div class="envoltorio" id="app" data-config="<?= Support::e(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">

    <header class="barra">
        <div class="marca">
            <strong>PIXEL<em>FORGE</em></strong>
            <span>cuarto oscuro digital</span>
        </div>
        <nav>
            <span class="chip <?= $activos ? 'viva' : 'alerta' ?>" title="Proveedores activos">
                <span class="punto"></span>
                <?= $activos ? Support::e($activos[0]['label']) : 'sin proveedor activo' ?>
            </span>
            <span class="chip tecnica" title="Motor de imagen y almacenamiento">
                <?= Support::e($engine === 'none' ? 'sin GD/Imagick' : $engine) ?> · <?= Support::e($driver) ?>
            </span>
            <a class="boton menudo" href="<?= Support::e($base) ?>/admin/index.php">Panel</a>
            <a class="boton menudo" href="<?= Support::e($base) ?>/index.php?action=logout&amp;csrf=<?= Support::e($csrf) ?>">Salir</a>
        </nav>
    </header>

    <main class="estudio">
        <aside class="consola">
            <form class="panel" id="forma">
                <div class="titulo-seccion">
                    <h2>Revelado</h2>
                    <span class="etiqueta-mono">prompt exacto</span>
                </div>

                <div class="campo">
                    <label for="prompt">Prompt</label>
                    <textarea id="prompt" name="prompt" required maxlength="2000"
                        placeholder="Retrato de una anciana pescadora al amanecer, piel curtida, luz lateral..."></textarea>
                    <p class="ayuda">Se envía tal cual, sin cambios ni mejoras automáticas.</p>
                </div>

                <div class="campo">
                    <label class="interruptor" for="realismo">
                        <input type="checkbox" id="realismo" name="realismo">
                        <span class="pista"></span>
                        <span class="texto">Potenciar realismo
                            <small>Añade el sufijo fotográfico configurable al final del prompt.</small>
                        </span>
                    </label>
                </div>

                <div class="campo">
                    <label for="negativo">Prompt negativo</label>
                    <textarea id="negativo" name="negativo" maxlength="1000" style="min-height:74px"
                        placeholder="dibujo, ilustración, deformado, marca de agua"></textarea>
                    <p class="ayuda" id="aviso-negativo">Solo Hugging Face lo admite; los demás proveedores lo ignoran.</p>
                </div>

                <div class="campo">
                    <label>Proporción</label>
                    <div class="grupo-opciones" id="proporciones">
                        <?php
                        $ratios = [
                            ['1:1', 1024, 1024],
                            ['16:9', 1344, 756],
                            ['9:16', 756, 1344],
                            ['4:3', 1152, 864],
                            ['3:4', 864, 1152],
                            ['21:9', 1512, 648],
                        ];
                        foreach ($ratios as $i => $r) :
                            ?>
                            <label class="opcion">
                                <input type="radio" name="proporcion" value="<?= $r[1] ?>x<?= $r[2] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                <span><?= Support::e($r[0]) ?><small><?= $r[1] ?>×<?= $r[2] ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="campo dos-columnas">
                    <div>
                        <label for="ancho">Ancho exacto (px)</label>
                        <input type="number" id="ancho" name="ancho" value="1024" min="64" max="4096" step="1" required>
                    </div>
                    <div>
                        <label for="alto">Alto exacto (px)</label>
                        <input type="number" id="alto" name="alto" value="1024" min="64" max="4096" step="1" required>
                    </div>
                </div>
                <p class="ayuda" style="margin-top:-10px">
                    Cualquier medida vale: se genera en el tamaño soportado más cercano y el servidor la ajusta al exacto, sin deformar.
                </p>

                <div class="campo">
                    <label>Variaciones</label>
                    <div class="grupo-opciones">
                        <?php foreach ([1, 2, 3, 4] as $n) : ?>
                            <label class="opcion">
                                <input type="radio" name="variaciones" value="<?= $n ?>" <?= $n === 1 ? 'checked' : '' ?>>
                                <span><?= $n ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="campo dos-columnas">
                    <div>
                        <label for="formato">Formato</label>
                        <select id="formato" name="formato">
                            <?php foreach (['png' => 'PNG', 'jpg' => 'JPG', 'webp' => 'WEBP'] as $valor => $etiqueta) : ?>
                                <option value="<?= $valor ?>" <?= $formatoDefecto === $valor ? 'selected' : '' ?>
                                    <?= ($valor === 'webp' && !$webp) ? 'disabled' : '' ?>>
                                    <?= $etiqueta ?><?= ($valor === 'webp' && !$webp) ? ' (no disponible)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="seed">Seed</label>
                        <input type="number" id="seed" name="seed" min="0" max="2147483646" step="1" placeholder="aleatoria">
                    </div>
                </div>
                <p class="ayuda" style="margin-top:-10px">
                    Deja la seed vacía para que sea aleatoria, o fíjala para repetir el mismo resultado.
                </p>

                <div class="campo">
                    <label for="proveedor">Proveedor</label>
                    <select id="proveedor" name="proveedor">
                        <option value="">Automático (con respaldo en cadena)</option>
                        <?php foreach ($providers as $p) : ?>
                            <option value="<?= Support::e($p['id']) ?>" <?= empty($p['enabled']) ? 'disabled' : '' ?>>
                                <?= Support::e($p['label']) ?><?= empty($p['enabled']) ? ' (inactivo)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="boton principal" id="btn-generar">Generar imagen</button>
                <p class="ayuda" id="pista-tiempo" style="text-align:center">
                    Pollinations permite una imagen cada 15 segundos sin cuenta: las variaciones se revelan una a una.
                </p>
            </form>

            <section class="panel">
                <div class="titulo-seccion">
                    <h2>Presets</h2>
                    <span class="etiqueta-mono">guardados</span>
                </div>
                <div class="campo">
                    <div class="grupo-opciones">
                        <button type="button" class="boton menudo" id="guardar-preset-prompt">Guardar prompt</button>
                        <button type="button" class="boton menudo" id="guardar-preset-tamano">Guardar tamaño</button>
                    </div>
                </div>
                <div class="lista-presets" id="lista-presets"></div>
            </section>
        </aside>

        <section>
            <div id="avisos" role="status" aria-live="polite"></div>

            <div class="panel" style="margin-bottom:18px">
                <div class="titulo-seccion">
                    <h2>Copias reveladas</h2>
                    <div class="grupo-opciones">
                        <a class="boton menudo" id="zip-sesion" href="<?= Support::e($base) ?>/download.php?zip=sesion">ZIP de la sesión</a>
                    </div>
                </div>
                <div class="lienzo vacio" id="lienzo">
                    <div class="vacio-mensaje">
                        <h3>La cubeta está vacía</h3>
                        <p>Escribe un prompt y pulsa «Generar imagen». Las copias aparecerán aquí, revelándose poco a poco.</p>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="titulo-seccion">
                    <h2>Historial</h2>
                    <div class="grupo-opciones">
                        <input type="search" id="buscar" placeholder="Buscar en prompts…" style="width:200px">
                    </div>
                </div>
                <div class="rejilla-historial" id="historial"></div>
                <p class="ayuda" id="historial-vacio">Todavía no hay imágenes guardadas.</p>
                <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="boton menudo" id="mas-historial" hidden>Cargar más</button>
                    <a class="boton menudo" href="<?= Support::e($base) ?>/download.php?zip=todo">ZIP del historial</a>
                </div>
            </div>
        </section>
    </main>
</div>

<div class="modal" id="modal" hidden>
    <div class="contenido" id="modal-contenido"></div>
</div>

<script src="<?= Support::e($base) ?>/assets/js/app.js?v=<?= Support::e(PF_VERSION) ?>"></script>
<?php require PF_VIEWS . '/pie.php'; ?>
