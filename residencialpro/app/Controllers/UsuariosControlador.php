<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Correo;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Subida;
use App\Core\Validador;
use App\Models\Casa;
use App\Models\Usuario;
use Vendor\Push\WebPush;

final class UsuariosControlador extends Controlador
{
    public function index(): void
    {
        $this->exigirRol('admin');
        $this->mostrar('admin/usuarios/index', [
            'tituloPagina' => 'Usuarios y accesos',
            'subtitulo'    => 'Quién puede entrar al sistema y con qué permisos',
            'usuarios'     => Usuario::listar(['rol' => Peticion::texto('rol'), 'buscar' => Peticion::texto('buscar')], 300),
            'rol'          => Peticion::texto('rol'),
            'buscar'       => Peticion::texto('buscar'),
        ]);
    }

    public function nuevo(int $id = 0): void
    {
        $this->exigirRol('admin');
        $usuario = $id > 0 ? Usuario::porId($id) : null;
        if ($id > 0 && $usuario === null) {
            $this->error('El usuario no existe.', '/admin/usuarios');
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $v = new Validador();
            $nombre  = Peticion::texto('nombre');
            $login   = mb_strtolower(Peticion::texto('usuario'));
            $correo  = mb_strtolower(Peticion::texto('correo'));
            $clave   = (string) ($_POST['clave'] ?? '');

            $v->requerido('nombre', $nombre, 'El nombre')
              ->en('rol', Peticion::texto('rol'), ['admin', 'junta', 'garita', 'residente', 'contabilidad'], 'El perfil')
              ->correo('correo', $correo, 'El correo', true);

            if (!preg_match('/^[a-z0-9._\-]{4,60}$/', $login)) {
                $v->agregar('usuario', 'El usuario debe tener de 4 a 60 caracteres: letras, números, punto o guion.');
            } elseif (!Usuario::usuarioDisponible($login, $id)) {
                $v->agregar('usuario', 'Ese nombre de usuario ya está ocupado.');
            }
            if ($correo !== '' && !Usuario::correoDisponible($correo, $id)) {
                $v->agregar('correo', 'Ese correo ya está registrado en otro usuario.');
            }
            if ($id === 0 || $clave !== '') {
                $problema = Auth::politicaClave($clave);
                if ($problema !== null) {
                    $v->agregar('clave', $problema);
                }
            }
            if ($id === Auth::id() && Peticion::texto('rol') !== 'admin') {
                $v->agregar('rol', 'No puede quitarse a sí mismo el perfil de administrador.');
            }

            if ($v->ok()) {
                $datos = [
                    'rol'      => Peticion::texto('rol'),
                    'nombre'   => $nombre,
                    'usuario'  => $login,
                    'correo'   => $correo ?: null,
                    'telefono' => Peticion::texto('telefono') ?: null,
                    'activo'   => Peticion::bool('activo') ? 1 : 0,
                    'clave'    => $clave,
                ];
                if ($id > 0) {
                    Usuario::actualizar($id, $datos);
                    $nuevoId = $id;
                } else {
                    $nuevoId = Usuario::crear($datos);
                }
                // Vinculación con viviendas (para residentes).
                $casas = array_map('intval', Peticion::arreglo('casas'));
                if ($datos['rol'] === 'residente') {
                    DB::q('UPDATE residentes SET usuario_id = NULL WHERE usuario_id = :u', ['u' => $nuevoId]);
                    foreach ($casas as $casaId) {
                        $residente = DB::uno(
                            'SELECT id FROM residentes WHERE casa_id = :c AND activo = 1 AND usuario_id IS NULL
                             ORDER BY (tipo = "propietario") DESC, id LIMIT 1',
                            ['c' => $casaId]
                        );
                        if ($residente !== null) {
                            DB::actualizar('residentes', ['usuario_id' => $nuevoId], 'id = :id', ['id' => (int) $residente['id']]);
                        } else {
                            DB::insertar('residentes', [
                                'casa_id'    => $casaId,
                                'usuario_id' => $nuevoId,
                                'nombre'     => $nombre,
                                'tipo'       => 'propietario',
                                'correo'     => $correo ?: null,
                                'telefono'   => $datos['telefono'],
                                'activo'     => 1,
                            ]);
                        }
                    }
                }
                if ($id === 0 && $correo !== '' && Peticion::bool('enviar_datos')) {
                    Correo::enviar(
                        $correo,
                        $nombre,
                        'Su acceso a ' . Ajustes::get('nombre', 'ResidencialPro'),
                        Correo::plantillaHtml(
                            'Su acceso al sistema',
                            '<p>Estimado(a) ' . e($nombre) . ',</p>'
                            . '<p>Se creó su acceso al sistema de administración de <strong>'
                            . e(Ajustes::get('nombre', '')) . '</strong> con el perfil <strong>'
                            . e(rolNombre($datos['rol'])) . '</strong>.</p>'
                            . '<table style="width:100%;border-collapse:collapse;margin:16px 0">'
                            . '<tr><td style="padding:8px 0;color:#6E6A61">Usuario</td><td style="padding:8px 0"><strong>' . e($login) . '</strong></td></tr>'
                            . '<tr><td style="padding:8px 0;color:#6E6A61">Contraseña</td><td style="padding:8px 0"><strong>' . e($clave) . '</strong></td></tr>'
                            . '</table>'
                            . '<p>Le recomendamos cambiarla al ingresar por primera vez.</p>',
                            'Ingresar',
                            \App\Core\Url::absoluta('/acceso')
                        )
                    );
                }
                $this->exito($id > 0 ? 'Usuario actualizado.' : 'Usuario creado.', '/admin/usuarios');
            }
            $this->error($v->primerError(), $id > 0 ? '/admin/usuarios/' . $id . '/editar' : '/admin/usuarios/nuevo');
        }

        $casasUsuario = $id > 0 ? Auth::casasDe($id) : [];
        $this->mostrar('admin/usuarios/editar', [
            'tituloPagina' => $id > 0 ? 'Editar usuario' : 'Nuevo usuario',
            'usuario'      => $usuario,
            'casas'        => Casa::opciones(),
            'casasUsuario' => $casasUsuario,
            'claveSugerida' => ResidentesControlador::claveTemporal(),
        ]);
    }

