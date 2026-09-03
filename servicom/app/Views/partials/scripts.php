<script nonce="<?= e($nonce ?? '') ?>">
window.CP = <?= ejs(array_merge([
    'token' => csrf_token(),
    'base'  => \App\Core\App::basePath() . '/',
    'sw'    => url('/sw.js'),
], $jsConfig ?? [])) ?>;
</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($withPanelJs)): ?><script src="<?= e(asset('js/panel.js')) ?>" defer></script><?php endif; ?>
<?php if (!empty($withChart)): ?><script src="<?= e(asset('vendor/chartjs/chart.umd.js')) ?>" defer></script><?php endif; ?>
<?= $inlineScript ?? '' ?>
