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
echo $f ? "FALLOS: $f\n" : "OK\n"; exit($f ? 1 : 0);