    // --------------------------------------------------------------- AJUSTES

    public function ajustes(): void
    {
        $this->exigirRol('admin');
        if ($this->post()) {
            $this->verificarCsrf();
            $grupo = Peticion::texto('grupo', 'general');

            if ($grupo === 'general') {
                $logo = Subida::guardar('logo', 'logos', Subida::IMAGENES, 4);
                if ($logo !== null) {
                    Ajustes::set('logo', $logo, 'general');
                    $this->regenerarIconos($logo);
                }
                $firma = Subida::guardar('firma_archivo', 'logos', Subida::IMAGENES, 3);
                if ($firma !== null) {
                    Ajustes::set('firma_archivo', $firma, 'general');
                }
                Ajustes::setVarios([
                    'nombre'         => Peticion::texto('nombre'),
                    'lema'           => Peticion::texto('lema'),
                    'nit'            => Peticion::texto('nit'),
                    'direccion'      => Peticion::texto('direccion'),
                    'ciudad'         => Peticion::texto('ciudad'),
                    'telefono'       => Peticion::texto('telefono'),
                    'whatsapp'       => Peticion::texto('whatsapp'),
                    'correo'         => Peticion::texto('correo'),
                    'moneda_simbolo' => Peticion::texto('moneda_simbolo', 'Q'),
                    'pais_codigo'    => Peticion::texto('pais_codigo', '502'),
                    'tema'           => Peticion::texto('tema', 'verde-oro'),
                    'color_primario' => preg_match('/^#[0-9a-f]{6}$/i', Peticion::texto('color_primario')) ? Peticion::texto('color_primario') : '#0E4C5A',
                    'color_acento'   => preg_match('/^#[0-9a-f]{6}$/i', Peticion::texto('color_acento')) ? Peticion::texto('color_acento') : '#B94E27',
                    'firma_nombre'   => Peticion::texto('firma_nombre'),
                    'firma_cargo'    => Peticion::texto('firma_cargo'),
                ], 'general');
                $reglamento = Subida::guardar('reglamento', 'documentos', Subida::DOCS, 12);
                if ($reglamento !== null) {
                    Ajustes::set('reglamento', $reglamento, 'general');
                }
            }

            if ($grupo === 'cobros') {
                Ajustes::setVarios([
                    'mora_tipo'                => Peticion::texto('mora_tipo', 'porcentaje'),
                    'mora_valor'               => (string) Peticion::decimal('mora_valor'),
                    'mora_dias_gracia'         => (string) Peticion::entero('mora_dias_gracia'),
                    'mora_tope_porcentaje'     => (string) Peticion::decimal('mora_tope_porcentaje', 100),
                    'recordatorio_previo_dias' => (string) Peticion::entero('recordatorio_previo_dias', 5),
                    'recordatorio_cada_dias'   => (string) Peticion::entero('recordatorio_cada_dias', 7),
                    'carta_dias'               => (string) Peticion::entero('carta_dias', 60),
                    'carta_plazo_dias'         => (string) Peticion::entero('carta_plazo_dias', 15),
                    'corte_dias'               => (string) Peticion::entero('corte_dias', 90),
                    'mostrar_restriccion_garita' => Peticion::bool('mostrar_restriccion_garita') ? '1' : '0',
                    'cuenta_deposito'          => Peticion::texto('cuenta_deposito'),
                    'enlace_pago'              => filter_var(Peticion::texto('enlace_pago'), FILTER_VALIDATE_URL) ? Peticion::texto('enlace_pago') : '',
                    'recibo_prefijo'           => Peticion::texto('recibo_prefijo'),
                    'carta_texto'              => Peticion::texto('carta_texto'),
                    'generacion_automatica'    => Peticion::bool('generacion_automatica') ? '1' : '0',
                ], 'cobros');
            }

            if ($grupo === 'mensajes') {
                Ajustes::setVarios([
                    'correo_activo'   => Peticion::bool('correo_activo') ? '1' : '0',
                    'smtp_host'       => Peticion::texto('smtp_host'),
                    'smtp_puerto'     => (string) Peticion::entero('smtp_puerto', 587),
                    'smtp_usuario'    => Peticion::texto('smtp_usuario'),
                    'smtp_seguridad'  => Peticion::texto('smtp_seguridad', 'tls'),
                    'smtp_de_correo'  => Peticion::texto('smtp_de_correo'),
                    'smtp_de_nombre'  => Peticion::texto('smtp_de_nombre'),
                    'correo_pie'      => Peticion::texto('correo_pie'),
                    'wa_recordatorio' => Peticion::texto('wa_recordatorio'),
                    'wa_recibo'       => Peticion::texto('wa_recibo'),
                    'wa_visita'       => Peticion::texto('wa_visita'),
                    'avisar_visita'   => Peticion::bool('avisar_visita') ? '1' : '0',
                ], 'mensajes');
                if (Peticion::texto('smtp_clave') !== '') {
                    Ajustes::set('smtp_clave', Peticion::texto('smtp_clave'), 'mensajes');
                }
                if (Peticion::texto('accion') === 'probar_smtp') {
                    $m = Correo::mailer();
                    $ok = $m->probarConexion();
                    Auditoria::registrar('probar_smtp', null, null, $ok ? 'Conexión correcta' : $m->error());
                    if ($ok) {
                        $this->exito('Conexión con el servidor de correo establecida correctamente.', '/admin/ajustes?seccion=mensajes');
                    }
                    $this->error('No se pudo conectar: ' . $m->error(), '/admin/ajustes?seccion=mensajes');
                }
                if (Peticion::texto('accion') === 'correo_prueba') {
                    $destino = Peticion::texto('correo_prueba');
                    $ok = Correo::enviar($destino, 'Prueba', 'Correo de prueba de ' . Ajustes::get('nombre', ''),
                        Correo::plantillaHtml('Correo de prueba',
                            '<p>Si está leyendo este mensaje, la configuración de correo de su sistema funciona correctamente.</p>'),
                        [], false);
                    if ($ok) {
                        $this->exito('Correo de prueba enviado a ' . $destino . '.', '/admin/ajustes?seccion=mensajes');
                    }
                    $this->error('No se pudo enviar el correo de prueba. Revise los datos SMTP.', '/admin/ajustes?seccion=mensajes');
                }
            }

            if ($grupo === 'notificaciones') {
                if (Peticion::texto('accion') === 'generar_vapid') {
                    try {
                        $c = WebPush::generarClaves();
                        Ajustes::setVarios(['vapid_publica' => $c['publica'], 'vapid_privada' => $c['privada']], 'notificaciones');
                        Auditoria::registrar('generar_vapid');
                        $this->exito('Claves de notificaciones generadas. Los residentes ya pueden activarlas.', '/admin/ajustes?seccion=notificaciones');
                    } catch (\Throwable $e) {
                        $this->error('No se pudieron generar: ' . $e->getMessage(), '/admin/ajustes?seccion=notificaciones');
                    }
                }
                Ajustes::setVarios([
                    'respaldo_automatico' => Peticion::bool('respaldo_automatico') ? '1' : '0',
                ], 'notificaciones');
            }

            Ajustes::limpiarCache();
            Auditoria::registrar('guardar_ajustes', null, null, 'Sección ' . $grupo);
            $this->exito('Ajustes guardados.', '/admin/ajustes?seccion=' . $grupo);
        }

        $this->mostrar('admin/ajustes', [
            'tituloPagina' => 'Ajustes del condominio',
            'subtitulo'    => 'Identidad, cobros, mensajes y notificaciones',
            'seccion'      => Peticion::texto('seccion', 'general'),
            'a'            => Ajustes::todos(),
            'pushOk'       => WebPush::disponible(),
            'cronToken'    => (string) \App\Core\Config::get('cron.token', ''),
        ]);
    }

