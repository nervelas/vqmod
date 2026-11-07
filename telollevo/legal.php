<?php
/**
 * Páginas legales (privacidad y términos), editables desde el panel.
 */
define('TLL_DEPTH', 0);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/render.php';
security_headers();

$C = get_content();
$p = $_GET['p'] ?? 'privacidad';
$L = $C['legal'];
if ($p === 'terminos') { $title = $L['terms_title']; $text = $L['terms']; }
else { $title = $L['privacy_title']; $text = $L['privacy']; }

/* Convierte el texto plano en párrafos; una línea corta seguida de salto = subtítulo. */
function legal_html(string $t): string {
    $out = '';
    foreach (preg_split('/\n{2,}/', trim(str_replace("\r", '', $t))) as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $parts = explode("\n", $block, 2);
        if (count($parts) === 2 && mb_strlen($parts[0]) <= 60 && mb_substr($parts[0], -1) !== '.') {
            $out .= '<h2>' . e($parts[0]) . '</h2><p>' . nl2br(e(trim($parts[1])), false) . '</p>';
        } else {
            $out .= '<p>' . nl2br(e($block), false) . '</p>';
        }
    }
    return $out;
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> | <?= e($C['site']['brand']) ?></title>
<meta name="theme-color" content="<?= e($C['theme']['navy']) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<style><?= theme_css($C['theme']) ?></style>
</head>
<body class="legal-page">
<a class="skip" href="#main">Saltar al contenido</a>
<?= render_header($C) ?>
<main id="main" class="legal">
  <div class="container narrow">
    <a class="back" href="<?= e(url()) ?>"><?= icon('arrow-left', '') ?: '←' ?> Volver al inicio</a>
    <h1><?= e($title) ?></h1>
    <div class="prose"><?= legal_html($text) ?></div>
    <p class="legal-switch">
      <a href="<?= e(url('legal.php?p=privacidad')) ?>">Política de privacidad</a> ·
      <a href="<?= e(url('legal.php?p=terminos')) ?>">Términos del servicio</a>
    </p>
  </div>
</main>
<?= render_footer($C) ?>
<?= render_floating($C) ?>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
