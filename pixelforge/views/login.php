<?php
$titulo = 'PixelForge — acceso';
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
            <h2>Acceso</h2>
            <span class="etiqueta-mono">privado</span>
        </div>

        <?php if (!empty($message)) : ?>
            <div class="aviso error"><?= Support::e($message) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= Support::e($base) ?>/index.php" autocomplete="off">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf" value="<?= Support::e($csrf) ?>">
            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" autofocus>
            </div>
            <button type="submit" class="boton principal">Revelar el estudio</button>
        </form>
    </div>
</main>
<?php require PF_VIEWS . '/pie.php'; ?>