    public function sitio(): void
    {
        $this->exigirRol('admin');
        if ($this->post()) {
            $this->verificarCsrf();
            $accion = Peticion::texto('accion');

            if ($accion === 'contenido') {
                $portada = Subida::guardar('portada', 'galeria', Subida::IMAGENES, 8);
                if ($portada !== null) {
                    Ajustes::set('portada', $portada, 'sitio');
                }
                Ajustes::setVarios([
                    'titular'         => Peticion::texto('titular'),
                    'descripcion'     => Peticion::texto('descripcion'),
                    'lema'            => Peticion::texto('lema'),
                    'horario_semana'  => Peticion::texto('horario_semana'),
                    'horario_sabado'  => Peticion::texto('horario_sabado'),
                ], 'sitio');
                $this->exito('Contenido del sitio actualizado.', '/admin/sitio');
            }

            if ($accion === 'amenidad') {
                $titulo = Peticion::texto('titulo');
                if ($titulo !== '') {
                    $datos = [
                        'titulo'  => $titulo,
                        'detalle' => Peticion::texto('detalle') ?: null,
                        'icono'   => Peticion::texto('icono', 'brillo'),
                        'orden'   => Peticion::entero('orden'),
                        'activo'  => 1,
                    ];
                    $id = Peticion::entero('id');
                    if ($id > 0) {
                        DB::actualizar('amenidades', $datos, 'id = :id', ['id' => $id]);
                    } else {
                        DB::insertar('amenidades', $datos);
                    }
                }
                $this->exito('Amenidad guardada.', '/admin/sitio');
            }

            if ($accion === 'galeria') {
                $archivo = Subida::guardar('imagen', 'galeria', Subida::IMAGENES, 8);
                if ($archivo === null) {
                    $this->error(Subida::$ultimoError !== '' ? Subida::$ultimoError : 'Seleccione una imagen.', '/admin/sitio');
                }
                DB::insertar('galeria', [
                    'titulo'  => Peticion::texto('titulo') ?: null,
                    'archivo' => $archivo,
                    'orden'   => Peticion::entero('orden'),
                    'activo'  => 1,
                ]);
                $this->exito('Imagen agregada a la galería.', '/admin/sitio');
            }
        }

        $this->mostrar('admin/sitio', [
            'tituloPagina' => 'Sitio público',
            'subtitulo'    => 'La página que ven quienes aún no son residentes',
            'a'            => Ajustes::todos(),
            'amenidades'   => DB::todos('SELECT * FROM amenidades ORDER BY orden, id'),
            'galeria'      => DB::todos('SELECT * FROM galeria ORDER BY orden, id'),
            'contactos'    => DB::todos('SELECT * FROM contactos_web ORDER BY id DESC LIMIT 30'),
        ]);
    }

