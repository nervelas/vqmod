<?php
declare(strict_types=1);
/** El dueño aprueba y publica una web cuyo cliente pagó por WhatsApp (sin subir comprobante) + el botón de comprobante siempre explica qué falta. */
require __DIR__ . '/lib.php';
reset_limits();
$s = build_site();
t_ok($s !== null && $s['url'] !== '', 'vista previa lista (sin pago)', json_encode($s));
$id = (int) $s['id'];
t_ok(mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id") === 'vista_lista', 'estado: vista_lista');
$adm = admin_client();
$p = $adm->page("/admin/pedido/$id");
t_ok(str_contains($p['body'], 'Pago recibido: aprobar y publicar'), 'el panel ofrece «Pago recibido: aprobar y publicar»');
$r = $adm->form("/admin/pedido/$id/accion", ['accion' => 'aprobar']);
t_ok($r['status'] === 302, 'aprobar responde 302', (string) $r['status']);
t_ok(mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id") === 'publicada', 'estado: publicada');
t_ok((int) mysql_val("SELECT COUNT(*) FROM s5test.s5_audit_log WHERE order_id=$id AND action='publicada' AND detail LIKE '%sin comprobante%'") === 1, 'queda en la bitácora que el dueño confirmó el pago');
$u = parse_url($s['url']); $site = new Client('http://127.0.0.1:8200', $u['host'] . ':8200');
$h = $site->req('GET', '/'); t_ok($h['status'] === 200 && !str_contains($h['body'], 'sc-preview-bar'), 'el sitio público responde 200 sin barra de vista previa', (string) $h['status']);
// un pedido ya publicado no se puede aprobar otra vez
$adm->form("/admin/pedido/$id/accion", ['accion' => 'aprobar']);
t_ok((int) mysql_val("SELECT COUNT(*) FROM s5test.s5_audit_log WHERE order_id=$id AND action='publicada'") === 1, 'no se publica dos veces');
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
