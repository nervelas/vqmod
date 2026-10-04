<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('sb2_backup', ['profession' => 'medico', 'demo' => true]);

use App\Core\Db;
use App\Services\BackupService;

foreach (glob(BackupService::dir() . '/backup-*') ?: [] as $f) {
    @unlink($f);
}

T::section('Datos difíciles de volcar');
$tricky = "Línea 1\nLínea 2 con \"comillas\", 'apóstrofos', \\barras\\ y ; punto y coma;\n-- no es un comentario\r\nEmoji 🙂 ñandú ü ';DROP TABLE x;--";
Db::insert('client_notes', ['client_id' => (int) Db::val('SELECT id FROM clients LIMIT 1'), 'body' => $tricky, 'created_at' => '2026-01-02 03:04:05']);
Db::pdo()->exec("INSERT INTO settings (k, v, updated_at) VALUES ('prueba_nulos', NULL, '2026-01-01 00:00:00'), ('prueba_vacio', '', '2026-01-01 00:00:00')");
for ($i = 0; $i < 700; $i++) {
    Db::insert('audit_log', ['action' => 'prueba', 'entity' => 'x', 'entity_id' => (string) $i, 'detail' => "fila $i\ncon salto", 'created_at' => '2026-01-01 00:00:00']);
}

T::section('Crear respaldo');
$path = BackupService::create();
T::ok(is_file($path), 'el archivo existe');
T::ok(str_starts_with($path, APP_ROOT . '/storage/backups/'), 'guardado en storage/backups');
T::ok((bool) preg_match('/backup-\d{8}-\d{6}-[a-f0-9]{16}\.sql(\.gz)?$/', $path), 'nombre con fecha y parte aleatoria');
T::ok(function_exists('gzopen') ? str_ends_with($path, '.sql.gz') : str_ends_with($path, '.sql'), 'comprimido con gzip si hay zlib');
$path2 = BackupService::create();
T::ok($path !== $path2, 'dos respaldos distintos no se pisan');
$sql = str_ends_with($path, '.gz') ? (string) gzdecode((string) file_get_contents($path)) : (string) file_get_contents($path);
T::ok(str_contains($sql, 'SET FOREIGN_KEY_CHECKS = 0;') && str_contains($sql, 'CREATE TABLE `bookings`'), 'incluye desactivación de llaves y CREATE TABLE');
T::ok(substr_count($sql, 'INSERT INTO `audit_log`') >= 3, 'las inserciones van por lotes');
T::ok(!str_contains($sql, 'mysqldump'), 'generado sin mysqldump');
T::ok(((int) (fileperms($path) & 0007)) === 0, 'permisos sin acceso para otros');
T::ok(is_file(BackupService::dir() . '/.htaccess'), 'carpeta protegida contra descarga directa');

T::section('Restaurar en otra base y comparar');
$restore = 'ap_t_sb2_backup_r';
$root = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'ap', 'ap_test_pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec("DROP DATABASE IF EXISTS `$restore`");
$root->exec("CREATE DATABASE `$restore` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$r = new PDO("mysql:host=127.0.0.1;dbname=$restore;charset=utf8mb4", 'ap', 'ap_test_pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$r->exec("SET time_zone = '+00:00'");
$stmts = 0;
foreach (preg_split('/;\n/', $sql) ?: [] as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || preg_match('/^(--[^\n]*\n?)+$/', $stmt)) {
        continue;
    }
    $r->exec($stmt);
    $stmts++;
}
T::ok($stmts > 100, "se ejecutaron $stmts sentencias sin error");
$tables = Db::col('SHOW TABLES');
$rt = $r->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
sort($rt);
T::eq($tables, $rt, 'mismas tablas (' . count($tables) . ')');
$mismatch = [];
foreach ($tables as $t) {
    $a = (int) Db::val("SELECT COUNT(*) FROM `$t`");
    $b = (int) $r->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    if ($a !== $b) {
        $mismatch[] = "$t: $a != $b";
    }
}
T::eq([], $mismatch, 'mismos conteos por tabla');
$bad = [];
foreach ($tables as $t) {
    $x = Db::one("CHECKSUM TABLE `$t`");
    $y = $r->query("CHECKSUM TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
    if ($x['Checksum'] !== $y['Checksum'] && $t !== 'rate_limits') {
        $bad[] = $t;
    }
}
T::eq([], $bad, 'mismo CHECKSUM TABLE en todas las tablas');
T::eq($tricky, (string) $r->query("SELECT body FROM client_notes WHERE body LIKE 'Línea 1%' LIMIT 1")->fetchColumn(), 'texto con comillas, saltos, barras y emoji idéntico');
T::eq(null, $r->query("SELECT v FROM settings WHERE k = 'prueba_nulos'")->fetchColumn() ?: null, 'NULL se conserva');
T::eq('', (string) $r->query("SELECT v FROM settings WHERE k = 'prueba_vacio'")->fetchColumn(), 'cadena vacía se conserva');
T::eq(1, (int) $r->query("SELECT COUNT(*) FROM settings WHERE k = 'prueba_nulos' AND v IS NULL")->fetchColumn(), 'NULL real (no cadena)');
$fk = (int) $r->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '$restore' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
$fk0 = (int) Db::val("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
T::eq($fk0, $fk, "llaves foráneas restauradas ($fk)");
$root->exec("DROP DATABASE `$restore`");

T::section('list, path y prune');
$list = BackupService::list();
T::eq(2, count($list), 'list devuelve los dos respaldos');
T::ok($list[0]['size'] > 1000 && $list[0]['name'] === basename($path2) || $list[0]['name'] === basename($path), 'list incluye nombre y tamaño, más reciente primero');
T::eq($path, BackupService::path(basename($path)), 'path resuelve un nombre válido');
foreach (['../../config/config.php', '..%2f..%2fconfig', '../backups/' . basename($path), basename($path) . '/../' . basename($path), "/etc/passwd", 'backup-20260101-000000-0123456789abcdef.sql', '', '.htaccess', 'index.html', basename($path) . "\0.txt", str_replace('.sql', '.php', basename($path)), '..', basename($path) . '.part', "\\..\\x"] as $evil) {
    T::eq(null, BackupService::path($evil), 'rechaza «' . addcslashes($evil, "\0") . '»');
}
@file_put_contents(APP_ROOT . '/storage/backups/backup-20260101-000000-0123456789abcdef.sql', 'x');
@symlink('/etc/passwd', APP_ROOT . '/storage/backups/backup-20260101-000000-aaaaaaaaaaaaaaaa.sql');
T::eq(null, BackupService::path('backup-20260101-000000-aaaaaaaaaaaaaaaa.sql'), 'un enlace simbólico hacia fuera no se entrega');
@unlink(APP_ROOT . '/storage/backups/backup-20260101-000000-aaaaaaaaaaaaaaaa.sql');
BackupService::prune(1);
$left = BackupService::list();
T::eq(1, count($left), 'prune conserva solo el más reciente');
T::eq(basename($path2) > basename($path) ? basename($path2) : basename($path), $left[0]['name'], 'conserva el más nuevo por nombre');
BackupService::prune(0);
T::eq(1, count(BackupService::list()), 'prune(0) nunca deja la carpeta sin respaldos');
foreach (BackupService::list() as $b) {
    @unlink(BackupService::path($b['name']));
}
T::done();
