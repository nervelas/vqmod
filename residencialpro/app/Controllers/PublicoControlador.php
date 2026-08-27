<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Controlador;
use App\Core\Correo;
use App\Core\DB;
use App\Core\LimiteIntentos;
use App\Core\Peticion;
use App\Core\Sesion;
use App\Core\Url;
use App\Core\Validador;
use App\Models\Casa;
use App\Models\Pago;

final class PublicoControlador extends Controlador
{
    public function inicio(): void
    {
        $this->mostrar('publico/inicio', [
            'tituloPagina' => Ajustes::get('nombre', 'ResidencialPro'),
            'amenidades'   => DB::todos('SELECT * FROM amenidades WHERE activo = 1 ORDER BY orden, id'),
            'galeria'      => DB::todos('SELECT * FROM galeria WHERE activo = 1 ORDER BY orden, id LIMIT 12'),
            'eventos'      => DB::todos('SELECT * FROM eventos WHERE publico = 1 AND inicio >= NOW() ORDER BY inicio LIMIT 3'),
        ], 'publico');
    }

    public function contacto(): void
    {
        $enviado = false;
        $errores = [];
        $a = random_int(2, 9);
        $b = random_int(2, 9);

        if ($this->post()) {
            $this->verificarCsrf();
            $llave = 'contacto:' . Peticion::ip();
            $v = new Validador();
            $v->requerido('nombre', Peticion::texto('nombre'), 'El nombre')
              ->largoMax('nombre', Peticion::texto('nombre'), 140, 'El nombre')
              ->correo('correo', Peticion::texto('correo'), 'El correo', true)
              ->requerido('mensaje', Peticion::texto('mensaje'), 'El mensaje')
              ->largoMax('mensaje', Peticion::texto('mensaje'), 3000, 'El mensaje');

            if (Peticion::texto('sitio_web') !== '') {
                $v->agregar('spam', 'No se pudo procesar el envío.');
            }
            $esperado = (int) Sesion::get('_captcha', -1);
            if ($esperado < 0 || Peticion::entero('captcha', -1) !== $esperado) {
                $v->agregar('captcha', 'La suma de verificación no es correcta.');
            }
            if (!LimiteIntentos::permitido($llave, 5, 30)) {
                $v->agregar('limite', 'Se enviaron demasiados mensajes desde esta conexión. Intente más tarde.');
            }

            if ($v->ok()) {
                LimiteIntentos::registrar($llave);
                DB::insertar('contactos_web', [
                    'nombre'   => Peticion::texto('nombre'),
                    'correo'   => Peticion::texto('correo'),
                    'telefono' => Peticion::texto('telefono'),
                    'mensaje'  => Peticion::texto('mensaje'),
                    'ip'       => Peticion::ip(),
                ]);
                $destino = Ajustes::get('correo', '');
                if ($destino !== '') {
                    Correo::enviar(
                        $destino,
                        Ajustes::get('nombre', ''),
                        'Nuevo mensaje desde el sitio web',
                        Correo::plantillaHtml(
                            'Mensaje del sitio web',
                            '<p><strong>' . e(Peticion::texto('nombre')) . '</strong><br>'
                            . e(Peticion::texto('correo')) . ' · ' . e(Peticion::texto('telefono')) . '</p>'
                            . '<p>' . nl2br(e(Peticion::texto('mensaje'))) . '</p>'
                        )
                    );
                }
                $enviado = true;
            } else {
                $errores = $v->errores();
            }
        }

        Sesion::set('_captcha', $a + $b);
        $this->mostrar('publico/contacto', [
            'tituloPagina' => 'Contacto',
            'enviado'      => $enviado,
            'errores'      => $errores,
            'sumaA'        => $a,
            'sumaB'        => $b,
        ], 'publico');
    }

    public function reglamento(): void
    {
        $archivo = Ajustes::get('reglamento', '');
        if ($archivo !== '' && is_file(RUTA_BASE . '/uploads/documentos/' . basename($archivo))) {
            $this->redirigir('/archivo/documentos/' . basename($archivo));
        }
        $this->mostrar('publico/reglamento', ['tituloPagina' => 'Reglamento interno'], 'publico');
    }

    /** Verificación pública de un recibo por QR. */
    public function verificar(string $hash = ''): void
    {
        $pago = Pago::porVerificacion($hash);
        $detalle = $pago !== null ? Pago::detalle((int) $pago['id']) : [];
        $this->mostrar('publico/verificar', [
            'tituloPagina' => 'Verificación de recibo',
            'pago'         => $pago,
            'detalle'      => $detalle,
        ], 'publico');
    }

    /** Verificación pública de una constancia de solvencia. */
    public function verificarSolvencia(int $casa = 0, string $codigo = ''): void
    {
        $c = Casa::porId($casa);
        $valido = false;
        $saldo  = 0.0;
        if ($c !== null) {
            for ($i = 0; $i <= 30; $i++) {
                $fecha = date('Y-m-d', strtotime("-{$i} days"));
                $esperado = strtoupper(substr(hash('sha256', $casa . '|' . $fecha . '|' . Ajustes::get('nombre', '')), 0, 12));
                if (hash_equals($esperado, strtoupper($codigo))) {
                    $valido = true;
                    break;
                }
            }
            $saldo = Casa::saldo($casa);
        }
        $this->mostrar('publico/verificar-solvencia', [
            'tituloPagina' => 'Verificación de solvencia',
            'casa'         => $c,
            'valido'       => $valido,
            'saldo'        => $saldo,
        ], 'publico');
    }

    /** robots.txt dinámico: incluye la URL absoluta del mapa del sitio. */
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        $base = Url::basePath();
        $lineas = [
            'User-agent: *',
        ];
        foreach (['/admin', '/portal', '/garita', '/install', '/cron', '/doc', '/excel', '/api', '/acceso',
                  '/recuperar', '/restablecer', '/perfil', '/verificar',
                  '/uploads/comprobantes', '/uploads/facturas', '/uploads/visitas', '/uploads/incidencias'] as $r) {
            $lineas[] = 'Disallow: ' . $base . $r;
        }
        $lineas[] = 'Allow: ' . ($base !== '' ? $base . '/' : '/');
        $lineas[] = '';
        $lineas[] = 'Sitemap: ' . Url::absoluta('/sitemap.xml');
        echo implode("\n", $lineas) . "\n";
        exit;
    }

    public function sitemap(): void
    {
        $raiz = rtrim(Url::absoluta('/'), '/');
        $rutas = ['/', '/contacto', '/acceso'];
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($rutas as $r) {
            echo "  <url>\n    <loc>" . e($raiz . $r) . "</loc>\n"
               . "    <changefreq>weekly</changefreq>\n"
               . "    <priority>" . ($r === '/' ? '1.0' : '0.6') . "</priority>\n  </url>\n";
        }
        echo '</urlset>';
        exit;
    }
}
