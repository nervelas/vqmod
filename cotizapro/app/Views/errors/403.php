<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso restringido · CotizaPro B2B</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="blueprint">
<main class="centerpage">
  <div class="centerpage__in">
    <span class="kicker kicker--plain" style="justify-content:center">Error 403</span>
    <p class="errcode">403</p>
    <h1 class="h2" style="margin-top:14px">Acceso restringido</h1>
    <p class="lead" style="margin:14px auto 30px">Su usuario no tiene permisos para ver esta sección. Consulte con el administrador de su empresa.</p>
    <div class="flex" style="justify-content:center;flex-wrap:wrap">
      <a class="btn btn--accent" href="<?= e(url('/')) ?>">Ir al inicio <span class="arw" aria-hidden="true">→</span></a>
      <a class="btn btn--ghost" href="<?= e(url('/entrar')) ?>">Acceder al panel</a>
    </div>
  </div>
</main>
</body>
</html>
