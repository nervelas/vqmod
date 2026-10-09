<?php
/**
 * SOLO PARA PRUEBAS. Genera un "job" (manifest.json + assets sintéticos con GD)
 * para construir un sitio de prueba. Las fotos son imágenes sintéticas, no reales.
 *
 *   php gen-job.php <outdir> [--plan=info|tienda] [--style=1..5] [--rubro=abogado|clinica|taller|ropa|restaurante|transporte|contabilidad|importaciones|otro]
 *       [--lang=es|en] [--services=N] [--products=N] [--cats=N] [--logo=0|1] [--banner=0..3] [--gallery=N] [--svcphotos=all|none|mixed]
 *       [--text=normal|long|short|emoji|special] [--youtube=0|1] [--wa=0|1] [--phone=0|1] [--address=0|1] [--map=0|1] [--social=0|1]
 *       [--port=8121] [--seed=1] [--productphotos=0|1] [--about=0|1] [--mode=preview|demo|published]
 */
$out = $argv[1] ?? '';
if ($out === '') {
	fwrite(STDERR, "uso: php gen-job.php <outdir> [--k=v]\n");
	exit(1);
}
$o = array(
	'plan' => 'info', 'style' => 1, 'rubro' => 'abogado', 'lang' => 'es', 'services' => 4, 'products' => 6, 'cats' => 3, 'logo' => 1, 'banner' => 2,
	'gallery' => 6, 'svcphotos' => 'mixed', 'text' => 'normal', 'youtube' => 1, 'wa' => 1, 'phone' => 1, 'address' => 1, 'map' => 1, 'social' => 1,
	'port' => 8121, 'seed' => 1, 'productphotos' => 1, 'about' => 1, 'mode' => 'preview',
);
foreach (array_slice($argv, 2) as $a) {
	if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) {
		$o[$m[1]] = is_numeric($m[2]) ? (int) $m[2] : $m[2];
	}
}
mt_srand((int) $o['seed']);
@mkdir($out . '/assets', 0777, true);
$en = ($o['lang'] === 'en');

/* ---------------- datos por rubro ---------------- */
$R = array(
	'abogado' => array('Bufete Herrera & Asociados', 'fas fa-gavel', 215, array('Derecho civil', 'Derecho penal', 'Derecho laboral', 'Derecho mercantil', 'Derecho de familia', 'Asesoría notarial', 'Registro de marcas', 'Contratos y escrituras')),
	'clinica' => array('Clínica Santa Lucía', 'fas fa-stethoscope', 175, array('Consulta general', 'Pediatría', 'Odontología', 'Laboratorio clínico', 'Ginecología', 'Nutrición', 'Fisioterapia', 'Rayos X')),
	'taller' => array('Taller Mecánico El Rápido', 'fas fa-wrench', 12, array('Cambio de aceite', 'Frenos', 'Suspensión', 'Alineación y balanceo', 'Diagnóstico electrónico', 'Aire acondicionado', 'Afinamiento', 'Llantas')),
	'ropa' => array('Moda Quetzal Boutique', 'fas fa-shirt', 320, array('Ropa casual', 'Ropa formal', 'Accesorios', 'Calzado', 'Ropa infantil', 'Uniformes', 'Arreglos de ropa')),
	'restaurante' => array('Restaurante Sabor Chapín', 'fas fa-utensils', 25, array('Desayunos típicos', 'Almuerzos ejecutivos', 'Parrilladas', 'Eventos y banquetes', 'Servicio a domicilio', 'Postres', 'Bebidas')),
	'transporte' => array('Transportes Ruta Segura', 'fas fa-truck', 95, array('Carga pesada', 'Mudanzas', 'Transporte de personal', 'Fletes locales', 'Envíos departamentales', 'Alquiler de camiones')),
	'contabilidad' => array('Contadores Asociados GT', 'fas fa-calculator', 140, array('Contabilidad mensual', 'Declaraciones SAT', 'Planillas y IGSS', 'Auditoría', 'Constitución de empresas', 'Asesoría fiscal')),
	'importaciones' => array('Importadora Pacífico', 'fas fa-boxes-stacked', 260, array('Importación de mercadería', 'Trámites aduanales', 'Almacenaje', 'Distribución', 'Asesoría de comercio exterior', 'Cotizaciones')),
	'otro' => array('Servicios Integrales Maya', 'fas fa-star', 40, array('Servicio uno', 'Servicio dos', 'Servicio tres', 'Servicio cuatro', 'Servicio cinco')),
);
$rub = $R[$o['rubro']] ?? $R['otro'];
$hue = $rub[2];

