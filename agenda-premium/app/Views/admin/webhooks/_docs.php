<?php
$php = <<<'CODE'
<?php
$secreto = 'whsec_...';                       // el secreto de tu webhook
$cuerpo  = file_get_contents('php://input');   // el cuerpo EXACTO, sin decodificar
$marca   = $_SERVER['HTTP_X_AGENDA_TIMESTAMP'] ?? '';
$firma   = $_SERVER['HTTP_X_AGENDA_SIGNATURE'] ?? '';

$esperada = 'sha256=' . hash_hmac('sha256', $marca . '.' . $cuerpo, $secreto);
if (!hash_equals($esperada, $firma) || abs(time() - (int) $marca) > 300) {
    http_response_code(401);
    exit('Firma no válida');
}
$evento = json_decode($cuerpo, true);
http_response_code(200);
CODE;
$js = <<<'CODE'
const crypto = require('crypto');
const express = require('express');
const app = express();

app.post('/avisos', express.raw({ type: '*/*' }), (req, res) => {
  const marca = req.header('X-Agenda-Timestamp') || '';
  const firma = req.header('X-Agenda-Signature') || '';
  const esperada = 'sha256=' + crypto
    .createHmac('sha256', process.env.WEBHOOK_SECRET)
    .update(marca + '.' + req.body.toString('utf8'))
    .digest('hex');

  const a = Buffer.from(esperada), b = Buffer.from(firma);
  const vencido = Math.abs(Date.now() / 1000 - Number(marca)) > 300;
  if (vencido || a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
    return res.status(401).send('Firma no válida');
  }
  const evento = JSON.parse(req.body.toString('utf8'));
  res.sendStatus(200);
});
CODE;
?>
<section class="card" aria-labelledby="h-firma">
  <div class="card-head"><h2 id="h-firma" class="serif">Cómo verificar la firma</h2></div>
  <div class="card-body stack">
    <p>Cada aviso se envía con el método POST y un cuerpo JSON. Para confirmar que lo mandó tu agenda y que nadie lo alteró, calcula una firma HMAC-SHA256 con tu secreto y compárala con la que llega en las cabeceras.</p>
    <div class="table-wrap"><table class="table table-sm">
      <caption class="sr-only">Cabeceras que acompañan a cada aviso</caption>
      <thead><tr><th scope="col">Cabecera</th><th scope="col">Qué contiene</th></tr></thead>
      <tbody>
        <tr><th scope="row" class="mono">X-Agenda-Event</th><td>Nombre del evento, por ejemplo <span class="mono">booking.created</span>.</td></tr>
        <tr><th scope="row" class="mono">X-Agenda-Delivery</th><td>Identificador único de la entrega. Úsalo para ignorar repetidos.</td></tr>
        <tr><th scope="row" class="mono">X-Agenda-Timestamp</th><td>Momento del envío en segundos Unix. Rechaza avisos con más de 5 minutos de antigüedad para evitar repeticiones.</td></tr>
        <tr><th scope="row" class="mono">X-Agenda-Signature</th><td><span class="mono">sha256=</span> seguido del HMAC-SHA256 en hexadecimal de <span class="mono">marca.cuerpo</span> (la marca, un punto y el cuerpo tal cual llegó).</td></tr>
      </tbody>
    </table></div>
    <div class="grid cols-2">
      <div><h3 class="serif">PHP</h3><pre class="p3-code" tabindex="0"><code><?= e($php) ?></code></pre></div>
      <div><h3 class="serif">JavaScript (Node)</h3><pre class="p3-code" tabindex="0"><code><?= e($js) ?></code></pre></div>
    </div>
    <p class="muted">Responde con un código 2xx en menos de 10 segundos. Si falla, reintentamos con esperas crecientes. Puedes pegar la dirección de un recibidor de Zapier o Make y usar el botón "Enviar prueba" para ver los datos llegar.</p>
  </div>
</section>
