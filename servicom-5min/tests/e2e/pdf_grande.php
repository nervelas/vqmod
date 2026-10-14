<?php
declare(strict_types=1);
/** Un PDF de ~43 MB (más de 10 MB) se acepta: el límite es 50 MB. Uno de más de 50 MB se rechaza con mensaje claro. */
require __DIR__ . '/lib.php';
reset_limits();
file_put_contents('/tmp/s5test/run/ai-mode', 'ok');
$dir = sys_get_temp_dir() . '/s5big_' . getmypid(); mkdir($dir);
$pdf = "$dir/grande.pdf";
$py = <<<'PY'
import os, io, sys
from PIL import Image
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
c = canvas.Canvas(sys.argv[1], pagesize=(960, 540))
c.setFont('Helvetica-Bold', 40); c.drawString(60, 460, 'Grupo Aurora Ingenieria'); c.setFont('Helvetica', 18); c.drawString(60, 420, 'Tel 3204 0756  info@servicom.gt'); c.drawString(60, 390, 'Servicios: Diseno estructural, Supervision de obra'); c.showPage()
for i in range(int(sys.argv[2])):
    im = Image.frombytes('RGB', (3200, 2400), os.urandom(3200 * 2400 * 3)); b = io.BytesIO(); im.save(b, 'JPEG', quality=95); b.seek(0)
    c.drawImage(ImageReader(b), 0, 0, 960, 540); c.showPage()
c.save()
PY;
file_put_contents("$dir/g.py", $py);
exec('python3 ' . escapeshellarg("$dir/g.py") . ' ' . escapeshellarg($pdf) . ' 4 2>&1');
$mb = filesize($pdf) / 1048576; t_ok($mb > 30 && $mb < 50, 'PDF de prueba de ' . round($mb, 1) . ' MB');
$c = new Client(); $c->page('/crear'); $tok = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
$c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['acepto' => true]]]);
$u = $c->upload($tok, 'presentacion', $pdf, 'application/pdf');
t_ok(!empty($u['json']['ok']), 'PDF de ' . round($mb, 1) . ' MB aceptado', json_encode($u['json'], JSON_UNESCAPED_UNICODE));
$c->api('POST', "/api/borrador/$tok/analizar");
$a = []; for ($i = 0; $i < 600; $i++) { $a = $c->api('GET', "/api/borrador/$tok/analisis"); if (in_array($a['json']['estado'] ?? '', ['lista', 'error'], true)) { break; } usleep(700000); }
t_ok(($a['json']['estado'] ?? '') === 'lista', 'análisis del PDF grande listo', json_encode($a['json'] ?? [], JSON_UNESCAPED_UNICODE));
t_ok(count($a['json']['resultado']['imagenes'] ?? []) >= 3, 'imágenes extraídas: ' . count($a['json']['resultado']['imagenes'] ?? []));
t_ok(str_contains((string) @file_get_contents('/tmp/s5test/run/ai-calls.log'), 'via-file-api'), 'el PDF pesado se envió por la API de archivos (no por petición directa)');
exec('rm -rf ' . escapeshellarg($dir));
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