$name = $rub[0];
if ($en) {
	$name = 'Summit ' . ucfirst($o['rubro']) . ' Group';
}
$text = $o['text'];
if ($text === 'emoji') {
	$name = 'Café Ñandú 🍕 & Cía, S.A. ✨';
} elseif ($text === 'special') {
	$name = 'O\'Brien "Pérez" & Hijos <Ltda.> [sc_nombre]';
} elseif ($text === 'short') {
	$name = 'Ana';
} elseif ($text === 'long') {
	$name = 'Corporación Internacional de Servicios Profesionales Especializados y Asociados de Centroamérica y el Caribe Sociedad Anónima';
}

function lorem($n, $en)
{
	$es = 'Brindamos atención cercana y profesional a cada cliente, con experiencia comprobada en el servicio que usted necesita. Nuestro equipo escucha, explica con claridad y acompaña cada paso del proceso para que usted tenga tranquilidad. Trabajamos con responsabilidad y puntualidad, cuidando cada detalle. ';
	$e = 'We provide close, professional attention to every client, with proven experience in the service you need. Our team listens, explains clearly and supports every step of the process so you can feel at ease. We work responsibly and on time, caring for every detail. ';
	$s = $en ? $e : $es;
	$r = '';
	while (mb_strlen($r) < $n) {
		$r .= $s;
	}
	$r = mb_substr($r, 0, $n);
	return rtrim($r, " ,;") ;
}
function fit($s, $n)
{
	return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}

/* ---------------- imágenes ---------------- */
$assets = array();
$seq = 0;
function sc_make_img($file, $w, $h, $hue, $variant, $fmt)
{
	$im = imagecreatetruecolor($w, $h);
	imagealphablending($im, true);
	$hsl = function ($h, $s, $l) {
		$h = fmod($h, 360) / 360;
		$q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
		$p = 2 * $l - $q;
		$f = function ($t) use ($p, $q) {
			if ($t < 0) {
				$t += 1;
			}
			if ($t > 1) {
				$t -= 1;
			}
			if ($t < 1 / 6) {
				return $p + ($q - $p) * 6 * $t;
			}
			if ($t < 1 / 2) {
				return $q;
			}
			if ($t < 2 / 3) {
				return $p + ($q - $p) * (2 / 3 - $t) * 6;
			}
			return $p;
		};
		return array((int) round($f($h + 1 / 3) * 255), (int) round($f($h) * 255), (int) round($f($h - 1 / 3) * 255));
	};
	$c1 = $hsl($hue + $variant * 23, 0.55, 0.38);
	$c2 = $hsl($hue + 40 + $variant * 23, 0.6, 0.62);
	for ($y = 0; $y < $h; $y++) {
		$t = $y / max(1, $h - 1);
		$col = imagecolorallocate($im, (int) ($c1[0] + ($c2[0] - $c1[0]) * $t), (int) ($c1[1] + ($c2[1] - $c1[1]) * $t), (int) ($c1[2] + ($c2[2] - $c1[2]) * $t));
		imageline($im, 0, $y, $w, $y, $col);
	}
	for ($i = 0; $i < 9; $i++) {
		$col = imagecolorallocatealpha($im, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255), mt_rand(95, 118));
		$r = mt_rand((int) ($w * 0.08), (int) ($w * 0.35));
		imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, $h), $r, $r, $col);
	}
	for ($i = 0; $i < 4; $i++) {
		$col = imagecolorallocatealpha($im, 255, 255, 255, mt_rand(100, 118));
		$x = mt_rand(0, (int) ($w * 0.7));
		imagefilledrectangle($im, $x, (int) ($h * 0.55), $x + mt_rand(40, (int) ($w * 0.25)), $h, $col);
	}
	$label = 'TEST ' . $w . 'x' . $h;
	$col = imagecolorallocatealpha($im, 255, 255, 255, 40);
	imagestring($im, 5, 12, 10, $label, $col);
	if ($fmt === 'png') {
		imagesavealpha($im, true);
		imagepng($im, $file);
	} elseif ($fmt === 'jpg') {
		imagejpeg($im, $file, 82);
	} else {
		imagewebp($im, $file, 78);
	}
	imagedestroy($im);
}
function sc_make_logo($file, $w, $h, $hue)
{
	$im = imagecreatetruecolor($w, $h);
	imagesavealpha($im, true);
	imagealphablending($im, false);
	imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
	imagealphablending($im, true);
	$c = imagecolorallocate($im, 30, 60, 120);
	imagefilledellipse($im, (int) ($h / 2), (int) ($h / 2), $h - 20, $h - 20, $c);
	$c2 = imagecolorallocate($im, 240, 180, 40);
	imagefilledrectangle($im, (int) ($h + 10), (int) ($h * 0.38), $w - 20, (int) ($h * 0.46), $c2);
	imagefilledrectangle($im, (int) ($h + 10), (int) ($h * 0.54), (int) ($w * 0.8), (int) ($h * 0.62), $c);
	imagepng($im, $file);
	imagedestroy($im);
}
$addAsset = function ($w, $h, $alt, $fmt = 'webp') use (&$assets, &$seq, $out, $hue) {
	$seq++;
	$id = 'a' . $seq;
	$ext = $fmt === 'jpg' ? 'jpg' : $fmt;
	$rel = 'assets/' . $id . '.' . $ext;
	sc_make_img($out . '/' . $rel, $w, $h, $hue, $seq, $fmt);
	$assets[] = array('id' => $id, 'path' => $rel, 'mime' => 'image/' . ($fmt === 'jpg' ? 'jpeg' : $fmt), 'w' => $w, 'h' => $h, 'alt' => $alt);
	return $id;
};

