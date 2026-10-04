<?php
/** Plantilla base de correo. Variables: $title, $content (HTML ya seguro), $brand, $gold, $logo, $preheader, $button, $footer, $signature, $contact.
 *  Los estilos van en línea y con tablas porque los clientes de correo no admiten CSS externo. */
$serif = "Georgia,'Times New Roman',serif";
$sans = "'Helvetica Neue',Helvetica,Arial,sans-serif";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($title) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#F4EEDF;">
<?php if ($preheader !== ''): ?>
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#F4EEDF;"><?= e($preheader) ?></div>
<?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F4EEDF" style="background-color:#F4EEDF;">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
    <tr><td align="center" bgcolor="#0E1118" style="background-color:#0E1118;padding:26px 24px;border-bottom:3px solid <?= e($gold) ?>;">
      <?php if ($logo !== ''): ?>
        <img src="<?= e($logo) ?>" alt="<?= e($brand) ?>" height="44" style="display:block;border:0;height:44px;max-width:240px;margin:0 auto 6px auto;">
      <?php endif; ?>
      <span style="font-family:<?= $serif ?>;font-size:22px;letter-spacing:2px;color:<?= e($gold) ?>;text-transform:uppercase;"><?= e($brand) ?></span>
    </td></tr>
    <tr><td bgcolor="#FFFDF8" style="background-color:#FFFDF8;padding:34px 36px 28px 36px;border-left:1px solid #E3D8BE;border-right:1px solid #E3D8BE;font-family:<?= $sans ?>;font-size:16px;line-height:1.6;color:#1B1A17;">
      <h1 style="margin:0 0 20px 0;font-family:<?= $serif ?>;font-size:24px;line-height:1.3;font-weight:normal;color:#1B1A17;"><?= e($title) ?></h1>
      <?= $content ?>
      <?php if ($button): ?>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 8px 0;">
        <tr><td bgcolor="<?= e($gold) ?>" style="background-color:<?= e($gold) ?>;border-radius:3px;">
          <a href="<?= e($button['url']) ?>" style="display:inline-block;padding:13px 28px;font-family:<?= $sans ?>;font-size:15px;font-weight:bold;color:#1A1305;text-decoration:none;"><?= e($button['label'] ?? 'Ver mi cita') ?></a>
        </td></tr>
      </table>
      <?php endif; ?>
      <?php if ($signature !== ''): ?>
      <p style="margin:24px 0 0 0;color:#4A4538;"><?= nl2br(e($signature), false) ?></p>
      <?php endif; ?>
    </td></tr>
    <tr><td bgcolor="#FFFDF8" style="background-color:#FFFDF8;border-left:1px solid #E3D8BE;border-right:1px solid #E3D8BE;border-bottom:1px solid #E3D8BE;padding:0 36px 26px 36px;font-family:<?= $sans ?>;font-size:12px;line-height:1.6;color:#6B6558;">
      <div style="border-top:1px solid #E3D8BE;padding-top:16px;">
        <?php if ($footer !== ''): ?><div><?= e($footer) ?></div><?php endif; ?>
        <?php if ($contact !== ''): ?><div><?= e($brand) ?> · <?= e($contact) ?></div><?php endif; ?>
        <div>Este mensaje se envió de forma automática; si necesitas ayuda, responde a este correo o comunícate con nosotros.</div>
      </div>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
