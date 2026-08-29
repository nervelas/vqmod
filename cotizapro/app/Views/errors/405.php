<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Método no permitido · CotizaPro B2B</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="blueprint">
<main class="centerpage">
  <div class="centerpage__in">
    <span class="kicker kicker--plain" style="justify-content:center">Error 405</span>
    <p class="errcode">405</p>
    <h1 class="h2" style="margin-top:14px">Método no permitido</h1>
    <p class="lead" style="margin:14px auto 30px">La petición no se puede procesar de esa forma.</p>
    <div class="flex" style="justify-content:center;flex-wrap:wrap">
      <a class="btn btn--accent" href="<?= e(url('/')) ?>">Ir al inicio <span class="arw" aria-hidden="true">→</span></a>
      <a class="btn btn--ghost" href="<?= e(url('/entrar')) ?>">Acceder al panel</a>
    </div>
  </div>
</main>
</body>
</html>
