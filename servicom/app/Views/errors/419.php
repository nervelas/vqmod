<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sesión expirada · CotizaPro B2B</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="blueprint">
<main class="centerpage">
  <div class="centerpage__in">
    <span class="kicker kicker--plain" style="justify-content:center">Error 419</span>
    <p class="errcode">419</p>
    <h1 class="h2" style="margin-top:14px">Sesión expirada</h1>
    <p class="lead" style="margin:14px auto 30px">Por seguridad, su sesión caducó. Vuelva a cargar la página e inténtelo de nuevo.</p>
    <div class="flex" style="justify-content:center;flex-wrap:wrap">
      <a class="btn btn--accent" href="<?= e(url('/')) ?>">Ir al inicio <span class="arw" aria-hidden="true">→</span></a>
      <a class="btn btn--ghost" href="<?= e(url('/entrar')) ?>">Acceder al panel</a>
    </div>
  </div>
</main>
</body>
</html>