$logo = null;
if ($o['logo']) {
	$seq++;
	$logo = 'a' . $seq;
	sc_make_logo($out . '/assets/' . $logo . '.png', 800, 300, $hue);
	$assets[] = array('id' => $logo, 'path' => 'assets/' . $logo . '.png', 'mime' => 'image/png', 'w' => 800, 'h' => 300, 'alt' => 'Logo de ' . $name);
}
$banner = array();
for ($i = 0; $i < (int) $o['banner']; $i++) {
	$banner[] = $addAsset(1600, 900, 'Imagen principal ' . ($i + 1), $i === 1 ? 'jpg' : 'webp');
}
$gallery = array();
for ($i = 0; $i < (int) $o['gallery']; $i++) {
	$gallery[] = $addAsset(1200, 800 + ($i % 3) * 100, 'Foto de la galería ' . ($i + 1));
}

/* ---------------- servicios ---------------- */
$services = array();
$nS = (int) $o['services'];
$base = $rub[3];
if ($en) {
	$base = array('Consulting', 'Support', 'Installation', 'Maintenance', 'Training', 'Audits', 'Planning', 'Delivery');
}
for ($i = 0; $i < $nS; $i++) {
	$nm = $base[$i % count($base)] . ($i >= count($base) ? ' ' . (intdiv($i, count($base)) + 1) : '');
	if ($text === 'long') {
		$nm = $nm . ' especializado de alta complejidad para empresas medianas y grandes en toda la región centroamericana, con cobertura nacional';
	} elseif ($text === 'short') {
		$nm = mb_substr($nm, 0, 4);
	} elseif ($text === 'emoji') {
		$nm = $nm . ' 🔧✨ <b>&</b> "Ñ" [sc_nombre]';
	} elseif ($text === 'special') {
		$nm = $nm . ' (O\'Brien & Co.) <i>x</i>';
	}
	$desc = ($text === 'short') ? 'Ok.' : lorem($text === 'long' ? 590 : 280, $en);
	if ($text === 'special') {
		$desc .= "\n\nSegundo párrafo con <script>alert(1)</script> y [gallery] y \"comillas\".";
	}
	$photo = null;
	$want = $o['svcphotos'] === 'all' || ($o['svcphotos'] === 'mixed' && $i % 2 === 0);
	if ($want && $i < 12) {
		$photo = $addAsset(1000, 750, $nm);
	}
	$services[] = array('nombre' => $nm, 'descripcion' => $desc, 'foto' => $photo, 'icono' => $rub[1], 'resumen' => fit(lorem(120, $en), 140), 'desc600' => fit($desc, 600));
}

