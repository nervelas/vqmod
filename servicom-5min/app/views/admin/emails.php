<?php /** Vars: $rows */ ?>
<h1>Correos y dominios solicitados</h1>
<p class="mut">Lista para crear manualmente (correos corporativos y registro del dominio .com). El cliente espera 2 días hábiles tras publicar.</p>
<?php foreach ($rows as $r): $b = json_decode((string) $r['data'], true) ?: []; $dom = $r['domain_assigned'] ?? ''; $dd = $r['domain_requested'] ?: '(dominio por definir)'; $mails = json_decode((string) $r['emails_requested'], true) ?: ['info']; ?>
<div class="card"><b><a href="/admin/pedido/<?= (int) $r['id'] ?>"><?= e($r['business_name']) ?></a></b> <span class="mut"><?= e($r['fqdn']) ?> · publicada <?= e(substr((string) $r['published_at'], 0, 10)) ?></span>
<dl class="kv"><dt>Dominio .com</dt><dd><?= !empty($b['dominio']['tiene']) ? 'Ya lo tiene: ' : 'Registrar: ' ?><b><?= e($dd) ?></b></dd>
<dt>Correos</dt><dd><?= e(implode(', ', array_map(fn($m) => $m . '@' . $dd, $mails))) ?></dd>
<dt>Contacto</dt><dd><?= e($r['client_email']) ?></dd></dl></div>
<?php endforeach; if (!$rows): ?><p class="mut">Todavía no hay webs publicadas.</p><?php endif; ?>
