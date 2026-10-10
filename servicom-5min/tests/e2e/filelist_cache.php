<?php
declare(strict_types=1);
/** Regresión: tras refrescar el paquete base (--only-pack) los archivos NUEVOS deben copiarse a las webs nuevas (lista en caché obsoleta). */
define('S5_ROOT', dirname(__DIR__, 2));
require S5_ROOT . '/app/bootstrap.php';
require __DIR__ . '/lib.php';
use S5\Provision\LocalDriver;
use S5\Services\BaseBuilder;

$T = sys_get_temp_dir() . '/s5fl_' . getmypid(); $base = "$T/webs/_base"; $pack = "$T/pack";
foreach ([$base . '/wp-includes', $base . '/wp-content/mu-plugins/servicom-core/includes/builder', $base . '/wp-content/themes/servicom', "$pack/theme/servicom", "$pack/mu-plugins/servicom-core/includes/builder", "$pack/provision"] as $d) { mkdir($d, 0777, true); }
file_put_contents("$base/wp-load.php", '<?php'); file_put_contents("$base/wp-includes/version.php", '<?php $wp_version="7.0";');
file_put_contents("$base/wp-content/mu-plugins/servicom-core.php", '<?php // v1'); file_put_contents("$base/wp-content/themes/servicom/style.css", '/* v1 */');
file_put_contents("$base/wp-content/mu-plugins/servicom-core/includes/builder/class-sc-builder.php", '<?php // v1');
$drv = new LocalDriver(['webs_path' => "$T/webs", 'base_path' => $base, 'domain_root' => 'servicom.test', 'auto_pack' => false, 'api' => (new ReflectionClass(\S5\Provision\SimCpanelApi::class))->newInstanceWithoutConstructor()]);
function copyAll(LocalDriver $d, string $dst): void { $c = 0; do { $r = $d->copyBase($dst, $c, 10); $c = $r['cursor']; } while (!$r['done']); }
mkdir("$T/webs/a", 0777, true); copyAll($drv, "$T/webs/a");
t_ok(!is_file("$T/webs/a/wp-content/mu-plugins/servicom-core/includes/builder/luxe.php"), 'la primera web no tiene luxe.php (aún no existe)');
// paquete nuevo: archivo nuevo en una subcarpeta y refresco como lo hace tools/build_base.php --only-pack
file_put_contents("$pack/theme/servicom/style.css", '/* v2 */'); file_put_contents("$pack/mu-plugins/servicom-core.php", '<?php // v2');
file_put_contents("$pack/mu-plugins/servicom-core/includes/builder/class-sc-builder.php", '<?php // v2'); file_put_contents("$pack/mu-plugins/servicom-core/includes/builder/luxe.php", '<?php // luxe');
file_put_contents("$pack/mu-plugins/servicom-core/includes/design.php", '<?php'); mkdir("$pack/theme/servicom/inc", 0777, true); file_put_contents("$pack/theme/servicom/inc/luxe-render.php", '<?php'); file_put_contents("$pack/provision/sc-provision.php", '<?php');
$bb = (new ReflectionClass(BaseBuilder::class))->newInstanceWithoutConstructor();
$bb->refreshPack($base, $pack);
// caso 1: aunque alguien deje la lista vieja en caché (sin que haya cambiado la marca antigua)
$cache = "$base/.filelist.json"; 
mkdir("$T/webs/b", 0777, true); copyAll($drv, "$T/webs/b");
t_ok(is_file("$T/webs/b/wp-content/mu-plugins/servicom-core/includes/builder/luxe.php"), 'la web nueva recibe luxe.php tras refrescar el paquete');
// caso 2: lista vieja restaurada (como en el hosting real) + marca antigua: debe reconstruirse igualmente
$old = json_decode((string) file_get_contents($cache), true); $old['files'] = array_values(array_filter($old['files'], fn($f) => !str_ends_with($f[0], 'builder/luxe.php')));
$old['stamp'] = (string) @filemtime("$base/wp-includes/version.php") . '|' . (string) @filemtime("$base/wp-content");   // formato antiguo
file_put_contents($cache, json_encode($old));
mkdir("$T/webs/c", 0777, true); copyAll($drv, "$T/webs/c");
t_ok(is_file("$T/webs/c/wp-content/mu-plugins/servicom-core/includes/builder/luxe.php"), 'una lista en caché con la marca antigua se descarta y se reconstruye');
// caso 3: el portal recibe una actualización de wp-pack: la próxima web nueva refresca sola el paquete base (sin cron)
file_put_contents("$pack/mu-plugins/servicom-core/includes/builder/nuevo-v3.php", '<?php // v3');
$auto = new LocalDriver(['webs_path' => "$T/webs", 'base_path' => $base, 'domain_root' => 'servicom.test', 'wp_pack' => $pack, 'api' => (new ReflectionClass(\S5\Provision\SimCpanelApi::class))->newInstanceWithoutConstructor()]);
mkdir("$T/webs/d", 0777, true); copyAll($auto, "$T/webs/d");
t_ok(is_file("$T/webs/d/wp-content/mu-plugins/servicom-core/includes/builder/nuevo-v3.php"), 'una actualización de wp-pack se aplica sola al crear la siguiente web (sin --only-pack)');
$h1 = (string) @file_get_contents("$base/.pack-hash"); mkdir("$T/webs/e", 0777, true); copyAll($auto, "$T/webs/e");
t_ok($h1 !== '' && $h1 === (string) @file_get_contents("$base/.pack-hash"), 'sin cambios en wp-pack no se vuelve a refrescar');
// caso 4: Regenerar actualiza el código del tema/mu-plugin de una web ya creada (aunque el archivo tenga el mismo tamaño)
file_put_contents("$T/webs/e/wp-content/themes/servicom/style.css", '/* v1 */'); file_put_contents("$T/webs/e/wp-content/uploads.txt", 'x');
file_put_contents("$base/wp-content/themes/servicom/style.css", '/* v9 */'); touch("$base/wp-content/themes/servicom/style.css");
$c = 0; do { $r = $auto->copyBase("$T/webs/e", $c, 10, false); $c = $r['cursor']; } while (!$r['done']);
t_ok(file_get_contents("$T/webs/e/wp-content/themes/servicom/style.css") === '/* v1 */', 'copia normal no pisa archivos existentes');
$c = 0; do { $r = $auto->copyBase("$T/webs/e", $c, 10, true); $c = $r['cursor']; } while (!$r['done']);
t_ok(file_get_contents("$T/webs/e/wp-content/themes/servicom/style.css") === '/* v9 */', 'regenerar actualiza el tema aunque el archivo ya exista');
t_ok(is_file("$T/webs/e/wp-content/uploads.txt"), 'regenerar no borra contenido del cliente');
exec('rm -rf ' . escapeshellarg($T));
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
