<?php
/**
 * Documentación pública de la API v1 (generada desde App\Services\ApiDocs).
 * @var array $groups grupos [id, title, endpoints]
 * @var string $baseUrl @var string $apiBase @var int $rate
 * @var array $errorCodes @var array $webhookEvents @var array $webhookExample
 */
use App\Services\ApiDocs;

$brand = (string) setting('business_name', 'Agenda Premium');
$anchor = static fn (string $id): string => 'ep-' . str_replace('.', '-', $id);
$scopeLabel = ['read' => 'Lectura', 'write' => 'Escritura'];
$inLabel = ['query' => 'URL', 'ruta' => 'Ruta', 'cuerpo' => 'Cuerpo JSON'];
$ok = ['data' => ['…'], 'meta' => ['request_id' => 'a1b2c3d4e5f6']];
$err = ['error' => 'No encontramos esa cita.', 'code' => 'not_found'];

$phpSample = <<<'CODE'
<?php
$secreto   = 'TU_SECRETO_DEL_WEBHOOK';
$cuerpo    = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_AGENDA_TIMESTAMP'] ?? '';
$firma     = $_SERVER['HTTP_X_AGENDA_SIGNATURE'] ?? '';

$esperada = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $cuerpo, $secreto);
if (!hash_equals($esperada, $firma) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(401);
    exit('Firma no válida');
}
$evento = json_decode($cuerpo, true);
http_response_code(200);
CODE;
$nodeSample = <<<'CODE'
const crypto = require('crypto');

// cuerpoCrudo: el texto EXACTO recibido (no el JSON ya convertido)
function firmaValida(secreto, timestamp, cuerpoCrudo, cabeceraFirma) {
  const esperada = 'sha256=' + crypto.createHmac('sha256', secreto)
    .update(timestamp + '.' + cuerpoCrudo).digest('hex');
  const a = Buffer.from(esperada);
  const b = Buffer.from(cabeceraFirma || '');
  const reciente = Math.abs(Date.now() / 1000 - Number(timestamp)) <= 300;
  return a.length === b.length && crypto.timingSafeEqual(a, b) && reciente;
}
CODE;
$pySample = <<<'CODE'
import hashlib, hmac, time

def firma_valida(secreto: str, timestamp: str, cuerpo_crudo: bytes, cabecera_firma: str) -> bool:
    mensaje = timestamp.encode() + b'.' + cuerpo_crudo
    esperada = 'sha256=' + hmac.new(secreto.encode(), mensaje, hashlib.sha256).hexdigest()
    reciente = abs(time.time() - int(timestamp)) <= 300
    return hmac.compare_digest(esperada, cabecera_firma or '') and reciente
CODE;

$toc = [['inicio', 'Introducción'], ['autenticacion', 'Autenticación'], ['limites', 'Límites de uso'], ['formato', 'Formato de respuestas'], ['errores', 'Errores']];
foreach ($groups as $g) {
    $toc[] = [$g['id'], $g['title']];
}
$toc[] = ['webhooks', 'Webhooks'];
$toc[] = ['firma', 'Verificar la firma'];
?>
<header class="ad-bar">
  <div class="ad-bar-in">
    <a class="ad-brand" href="<?= e(url('/')) ?>"><?= icon('calendar') ?><span><?= e($brand) ?></span></a>
    <span class="ad-bar-tag">Documentación de la API</span>
    <button type="button" class="btn btn-ghost btn-icon btn-sm" data-theme-toggle aria-label="Cambiar entre modo claro y oscuro"><?= icon('moon') ?></button>
  </div>
</header>

