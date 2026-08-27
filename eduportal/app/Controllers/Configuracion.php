<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Imagen;
use App\Core\Mail;
use App\Core\Security;
use App\Core\Settings;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\Academico;

final class Configuracion extends Controller
{
    /** Ocho temas premium mas el institucional por defecto. */
    public const TEMAS = [
        'default'   => ['nombre' => 'Marino y Oro',   'primario' => '#0B1F3A', 'acento' => '#C9A961', 'fondo' => '#F7F5F0'],
        'esmeralda' => ['nombre' => 'Esmeralda',      'primario' => '#0B3B32', 'acento' => '#C9A961', 'fondo' => '#F5F8F6'],
        'borgona'   => ['nombre' => 'Borgona',        'primario' => '#4A1220', 'acento' => '#D4A373', 'fondo' => '#FAF6F4'],
        'grafito'   => ['nombre' => 'Grafito',        'primario' => '#22262B', 'acento' => '#B8B2A7', 'fondo' => '#F6F6F5'],
        'azulreal'  => ['nombre' => 'Azul Real',      'primario' => '#12275C', 'acento' => '#E2C275', 'fondo' => '#F5F7FC'],
        'bosque'    => ['nombre' => 'Verde Bosque',   'primario' => '#1B3A2B', 'acento' => '#CBB287', 'fondo' => '#F5F8F4'],
        'terracota' => ['nombre' => 'Terracota',      'primario' => '#63301F', 'acento' => '#E0A45E', 'fondo' => '#FBF6F1'],
        'purpura'   => ['nombre' => 'Purpura',        'primario' => '#33184F', 'acento' => '#C9A6E0', 'fondo' => '#F8F5FB'],
        'negrooro'  => ['nombre' => 'Negro y Oro',    'primario' => '#131313', 'acento' => '#D4AF37', 'fondo' => '#F7F6F3'],
    ];

    public function index(): string
    {
        $this->requireRol('superadmin');
        return $this->view('admin/config/general', [
            'titulo' => 'Configuracion del colegio',
            'temas'  => self::TEMAS,
            'cfg'    => Settings::all(),
        ]);
    }

    public function guardar(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $v = Validator::make($this->req->all(), [
            'colegio_nombre'    => 'required|len:2,120',
            'colegio_lema'      => 'nullable|max:180',
            'colegio_direccion' => 'nullable|max:255',
            'colegio_telefono'  => 'nullable|max:60',
            'colegio_whatsapp'  => 'nullable|max:40',
            'colegio_email'     => 'nullable|email|max:160',
            'colegio_nit'       => 'nullable|max:30',
            'moneda'            => 'required|max:5',
            'zona_horaria'      => 'required|max:60',
            'tema'              => 'required|in:' . implode(',', array_keys(self::TEMAS)),
            'color_personalizado' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'director_nombre'   => 'nullable|max:120',
            'nota_minima'       => 'required|numeric|min:1|max:100',
            'nota_maxima'       => 'required|numeric|min:10|max:100',
            'pond_zona'         => 'required|numeric|min:0|max:100',
            'pond_examen'       => 'required|numeric|min:0|max:100',
        ], [
            'colegio_nombre' => 'nombre del colegio', 'moneda' => 'moneda',
            'zona_horaria' => 'zona horaria', 'tema' => 'tema',
            'nota_minima' => 'nota minima', 'pond_zona' => 'ponderacion de zona',
        ]);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('configuracion');
        }
        if (!in_array((string)$v->get('zona_horaria'), \DateTimeZone::listIdentifiers(), true)) {
            $this->error('La zona horaria indicada no es valida.');
            return $this->redirect('configuracion');
        }
        if ((float)$v->get('pond_zona') + (float)$v->get('pond_examen') > (float)$v->get('nota_maxima')) {
            $this->error('La suma de las ponderaciones de zona y examen no puede superar la nota maxima.');
            return $this->redirect('configuracion');
        }
        foreach ([
            'colegio_nombre', 'colegio_lema', 'colegio_direccion', 'colegio_telefono', 'colegio_whatsapp',
            'colegio_email', 'colegio_nit', 'moneda', 'zona_horaria', 'tema', 'color_personalizado',
            'director_nombre', 'nota_minima', 'nota_maxima', 'pond_zona', 'pond_examen',
        ] as $clave) {
            Settings::set($clave, (string)($v->get($clave) ?? ''), 'general');
        }
        Settings::set('ranking_boleta', $this->req->bool('ranking_boleta') ? '1' : '0', 'academico');

