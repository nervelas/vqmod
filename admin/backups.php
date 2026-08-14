<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('backups.manage');

$action = str_input('action', '');
$backupDir = FL_ROOT . '/backups';

/** Ensure the backups directory exists and is protected from web access. */
function backups_prepare_dir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
}

/** Validate a backup filename is a plain basename within the backups dir. */
function backups_valid_name(string $name): bool
{
    if ($name === '' || basename($name) !== $name) {
        return false;
    }
    if (strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, '\\') !== false) {
        return false;
    }
    return (bool)preg_match('/^backup-[0-9]{8}-[0-9]{6}\.sql$/', $name);
}

/* ---- Handle POST -------------------------------------------------------- */
if (is_post()) {
    Security::requireCsrf();

    if ($action === 'create') {
        backups_prepare_dir($backupDir);
        $pdo = Database::pdo();
        $filename = 'backup-' . date('Ymd-His') . '.sql';
        $path = $backupDir . '/' . $filename;

        $out = "-- Football League platform database backup\n";
        $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = [];
        foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM) as $row) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $out .= "-- ----------------------------\n-- Table: {$table}\n-- ----------------------------\n";
            $create = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_NUM);
            if ($create && isset($create[1])) {
                $out .= "DROP TABLE IF EXISTS {$quotedTable};\n" . $create[1] . ";\n\n";
            }

            $stmt = $pdo->query("SELECT * FROM {$quotedTable}");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = array_keys($rows[0]);
                $colList = implode(', ', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', $cols));
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($r as $val) {
                        $vals[] = $val === null ? 'NULL' : $pdo->quote((string)$val);
                    }
                    $out .= "INSERT INTO {$quotedTable} ({$colList}) VALUES (" . implode(', ', $vals) . ");\n";
                }
                $out .= "\n";
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($path, $out);
        Audit::log('create', 'backups', $filename, null, ['file' => $filename, 'bytes' => strlen($out)]);
        flash('success', 'Backup generado correctamente: ' . $filename);
        redirect(base_url('admin/backups.php'));
    }

    if ($action === 'delete') {
        $name = str_input('file');
        if (!backups_valid_name($name)) {
            flash('danger', 'Nombre de archivo no válido.');
            redirect(base_url('admin/backups.php'));
        }
        $path = $backupDir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
            Audit::log('delete', 'backups', $name, ['file' => $name], null);
            flash('success', 'Backup eliminado: ' . $name);
        } else {
            flash('danger', 'El archivo de backup no existe.');
        }
        redirect(base_url('admin/backups.php'));
    }
}

/* ---- List existing backups --------------------------------------------- */
$files = [];
if (is_dir($backupDir)) {
    foreach (scandir($backupDir) as $f) {
        if (backups_valid_name($f)) {
            $full = $backupDir . '/' . $f;
            $files[] = [
                'name'  => $f,
                'size'  => filesize($full),
                'mtime' => filemtime($full),
            ];
        }
    }
    usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
}

/** Human-readable byte size. */
function backups_fmt_size(int $bytes): string
{
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
    if ($bytes >= 1024)    { return number_format($bytes / 1024, 1) . ' KB'; }
    return $bytes . ' B';
}

$PAGE_TITLE = 'Backups';
$ACTIVE = 'backups';
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Backups</h1>
        <p>Genere y gestione copias de seguridad de la base de datos.</p>
    </div>
    <div class="page-actions">
        <form method="post" action="<?= e(base_url('admin/backups.php?action=create')) ?>">
            <?= Security::csrfField() ?>
            <button class="btn" type="submit">Generar backup SQL</button>
        </form>
    </div>
</div>

<div class="card card-pad-lg">
    <p class="muted" style="font-size:.85rem;margin-top:0">
        Los backups se guardan en el directorio <code>backups/</code> del servidor, protegido contra el acceso
        web mediante <code>.htaccess</code>. El análisis antimalware (ClamAV) solo se ejecuta si está disponible
        en el servidor; este panel no simula resultados de análisis.
    </p>
</div>

<?php if (!$files): ?>
    <div class="empty-state card">
        <div class="es-icon">💾</div>
        <h2>No hay backups</h2>
        <p>Genera tu primera copia de seguridad de la base de datos.</p>
        <form method="post" action="<?= e(base_url('admin/backups.php?action=create')) ?>">
            <?= Security::csrfField() ?>
            <button class="btn" type="submit">Generar backup SQL</button>
        </form>
    </div>
<?php else: ?>
    <div class="table-wrap mt-3">
        <table class="data">
            <thead>
                <tr><th>Archivo</th><th class="num">Tamaño</th><th>Fecha</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td><strong><?= e($f['name']) ?></strong></td>
                    <td class="num"><?= e(backups_fmt_size((int)$f['size'])) ?></td>
                    <td><?= e(fmt_date(date('Y-m-d H:i:s', $f['mtime']), 'd/m/Y H:i')) ?></td>
                    <td>
                        <form method="post" action="<?= e(base_url('admin/backups.php?action=delete')) ?>" data-confirm="¿Eliminar este archivo de backup?">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