/* ---------------- tienda ---------------- */
$cats = array();
$products = array();
if ($o['plan'] === 'tienda') {
	$cn = array('Hombres', 'Mujeres', 'Niños', 'Accesorios', 'Calzado', 'Hogar', 'Ofertas');
	$nc = max(1, (int) $o['cats']);
	for ($i = 0; $i < $nc; $i++) {
		$cats[] = array('nombre' => $cn[$i % count($cn)] . ($i >= count($cn) ? ' ' . $i : ''), 'padre' => '');
	}
	if ($nc >= 2) {
		$cats[] = array('nombre' => 'Camisas', 'padre' => $cats[0]['nombre']);
		$cats[] = array('nombre' => 'Pantalones', 'padre' => $cats[0]['nombre']);
	}
	for ($i = 0; $i < (int) $o['products']; $i++) {
		$pn = 'Producto ' . ($i + 1) . ' ' . ($text === 'emoji' ? '🔥 Ñoño' : ($text === 'long' ? 'con un nombre extremadamente largo que no cabe en una sola línea de la tarjeta y debe recortarse' : 'modelo clásico'));
		if ($text === 'short') {
			$pn = 'P' . ($i + 1);
		}
		$cat = $cats ? $cats[$i % count($cats)]['nombre'] : '';
		$ph = null;
		if ($o['productphotos'] && $i < 24) {
			$ph = $addAsset(800, 800, $pn, 'webp');
		} elseif ($o['productphotos'] && $assets) {
			$ph = $assets[count($assets) - 1]['id']; // reutiliza una foto
		}
		$products[] = array('nombre' => $pn, 'categoria' => $cat, 'precio' => round(25 + fmod($i * 13.37, 900), 2), 'descripcion' => $text === 'short' ? '' : lorem(240, $en),
			'foto' => $ph, 'stock' => $i % 7 === 0 ? 0 : (3 + $i % 20));
	}
}

/* ---------------- textos ---------------- */
$T = array();
$T['hero_titulo'] = fit($en ? 'Professional ' . $o['rubro'] . ' you can trust' : 'Atención profesional en la que puede confiar', 70);
$T['hero_subtitulo'] = fit(lorem(170, $en), 170);
$T['hero_boton'] = $en ? 'Message us' : 'Escríbanos';
$T['servicios_titulo'] = $en ? 'Our services' : 'Nuestros servicios';
$T['servicios_intro'] = fit(lorem(200, $en), 220);
$T['nosotros_titulo'] = $en ? 'About us' : 'Quiénes somos';
$T['nosotros_texto'] = $o['about'] ? lorem($text === 'long' ? 900 : 520, $en) : '';
$T['cta_titulo'] = $en ? 'Ready to start?' : '¿Listo para comenzar?';
$T['cta_texto'] = fit(lorem(150, $en), 200);
$T['cta_boton'] = $en ? 'Contact us' : 'Contáctenos';
$T['contacto_titulo'] = $en ? 'Contact' : 'Contacto';
$T['contacto_intro'] = fit(lorem(140, $en), 220);
$T['galeria_titulo'] = $en ? 'Gallery' : 'Galería';
$T['tienda_titulo'] = $en ? 'Our store' : 'Nuestra tienda';
$T['tienda_intro'] = fit(lorem(150, $en), 220);
$T['servicios'] = array();
foreach ($services as $s) {
	$T['servicios'][] = array('resumen' => $s['resumen'], 'descripcion' => $s['desc600']);
}
if ($text === 'short') {
	$T['hero_titulo'] = 'Hola';
	$T['hero_subtitulo'] = '';
	$T['nosotros_texto'] = $o['about'] ? 'Somos Ana.' : '';
	$T['servicios_intro'] = '';
	$T['cta_texto'] = '';
	$T['contacto_intro'] = '';
	$T['tienda_intro'] = '';
	foreach ($T['servicios'] as &$ts) {
		$ts = array('resumen' => '', 'descripcion' => '');
	}
	unset($ts);
} elseif ($text === 'long') {
	$T['hero_titulo'] = fit(lorem(200, $en), 70);
} elseif ($text === 'emoji') {
	$T['hero_titulo'] = '¡Bienvenidos! 🎉 Café & más ñandú';
	$T['hero_subtitulo'] = 'Atención 24/7 ✅ — precios "justos" & <b>calidad</b> [sc_nombre]';
} elseif ($text === 'special') {
	$T['hero_titulo'] = 'Texto con <u>HTML</u> & "comillas" y [shortcode]';
}

