<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Suscripción vencida · CotizaPro B2B</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="blueprint">
<main class="centerpage">
  <div class="centerpage__in">
    <span class="kicker kicker--plain" style="justify-content:center">Error 402</span>
    <p class="errcode">402</p>
    <h1 class="h2" style="margin-top:14px">Suscripción vencida</h1>
    <p class="lead" style="margin:14px auto 30px">La suscripción de esta empresa venció. Contacte al administrador de la plataforma para reactivarla.</p>
    <div class="flex" style="justify-content:center;flex-wrap:wrap">
      <a class="btn btn--accent" href="<?= e(url('/')) ?>">Ir al inicio <span class="arw" aria-hidden="true">→</span></a>
      <a class="btn btn--ghost" href="<?= e(url('/entrar')) ?>">Acceder al panel</a>
    </div>
  </div>
</main>
</body>
</html>