<main id="main" class="ad-wrap">
  <nav class="ad-toc" aria-label="Contenido de la documentación">
    <p class="eyebrow">Contenido</p>
    <ol>
      <?php foreach ($toc as [$id, $label]) : ?>
        <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <div class="ad-content">
    <section id="inicio" class="ad-sec">
      <p class="eyebrow">API REST · versión 1</p>
      <h1 class="serif">Conecta tu agenda con tus propios sistemas</h1>
      <p class="ad-lead">Consulta eventos y horarios libres, crea y cancela citas, y registra clientes desde tu sitio web, tu tienda o una automatización. Todas las respuestas son JSON en UTF-8.</p>
      <div class="ad-base">
        <span class="muted small">URL base</span>
        <code id="ad-base-url" class="mono"><?= e($apiBase) ?></code>
        <button type="button" class="btn btn-outline btn-sm" data-copy="#ad-base-url"><?= icon('copy') ?> Copiar</button>
      </div>
    </section>

    <section id="autenticacion" class="ad-sec">
      <h2>Autenticación</h2>
      <p>Cada petición lleva una clave de API. El negocio la crea en el panel, en <strong>API y claves</strong>, y se muestra completa una sola vez: guárdala en un lugar seguro. Las claves se pueden revocar en cualquier momento.</p>
      <div class="ad-code"><pre id="ad-auth-1"><code>curl '<?= e($apiBase) ?>/events' \
  -H 'Authorization: Bearer TU_CLAVE_DE_API'</code></pre><button type="button" class="btn btn-ghost btn-sm" data-copy="#ad-auth-1"><?= icon('copy') ?> Copiar</button></div>
      <p>También puedes enviarla en la cabecera <code>X-API-Key: TU_CLAVE_DE_API</code>. Nunca la pongas en la URL ni en código que se ejecute en el navegador de tus visitantes.</p>
      <div class="table-wrap"><table class="table table-sm">
        <thead><tr><th>Alcance</th><th>Permite</th></tr></thead>
        <tbody>
          <tr><td><span class="badge badge-ok">Lectura</span></td><td>Todos los endpoints <code>GET</code>.</td></tr>
          <tr><td><span class="badge badge-gold">Escritura</span></td><td>Crear y cancelar citas y crear clientes. Incluye la lectura.</td></tr>
        </tbody>
      </table></div>
    </section>

    <section id="limites" class="ad-sec">
      <h2>Límites de uso</h2>
      <p>Cada clave puede hacer hasta <strong><?= e((string) $rate) ?> solicitudes por minuto</strong>. Si lo superas recibes el código <code>429</code> y el límite se reinicia al comenzar el minuto siguiente. Si necesitas más, pídele al negocio que aumente el límite en la configuración del sistema.</p>
      <p>Para no gastar solicitudes: pide solo el rango de fechas que necesitas en <code>/availability</code>, usa <code>limit</code> y <code>offset</code> en los listados y guarda en caché lo que cambia poco, como la lista de eventos.</p>
    </section>

    <section id="formato" class="ad-sec">
      <h2>Formato de respuestas</h2>
      <p>Las respuestas correctas tienen siempre dos partes: <code>data</code> con el resultado y <code>meta</code> con información adicional. Cada respuesta incluye un <code>request_id</code> que puedes citar al pedir soporte.</p>
      <div class="ad-code"><pre id="ad-fmt-1"><code><?= e(ApiDocs::json($ok)) ?></code></pre></div>
      <ul class="ad-list">
        <li><strong>Paginación.</strong> Los listados aceptan <code>limit</code> (1 a 100, por defecto 25) y <code>offset</code>. En <code>meta</code> encuentras <code>total</code>, <code>count</code>, <code>limit</code>, <code>offset</code> y <code>has_more</code>.</li>
        <li><strong>Fechas y horas.</strong> Se devuelven en UTC con formato ISO 8601 (<code>2026-10-07T15:00:00Z</code>). Guatemala es UTC-6 y no cambia de horario durante el año. Al enviar horas incluye siempre la zona (<code>-06:00</code> o <code>Z</code>).</li>
        <li><strong>Dinero.</strong> Números decimales en quetzales (<code>currency: "GTQ"</code>).</li>
        <li><strong>Datos vacíos.</strong> Los campos sin valor llegan como <code>null</code>.</li>
        <li><strong>Privacidad.</strong> La API nunca expone notas internas, enlaces de gestión de citas, contraseñas ni claves.</li>
        <li><strong>Cuerpos JSON.</strong> En <code>POST</code> envía <code>Content-Type: application/json</code> y un objeto JSON de hasta 256 KB.</li>
      </ul>
    </section>

    <section id="errores" class="ad-sec">
      <h2>Errores</h2>
      <p>Cuando algo falla, la respuesta usa el código HTTP correspondiente y este cuerpo. El mensaje <code>error</code> está en español y se puede mostrar a una persona; <code>code</code> es estable y pensado para programas.</p>
      <div class="ad-code"><pre id="ad-err-1"><code><?= e(ApiDocs::json($err)) ?></code></pre></div>
      <div class="table-wrap"><table class="table table-sm">
        <thead><tr><th>HTTP</th><th>code</th><th>Significado</th></tr></thead>
        <tbody>
          <?php foreach ($errorCodes as $c) : ?>
            <tr><td class="mono"><?= e((string) $c['status']) ?></td><td><code><?= e($c['code']) ?></code></td><td><?= e($c['meaning']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>

    <?php foreach ($groups as $group) : ?>
      <section id="<?= e($group['id']) ?>" class="ad-sec">
        <h2><?= e($group['title']) ?></h2>
        <?php foreach ($group['endpoints'] as $ep) : ?>
          <?php $a = $anchor($ep['id']); ?>
          <article class="ad-ep" id="<?= e($a) ?>">
            <header class="ad-ep-head">
              <span class="ad-method ad-method-<?= e(strtolower($ep['method'])) ?>"><?= e($ep['method']) ?></span>
              <code class="ad-path"><?= e(ApiDocs::displayPath($ep)) ?></code>
              <span class="badge <?= $ep['scope'] === 'write' ? 'badge-gold' : 'badge-ok' ?>"><?= e($scopeLabel[$ep['scope']]) ?></span>
            </header>
            <h3><?= e($ep['summary']) ?></h3>
            <p><?= e($ep['description']) ?></p>

            <?php if ($ep['params']) : ?>
              <div class="table-wrap"><table class="table table-sm">
                <caption class="sr-only">Parámetros de <?= e($ep['summary']) ?></caption>
                <thead><tr><th>Parámetro</th><th>Dónde</th><th>Tipo</th><th>Obligatorio</th><th>Descripción</th></tr></thead>
                <tbody>
                  <?php foreach ($ep['params'] as $prm) : ?>
                    <tr>
                      <td><code><?= e($prm['name']) ?></code></td>
                      <td><?= e($inLabel[$prm['in']] ?? $prm['in']) ?></td>
                      <td><?= e($prm['type']) ?></td>
                      <td><?= $prm['required'] ? '<span class="badge badge-warn">Sí</span>' : '<span class="muted">No</span>' ?></td>
                      <td><?= e($prm['description']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
            <?php endif; ?>

            <h4>Ejemplo de petición</h4>
            <div class="ad-code"><pre id="<?= e($a) ?>-req"><code><?= e(ApiDocs::curl($ep, $baseUrl)) ?></code></pre><button type="button" class="btn btn-ghost btn-sm" data-copy="#<?= e($a) ?>-req"><?= icon('copy') ?> Copiar</button></div>

            <h4>Respuesta <span class="mono muted"><?= e((string) ($ep['status'] ?? 200)) ?></span></h4>
            <div class="ad-code"><pre id="<?= e($a) ?>-res"><code><?= e(ApiDocs::json($ep['response'])) ?></code></pre><button type="button" class="btn btn-ghost btn-sm" data-copy="#<?= e($a) ?>-res"><?= icon('copy') ?> Copiar</button></div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>

    <section id="webhooks" class="ad-sec">
      <h2>Webhooks</h2>
      <p>Además de consultar la API, el sistema puede avisar a tu servidor, a Zapier o a Make cada vez que ocurre algo con una cita. El negocio registra la dirección de destino en <strong>Webhooks</strong> dentro del panel y recibe un secreto para verificar que los avisos son auténticos.</p>
      <div class="table-wrap"><table class="table table-sm">
        <thead><tr><th>Evento</th><th>Cuándo se envía</th></tr></thead>
        <tbody>
          <?php foreach ($webhookEvents as $name => $when) : ?>
            <tr><td><code><?= e($name) ?></code></td><td><?= e($when) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <p>Cada aviso es un <code>POST</code> con cuerpo JSON y estas cabeceras:</p>
      <div class="table-wrap"><table class="table table-sm">
        <thead><tr><th>Cabecera</th><th>Contenido</th></tr></thead>
        <tbody>
          <tr><td><code>X-Agenda-Event</code></td><td>Nombre del evento, por ejemplo <code>booking.created</code>.</td></tr>
          <tr><td><code>X-Agenda-Delivery</code></td><td>Identificador de la entrega; sirve para ignorar duplicados.</td></tr>
          <tr><td><code>X-Agenda-Timestamp</code></td><td>Segundos Unix en que se firmó el aviso.</td></tr>
          <tr><td><code>X-Agenda-Signature</code></td><td><code>sha256=</code> seguido de la firma HMAC en hexadecimal.</td></tr>
        </tbody>
      </table></div>
      <h4>Ejemplo de cuerpo</h4>
      <div class="ad-code"><pre id="ad-wh-1"><code><?= e(ApiDocs::json($webhookExample)) ?></code></pre><button type="button" class="btn btn-ghost btn-sm" data-copy="#ad-wh-1"><?= icon('copy') ?> Copiar</button></div>
      <p>Responde con un código <code>2xx</code> en menos de 10 segundos. Si tu servidor falla o no responde, el sistema reintenta con esperas cada vez mayores durante varias horas.</p>
    </section>

    <section id="firma" class="ad-sec">
      <h2>Verificar la firma</h2>
      <p>Para confirmar que un aviso viene de tu agenda y no fue alterado:</p>
      <ol class="ad-list">
        <li>Toma el cuerpo <strong>exacto</strong> que recibiste, sin convertirlo ni reformatearlo.</li>
        <li>Arma el mensaje: <code>{X-Agenda-Timestamp}.{cuerpo}</code>, es decir, el timestamp, un punto y el cuerpo.</li>
        <li>Calcula HMAC-SHA256 de ese mensaje con el secreto del webhook y escríbelo en hexadecimal.</li>
        <li>Compara <code>sha256=&lt;hex&gt;</code> con la cabecera <code>X-Agenda-Signature</code> usando una comparación de tiempo constante.</li>
        <li>Rechaza avisos cuyo timestamp tenga más de 5 minutos de antigüedad, para evitar que alguien reenvíe uno viejo.</li>
      </ol>
      <details class="ad-det" open>
        <summary>PHP</summary>
        <div class="ad-code"><pre id="ad-sig-php"><code><?= e($phpSample) ?></code></pre><button type="button" class="btn btn-ghost btn-sm" data-copy="#ad-sig-php"><?= icon('copy') ?> Copiar</button></div>
      </details>
      <details class="ad-det">
        <summary>Node.js</summary>
        <div class="ad-code"><pre id="ad-sig-node"><code><?= e($nodeSample) ?></code></pre><button type="button" class="btn btn-ghost btn-sm" data-copy="#ad-sig-node"><?= icon('copy') ?> Copiar</button></div>
      </details>
      <details class="ad-det">
        <summary>Python</summary>
        <div class="ad-code"><pre id="ad-sig-py"><code><?= e($pySample) ?></code></pre><button type="button" class="btn btn-ghost btn-sm" data-copy="#ad-sig-py"><?= icon('copy') ?> Copiar</button></div>
      </details>
    </section>
  </div>
</main>
<footer class="ad-foot"><div class="ad-foot-in muted small"><?= e($brand) ?> · API v1 · <a href="<?= e(url('/')) ?>">Volver al sitio</a></div></footer>
