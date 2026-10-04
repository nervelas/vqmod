<?php $iframe = '<iframe src="' . $embed . '" title="Reservar cita" width="100%" height="760" style="border:0;max-width:100%" loading="lazy"></iframe>'; $btn = '<script src="' . $script . '" data-label="Agendar cita" defer></script>'; $inline = '<div data-aurea-inline></div>' . "\n" . '<script src="' . $script . '" defer></script>'; ?>
<div class="grid grid-2">
  <div class="card"><h2><?= e(__('Enlace de reservas')) ?></h2>
    <div class="field"><input id="lnk" readonly value="<?= e($link) ?>" aria-label="<?= e(__('Enlace')) ?>"></div>
    <div class="btn-row" style="justify-content:flex-start"><button class="btn btn-ink btn-sm" type="button" data-copy="#lnk"><?= e(__('Copiar')) ?></button><a class="btn btn-line btn-sm" target="_blank" rel="noopener" href="<?= e($link) ?>"><?= e(__('Abrir')) ?></a><a class="btn btn-line btn-sm" target="_blank" rel="noopener" href="<?= e('https://wa.me/?text=' . rawurlencode(__('Agenda tu %s aquí: %s', mb_strtolower(term('appt')), $link))) ?>"><?= e(__('Compartir por WhatsApp')) ?></a></div>
    <h3 style="margin-top:22px"><?= e(__('Código QR')) ?></h3><div data-qr="<?= e($link) ?>" data-size="240" data-download="#dlqr"></div><p><a id="dlqr" href="#" class="btn btn-gold btn-sm"><?= e(__('Descargar QR (PNG)')) ?></a></p>
  </div>
  <div class="card"><h2><?= e(__('Widget para tu sitio web')) ?></h2>
    <h3><?= e(__('1. Botón flotante "Agendar cita"')) ?></h3><div class="field"><textarea id="w1" readonly style="min-height:70px"><?= e($btn) ?></textarea></div><button class="btn btn-ink btn-sm" type="button" data-copy="#w1"><?= e(__('Copiar')) ?></button>
    <h3 style="margin-top:20px"><?= e(__('2. Reserva incrustada en una página')) ?></h3><div class="field"><textarea id="w2" readonly style="min-height:70px"><?= e($inline) ?></textarea></div><button class="btn btn-ink btn-sm" type="button" data-copy="#w2"><?= e(__('Copiar')) ?></button>
    <h3 style="margin-top:20px"><?= e(__('3. Iframe simple')) ?></h3><div class="field"><textarea id="w3" readonly style="min-height:90px"><?= e($iframe) ?></textarea></div><button class="btn btn-ink btn-sm" type="button" data-copy="#w3"><?= e(__('Copiar')) ?></button>
    <h3 style="margin-top:20px"><?= e(__('Cómo pegarlo en WordPress')) ?></h3>
    <ol class="hint" style="padding-left:18px"><li><?= e(__('Edita la página donde quieres el widget (Páginas → Editar).')) ?></li><li><?= e(__('Agrega un bloque "HTML personalizado" (en Elementor: widget HTML).')) ?></li><li><?= e(__('Pega el código de arriba y guarda. Para el botón flotante en todo el sitio, pégalo en el pie con un plugin como "WPCode".')) ?></li><li><?= e(__('Si tu sitio tiene una política de seguridad de contenido (CSP) estricta, permite este dominio en script-src y frame-src.')) ?></li></ol>
  </div>
</div>
<div class="card"><h2><?= e(__('Páginas por %s y calendarios privados', mb_strtolower(term('professional')))) ?></h2>
  <?php foreach ($profs as $p): ?><div style="padding:8px 0;border-bottom:1px solid var(--border)"><b><?= e($p['name']) ?></b> · <a href="<?= e(url('/profesional/' . $p['slug'])) ?>" target="_blank" rel="noopener"><?= e(__('Página pública')) ?></a> · <span class="hint"><?= e(__('Suscripción ICS')) ?>: <?= e(abs_url('/ics/' . $p['ics_token'] . '.ics')) ?></span></div><?php endforeach; ?>
  <?php if (!$profs): ?><p class="hint"><?= e(__('Aún no hay profesionales activos.')) ?></p><?php endif; ?></div>
