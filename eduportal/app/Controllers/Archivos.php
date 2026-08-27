<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Sirve archivos de /storage/uploads verificando permisos.
 * /storage no es accesible directamente por el navegador.
 */
final class Archivos extends Controller
{
    private const PUBLICAS = ['sitio', 'galeria', 'logo', 'pwa'];

    public function servir(string $carpeta, string $nombre): string
    {
        $carpeta = preg_replace('/[^a-z0-9_-]/i', '', $carpeta) ?? '';
        if (!preg_match('/^[a-f0-9]{8,64}\.(jpg|jpeg|png|webp|pdf|xlsx|csv)$/i', $nombre)
            && !preg_match('/^icon-\d{2,4}\.png$/', $nombre)) {
            throw new HttpException(404, 'Archivo no encontrado.');
        }
        $rel = $carpeta . '/' . $nombre;
        $ruta = BASE_PATH . '/storage/uploads/' . $rel;
        $real = realpath($ruta);
        $base = realpath(BASE_PATH . '/storage/uploads');
        if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
            throw new HttpException(404, 'Archivo no encontrado.');
        }
        if (!in_array($carpeta, self::PUBLICAS, true)) {
            $this->requireAuth();
            $this->verificarPropiedad($carpeta, $rel);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($real) ?: 'application/octet-stream';
        $permitidos = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf',
                       'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv', 'text/plain'];
        if (!in_array($mime, $permitidos, true)) {
            $mime = 'application/octet-stream';
        }
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string)filesize($real));
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: inline; filename="' . $nombre . '"');
            header('Cache-Control: private, max-age=604800');
        }
        readfile($real);
        return '';
    }

    /** Un padre solo accede a archivos ligados a SUS hijos. */
    private function verificarPropiedad(string $carpeta, string $rel): void
    {
        if (Auth::is('superadmin', 'secretaria')) {
            return;
        }
        if ($carpeta === 'usuarios' || $carpeta === 'avisos' || $carpeta === 'tareas') {
            return;
        }
        if ($carpeta === 'alumnos') {
            $alumnoId = (int)Database::value('SELECT id FROM alumnos WHERE foto = :f', ['f' => $rel], 0);
            if ($alumnoId === 0) {
                $alumnoId = (int)Database::value('SELECT alumno_id FROM documentos WHERE archivo = :f', ['f' => $rel], 0);
            }
            if ($alumnoId > 0 && Auth::puedeVerAlumno($alumnoId)) {
                return;
            }
            throw new HttpException(403, 'No tiene acceso a este archivo.');
        }
        if ($carpeta === 'comprobantes') {
            $alumnoId = (int)Database::value('SELECT alumno_id FROM pagos WHERE comprobante = :f', ['f' => $rel], 0);
            if ($alumnoId > 0 && Auth::puedeVerAlumno($alumnoId)) {
                return;
            }
            throw new HttpException(403, 'No tiene acceso a este archivo.');
        }
        if ($carpeta === 'entregas') {
            $alumnoId = (int)Database::value('SELECT alumno_id FROM tarea_entregas WHERE archivo = :f', ['f' => $rel], 0);
            if ($alumnoId > 0 && Auth::puedeVerAlumno($alumnoId)) {
                return;
            }
            throw new HttpException(403, 'No tiene acceso a este archivo.');
        }
        throw new HttpException(403, 'No tiene acceso a este archivo.');
    }
}
