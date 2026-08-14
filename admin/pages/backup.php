<?php
/** Database backup: export a .sql dump (PHP-based, no shell required). */
if (!defined('BASE_PATH')) { exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('do') === 'export') {
    Csrf::verifyPost();
    Auth::log('backup_export', 'Exportó respaldo de base de datos');

    $tables = ['admins','settings','pages','sections','menu_items','platforms','media','albums','photos','submissions','admin_logs'];
    $filename = 'respaldo-fuentedevida-' . date('Ymd-His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $pdo = Database::pdo();
    echo "-- Respaldo Fuente de Vida — " . date('Y-m-d H:i:s') . "\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $t) {
        try { $rows = $pdo->query('SELECT * FROM `' . $t . '`')->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { continue; }
        echo "-- Tabla: $t\n";
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => '`' . $c . '`', array_keys($row));
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, array_values($row));
            echo "INSERT INTO `$t` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

admin_header('Respaldos');
$counts = [];
foreach (['pages','sections','platforms','albums','photos','submissions','media','admins'] as $t) {
    $counts[$t] = Database::scalar('SELECT COUNT(*) FROM ' . $t);
}
?>
<div class="card" style="max-width:640px">
  <h2>Respaldo de la base de datos</h2>
  <p class="muted">Descarga un archivo <code>.sql</code> con todo el contenido del sitio (páginas, secciones, accesos, galería, solicitudes y configuración). Guárdalo en un lugar seguro; contiene los datos administrativos.</p>
  <ul class="stat-list">
    <?php foreach ($counts as $t => $c): ?><li><strong><?= (int)$c ?></strong> <?= e($t) ?></li><?php endforeach; ?>
  </ul>
  <div class="notice notice--warn">El archivo incluye los usuarios administradores (con contraseñas cifradas). No lo compartas ni lo subas a carpetas públicas.</div>
  <form method="post">
    <?= Csrf::field() ?><input type="hidden" name="do" value="export">
    <button class="btn btn--primary btn--lg">Descargar respaldo (.sql)</button>
  </form>
  <p class="muted" style="margin-top:1rem">Para restaurar, importa este archivo desde phpMyAdmin en tu cPanel.</p>
</div>
<?php admin_footer(); ?>
