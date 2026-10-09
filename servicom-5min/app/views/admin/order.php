<?php
/** Vars: $o $b $analysis $proof $steps $log $ai $previewUrl $siteUrl $total $qa $texts */
use S5\Services\Orders;
$csrf = '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
$act = fn(string $a, string $label, string $cls = 'btn sec', string $confirm = '', string $extra = '') => '<form method="post" action="/admin/pedido/' . (int) $o['id'] . '/accion"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . $csrf . '<input type="hidden" name="accion" value="' . e($a) . '">' . $extra . '<button class="' . $cls . '">' . e($label) . '</button></form>';
$st = $o['status'];
?>
<h1><?= e($o['business_name'] ?: 'Pedido #' . $o['id']) ?> <span class="tag <?= e($st) ?>"><?= e(Orders::LABELS[$st] ?? $st) ?></span></h1>
<p class="mut">Pedido #<?= (int) $o['id'] ?> · <?= $o['plan'] === 'tienda' ? 'Tienda virtual' : 'Página informativa' ?> · Total <b><?= e(money_q($total)) ?></b><?= $o['card_extra'] ? ' (incluye extra tarjeta)' : '' ?><?= $o['is_demo'] ? ' · DEMO' : '' ?></p>

<?php if ($st === Orders::ST_CONSTRUYENDO): ?>
<div class="card" data-tick="<?= (int) $o['id'] ?>"><b>Construyendo…</b><div class="bar" style="margin:.6rem 0"><i id="tick-bar" style="width:0"></i></div><span class="mut" id="tick-msg">iniciando…</span></div>
<?php endif; ?>

<?php if ($st === Orders::ST_PAGO): ?>
<div class="card"><h2 style="margin-top:0">Pago por revisar</h2>
<dl class="kv"><dt>Nombre / NIT</dt><dd><?= e($o['pay_name']) ?></dd><dt>Monto esperado</dt><dd><?= e(money_q($total)) ?></dd></dl>
<?php if ($proof): ?><p><?php if (str_starts_with((string) $proof['mime'], 'image/')): ?><a href="/admin/archivo/<?= (int) $proof['id'] ?>" target="_blank" rel="noopener"><img class="proof" alt="Comprobante" src="/admin/archivo/<?= (int) $proof['id'] ?>"></a><?php else: ?><a class="btn sec" target="_blank" rel="noopener" href="/admin/archivo/<?= (int) $proof['id'] ?>">Abrir comprobante (PDF)</a><?php endif; ?></p><?php endif; ?>
<div class="actions"><?= $act('aprobar', 'Aprobar y publicar', 'btn ok', '¿Aprobar el pago y publicar la web?') ?></div>
<form method="post" action="/admin/pedido/<?= (int) $o['id'] ?>/accion" style="margin-top:.8rem"><?= $csrf ?><input type="hidden" name="accion" value="rechazar">
<label for="motivo">Motivo del rechazo (se envía al cliente)</label><input id="motivo" name="motivo" required maxlength="300"><p><button class="btn danger">Rechazar</button></p></form></div>
<?php elseif (!empty($o['pay_reject_reason']) && $st === Orders::ST_LISTA): ?>
<p class="warn">Último rechazo de pago: <?= e($o['pay_reject_reason']) ?></p>
<?php endif; ?>

