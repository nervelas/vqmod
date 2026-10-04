<?php
use App\Core\Fmt;

$tz = \App\Core\Settings::tz();
$brand = (string) setting('business_name', 'Agenda Premium');
$theme = (\App\Core\Auth::user()['theme'] ?? 'dark');
?>
<!doctype html>
<html lang="es" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title . ' · ' . $brand) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin-p3.css')) ?>">
</head>
<body class="p3-print-body">
<div class="p3-print-bar no-print">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/certificados')) ?>"><?= icon('arrow-left') ?>Volver</a>
  <button class="btn btn-gold btn-sm" type="button" data-print><?= icon('download') ?>Imprimir o guardar PDF</button>
</div>
<main class="p3-gift" id="main">
  <div class="p3-gift-bg"><?= partial('admin/giftcards/_guilloche', ['seed' => (int) $card['id'], 'w' => 900, 'h' => 520, 'curves' => 18]) ?></div>
  <div class="p3-gift-frame">
    <p class="p3-gift-brand serif"><?= e($brand) ?></p>
    <p class="p3-gift-kicker">Certificado de regalo</p>
    <p class="p3-gift-amount serif"><?= e(money($card['initial_amount'])) ?></p>
    <?php if ($card['recipient_name']) : ?><p class="p3-gift-for">Para <strong><?= e($card['recipient_name']) ?></strong><?php if ($card['buyer_name']) : ?>, de parte de <strong><?= e($card['buyer_name']) ?></strong><?php endif; ?></p><?php endif; ?>
    <?php if ($card['message']) : ?><p class="p3-gift-msg serif">&ldquo;<?= e($card['message']) ?>&rdquo;</p><?php endif; ?>
    <p class="p3-gift-code mono"><?= e($card['code']) ?></p>
    <p class="p3-gift-fine">Canjéalo al reservar escribiendo este código.<?php if ($card['expires_at']) : ?> Válido hasta el <?= e(Fmt::dateLong((string) $card['expires_at'], $tz)) ?>.<?php endif; ?></p>
  </div>
</main>
<script src="<?= e(asset('js/admin-p3.js')) ?>" defer></script>
</body>
</html>
