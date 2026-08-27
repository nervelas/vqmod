<?php
use App\Core\Ajustes;
use App\Core\Sesion;
?>
<div class="acceso-pantalla">
  <section class="acceso-arte">
    <div>
      <div style="display:flex;align-items:center;gap:13px;margin-bottom:38px">
        <?php $logo = Ajustes::get('logo', ''); if ($logo !== '' && is_file(RUTA_BASE . '/uploads/logos/' . $logo)): ?>
          <img src="<?= e(subida($logo, 'logos')) ?>" alt="" width="46" height="46" style="border-radius:12px">
        <?php else: ?>
          <span class="escudo" style="width:46px;height:46px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,var(--acento-2),var(--acento-3));color:#1F1B10"><?= ico('casa', 24) ?></span>
        <?php endif; ?>
        <div>
          <b style="font-family:var(--f-titulo);font-size:1.35rem;color:var(--acento-2);display:block;line-height:1.15"><?= e(Ajustes::get('nombre', 'ResidencialPro')) ?></b>
          <span style="font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(233,238,233,.6)">Administración residencial</span>
        </div>
      </div>
      <h1>Todo su residencial, en un solo lugar.</h1>
      <p>Cuotas al día, visitas controladas, áreas comunes reservadas y comunicación directa con la administración.</p>
      <div class="puntos">
        <div><?= ico('billetera', 19) ?><span>Consulte y pague su cuota desde el celular, con recibo digital verificable.</span></div>
        <div><?= ico('qr', 19) ?><span>Autorice a sus visitas con un código QR y entran en segundos.</span></div>
        <div><?= ico('calendario', 19) ?><span>Reserve el salón, la piscina o la churrasquera sin llamadas.</span></div>
        <div><?= ico('escudo', 19) ?><span>Información resguardada, con bitácora de cada operación sensible.</span></div>
      </div>
    </div>
    <p style="font-size:.78rem;color:rgba(233,238,233,.5);margin:0">© <?= date('Y') ?> <?= e(Ajustes::get('nombre', 'ResidencialPro')) ?></p>
  </section>

  <section class="acceso-forma">
    <div class="caja">
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
