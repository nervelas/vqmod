<?php
/** Primera visita: solo se pide crear una contraseña. */
$titulo = 'PixelForge — crear contraseña';
$clase = 'acceso';
require PF_VIEWS . '/cabecera.php';
?>
<main class="caja">
    <div class="marca">
        <strong>PIXEL<em>FORGE</em></strong>
        <span>estudio de imagen hiperrealista</span>
    </div>

    <div class="panel">
        <div class="titulo-seccion">
            <h2>Crea tu contraseña</h2>
            <span class="etiqueta-mono">paso único</span>
        </div>
        <p class="ayuda" style="margin-bottom:18px">
            PixelForge ya creó su base de datos y sus carpetas. Elige una contraseña y empieza a generar:
            Pollinations funciona sin ninguna API key.
        </p>

        <?php if (!empty($message)) : ?>
            <div class="aviso error"><?= Support::e($message) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= Support::e($base) ?>/index.php" autocomplete="off">
            <input type="hidden" name="action" value="instalar">
            <input type="hidden" name="csrf" value="<?= Support::e($csrf) ?>">
            <div class="campo">
                <label for="password">Contraseña (mínimo 8 caracteres)</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
            </div>
            <div class="campo">
                <label for="password_confirm">Repite la contraseña</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="boton principal">Entrar al estudio</button>
        </form>

        <ul class="lista-chequeo">
            <?php foreach ($checks as $check) : ?>
                <li>
                    <span class="<?= !empty($check['ok']) ? 'marca-ok' : 'marca-no' ?>"><?= !empty($check['ok']) ? '●' : '○' ?></span>
                    <span><?= Support::e($check['label']) ?><?php if (empty($check['ok'])) : ?> — <?= Support::e($check['detail']) ?><?php endif; ?></span>
                </li>
            <?php endforeach; ?>
            <li>
                <span class="marca-ok">●</span>
                <span>Almacenamiento: <?= $driver === 'sqlite' ? 'SQLite' : 'archivos JSON (respaldo automático)' ?></span>
            </li>
        </ul>
    </div>
</main>
<?php require PF_VIEWS . '/pie.php'; ?>
