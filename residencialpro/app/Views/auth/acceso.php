<?php
use App\Core\Ajustes;
use App\Core\Sesion;
?>
<div class="acceso-pantalla">
  <section class="acceso-arte">
    <picture>
      <source type="image/webp" srcset="<?= e(url('/assets/img/sitio/residencial-900.webp')) ?> 900w, <?= e(url('/assets/img/sitio/residencial.webp')) ?> 1600w" sizes="50vw">
      <img src="<?= e(url('/assets/img/sitio/residencial.jpg')) ?>" alt="" width="1600" height="1067" decoding="async">
    </picture>
    <div class="pie">
      <span class="lema" style="color:var(--arcilla-3);display:inline-flex;align-items:center;gap:10px;margin-bottom:14px">
        Portal del residente
      </span>
      <h2>Todo su residencial, en un solo lugar.</h2>
      <p>Consulte su estado de cuenta, reporte un pago, autorice visitas con código QR y
         reserve las áreas comunes. Sin llamadas y sin papeles.</p>
    </div>
  </section>

  <section class="acceso-forma">
    <div>
      <a class="web-marca" href="<?= e(url('/')) ?>" style="margin-bottom:34px;color:var(--texto)">
        <span class="escudo">
          <?php $logo = Ajustes::get('logo', ''); if ($logo !== '' && is_file(RUTA_BASE . '/uploads/logos/' . $logo)): ?>
            <img src="<?= e(subida($logo, 'logos')) ?>" alt="" width="38" height="38">
          <?php else: ?><?= ico('casa', 19) ?><?php endif; ?>
        </span>
        <span>
          <span class="n"><?= e(Ajustes::get('nombre', 'ResidencialPro')) ?></span>
          <span class="sub">Administración residencial</span>
        </span>
      </a>
      <h2 style="margin-bottom:6px">Bienvenido</h2>
      <p class="texto-3" style="font-size:.92rem">Ingrese con los datos que le entregó la administración.</p>

      <?php foreach (Sesion::tomarFlash() as $m): ?>
        <div class="aviso-caja <?= $m['tipo'] === 'exito' ? 'ok' : 'info' ?> mb-2"><?= ico('checkCirculo', 19) ?><div><?= e($m['mensaje']) ?></div></div>
      <?php endforeach; ?>

      <?php if ($error !== ''): ?>
        <div class="aviso-caja error mb-2" role="alert"><?= ico('alerta', 19) ?><div><?= e($error) ?></div></div>
      <?php endif; ?>

      <form method="post" class="mt-2" autocomplete="on">
        <?= csrf() ?>
        <div class="campo">
          <label for="usuario">Usuario o correo electrónico</label>
          <div class="entrada-icono">
            <?= ico('usuario', 18) ?>
            <input type="text" id="usuario" name="usuario" required autocomplete="username"
                   value="<?= e(Sesion::viejo('usuario')) ?>" placeholder="casa12@residencial.gt" autofocus>
          </div>
        </div>
        <div class="campo">
          <label for="clave">Contraseña</label>
          <div class="entrada-icono">
            <?= ico('candado', 18) ?>
            <input type="password" id="clave" name="clave" required autocomplete="current-password" placeholder="••••••••••">
          </div>
        </div>
        <div class="fila-entre mb-2">
          <label class="marca-check"><input type="checkbox" name="recordar" value="1"> <span>Mantener la sesión</span></label>
          <a href="<?= e(url('/recuperar')) ?>" style="font-size:.85rem">¿Olvidó su contraseña?</a>
        </div>
        <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('entrar', 19) ?> Ingresar</button>
      </form>

      <p class="centrado texto-3 mt-3" style="font-size:.84rem">
        ¿Aún no tiene acceso? Solicítelo a la administración.<br>
        <a href="<?= e(url('/')) ?>">Volver al sitio del residencial</a>
      </p>
    </div>
  </section>
</div>
