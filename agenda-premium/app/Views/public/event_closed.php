<?php /** @var array $biz @var string $reason @var bool $embed */ ?>
<main id="main" class="pub-closed">
  <div class="empty">
    <?= icon('clock') ?>
    <p class="empty-title">Esta reserva no está disponible</p>
    <p class="empty-text"><?= e($reason) ?></p>
    <?php if (empty($embed)) : ?><a class="btn btn-gold" href="<?= e(url('/')) ?>">Ver otros servicios</a><?php endif; ?>
    <?php if ($biz['wa_link'] !== '') : ?><a class="btn btn-outline" href="<?= e($biz['wa_link']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?> Escribirnos</a><?php endif; ?>
  </div>
</main>
