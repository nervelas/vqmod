<?php
/** Vista del instalador (sin dependencias de la app instalada). */
/** @var array $checks */
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación · CotizaPro B2B</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="authpage">
  <div class="authpage__art blueprint" aria-hidden="true">
    <div class="authpage__artin">
      <span class="kicker" style="color:rgba(255,255,255,.66)">Instalación</span>
      <p class="h1" style="color:#fff;margin-top:20px;max-width:12ch">CotizaPro<br>B2B</p>
      <div class="cota" style="margin-top:34px;color:rgba(255,255,255,.42)">Paso <?= e($step) ?> de 3</div>
      <ol style="list-style:none;padding:0;margin:26px 0 0;display:grid;gap:12px;color:rgba(255,255,255,.7);font-size:.875rem">
        <?php foreach (['Requisitos del servidor', 'Base de datos', 'Administrador y datos demo'] as $i => $lbl): ?>
          <li style="display:flex;gap:12px;align-items:center<?= $step === $i + 1 ? ';color:#fff' : '' ?>">
            <span class="secnum"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?>/</span><?= e($lbl) ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>

  <div class="authpage__form">
    <div class="authpage__box" style="max-width:520px">
      <?php if ($installed && !$done): ?>
        <h1>Ya está instalado</h1>
        <p class="sub">El instalador está bloqueado por seguridad. Para reinstalar, borre <code>install/.lock</code> y <code>config/config.php</code> desde el administrador de archivos.</p>
        <a class="btn btn--accent btn--block" href="<?= e(url('/entrar')) ?>">Ir al acceso</a>

      <?php elseif ($done): ?>
        <h1>¡Listo!</h1>
        <p class="sub">CotizaPro B2B quedó instalado y el instalador se bloqueó solo.</p>
        <div class="alert alert--ok"><span aria-hidden="true">✓</span><span>Elimine la carpeta <code>/install</code> del servidor cuando termine de revisar.</span></div>
        <div class="stack-sm" style="margin-top:20px">
          <a class="btn btn--accent btn--block" href="<?= e(url('/entrar')) ?>">Entrar al sistema</a>
          <a class="btn btn--ghost btn--block" href="<?= e(url('/')) ?>">Ver la landing</a>
        </div>
        <p class="hint" style="margin-top:18px">Recuerde configurar la tarea cron: el comando exacto está en <strong>Superadmin → Ajustes</strong>.</p>

      <?php else: ?>
        <?php foreach ($errors as $er): ?>
          <div class="alert alert--error"><span aria-hidden="true">!</span><span><?= e($er) ?></span></div>
        <?php endforeach; ?>
        <?php foreach ($ok as $o): ?>
          <div class="alert alert--ok"><span aria-hidden="true">✓</span><span><?= e($o) ?></span></div>
        <?php endforeach; ?>

        <?php if ($step === 1): ?>
          <h1>Requisitos del servidor</h1>
          <p class="sub">Revisamos que su hosting cumpla con lo necesario.</p>
          <table class="spectable" style="margin-bottom:24px">
            <caption class="sr-only">Requisitos</caption>
            <tbody>
              <?php foreach ($checks as $c): ?>
                <tr>
                  <th scope="row" style="width:58%"><?= e($c[0]) ?></th>
                  <td><span class="badge<?= $c[1] ? ' badge--ok' : ' badge--bad' ?>"><?= $c[1] ? 'OK' : 'Falta' ?></span>
                    <span class="small muted" style="margin-left:8px"><?= e($c[2]) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if ($allOk): ?>
            <a class="btn btn--accent btn--block" href="<?= e(url('/install/?paso=2')) ?>">Continuar <span class="arw" aria-hidden="true">&rarr;</span></a>
          <?php else: ?>
            <div class="alert alert--warn"><span aria-hidden="true">△</span><span>Corrija los puntos marcados y recargue esta página.</span></div>
            <a class="btn btn--ghost btn--block" href="<?= e(url('/install/')) ?>">Volver a comprobar</a>
          <?php endif; ?>

        <?php elseif ($step === 2): ?>
          <h1>Base de datos</h1>
          <p class="sub">Cree la base en cPanel → MySQL Databases y escriba aquí sus datos.</p>
          <form method="post" action="<?= e(url('/install/?paso=2')) ?>">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="accion" value="probar">
            <div class="row-2">
              <div class="field"><label for="db_host">Servidor</label>
                <input class="input" id="db_host" name="db_host" value="<?= e($_SESSION['inst_db']['host'] ?? 'localhost') ?>" required></div>
              <div class="field"><label for="db_port">Puerto</label>
                <input class="input" id="db_port" name="db_port" value="<?= e($_SESSION['inst_db']['port'] ?? '3306') ?>"></div>
            </div>
            <div class="field"><label for="db_name">Nombre de la base</label>
              <input class="input" id="db_name" name="db_name" value="<?= e($_SESSION['inst_db']['name'] ?? '') ?>" required placeholder="usuario_cotizapro"></div>
            <div class="field"><label for="db_user">Usuario</label>
              <input class="input" id="db_user" name="db_user" value="<?= e($_SESSION['inst_db']['user'] ?? '') ?>" required></div>
            <div class="field"><label for="db_pass">Contraseña</label>
              <input class="input" id="db_pass" name="db_pass" type="password" autocomplete="off"></div>
            <button class="btn btn--accent btn--block" type="submit">Probar conexión <span class="arw" aria-hidden="true">&rarr;</span></button>
            <p class="center small" style="margin-top:14px"><a class="linkarrow" href="<?= e(url('/install/?paso=1')) ?>">Volver a requisitos</a></p>
          </form>

        <?php else: ?>
          <h1>Su cuenta de superadministrador</h1>
          <p class="sub">Con esta cuenta creará y administrará las empresas de la plataforma.</p>
          <form method="post" action="<?= e(url('/install/?paso=3')) ?>">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="accion" value="instalar">
            <div class="field"><label for="admin_name">Nombre</label>
              <input class="input" id="admin_name" name="admin_name" maxlength="120" value="Superadministrador"></div>
            <div class="field"><label for="admin_email">Correo *</label>
              <input class="input" id="admin_email" name="admin_email" type="email" required placeholder="admin@sudominio.gt"></div>
            <div class="field"><label for="admin_pass">Contraseña *</label>
              <input class="input" id="admin_pass" name="admin_pass" type="password" minlength="8" required autocomplete="new-password">
              <p class="hint">Mínimo 8 caracteres con mayúsculas, minúsculas y números.</p></div>
            <div class="field"><label for="site_url">Dirección del sitio</label>
              <input class="input" id="site_url" name="site_url" value="<?= e($guessUrl) ?>">
              <p class="hint">Se usa en los enlaces de los correos y del PDF.</p></div>
            <label class="check"><input type="checkbox" name="demo" value="1" checked>
              <span>Instalar los datos de demostración (dos empresas con catálogo y cotizaciones)</span></label>
            <button class="btn btn--accent btn--block" type="submit">Instalar ahora <span class="arw" aria-hidden="true">&rarr;</span></button>
            <p class="center small" style="margin-top:14px"><a class="linkarrow" href="<?= e(url('/install/?paso=2')) ?>">Volver a la base de datos</a></p>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