        foreach ([
            'colegio_logo'    => ['logo', Upload::IMAGENES],
            'colegio_favicon' => ['logo', Upload::IMAGENES],
            'director_firma'  => ['logo', Upload::IMAGENES],
        ] as $campo => [$carpeta, $tipos]) {
            if (($this->req->file($campo)['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $r = Upload::store($this->req->file($campo), $carpeta, $tipos);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->redirect('configuracion');
            }
            Imagen::redimensionar(BASE_PATH . '/storage/uploads/' . $r['archivo'], 1000, 1000);
            Settings::set($campo, $r['archivo'], 'general');
            if ($campo === 'colegio_logo') {
                $tema = self::TEMAS[(string)$v->get('tema')] ?? self::TEMAS['default'];
                $n = Imagen::generarIconos(
                    BASE_PATH . '/storage/uploads/' . $r['archivo'],
                    BASE_PATH . '/storage/uploads/pwa',
                    $tema['primario']
                );
                if ($n > 0) {
                    $this->aviso('Se regeneraron ' . $n . ' iconos de la aplicacion movil con el nuevo logo.');
                }
            }
        }
        Settings::flush();
        Audit::log('config.guardar', 'settings', null, 'Configuracion general');
        $this->ok('La configuracion fue guardada.');
        return $this->redirect('configuracion');
    }

    // ---------------- Cobranza y correo ----------------

    public function cobranza(): string
    {
        $this->requireRol('superadmin');
        return $this->view('admin/config/cobranza', [
            'titulo' => 'Configuracion de cobranza y correo',
            'cfg'    => Settings::all(),
        ]);
    }

    public function guardarCobranza(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $v = Validator::make($this->req->all(), [
            'recibo_prefijo'           => 'nullable|max:5',
            'recibo_texto'             => 'nullable|max:500',
            'descuento_hermanos'       => 'nullable|numeric|min:0|max:100',
            'meta_ingresos'            => 'nullable|numeric|min:0|max:100000000',
            'pago_link'                => 'nullable|max:255',
            'recordatorio_previo_dias' => 'nullable|int|min:0|max:30',
            'recordatorio_mora_cada'   => 'nullable|int|min:1|max:60',
            'plantilla_wa'             => 'nullable|max:1000',
            'plantilla_correo'         => 'nullable|max:4000',
            'smtp_host'                => 'nullable|max:160',
            'smtp_puerto'              => 'nullable|int|min:1|max:65535',
            'smtp_usuario'             => 'nullable|max:160',
            'smtp_seguridad'           => 'nullable|in:tls,ssl,none',
            'smtp_remitente'           => 'nullable|email|max:160',
            'smtp_nombre'              => 'nullable|max:120',
            'subida_max_mb'            => 'nullable|int|min:1|max:64',
        ], ['smtp_remitente' => 'correo remitente', 'pago_link' => 'enlace de pago en linea']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('configuracion/cobranza');
        }
        $link = trim((string)($v->get('pago_link') ?? ''));
        if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
            $this->error('El enlace de pago en linea debe ser una direccion web valida.');
            return $this->redirect('configuracion/cobranza');
        }
        foreach ([
            'recibo_prefijo', 'recibo_texto', 'descuento_hermanos', 'meta_ingresos', 'pago_link',
            'recordatorio_previo_dias', 'recordatorio_mora_cada', 'plantilla_wa', 'plantilla_correo',
        ] as $clave) {
            Settings::set($clave, (string)($v->get($clave) ?? ''), 'cobranza');
        }
        foreach (['smtp_host', 'smtp_puerto', 'smtp_usuario', 'smtp_seguridad', 'smtp_remitente', 'smtp_nombre'] as $clave) {
            Settings::set($clave, (string)($v->get($clave) ?? ''), 'correo');
        }
        $pass = (string)$this->req->raw('smtp_password', '');
        if ($pass !== '') {
            Settings::set('smtp_password', $pass, 'correo');
        }
        Settings::set('smtp_activo', $this->req->bool('smtp_activo') ? '1' : '0', 'correo');
        Settings::set('subida_max_mb', (string)($v->get('subida_max_mb') ?? 8), 'seguridad');
        Settings::set('backup_semanal', $this->req->bool('backup_semanal') ? '1' : '0', 'seguridad');
        Settings::flush();
        Audit::log('config.cobranza', 'settings');
        $this->ok('La configuracion de cobranza y correo fue guardada.');
        return $this->redirect('configuracion/cobranza');
    }

