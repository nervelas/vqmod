<?php
$titulo = 'PixelForge — revisión del servidor';
$clase = 'acceso';
require PF_VIEWS . '/cabecera.php';
?>
<main class="caja">
    <div class="marca">
        <strong>PIXEL<em>FORGE</em></strong>
        <span>revisión del servidor</span>
    </div>
    <div class="panel">
        <div class="titulo-seccion">
            <h2>Falta algo en el hosting</h2>
            <span class="etiqueta-mono">diagnóstico</span>
        </div>
        <p class="ayuda">
            PixelForge no puede arrancar hasta resolver los puntos marcados en rojo. El resto de avisos son informativos.
        </p>
        <ul class="lista-chequeo">
            <?php foreach ($checks as $check) : ?>
                <li>
                    <span class="<?= !empty($check['ok']) ? 'marca-ok' : 'marca-no' ?>"><?= !empty($check['ok']) ? '●' : '○' ?></span>
                    <span><strong><?= Support::e($check['label']) ?></strong><br><?= Support::e($check['detail']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p style="margin-top:20px"><a class="boton menudo" href="<?= Support::e($base) ?>/index.php">Volver a comprobar</a></p>
    </div>
</main>
<?php require PF_VIEWS . '/pie.php'; ?>
