<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Captcha;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Mail;
use App\Core\RateLimit;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\Academico;
use App\Models\Comunicacion;

final class Sitio extends Controller
{
    public function inicio(): string
    {
        if (!Settings::bool('sitio_activo', true)) {
            return $this->redirect('ingresar');
        }
        return $this->view('site/inicio', [
            'titulo'    => (string)Settings::get('seo_title', Settings::get('colegio_nombre', 'EduPortal')),
            'niveles'   => Academico::niveles(),
            'grados'    => Academico::grados(),
            'galeria'   => Database::all('SELECT * FROM galeria WHERE activo = 1 ORDER BY orden, id LIMIT 12'),
            'eventos'   => Comunicacion::eventos(date('Y-m-d'), date('Y-m-d', strtotime('+90 days')), true),
            'paginas'   => $this->paginas(),
            'captcha'   => Captcha::generate('contacto'),
        ], 'layouts/publico');
    }

    private function paginas(): array
    {
        $out = [];
        foreach (Database::all('SELECT * FROM paginas WHERE activo = 1') as $p) {
            $out[$p['slug']] = $p;
        }
        return $out;
    }

    public function calendario(): string
    {
        return $this->view('site/calendario', [
            'titulo'  => 'Calendario escolar',
            'eventos' => Comunicacion::eventos(date('Y-01-01'), date('Y-12-31'), true),
        ], 'layouts/publico');
    }

    public function contacto(): string
    {
        $this->requireCsrf();
        if (!RateLimit::throttleSession('contacto', 5, 900)) {
            $this->error('Ha enviado demasiados mensajes. Intente mas tarde.');
            return $this->redirect('/#contacto');
        }
        if (!Captcha::check('contacto', $this->req->input('captcha'))) {
            $this->error('La respuesta de verificacion no es correcta.');
            return $this->redirect('/#contacto');
        }
        $v = Validator::make($this->req->all(), [
            'nombre'   => 'required|len:3,160',
            'email'    => 'nullable|email|max:160',
            'telefono' => 'nullable|max:40',
            'mensaje'  => 'required|len:10,2000',
        ], ['nombre' => 'nombre', 'email' => 'correo', 'telefono' => 'telefono', 'mensaje' => 'mensaje']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('/#contacto');
        }
        Database::run(
            'INSERT INTO contactos (nombre, email, telefono, mensaje) VALUES (:n, :e, :t, :m)',
            ['n' => $v->get('nombre'), 'e' => $v->get('email'), 't' => $v->get('telefono'), 'm' => $v->get('mensaje')]
        );
        $destino = (string)Settings::get('colegio_email', '');
        if ($destino !== '') {
            Mail::enviar($destino, (string)Settings::get('colegio_nombre', 'EduPortal'), 'Nuevo mensaje de contacto',
                '<p><strong>' . e((string)$v->get('nombre')) . '</strong> escribio desde el sitio web:</p>'
                . '<blockquote style="border-left:3px solid #C9A961;padding-left:12px;color:#444">'
                . nl2br(e((string)$v->get('mensaje'))) . '</blockquote>'
                . '<p>Correo: ' . e((string)$v->get('email')) . '<br>Telefono: ' . e((string)$v->get('telefono')) . '</p>');
        }
        $this->ok('Gracias por escribirnos. Le responderemos a la brevedad.');
        return $this->redirect('/#contacto');
    }

    public function preinscripcionForm(): string
    {
        if (!Settings::bool('sitio_inscripcion', true)) {
            return $this->redirect('/');
        }
        return $this->view('site/preinscripcion', [
            'titulo'  => 'Pre-inscripcion en linea',
            'grados'  => Academico::grados(),
            'captcha' => Captcha::generate('preinscripcion'),
        ], 'layouts/publico');
    }

