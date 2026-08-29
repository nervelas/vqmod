<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\Backup;
use MenuGold\Core\Config;
use MenuGold\Core\DB;
use MenuGold\Core\Response;
use MenuGold\Core\Session;
use MenuGold\Core\Url;

class BackupController extends BaseController
{
    protected $ability = 'backup';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return $this->view('admin/backups', array(
            'files'    => Backup::listFiles(),
            'cronLine' => '*/10 * * * * curl -s "' . Url::abs('/cron/run.php') . '?token='
                        . Config::get('security.cron_token', 'TU-TOKEN') . '"',
        ));
    }

    public function create()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        try {
            $file = Backup::create();
            Audit::log('backup_created', 'system', 0, array('file' => basename($file)));
            Session::flash('success', 'Respaldo creado: ' . basename($file));
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo crear el respaldo: ' . $e->getMessage());
        }
        return $this->redirect('/panel/respaldo');
    }

    public function download()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $name = basename($this->request->str('archivo', ''));
        $path = MG_STORAGE . '/backups/' . $name;
        if ($name === '' || !preg_match('/^menugold-[\w\-]+\.sql(\.gz)?$/', $name) || !is_file($path)) {
            return $this->notFound('Ese respaldo no existe.');
        }
        Audit::log('backup_downloaded', 'system', 0, array('file' => $name));
        return Response::make(file_get_contents($path), 200, array(
            'Content-Type'        => substr($name, -3) === '.gz' ? 'application/gzip' : 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length'      => (string)filesize($path),
        ));
    }

    public function audit()
    {
        $stop = $this->guard('settings');
        if ($stop) { return $stop; }
        return $this->view('admin/audit', array(
            'entries' => DB::all(
                'SELECT a.*, u.name AS user_name FROM mg_audit_log a
                 LEFT JOIN mg_users u ON u.id = a.user_id
                 ORDER BY a.id DESC LIMIT 200'),
        ));
    }
}