<div class="card"><h2 style="margin-top:0">Acciones</h2><div class="actions">
<?php if ($previewUrl && !in_array($st, [Orders::ST_ELIMINADA], true)): ?><a class="btn" href="<?= e($st === Orders::ST_PUBLICADA ? $siteUrl : $previewUrl) ?>" target="_blank" rel="noopener">Abrir <?= $st === Orders::ST_PUBLICADA ? 'sitio' : 'vista previa' ?></a><?php endif; ?>
<?php if ($o['fqdn'] && !in_array($st, [Orders::ST_ELIMINADA, Orders::ST_CONSTRUYENDO], true)): ?><?= $act('regenerar', 'Regenerar', 'btn sec', '¿Regenerar las páginas con los datos actuales?') ?><?php endif; ?>
<?php if (in_array($st, [Orders::ST_PREPARANDO], true)): ?><?= $act('reanudar', 'Reanudar construcción', 'btn') ?><?php endif; ?>
<?php if (in_array($st, [Orders::ST_BORRADOR, Orders::ST_ANALIZANDO], true) && !$o['fqdn']): ?><?= $act('iniciar', 'Construir ahora', 'btn') ?><?php endif; ?>
<?php if (!in_array($st, [Orders::ST_PUBLICADA, Orders::ST_VENCIDA, Orders::ST_SUSPENDIDA, Orders::ST_ELIMINADA], true) && !$o['is_demo']): ?><?= $act('borrar', 'Borrar vista previa', 'btn danger', '¿Borrar la vista previa y TODOS sus archivos? No se puede deshacer.') ?><?php endif; ?>
<?php if (in_array($st, [Orders::ST_PUBLICADA, Orders::ST_VENCIDA], true)): ?><?= $act('renovado', 'Marcar renovado (+1 año)', 'btn ok', '¿Marcar como renovado por un año más?') ?><?= $act('suspender', 'Suspender', 'btn danger', '¿Suspender este sitio? Dejará de mostrarse al público.') ?><?php endif; ?>
<?php if ($st === Orders::ST_SUSPENDIDA): ?><?= $act('reactivar', 'Reactivar', 'btn ok') ?><?php endif; ?>
</div>
<?php if (in_array($st, [Orders::ST_PUBLICADA, Orders::ST_VENCIDA], true) && !$o['is_demo']): ?>
<form method="post" action="/admin/pedido/<?= (int) $o['id'] ?>/accion" class="row" style="margin-top:.8rem"><?= $csrf ?><input type="hidden" name="accion" value="dominio">
<div><label for="dom">Asignar dominio propio del cliente (.com)</label><input id="dom" name="dominio" placeholder="minegocio.com" value="<?= e($o['domain_assigned'] ?: ($b['dominio']['tiene'] ? $b['dominio']['dominio'] : '')) ?>"></div>
<div style="flex:0 0 auto"><button class="btn sec">Asignar dominio</button></div></form>
<p class="mut" style="font-size:.85rem">El dominio debe apuntar ya a este hosting (DNS). Se verifica antes de cambiar nada.</p>
<?php endif; ?>
</div>

<div class="card"><h2 style="margin-top:0">Datos</h2><dl class="kv">
<dt>Dirección</dt><dd><?= e($o['fqdn'] ?: '—') ?><?= $o['domain_assigned'] ? ' → <b>' . e($o['domain_assigned']) . '</b>' : '' ?></dd>
<dt>Rubro / idioma / estilo</dt><dd><?= e($b['negocio']['rubro']) ?><?= $b['negocio']['rubro_otro'] ? ' (' . e($b['negocio']['rubro_otro']) . ')' : '' ?> · <?= e($b['negocio']['idioma']) ?> · estilo <?= (int) $b['negocio']['estilo'] ?></dd>
<dt>WhatsApp / teléfono</dt><dd><?= e($b['contacto']['whatsapp']) ?> / <?= e($b['contacto']['telefono']) ?></dd>
<dt>Correo de contacto</dt><dd><?= e($b['correo_contacto']) ?></dd>
<dt>Dirección física / horario</dt><dd><?= e($b['contacto']['direccion']) ?> · <?= e($b['contacto']['horario']) ?></dd>
<dt>Dominio</dt><dd><?= $b['dominio']['tiene'] ? 'Ya tiene: ' . e($b['dominio']['dominio']) : 'Quiere nuevo: ' . e($b['dominio']['deseado'] ?: '(sin indicar)') ?></dd>
<dt>Correos solicitados</dt><dd><?= e(implode(', ', $b['correos'] ?: ['info'])) ?></dd>
<dt>Frase / apoyo</dt><dd><?= e($b['contenido']['frase']) ?> — <?= e($b['contenido']['apoyo']) ?></dd>
<dt>Quiénes somos</dt><dd><?= nl2br(e($b['contenido']['quienes'])) ?></dd>
<dt>Servicios (<?= count($b['contenido']['servicios']) ?>)</dt><dd><?= e(implode(' · ', array_map(fn($s) => $s['nombre'], array_slice($b['contenido']['servicios'], 0, 40)))) ?></dd>
<?php $sugeridos = \S5\Services\Brief::serviciosSugeridos($b); if ($sugeridos): ?><dt>Servicios sugeridos por el sistema</dt><dd>Servicios sugeridos por el sistema: <?= e(implode(' · ', $sugeridos)) ?> <span class="mut">(no los escribió el cliente; puede quitarlos en el editor de su web)</span></dd><?php endif; ?>
<?php if ($o['plan'] === 'tienda'): ?><dt>Productos / categorías</dt><dd><?= count($b['tienda']['productos']) ?> / <?= count($b['tienda']['categorias']) ?></dd>
<dt>Pedidos / alertas</dt><dd><?= e($b['tienda']['correo_pedidos']) ?> / <?= e($b['tienda']['correo_alertas']) ?></dd>
<dt>Cobros</dt><dd>Contra entrega: <?= $b['tienda']['contra_entrega'] ? 'sí' : 'no' ?> · Tarjeta: <?= $o['card_extra'] ? 'SÍ (activar manualmente)' : 'no' ?></dd><?php endif; ?>
<dt>Texto IA</dt><dd><?= e($o['texts_source'] ?: '—') ?> · regeneraciones: <?= (int) $o['regen_count'] ?></dd>
<dt>Renovación</dt><dd><?= e($o['renewal_at'] ?: '—') ?></dd>
<dt>Vence la vista previa</dt><dd><?= e($o['expires_at'] ?: '—') ?></dd>
</dl></div>

