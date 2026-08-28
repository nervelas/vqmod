<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Super;

use MenuGold\Core\Audit;
use MenuGold\Core\Backup;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Image;
use MenuGold\Core\Request;
use MenuGold\Core\Setting;

/**
 * Ajustes de la plataforma, mensajes de contacto y respaldos globales.
 */
class Ajustes extends Panel
{
    private const CLAVES = [
        'nombre_plataforma', 'eslogan', 'descripcion', 'email_contacto', 'whatsapp', 'telefono',
        'direccion', 'hero_titulo', 'hero_subtitulo', 'cta_texto', 'demo_slug',
        'smtp_host', 'smtp_puerto', 'smtp_usuario', 'smtp_seguridad', 'smtp_desde',
        'backup_semanal', 'aviso_vencimiento_dias', 'landing_logo', 'landing_imagen',
        'facebook', 'instagram', 'video_url', 'terminos', 'privacidad',
    ];

    public function index(): void
    {
        $valores = [];
        foreach (self::CLAVES as $c) $valores[$c] = (string)Setting::plat($c, '');
        $valores['tiene_smtp_clave'] = Setting::plat('smtp_clave', '') !== '';

        $this->super('super/ajustes', [
            'v'      => $valores,
            'slugs'  => DB::pairs("SELECT slug, nombre FROM restaurants ORDER BY nombre"),
            'cron'   => (string)\MenuGold\Core\App::config('cron_token', ''),
        ]);
    }

    public function guardar(): void
    {
        $pares = [];
        foreach (self::CLAVES as $c) {
            if ($c === 'landing_logo' || $c === 'landing_imagen') continue;
            $pares[$c] = Request::str($c, '', 2500);
        }
        $clave = (string)Request::input('smtp_clave', '');
        if ($clave !== '') $pares['smtp_clave'] = $clave;
        $pares['backup_semanal'] = Request::bool('backup_semanal') ? '1' : '0';

        foreach (['landing_logo' => [500, 500], 'landing_imagen' => [1600, 1100]] as $campo => $tam) {
            $f = Request::file($campo);
            if ($f) {
                [$ok, $res] = Image::upload($f, 'plataforma', $tam[0], $tam[1], 85);
                if ($ok) {
                    Image::delete((string)Setting::plat($campo, ''));
                    $pares[$campo] = $res;
                } else {
                    flash('error', $res);
                }
            }
        }

        Setting::setPlatMany($pares);
        Audit::log('plataforma.ajustes', 'platform_settings');
        flash('exito', 'Ajustes de la plataforma guardados.');
        redirect('super/ajustes');
    }

    // ---------------------------------------------------------------- mensajes
    public function mensajes(): void
    {
        $this->super('super/mensajes', [
            'mensajes' => DB::all('SELECT * FROM contact_messages ORDER BY creado DESC LIMIT 300'),
            'sinLeer'  => DB::int('SELECT COUNT(*) FROM contact_messages WHERE leido = 0'),
        ]);
    }

    public function mensajeLeido(): void
    {
        $id = Request::int('id');
        if (Request::bool('borrar')) {
            DB::delete('contact_messages', 'id = :i', ['i' => $id]);
            $this->ok([], 'Mensaje eliminado');
        }
        DB::update('contact_messages', ['leido' => 1], 'id = :i', ['i' => $id]);
        $this->ok([], 'Marcado como leído');
    }

    // ---------------------------------------------------------------- respaldos
    public function respaldos(): void
    {
        $this->super('super/respaldos', [
            'archivos' => Backup::listar(),
            'espacio'  => Backup::espacio(),
            'semanal'  => Setting::plat('backup_semanal', '1') === '1',
            'cron'     => (string)\MenuGold\Core\App::config('cron_token', ''),
        ]);
    }

    public function respaldoCrear(): void
    {
        try {
            $archivo = Backup::crear('plataforma');
            Audit::log('respaldo', 'backup', 0, null, ['archivo' => basename($archivo)]);
            $this->ok(['archivo' => basename($archivo)], 'Respaldo creado');
        } catch (\Throwable $e) {
            $this->fail('No se pudo crear el respaldo: ' . $e->getMessage(), 500);
        }
    }

    public function respaldoBajar(array $p = []): void
    {
        $archivo = Backup::ruta((string)($p['archivo'] ?? ''));
        if (!$archivo) throw HttpException::notFound('Respaldo no encontrado.');
        $this->download((string)file_get_contents($archivo), basename($archivo), 'application/sql');
    }
}
