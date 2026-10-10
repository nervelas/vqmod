<?php
require_once __DIR__ . '/_icons.php';
$waNum = (string)($cfg['wa_servicom'] ?? '');
?>
<footer class="ftr">
  <div class="wrap ftr__in">
    <div>
      <a class="brand" href="/"><span>Servicom</span></a>
      <p>Webs profesionales para negocios de Guatemala. Dominio .com, correos corporativos, hosting y diseño premium en un solo plan anual.</p>
    </div>
    <div>
      <ul>
        <li><a href="/crear">Crear mi web</a></li>
        <li><a href="/guia-presentacion">Qué poner en mi PDF</a></li>
        <li><a href="/#planes">Planes</a></li>
        <li><a href="/#faq">Preguntas</a></li>
<?php if ($waNum !== ''): ?>
        <li><a href="<?= e(wa_link($waNum, 'Hola, tengo una consulta sobre mi web.')) ?>" target="_blank" rel="noopener">WhatsApp</a></li>
<?php endif; ?>
      </ul>
      <small>© <?= date('Y') ?> Servicom, Guatemala. Todos los derechos reservados.</small>
    </div>
  </div>
</footer>