    public function probarCorreo(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $destino = (string)$this->req->input('destino', '');
        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            $this->error('Indique un correo valido para la prueba.');
            return $this->redirect('configuracion/cobranza');
        }
        Settings::flush();
        $ok = Mail::enviar($destino, 'Prueba', 'Prueba de configuracion de correo',
            '<p>Si usted recibe este mensaje, la configuracion de correo de EduPortal funciona correctamente.</p>');
        if ($ok) {
            $this->ok('Correo de prueba enviado a ' . $destino . '.');
        } else {
            $this->error('No se pudo enviar el correo de prueba. Revise los datos SMTP y el registro en storage/logs.');
        }
        return $this->redirect('configuracion/cobranza');
    }

    // ---------------- Estructura academica ----------------

    public function academico(): string
    {
        $this->requireRol('superadmin');
        return $this->view('admin/config/academico', [
            'titulo'    => 'Estructura academica',
            'ciclos'    => Academico::ciclos(),
            'niveles'   => Academico::niveles(),
            'grados'    => Academico::grados(),
            'secciones' => Academico::secciones(),
            'materias'  => Academico::materias(),
            'periodos'  => Academico::periodos(),
            'asignaciones' => Academico::asignaciones(),
            'docentes'  => Academico::docentes(),
        ]);
    }

    public function guardarAcademico(string $tipo): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $id = $this->req->int('id');
        $ciclo = Academico::cicloActivoId();
        switch ($tipo) {
            case 'ciclo':
                $v = Validator::make($this->req->all(), [
                    'nombre' => 'required|len:2,60', 'fecha_inicio' => 'nullable|date', 'fecha_fin' => 'nullable|date',
                ], ['nombre' => 'nombre del ciclo']);
                if ($v->fails()) { break; }
                $activo = $this->req->bool('activo') ? 1 : 0;
                if ($activo) {
                    Database::run('UPDATE ciclos SET activo = 0');
                }
                if ($id > 0) {
                    Database::run('UPDATE ciclos SET nombre = :n, fecha_inicio = :i, fecha_fin = :f, activo = :a WHERE id = :id',
                        ['n' => $v->get('nombre'), 'i' => $v->get('fecha_inicio'), 'f' => $v->get('fecha_fin'), 'a' => $activo, 'id' => $id]);
                } else {
                    Database::run('INSERT INTO ciclos (nombre, fecha_inicio, fecha_fin, activo) VALUES (:n, :i, :f, :a)',
                        ['n' => $v->get('nombre'), 'i' => $v->get('fecha_inicio'), 'f' => $v->get('fecha_fin'), 'a' => $activo]);
                }
                break;

            case 'nivel':
                $v = Validator::make($this->req->all(), ['nombre' => 'required|len:2,60', 'orden' => 'nullable|int'], ['nombre' => 'nombre del nivel']);
                if ($v->fails()) { break; }
                if ($id > 0) {
                    Database::run('UPDATE niveles SET nombre = :n, orden = :o WHERE id = :id',
                        ['n' => $v->get('nombre'), 'o' => (int)($v->get('orden') ?? 0), 'id' => $id]);
                } else {
                    Database::run('INSERT INTO niveles (nombre, orden) VALUES (:n, :o)',
                        ['n' => $v->get('nombre'), 'o' => (int)($v->get('orden') ?? 0)]);
                }
                break;

            case 'grado':
                $v = Validator::make($this->req->all(), [
                    'nombre' => 'required|len:1,60', 'nivel_id' => 'required|int', 'orden' => 'nullable|int',
                ], ['nombre' => 'nombre del grado', 'nivel_id' => 'nivel']);
                if ($v->fails()) { break; }
                if ($id > 0) {
                    Database::run('UPDATE grados SET nombre = :n, nivel_id = :ni, orden = :o WHERE id = :id',
                        ['n' => $v->get('nombre'), 'ni' => (int)$v->get('nivel_id'), 'o' => (int)($v->get('orden') ?? 0), 'id' => $id]);
                } else {
                    Database::run('INSERT INTO grados (nombre, nivel_id, orden) VALUES (:n, :ni, :o)',
                        ['n' => $v->get('nombre'), 'ni' => (int)$v->get('nivel_id'), 'o' => (int)($v->get('orden') ?? 0)]);
                }
                break;

            case 'seccion':
                $v = Validator::make($this->req->all(), [
                    'nombre' => 'required|len:1,30', 'grado_id' => 'required|int',
                    'capacidad' => 'nullable|int|min:1|max:200', 'docente_guia_id' => 'nullable|int',
                ], ['nombre' => 'nombre de la seccion', 'grado_id' => 'grado']);
                if ($v->fails()) { break; }
                $guia = (int)($v->get('docente_guia_id') ?? 0) ?: null;
                if ($id > 0) {
                    Database::run('UPDATE secciones SET nombre = :n, grado_id = :g, capacidad = :c, docente_guia_id = :d WHERE id = :id',
                        ['n' => $v->get('nombre'), 'g' => (int)$v->get('grado_id'), 'c' => (int)($v->get('capacidad') ?? 30), 'd' => $guia, 'id' => $id]);
                } else {
                    Database::run('INSERT INTO secciones (nombre, grado_id, ciclo_id, capacidad, docente_guia_id) VALUES (:n, :g, :ci, :c, :d)',
                        ['n' => $v->get('nombre'), 'g' => (int)$v->get('grado_id'), 'ci' => $ciclo, 'c' => (int)($v->get('capacidad') ?? 30), 'd' => $guia]);
                }
                break;

            case 'materia':
                $v = Validator::make($this->req->all(), [
                    'nombre' => 'required|len:2,90', 'codigo' => 'nullable|max:20', 'nivel_id' => 'nullable|int',
                ], ['nombre' => 'nombre de la materia']);
                if ($v->fails()) { break; }
                $nivel = (int)($v->get('nivel_id') ?? 0) ?: null;
                if ($id > 0) {
                    Database::run('UPDATE materias SET nombre = :n, codigo = :c, nivel_id = :ni WHERE id = :id',
                        ['n' => $v->get('nombre'), 'c' => $v->get('codigo'), 'ni' => $nivel, 'id' => $id]);
                } else {
                    Database::run('INSERT INTO materias (nombre, codigo, nivel_id, activo) VALUES (:n, :c, :ni, 1)',
                        ['n' => $v->get('nombre'), 'c' => $v->get('codigo'), 'ni' => $nivel]);
                }
                break;

            case 'periodo':
                $v = Validator::make($this->req->all(), [
                    'nombre' => 'required|len:2,60', 'orden' => 'nullable|int',
                    'fecha_inicio' => 'nullable|date', 'fecha_fin' => 'nullable|date',
                ], ['nombre' => 'nombre del periodo']);
                if ($v->fails()) { break; }
                if ($id > 0) {
                    Database::run('UPDATE periodos SET nombre = :n, orden = :o, fecha_inicio = :i, fecha_fin = :f WHERE id = :id',
                        ['n' => $v->get('nombre'), 'o' => (int)($v->get('orden') ?? 1), 'i' => $v->get('fecha_inicio'), 'f' => $v->get('fecha_fin'), 'id' => $id]);
                } else {
                    Database::run('INSERT INTO periodos (ciclo_id, nombre, orden, fecha_inicio, fecha_fin) VALUES (:c, :n, :o, :i, :f)',
                        ['c' => $ciclo, 'n' => $v->get('nombre'), 'o' => (int)($v->get('orden') ?? 1), 'i' => $v->get('fecha_inicio'), 'f' => $v->get('fecha_fin')]);
                }
                break;

            case 'asignacion':
                $v = Validator::make($this->req->all(), [
                    'seccion_id' => 'required|int', 'materia_id' => 'required|int', 'docente_id' => 'nullable|int',
                ], ['seccion_id' => 'seccion', 'materia_id' => 'materia']);
                if ($v->fails()) { break; }
                $docente = (int)($v->get('docente_id') ?? 0) ?: null;
                $existe = Database::value(
                    'SELECT id FROM asignaciones WHERE ciclo_id = :c AND seccion_id = :s AND materia_id = :m',
                    ['c' => $ciclo, 's' => (int)$v->get('seccion_id'), 'm' => (int)$v->get('materia_id')]
                );
                if ($existe) {
                    Database::run('UPDATE asignaciones SET docente_id = :d WHERE id = :id', ['d' => $docente, 'id' => (int)$existe]);
                } else {
                    Database::run('INSERT INTO asignaciones (ciclo_id, seccion_id, materia_id, docente_id) VALUES (:c, :s, :m, :d)',
                        ['c' => $ciclo, 's' => (int)$v->get('seccion_id'), 'm' => (int)$v->get('materia_id'), 'd' => $docente]);
                }
                break;

            default:
                throw new HttpException(404, 'Elemento desconocido.');
        }
        if (isset($v) && $v->fails()) {
            $this->error($v->firstError());
        } else {
            Audit::log('academico.' . $tipo, $tipo, $id > 0 ? $id : null);
            $this->ok('Los datos academicos fueron guardados.');
        }
        return $this->redirect('configuracion/academico');
    }

    public function eliminarAcademico(string $tipo, string $id): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $tablas = [
            'ciclo' => 'ciclos', 'nivel' => 'niveles', 'grado' => 'grados', 'seccion' => 'secciones',
            'materia' => 'materias', 'periodo' => 'periodos', 'asignacion' => 'asignaciones',
        ];
        if (!isset($tablas[$tipo])) {
            throw new HttpException(404, 'Elemento desconocido.');
        }
        $dependencias = [
            'ciclo'   => ['inscripciones' => 'ciclo_id'],
            'nivel'   => ['grados' => 'nivel_id'],
            'grado'   => ['secciones' => 'grado_id'],
            'seccion' => ['inscripciones' => 'seccion_id'],
            'materia' => ['asignaciones' => 'materia_id'],
            'periodo' => ['actividades' => 'periodo_id'],
        ];
        foreach ($dependencias[$tipo] ?? [] as $tabla => $campo) {
            $n = (int)Database::value("SELECT COUNT(*) FROM {$tabla} WHERE {$campo} = :id", ['id' => (int)$id], 0);
            if ($n > 0) {
                $this->error('No se puede eliminar: existen ' . $n . ' registros que dependen de este elemento.');
                return $this->redirect('configuracion/academico');
            }
        }
        Database::run('DELETE FROM ' . $tablas[$tipo] . ' WHERE id = :id', ['id' => (int)$id]);
        Audit::log('academico.eliminar', $tipo, (int)$id);
        $this->ok('Elemento eliminado.');
        return $this->redirect('configuracion/academico');
    }

    // ---------------- Sitio publico ----------------

    public function sitio(): string
    {
        $this->requireRol('superadmin');
        return $this->view('admin/config/sitio', [
            'titulo'  => 'Sitio web publico',
            'cfg'     => Settings::all(),
            'paginas' => Database::all('SELECT * FROM paginas ORDER BY id'),
            'galeria' => Database::all('SELECT * FROM galeria ORDER BY orden, id'),
        ]);
    }

    public function guardarSitio(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $v = Validator::make($this->req->all(), [
            'seo_title'         => 'nullable|max:180',
            'seo_description'   => 'nullable|max:300',
            'sitio_hero_titulo' => 'nullable|max:180',
            'sitio_hero_texto'  => 'nullable|max:500',
            'sitio_mision'      => 'nullable|max:3000',
            'sitio_vision'      => 'nullable|max:3000',
            'sitio_mapa'        => 'nullable|max:1000',
        ]);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('configuracion/sitio');
        }
        $mapa = trim((string)($v->get('sitio_mapa') ?? ''));
        if ($mapa !== '' && !preg_match('#^https://(www\.)?(google\.[a-z.]+/maps|maps\.google\.[a-z.]+)/#i', $mapa)) {
            $this->error('El mapa debe ser un enlace de Google Maps que empiece con https://.');
            return $this->redirect('configuracion/sitio');
        }
        foreach (['seo_title', 'seo_description', 'sitio_hero_titulo', 'sitio_hero_texto', 'sitio_mision', 'sitio_vision', 'sitio_mapa'] as $c) {
            Settings::set($c, (string)($v->get($c) ?? ''), 'sitio');
        }
        Settings::set('sitio_activo', $this->req->bool('sitio_activo') ? '1' : '0', 'sitio');
        Settings::set('sitio_inscripcion', $this->req->bool('sitio_inscripcion') ? '1' : '0', 'sitio');

        foreach (['sitio_hero_imagen' => 'sitio', 'seo_og' => 'sitio'] as $campo => $carpeta) {
            if (($this->req->file($campo)['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $r = Upload::store($this->req->file($campo), $carpeta, Upload::IMAGENES);
            if (!$r['ok']) {
                $this->error($r['error']);
                return $this->redirect('configuracion/sitio');
            }
            Imagen::redimensionar(BASE_PATH . '/storage/uploads/' . $r['archivo'], 1920, 1200);
            Settings::set($campo, $r['archivo'], 'sitio');
        }
        Settings::flush();
        Audit::log('config.sitio', 'settings');
        $this->ok('El sitio publico fue actualizado.');
        return $this->redirect('configuracion/sitio');
    }

    public function subirGaleria(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $r = Upload::store($this->req->file('imagen'), 'galeria', Upload::IMAGENES);
        if (!$r['ok']) {
            $this->error($r['error']);
            return $this->redirect('configuracion/sitio');
        }
        Imagen::redimensionar(BASE_PATH . '/storage/uploads/' . $r['archivo'], 1600, 1200);
        Database::run('INSERT INTO galeria (titulo, archivo, orden, activo) VALUES (:t, :a, :o, 1)', [
            't' => mb_substr((string)$this->req->input('titulo', ''), 0, 160),
            'a' => $r['archivo'],
            'o' => $this->req->int('orden'),
        ]);
        $this->ok('Imagen agregada a la galeria.');
        return $this->redirect('configuracion/sitio');
    }

    public function eliminarGaleria(string $id): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $img = Database::one('SELECT * FROM galeria WHERE id = :id', ['id' => (int)$id]);
        if ($img) {
            Upload::delete((string)$img['archivo']);
            Database::run('DELETE FROM galeria WHERE id = :id', ['id' => (int)$id]);
        }
        $this->ok('Imagen eliminada.');
        return $this->redirect('configuracion/sitio');
    }

    public function guardarPagina(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $v = Validator::make($this->req->all(), [
            'slug'      => 'required|regex:/^[a-z0-9-]{2,60}$/',
            'titulo'    => 'required|len:2,160',
            'contenido' => 'nullable|max:20000',
        ], ['slug' => 'identificador', 'titulo' => 'titulo']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('configuracion/sitio');
        }
        $contenido = strip_tags((string)($v->get('contenido') ?? ''), '<p><br><strong><em><ul><ol><li><h3><h4>');
        $existe = Database::value('SELECT id FROM paginas WHERE slug = :s', ['s' => $v->get('slug')]);
        if ($existe) {
            Database::run('UPDATE paginas SET titulo = :t, contenido = :c, activo = :a WHERE id = :id', [
                't' => $v->get('titulo'), 'c' => $contenido, 'a' => $this->req->bool('activo') ? 1 : 0, 'id' => (int)$existe,
            ]);
        } else {
            Database::run('INSERT INTO paginas (slug, titulo, contenido, activo) VALUES (:s, :t, :c, :a)', [
                's' => $v->get('slug'), 't' => $v->get('titulo'), 'c' => $contenido, 'a' => $this->req->bool('activo') ? 1 : 0,
            ]);
        }
        $this->ok('Contenido guardado.');
        return $this->redirect('configuracion/sitio');
    }

    // ---------------- Bitacora, preinscripciones y respaldo ----------------

    public function bitacora(): string
    {
        $this->requireRol('superadmin');
        [$p, $pp, $off] = $this->pagina(50);
        $q = (string)$this->req->input('q', '');
        $w = ['1 = 1'];
        $par = [];
        if ($q !== '') {
            $w[] = '(b.accion LIKE :q OR b.detalle LIKE :q2 OR u.nombre LIKE :q3)';
            $like = '%' . $q . '%';
            $par = ['q' => $like, 'q2' => $like, 'q3' => $like];
        }
        return $this->view('admin/config/bitacora', [
            'titulo'    => 'Bitacora de auditoria',
            'filas'     => Database::all(
                'SELECT b.*, u.nombre AS usuario FROM bitacora b LEFT JOIN users u ON u.id = b.user_id
                 WHERE ' . implode(' AND ', $w) . ' ORDER BY b.id DESC LIMIT ' . $pp . ' OFFSET ' . $off,
                $par
            ),
            'total'     => (int)Database::value(
                'SELECT COUNT(*) FROM bitacora b LEFT JOIN users u ON u.id = b.user_id WHERE ' . implode(' AND ', $w),
                $par,
                0
            ),
            'pagina'    => $p,
            'porPagina' => $pp,
            'q'         => $q,
        ]);
    }

    public function preinscripciones(): string
    {
        $this->requirePermiso('preinscripciones.ver');
        return $this->view('admin/preinscripciones', [
            'titulo' => 'Pre-inscripciones',
            'filas'  => Database::all(
                'SELECT p.*, g.nombre AS grado FROM preinscripciones p
                 LEFT JOIN grados g ON g.id = p.grado_id ORDER BY p.id DESC LIMIT 200'
            ),
            'contactos' => Database::all('SELECT * FROM contactos ORDER BY id DESC LIMIT 100'),
        ]);
    }

    public function estadoPreinscripcion(string $id): string
    {
        $this->requirePermiso('preinscripciones.ver');
        $this->requireCsrf();
        $estado = (string)$this->req->input('estado', 'nueva');
        if (!in_array($estado, ['nueva', 'contactada', 'inscrita', 'descartada'], true)) {
            $this->error('Estado no valido.');
            return $this->redirect('preinscripciones');
        }
        Database::run('UPDATE preinscripciones SET estado = :e WHERE id = :id', ['e' => $estado, 'id' => (int)$id]);
        Audit::log('preinscripcion.estado', 'preinscripciones', (int)$id, $estado);
        $this->ok('Estado actualizado.');
        return $this->redirect('preinscripciones');
    }

    public function respaldo(): string
    {
        $this->requireRol('superadmin');
        $dir = BASE_PATH . '/storage/backups';
        $archivos = [];
        foreach (glob($dir . '/*.sql.gz') ?: [] as $f) {
            $archivos[] = ['nombre' => basename($f), 'tamano' => filesize($f), 'fecha' => date('Y-m-d H:i', (int)filemtime($f))];
        }
        usort($archivos, static fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return $this->view('admin/config/respaldo', [
            'titulo'    => 'Respaldo de la base de datos',
            'archivos'  => $archivos,
            'cronToken' => (string)Settings::get('cron_token', ''),
            'cronUrl'   => url_absoluta('cron/run.php'),
        ]);
    }

    public function generarRespaldo(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $archivo = \App\Servicios\Respaldo::generar();
        if ($archivo === null) {
            $this->error('No se pudo generar el respaldo. Revise los permisos de storage/backups.');
            return $this->redirect('configuracion/respaldo');
        }
        Audit::log('respaldo.generar', 'sistema', null, basename($archivo));
        $this->ok('Respaldo generado: ' . basename($archivo));
        return $this->redirect('configuracion/respaldo');
    }

    public function descargarRespaldo(string $nombre): string
    {
        $this->requireRol('superadmin');
        if (!preg_match('/^respaldo-[\w.-]+\.sql\.gz$/', $nombre)) {
            throw new HttpException(404, 'Respaldo no encontrado.');
        }
        $ruta = BASE_PATH . '/storage/backups/' . $nombre;
        if (!is_file($ruta)) {
            throw new HttpException(404, 'Respaldo no encontrado.');
        }
        Audit::log('respaldo.descargar', 'sistema', null, $nombre);
        if (!headers_sent()) {
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . (string)filesize($ruta));
        }
        readfile($ruta);
        return '';
    }

    public function regenerarToken(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        Settings::set('cron_token', Security::randomToken(24), 'seguridad');
        Settings::flush();
        Audit::log('cron.token', 'settings');
        $this->ok('Se genero un nuevo token para las tareas programadas. Actualice el cron en cPanel.');
        return $this->redirect('configuracion/respaldo');
    }

    public function regenerarIconos(): string
    {
        $this->requireRol('superadmin');
        $this->requireCsrf();
        $logo = (string)Settings::get('colegio_logo', '');
        if ($logo === '' || !is_file(BASE_PATH . '/storage/uploads/' . $logo)) {
            $this->error('Primero debe cargar el logo del colegio.');
            return $this->redirect('configuracion');
        }
        $tema = self::TEMAS[(string)Settings::get('tema', 'default')] ?? self::TEMAS['default'];
        $n = Imagen::generarIconos(BASE_PATH . '/storage/uploads/' . $logo, BASE_PATH . '/storage/uploads/pwa', $tema['primario']);
        if ($n === 0) {
            $this->error('No se pudieron generar los iconos. Verifique que la extension GD este habilitada.');
        } else {
            $this->ok('Se regeneraron ' . $n . ' iconos de la aplicacion movil.');
        }
        return $this->redirect('configuracion');
    }
}