/* ---------------- manifiesto ---------------- */
$port = (int) $o['port'];
$redes = $o['social'] ? array('facebook' => 'https://facebook.com/ejemplo', 'instagram' => 'https://instagram.com/ejemplo', 'tiktok' => '', 'youtube' => 'https://youtube.com/@ejemplo', 'x' => '', 'linkedin' => 'https://linkedin.com/company/ejemplo')
	: array('facebook' => '', 'instagram' => '', 'tiktok' => '', 'youtube' => '', 'x' => '', 'linkedin' => '');
$man = array(
	'version' => 1,
	'site' => array(
		'url' => getenv('SC_TEST_URL') ?: 'http://127.0.0.1:' . $port, 'slug' => 'prueba' . $port, 'title' => $name, 'locale' => $en ? 'en_US' : 'es_ES', 'lang' => $o['lang'],
		'timezone' => 'America/Guatemala', 'admin_user' => 'duenio_sitio', 'admin_pass' => 'Pr0v!sion-' . bin2hex(random_bytes(4)),
		'admin_email' => 'admin@example.test', 'table_prefix' => 'wp_',
	),
	'plan' => $o['plan'], 'style' => (int) $o['style'], 'rubro' => $o['rubro'],
	'business' => array(
		'nombre' => $name, 'whatsapp' => $o['wa'] ? '50255550000' : '', 'whatsapp_msg' => $en ? 'Hello! I found your website.' : 'Hola, vi su sitio web y quisiera información.',
		'telefono' => $o['phone'] ? '+502 2222-3333' : '', 'correo_contacto' => 'contacto@example.test',
		'direccion' => $o['address'] ? ($text === 'long' ? lorem(160, $en) : '6a avenida 12-34 zona 1, Ciudad de Guatemala') : '',
		'mapa_url' => $o['map'] ? 'https://maps.app.goo.gl/ejemplo' : '', 'horario' => $o['address'] ? 'Lunes a viernes 8:00 a 17:00' : '',
		'redes' => $redes, 'logo' => $logo, 'youtube' => $o['youtube'] ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : '', 'footer_credit' => 'Sitio creado por Servicom',
	),
	'texts' => $T,
	'content' => array(
		'banner' => $banner,
		'quienes' => $o['about'] ? lorem($text === 'long' ? 4000 : 400, $en) : '',
		'servicios' => array_map(function ($s) {
			return array('nombre' => $s['nombre'], 'descripcion' => $s['descripcion'], 'foto' => $s['foto'], 'icono' => $s['icono']);
		}, $services),
		'galeria' => $gallery,
	),
	'store' => array(
		'categorias' => $cats, 'productos' => $products, 'umbral_stock' => 3, 'correo_alertas' => 'alertas@example.test', 'correo_pedidos' => 'pedidos@example.test',
		'banco' => array('banco' => 'Banco Industrial', 'numero' => '123-456789-0', 'titular' => $name, 'tipo' => 'Monetaria'),
		'contra_entrega' => true, 'nota_entrega' => $en ? 'Deliveries within Guatemala City in 24-48 hours.' : "Entregas en la ciudad de Guatemala en 24 a 48 horas.\nPara el interior, coordinaremos con usted.",
	),
	'assets' => $assets,
	'preview' => array(
		'mode' => $o['mode'], 'key' => bin2hex(random_bytes(16)), 'portal_url' => 'https://crear.servicom.gt',
		'pay_url' => 'https://crear.servicom.gt/vp/KEY/pagar', 'edit_url' => 'https://crear.servicom.gt/vp/KEY/editar', 'wa_servicom' => '50255551111',
	),
	'support' => array('wa_servicom' => '50255551111', 'email_owner' => 'owner@example.test'),
);
file_put_contents($out . '/manifest.json', json_encode($man, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo "job generado: $out (assets: " . count($assets) . ")\n";
