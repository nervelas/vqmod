<?php
declare(strict_types=1);
/** Todos los avisos van al WhatsApp del dueño (32040756); NUNCA al contacto del cliente. Respaldo por correo al dueño si el WhatsApp falla. */
require __DIR__ . '/lib.php';
reset_limits();
$adm = admin_client();
$WA = '/tmp/s5test/run/wa-calls.log';
$mailsTo = function (): array { $o = []; foreach (glob('/tmp/s5test/mail/*.json') ?: [] as $f) { $j = json_decode((string) file_get_contents($f), true) ?: []; $o[] = strtolower(implode(',', (array) ($j['to'] ?? ''))); } return $o; };
$save = fn(array $f) => $adm->form('/admin/ajustes', $f + ['wa_notify_number' => '32040756']);
$p = $adm->page('/admin/ajustes');
t_ok(str_contains($p['body'], 'Avisos por WhatsApp') && str_contains($p['body'], 'Nunca'), 'el panel tiene la sección «Avisos por WhatsApp»');
// --- CallMeBot configurado
$save(['wa_notify_provider' => 'callmebot', 'wa_notify_key' => 'KEYOK']);
$p = $adm->page('/admin/ajustes'); t_ok(!str_contains($p['body'], 'KEYOK'), 'la clave del proveedor no se muestra');
@unlink($WA); array_map('unlink', glob('/tmp/s5test/mail/*.json') ?: []);
$s = build_site(); $id = (int) $s['id'];
t_ok($s['url'] !== '', 'web creada (vista previa lista)');
$cli = strtolower((string) mysql_val("SELECT client_email FROM s5test.s5_orders WHERE id=$id"));
$log = (string) @file_get_contents($WA);
t_ok(str_contains($log, 'Vista previa lista') && str_contains($log, '"phone":"50232040756"') && str_contains($log, '"ok":true'), 'aviso «vista previa lista» llegó al WhatsApp 32040756', substr($log, 0, 300));
t_ok(str_contains($log, 'continuar'), 'el aviso trae el enlace privado para que el dueño se lo mande al cliente');
$to = $mailsTo();
t_ok($cli !== '' && !array_filter($to, fn($t) => str_contains($t, $cli)), 'ningún correo salió al contacto del cliente (' . $cli . ')', implode(' | ', $to));
t_ok(count($to) === 0, 'con WhatsApp funcionando tampoco hay correo de respaldo', implode(' | ', $to));
// --- publicar
@unlink($WA);
$adm->form("/admin/pedido/$id/accion", ['accion' => 'aprobar']);
t_ok(mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id") === 'publicada', 'web publicada');
$log = (string) @file_get_contents($WA);
t_ok(str_contains($log, 'Web publicada') && str_contains($log, 'defina su contraseña'), 'aviso «web publicada» con el enlace de contraseña llegó al WhatsApp', substr($log, 0, 300));
$to = $mailsTo();
t_ok(!array_filter($to, fn($t) => str_contains($t, $cli)), 'publicar tampoco escribe al cliente', implode(' | ', $to));
// --- respaldo: WhatsApp falla → correo al DUEÑO (nunca al cliente)
$save(['wa_notify_provider' => 'callmebot', 'wa_notify_key' => 'MALA']);
array_map('unlink', glob('/tmp/s5test/mail/*.json') ?: []); @unlink($WA);
$s2 = build_site(); $id2 = (int) $s2['id'];
$to = $mailsTo();
t_ok(count($to) >= 1 && !array_filter($to, fn($t) => str_contains($t, strtolower((string) mysql_val("SELECT client_email FROM s5test.s5_orders WHERE id=$id2")))), 'si el WhatsApp falla, el respaldo es correo al dueño y no al cliente', implode(' | ', $to));
t_ok((int) mysql_val("SELECT COUNT(*) FROM s5test.s5_audit_log WHERE action='wa_aviso_no_enviado'") >= 1, 'queda anotado en la bitácora que el WhatsApp no salió');
// --- Cloud API de Meta
$save(['wa_notify_provider' => 'cloud', 'wa_notify_key' => 'TOKOK', 'wa_notify_phone_id' => '123456789']);
@unlink($WA);
$s3 = build_site();
$log = (string) @file_get_contents($WA);
t_ok(str_contains($log, '"via":"cloud"') && str_contains($log, '"to":"50232040756"') && str_contains($log, '"ok":true'), 'también funciona con la Cloud API de Meta', substr($log, 0, 240));
// dejar el entorno como estaba
$save(['wa_notify_provider' => 'callmebot', 'wa_notify_key' => '']);
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