<?php if ($analysis): ?><details class="card"><summary>Lo extraído de la presentación</summary>
<dl class="kv"><?php foreach (['nombre', 'rubro_sugerido', 'frase_principal', 'quienes_somos'] as $k): if (!empty($analysis[$k]['v'])): ?><dt><?= e($k) ?></dt><dd><?= e($analysis[$k]['v']) ?></dd><?php endif; endforeach; ?>
<dt>Servicios</dt><dd><?= e(implode(' · ', array_map(fn($s) => $s['nombre'] ?? '', $analysis['servicios'] ?? []))) ?></dd></dl></details><?php endif; ?>

<?php if ($qa): ?><div class="card"><h2 style="margin-top:0">Control de calidad</h2><p class="<?= !empty($qa['ok']) ? 'ok' : 'err' ?>"><?= !empty($qa['ok']) ? 'Sin problemas' : 'Problemas detectados' ?></p>
<?php foreach ($qa['problemas'] ?? [] as $p): ?><p class="err"><?= e(($p['tipo'] ?? '') . ' ' . ($p['url'] ?? '') . ' ' . ($p['detalle'] ?? '')) ?></p><?php endforeach; ?></div><?php endif; ?>

<?php if ($steps): ?><details class="card"><summary>Pasos de construcción</summary><table class="resp"><tbody><?php foreach ($steps as $s): ?><tr><td data-l="Paso"><?= e($s['step_key']) ?></td><td data-l="Estado"><?= e($s['status']) ?></td><td data-l="Intentos"><?= (int) $s['attempts'] ?></td><td data-l="Mensaje"><?= e($s['message']) ?></td></tr><?php endforeach; ?></tbody></table></details><?php endif; ?>
<?php if ($ai): ?><details class="card"><summary>Consumo de IA de este pedido</summary><table class="resp"><tbody><?php foreach ($ai as $a): ?><tr><td data-l="Tipo"><?= e($a['kind']) ?></td><td data-l="Modelo"><?= e($a['model']) ?></td><td data-l="Tokens"><?= (int) $a['tokens_in'] ?>/<?= (int) $a['tokens_out'] ?></td><td data-l="Costo">$<?= e($a['cost_usd']) ?></td><td data-l="OK"><?= $a['ok'] ? 'sí' : 'no' ?></td></tr><?php endforeach; ?></tbody></table></details><?php endif; ?>
<details class="card"><summary>Bitácora del pedido</summary><table class="resp"><tbody><?php foreach ($log as $l): ?><tr><td data-l="Fecha"><?= e($l['created_at']) ?></td><td data-l="Acción"><?= e($l['action']) ?></td><td data-l="Detalle"><?= e($l['detail']) ?></td></tr><?php endforeach; ?></tbody></table></details>