    public function preinscripcion(): string
    {
        $this->requireCsrf();
        if (!Settings::bool('sitio_inscripcion', true)) {
            return $this->redirect('/');
        }
        if (!RateLimit::throttleSession('preinscripcion', 4, 900)) {
            $this->error('Ha enviado demasiadas solicitudes. Intente mas tarde.');
            return $this->redirect('inscripcion');
        }
        if (!Captcha::check('preinscripcion', $this->req->input('captcha'))) {
            $this->error('La respuesta de verificacion no es correcta.');
            return $this->redirect('inscripcion');
        }
        $v = Validator::make($this->req->all(), [
            'alumno_nombre'    => 'required|len:3,160',
            'fecha_nacimiento' => 'nullable|date',
            'grado_id'         => 'nullable|int',
            'encargado'        => 'required|len:3,160',
            'telefono'         => 'required|len:8,40',
            'email'            => 'nullable|email|max:160',
            'mensaje'          => 'nullable|max:2000',
        ], [
            'alumno_nombre' => 'nombre del alumno', 'encargado' => 'nombre del encargado',
            'telefono' => 'telefono', 'email' => 'correo',
        ]);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('inscripcion');
        }
        $id = Database::insert(
            'INSERT INTO preinscripciones (alumno_nombre, fecha_nacimiento, grado_id, encargado, telefono, email, mensaje)
             VALUES (:a, :f, :g, :e, :t, :c, :m)',
            [
                'a' => $v->get('alumno_nombre'),
                'f' => $v->get('fecha_nacimiento'),
                'g' => $v->get('grado_id') ?: null,
                'e' => $v->get('encargado'),
                't' => $v->get('telefono'),
                'c' => $v->get('email'),
                'm' => $v->get('mensaje'),
            ]
        );
        $destino = (string)Settings::get('colegio_email', '');
        if ($destino !== '') {
            Mail::enviar($destino, (string)Settings::get('colegio_nombre', 'EduPortal'), 'Nueva pre-inscripcion',
                '<p>Se recibio una nueva solicitud de pre-inscripcion (#' . $id . ').</p>'
                . '<p>Alumno: <strong>' . e((string)$v->get('alumno_nombre')) . '</strong><br>'
                . 'Encargado: ' . e((string)$v->get('encargado')) . '<br>'
                . 'Telefono: ' . e((string)$v->get('telefono')) . '</p>');
        }
        if ($v->get('email')) {
            Mail::enviar((string)$v->get('email'), (string)$v->get('encargado'), 'Recibimos su solicitud',
                '<p>Estimado/a ' . e((string)$v->get('encargado')) . ',</p>'
                . '<p>Recibimos la solicitud de pre-inscripcion de <strong>' . e((string)$v->get('alumno_nombre'))
                . '</strong>. Nuestro equipo de admisiones se comunicara con usted muy pronto.</p>');
        }
        $this->ok('Su solicitud fue enviada. Nos comunicaremos con usted muy pronto.');
        return $this->redirect('inscripcion');
    }

    public function sitemap(): string
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = [url_absoluta('/'), url_absoluta('calendario'), url_absoluta('inscripcion'), url_absoluta('ingresar')];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . e($u) . '</loc><changefreq>weekly</changefreq></url>' . "\n";
        }
        return $xml . '</urlset>';
    }

    public function offline(): string
    {
        return $this->view('site/offline', ['titulo' => 'Sin conexion'], null);
    }

    /** Manifiesto PWA generado con la identidad del colegio. */
    public function manifiesto(): string
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $nombre = (string)Settings::get('colegio_nombre', 'EduPortal');
        $tema = Configuracion::TEMAS[(string)Settings::get('tema', 'default')] ?? Configuracion::TEMAS['default'];
        $iconos = [];
        foreach ([72, 96, 128, 144, 152, 192, 256, 384, 512] as $t) {
            $ruta = is_file(BASE_PATH . '/storage/uploads/pwa/icon-' . $t . '.png')
                ? url('archivo/pwa/icon-' . $t . '.png')
                : url('assets/icons/icon-' . $t . '.png');
            $iconos[] = [
                'src'     => $ruta,
                'sizes'   => $t . 'x' . $t,
                'type'    => 'image/png',
                'purpose' => $t >= 192 ? 'any maskable' : 'any',
            ];
        }
        return (string)json_encode([
            'name'             => $nombre,
            'short_name'       => mb_substr($nombre, 0, 12),
            'description'      => (string)Settings::get('seo_description', 'Portal escolar'),
            'start_url'        => url('portal'),
            'scope'            => url('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'theme_color'      => $tema['primario'],
            'background_color' => '#F7F5F0',
            'lang'             => 'es-GT',
            'dir'              => 'ltr',
            'categories'       => ['education'],
            'icons'            => $iconos,
            'shortcuts'        => [
                ['name' => 'Estado de cuenta', 'url' => url('portal/cuenta')],
                ['name' => 'Notas', 'url' => url('portal/notas')],
                ['name' => 'Avisos', 'url' => url('portal/avisos')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