    public function perfil(): void
    {
        if (Auth::invitado()) {
            $this->redirigir('/acceso');
        }
        $u = Usuario::porId(Auth::id());
        if ($u === null) {
            Auth::salir();
            $this->redirigir('/acceso');
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $accion = Peticion::texto('accion');

            if ($accion === 'datos') {
                $nombre = Peticion::texto('nombre');
                $correo = mb_strtolower(Peticion::texto('correo'));
                if ($nombre === '') {
                    $this->error('Escriba su nombre.', '/perfil');
                }
                if ($correo !== '' && !Usuario::correoDisponible($correo, (int) $u['id'])) {
                    $this->error('Ese correo ya está registrado por otro usuario.', '/perfil');
                }
                DB::actualizar('usuarios', [
                    'nombre'       => $nombre,
                    'correo'       => $correo ?: null,
                    'telefono'     => Peticion::texto('telefono') ?: null,
                    'dos_factores' => Peticion::bool('dos_factores') ? 1 : 0,
                ], 'id = :id', ['id' => (int) $u['id']]);
                $_SESSION['usuario']['nombre'] = $nombre;
                Auditoria::registrar('editar_perfil', 'usuarios', (int) $u['id']);
                $this->exito('Sus datos se actualizaron.', '/perfil');
            }

            if ($accion === 'clave') {
                $actual = (string) ($_POST['clave_actual'] ?? '');
                $nueva  = (string) ($_POST['clave'] ?? '');
                $nueva2 = (string) ($_POST['clave2'] ?? '');
                if (!Auth::verificar($actual, (string) $u['password_hash'])) {
                    $this->error('La contraseña actual no es correcta.', '/perfil');
                }
                $problema = Auth::politicaClave($nueva);
                if ($problema !== null) {
                    $this->error($problema, '/perfil');
                }
                if ($nueva !== $nueva2) {
                    $this->error('Las contraseñas nuevas no coinciden.', '/perfil');
                }
                DB::actualizar('usuarios', ['password_hash' => Auth::hash($nueva)], 'id = :id', ['id' => (int) $u['id']]);
                Auditoria::registrar('cambiar_clave', 'usuarios', (int) $u['id']);
                $this->exito('Su contraseña se actualizó correctamente.', '/perfil');
            }
        }

        $this->mostrar('admin/perfil', [
            'tituloPagina' => 'Mi perfil',
            'u'            => $u,
            'casas'        => array_map(static fn($id) => Casa::porId((int) $id), Auth::casas()),
            'dispositivos' => DB::todos('SELECT id, endpoint, creado_en FROM push_subs WHERE usuario_id = :u', ['u' => (int) $u['id']]),
        ], Auth::esStaff() || Auth::es('garita') ? 'app' : 'portal');
    }

