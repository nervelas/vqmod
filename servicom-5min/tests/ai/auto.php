<?php
declare(strict_types=1);
/** Modo solo archivo (Brief::autoConfirm): tope de servicios y WhatsApp con código de país. */
define('S5_ROOT', dirname(__DIR__, 2));
require S5_ROOT . '/app/bootstrap.php';
use S5\Services\Brief;
$f = 0; $t = function (bool $c, string $m) use (&$f) { echo ($c ? '  PASS ' : '  FAIL ') . $m . "\n"; if (!$c) { $f++; } };
$W = fn($v) => ['v' => $v, 'textual' => true];
$serv = []; for ($i = 1; $i <= 53; $i++) { $serv[] = ['nombre' => "Servicio número $i", 'descripcion' => "Detalle $i", 'textual' => true]; }
$an = ['nombre' => $W('Grupo IPS Integral'), 'rubro_sugerido' => $W(''), 'frase_principal' => $W(''), 'quienes_somos' => $W(''), 'servicios' => $serv, 'productos' => [], 'categorias' => [],
    'contacto' => ['telefono' => $W(''), 'whatsapp' => $W('3133906346'), 'correo' => $W(''), 'direccion' => $W('Cra 15 A # 120-42 Oficina 101, Bogotá, Colombia'), 'horario' => $W(''), 'redes' => []], 'imagenes' => [], 'colores' => []];
$b = Brief::blank('info'); $b['presentacion']['file'] = 1;
$m = Brief::autoConfirm($b, $an, 0);
$d = $m['data'];
$t(count($d['contenido']['servicios']) === Brief::AUTO_MAX_SERVICIOS, 'de 53 servicios se publican ' . count($d['contenido']['servicios']) . ' (tope ' . Brief::AUTO_MAX_SERVICIOS . ')');
$t($d['contacto']['whatsapp'] === '573133906346', 'WhatsApp colombiano recibe el código 57: ' . $d['contacto']['whatsapp']);
$t(Brief::normWhatsapp('55551234', 'Zona 10 Guatemala') === '50255551234', 'WhatsApp de 8 dígitos en Guatemala recibe 502');
$t(Brief::normWhatsapp('50255551234', '') === '50255551234', 'un número que ya trae código no se toca');
$t(Brief::normWhatsapp('', 'Bogotá') === '', 'sin número no se inventa nada');

// ---- fotos por servicio y por producto (según la hoja del PDF donde aparece cada uno)
use S5\Core\Db;
$oid = 987654; Db::q('DELETE FROM ' . Db::t('files') . ' WHERE order_id=?', [$oid]);
$ids = [];
for ($i = 1; $i <= 40; $i++) { $ids[$i] = (int) Db::insert('files', ['order_id' => $oid, 'kind' => 'presentacion_img', 'orig_name' => "p$i", 'stored' => "x$i", 'mime' => 'image/webp', 'size' => 1, 'w' => 900, 'h' => 900, 'source' => 'presentacion', 'created_at' => gmdate('Y-m-d H:i:s')]); }
$logoId = (int) Db::insert('files', ['order_id' => $oid, 'kind' => 'presentacion_img', 'orig_name' => 'logo', 'stored' => 'lg', 'mime' => 'image/webp', 'size' => 1, 'w' => 900, 'h' => 900, 'source' => 'presentacion', 'created_at' => gmdate('Y-m-d H:i:s')]);
$imgs = [['id' => $logoId, 'w' => 900, 'h' => 900, 'pagina' => 1, 'logo' => true]];
$prods = []; $k = 0;
for ($pg = 6; $pg <= 8; $pg++) { for ($j = 1; $j <= 4; $j++) { $k++; $prods[] = ['nombre' => "Producto $k", 'descripcion' => 'd', 'precio' => 100 + $k, 'categoria' => "Categoría $pg", 'pagina' => $pg, 'textual' => true]; $imgs[] = ['id' => $ids[$k], 'w' => 900, 'h' => 900, 'pagina' => $pg, 'logo' => false]; } }
$serv = [['nombre' => 'Servicio A', 'descripcion' => 'a', 'pagina' => 3], ['nombre' => 'Servicio B', 'descripcion' => 'b', 'pagina' => 4], ['nombre' => 'Servicio C sin foto', 'descripcion' => 'c', 'pagina' => 9]];
$imgs[] = ['id' => $ids[30], 'w' => 1400, 'h' => 900, 'pagina' => 3, 'logo' => false]; $imgs[] = ['id' => $ids[31], 'w' => 1400, 'h' => 900, 'pagina' => 4, 'logo' => false];
for ($i = 32; $i <= 36; $i++) { $imgs[] = ['id' => $ids[$i], 'w' => 1400, 'h' => 900, 'pagina' => 2, 'logo' => false]; }
$an2 = $an; $an2['servicios'] = $serv; $an2['productos'] = $prods; $an2['imagenes'] = $imgs;
$bt = Brief::blank('tienda'); $bt['presentacion']['file'] = 1;
$m2 = Brief::autoConfirm($bt, $an2, $oid); $d2 = $m2['data'];
$t(count($d2['tienda']['productos']) === 12 && count($d2['tienda']['categorias']) === 3, 'tienda: 12 productos y 3 categorías desde el análisis');
$okp = true; foreach ($d2['tienda']['productos'] as $i => $p) { if (($p['foto'] ?? null) !== $ids[$i + 1]) { $okp = false; } }
$t($okp, 'cada producto recibe SU foto (la de su hoja, en orden)');
$t(($d2['negocio']['logo'] ?? null) === $logoId, 'el logo se detecta aparte');
$bs = Brief::blank('info'); $bs['presentacion']['file'] = 1; $m3 = Brief::autoConfirm($bs, $an2, $oid); $sv = $m3['data']['contenido']['servicios'];
$t(($sv[0]['foto'] ?? null) === $ids[30] && ($sv[1]['foto'] ?? null) === $ids[31] && empty($sv[2]['foto']), 'cada servicio recibe la foto de su hoja; sin foto en su hoja, queda sin foto (se completa con stock/arte)');
$usadas = array_merge([$ids[30], $ids[31]]); $banner = $m3['data']['presentacion']['fotos_usar'] ?? [];
$t(!array_intersect($usadas, $banner), 'las fotos ya asignadas a servicios no se repiten en la galería');
Db::q('DELETE FROM ' . Db::t('files') . ' WHERE order_id=?', [$oid]);
echo $f ? "FALLOS: $f\n" : "OK\n"; exit($f ? 1 : 0);