    /** Regenera los iconos de la PWA a partir del logotipo cargado. */
    private function regenerarIconos(string $logo): void
    {
        $origen = RUTA_BASE . '/uploads/logos/' . $logo;
        if (!extension_loaded('gd') || !is_file($origen)) {
            return;
        }
        $img = @imagecreatefromstring((string) file_get_contents($origen));
        if ($img === false) {
            return;
        }
        $fondo = Ajustes::get('color_primario', '#0E4C5A');
        [$r, $g, $b] = [hexdec(substr($fondo, 1, 2)), hexdec(substr($fondo, 3, 2)), hexdec(substr($fondo, 5, 2))];
        $dir = RUTA_BASE . '/assets/img';
        foreach ([48, 72, 96, 128, 144, 152, 167, 180, 192, 256, 384, 512] as $t) {
            $this->iconoDesde($img, $t, (int) $r, (int) $g, (int) $b, $dir . '/icono-' . $t . '.png', 0.74);
        }
        foreach ([192, 512] as $t) {
            $this->iconoDesde($img, $t, (int) $r, (int) $g, (int) $b, $dir . '/icono-maskable-' . $t . '.png', 0.58);
        }
        $this->iconoDesde($img, 32, (int) $r, (int) $g, (int) $b, $dir . '/favicon.png', 0.8);
        imagedestroy($img);
        Auditoria::registrar('regenerar_iconos', null, null, $logo);
    }

    private function iconoDesde(\GdImage $src, int $tam, int $r, int $g, int $b, string $destino, float $escala): void
    {
        $lienzo = imagecreatetruecolor($tam, $tam);
        imagefilledrectangle($lienzo, 0, 0, $tam, $tam, imagecolorallocate($lienzo, $r, $g, $b));
        $anO = imagesx($src);
        $alO = imagesy($src);
        $lado = (int) round($tam * $escala);
        $an = $anO >= $alO ? $lado : (int) round($lado * $anO / $alO);
        $al = $alO > $anO ? $lado : (int) round($lado * $alO / $anO);
        imagecopyresampled($lienzo, $src, (int) (($tam - $an) / 2), (int) (($tam - $al) / 2), 0, 0, $an, $al, $anO, $alO);
        imagepng($lienzo, $destino, 8);
        imagedestroy($lienzo);
    }
}
