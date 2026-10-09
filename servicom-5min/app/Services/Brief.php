<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Sanitize;

/** Esquema canónico del formulario: saneo estricto, validación mínima y fusión con la presentación. */
final class Brief
{
    public const RUBROS = ['abogado', 'clinica', 'taller', 'ropa', 'restaurante', 'transporte', 'contabilidad', 'importaciones', 'otro'];
    public const REDES = ['facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin'];
    public const MAX_SERVICIOS = 300;
    public const MAX_PRODUCTOS = 1000;
    public const MAX_CATEGORIAS = 200;
    public const MAX_GALERIA = 60;

    public static function blank(string $plan = 'info'): array
    {
        return [
            'plan' => $plan === 'tienda' ? 'tienda' : 'info',
            'tarjeta_extra' => false,
            'negocio' => ['nombre' => '', 'rubro' => '', 'rubro_otro' => '', 'idioma' => 'es', 'estilo' => 1, 'logo' => null],
            'dominio' => ['tiene' => false, 'dominio' => '', 'deseado' => ''],
            'correos' => [],
            'correo_contacto' => '',
            'contenido' => ['frase' => '', 'apoyo' => '', 'banner' => [], 'quienes' => '', 'servicios' => [], 'galeria' => [], 'youtube' => ''],
            'tienda' => ['categorias' => [], 'productos' => [], 'umbral_stock' => 3, 'correo_alertas' => '', 'correo_pedidos' => '', 'banco' => ['banco' => '', 'numero' => '', 'titular' => '', 'tipo' => ''], 'contra_entrega' => false, 'nota_entrega' => ''],
            'contacto' => ['whatsapp' => '', 'whatsapp_msg' => '', 'telefono' => '', 'direccion' => '', 'mapa_url' => '', 'horario' => '', 'redes' => array_fill_keys(self::REDES, '')],
            'pago' => ['nombre_nit' => '', 'comprobante' => null],
            'presentacion' => ['file' => null, 'acepto' => false, 'estado' => 'ninguna', 'confirmada' => false, 'usar' => [], 'fotos_usar' => []],
            'origen' => [],
        ];
    }

    /** IDs de archivo válidos del pedido, por tipo. */
    private static function fileIds(int $orderId): array
    {
        $rows = Db::all('SELECT id, kind FROM ' . Db::t('files') . ' WHERE order_id=?', [$orderId]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['kind'];
        }
        return $out;
    }

    private static function fid($v, array $valid, array $kinds): ?int
    {
        if ($v === null || $v === '' || $v === false) {
            return null;
        }
        $id = (int) $v;
        return isset($valid[$id]) && in_array($valid[$id], $kinds, true) ? $id : null;
    }

    private static function ids($v, array $valid, array $kinds, int $max): array
    {
        $out = [];
        foreach ((array) $v as $x) {
            $id = self::fid($x, $valid, $kinds);
            if ($id !== null && !in_array($id, $out, true)) {
                $out[] = $id;
            }
            if (count($out) >= $max) {
                break;
            }
        }
        return $out;
    }

    /**
     * Mezcla lo recibido del navegador sobre lo guardado, con lista blanca, tipos y longitudes estrictos.
     * Solo se tocan las claves presentes en $in. Nunca se acepta 'presentacion.confirmada' ni 'origen' del cliente.
     */
    public static function sanitize(array $in, array $cur, int $orderId): array
    {
        $b = $cur + self::blank();
        $valid = self::fileIds($orderId);
        $photo = ['foto', 'logo', 'presentacion_img'];

        if (isset($in['plan']) && in_array($in['plan'], ['info', 'tienda'], true)) {
            $b['plan'] = $in['plan'];
        }
        if (array_key_exists('tarjeta_extra', $in)) {
            $b['tarjeta_extra'] = (bool) $in['tarjeta_extra'];
        }
        if (isset($in['negocio']) && is_array($in['negocio'])) {
            $n = $in['negocio'];
            if (array_key_exists('nombre', $n)) { $b['negocio']['nombre'] = Sanitize::text($n['nombre'], 80); }
            if (array_key_exists('rubro', $n)) { $b['negocio']['rubro'] = in_array($n['rubro'], self::RUBROS, true) ? $n['rubro'] : ''; }
            if (array_key_exists('rubro_otro', $n)) { $b['negocio']['rubro_otro'] = Sanitize::text($n['rubro_otro'], 60); }
            if (array_key_exists('idioma', $n)) { $b['negocio']['idioma'] = $n['idioma'] === 'en' ? 'en' : 'es'; }
            if (array_key_exists('estilo', $n)) { $b['negocio']['estilo'] = Sanitize::intRange($n['estilo'], 1, 5); }
            if (array_key_exists('logo', $n)) { $b['negocio']['logo'] = self::fid($n['logo'], $valid, ['logo', 'foto', 'presentacion_img']); }
        }
        if (isset($in['dominio']) && is_array($in['dominio'])) {
            $d = $in['dominio'];
            if (array_key_exists('tiene', $d)) { $b['dominio']['tiene'] = (bool) $d['tiene']; }
            if (array_key_exists('dominio', $d)) { $b['dominio']['dominio'] = self::domain($d['dominio']); }
            if (array_key_exists('deseado', $d)) { $b['dominio']['deseado'] = self::domain($d['deseado']); }
        }
        if (array_key_exists('correos', $in)) {
            $list = [];
            foreach ((array) $in['correos'] as $c) {
                $c = strtolower(preg_replace('/[^a-z0-9._-]/i', '', explode('@', Sanitize::text($c, 60))[0]) ?? '');
                $c = trim($c, '.-_');
                if ($c !== '' && !in_array($c, $list, true)) {
                    $list[] = substr($c, 0, 40);
                }
                if (count($list) >= 10) {
                    break;
                }
            }
            $b['correos'] = $list;
        }
        if (array_key_exists('correo_contacto', $in)) {
            $b['correo_contacto'] = Sanitize::email($in['correo_contacto']);
        }
        if (isset($in['contenido']) && is_array($in['contenido'])) {
            $c = $in['contenido'];
            if (array_key_exists('frase', $c)) { $b['contenido']['frase'] = Sanitize::text($c['frase'], 120); }
            if (array_key_exists('apoyo', $c)) { $b['contenido']['apoyo'] = Sanitize::text($c['apoyo'], 220); }
            if (array_key_exists('banner', $c)) { $b['contenido']['banner'] = self::ids($c['banner'], $valid, $photo, 3); }
            if (array_key_exists('quienes', $c)) { $b['contenido']['quienes'] = Sanitize::multiline($c['quienes'], 1500); }
            if (array_key_exists('galeria', $c)) { $b['contenido']['galeria'] = self::ids($c['galeria'], $valid, $photo, self::MAX_GALERIA); }
            if (array_key_exists('youtube', $c)) { $b['contenido']['youtube'] = self::youtube($c['youtube']); }
            if (array_key_exists('servicios', $c)) {
                $s = [];
                foreach (array_slice((array) $c['servicios'], 0, self::MAX_SERVICIOS) as $x) {
                    if (!is_array($x)) { continue; }
                    $nom = Sanitize::text($x['nombre'] ?? '', 80);
                    $desc = Sanitize::text($x['descripcion'] ?? '', 300);
                    $foto = self::fid($x['foto'] ?? null, $valid, $photo);
                    if ($nom === '' && $desc === '' && $foto === null) { continue; }
                    $s[] = ['nombre' => $nom, 'descripcion' => $desc, 'foto' => $foto, 'origen' => ($x['origen'] ?? '') === 'pres' ? 'pres' : 'form'];
                }
                $b['contenido']['servicios'] = $s;
            }
        }
        if (isset($in['tienda']) && is_array($in['tienda'])) {
            $t = $in['tienda'];
            if (array_key_exists('categorias', $t)) {
                $cats = [];
                foreach (array_slice((array) $t['categorias'], 0, self::MAX_CATEGORIAS) as $x) {
                    if (!is_array($x)) { continue; }
                    $nom = Sanitize::text($x['nombre'] ?? '', 60);
                    if ($nom === '') { continue; }
                    $cats[] = ['nombre' => $nom, 'padre' => Sanitize::text($x['padre'] ?? '', 60)];
                }
                $b['tienda']['categorias'] = $cats;
            }
            if (array_key_exists('productos', $t)) {
                $ps = [];
                foreach (array_slice((array) $t['productos'], 0, self::MAX_PRODUCTOS) as $x) {
                    if (!is_array($x)) { continue; }
                    $nom = Sanitize::text($x['nombre'] ?? '', 120);
                    if ($nom === '') { continue; }
                    $ps[] = [
                        'nombre' => $nom,
                        'categoria' => Sanitize::text($x['categoria'] ?? '', 60),
                        'precio' => Sanitize::price($x['precio'] ?? 0),
                        'descripcion' => Sanitize::text($x['descripcion'] ?? '', 600),
                        'foto' => self::fid($x['foto'] ?? null, $valid, $photo),
                        'stock' => Sanitize::intRange($x['stock'] ?? 0, 0, 1000000),
                        'origen' => ($x['origen'] ?? '') === 'pres' ? 'pres' : 'form',
                    ];
                }
                $b['tienda']['productos'] = $ps;
            }
            if (array_key_exists('umbral_stock', $t)) { $b['tienda']['umbral_stock'] = Sanitize::intRange($t['umbral_stock'], 0, 10000); }
            if (array_key_exists('correo_alertas', $t)) { $b['tienda']['correo_alertas'] = Sanitize::email($t['correo_alertas']); }
            if (array_key_exists('correo_pedidos', $t)) { $b['tienda']['correo_pedidos'] = Sanitize::email($t['correo_pedidos']); }
            if (isset($t['banco']) && is_array($t['banco'])) {
                foreach (['banco' => 60, 'numero' => 40, 'titular' => 80, 'tipo' => 30] as $k => $max) {
                    if (array_key_exists($k, $t['banco'])) { $b['tienda']['banco'][$k] = Sanitize::text($t['banco'][$k], $max); }
                }
            }
            if (array_key_exists('contra_entrega', $t)) { $b['tienda']['contra_entrega'] = (bool) $t['contra_entrega']; }
            if (array_key_exists('nota_entrega', $t)) { $b['tienda']['nota_entrega'] = Sanitize::multiline($t['nota_entrega'], 500); }
        }
        if (isset($in['contacto']) && is_array($in['contacto'])) {
            $c = $in['contacto'];
            if (array_key_exists('whatsapp', $c)) { $b['contacto']['whatsapp'] = Sanitize::phoneDigits($c['whatsapp'], 15); }
            if (array_key_exists('whatsapp_msg', $c)) { $b['contacto']['whatsapp_msg'] = Sanitize::text($c['whatsapp_msg'], 200); }
            if (array_key_exists('telefono', $c)) { $b['contacto']['telefono'] = Sanitize::phoneDisplay($c['telefono']); }
            if (array_key_exists('direccion', $c)) { $b['contacto']['direccion'] = Sanitize::text($c['direccion'], 200); }
            if (array_key_exists('mapa_url', $c)) { $b['contacto']['mapa_url'] = Sanitize::url($c['mapa_url']); }
            if (array_key_exists('horario', $c)) { $b['contacto']['horario'] = Sanitize::text($c['horario'], 160); }
            if (isset($c['redes']) && is_array($c['redes'])) {
                $hosts = ['facebook' => ['facebook.com', 'fb.com', 'fb.me'], 'instagram' => ['instagram.com'], 'tiktok' => ['tiktok.com'], 'youtube' => ['youtube.com', 'youtu.be'], 'x' => ['x.com', 'twitter.com'], 'linkedin' => ['linkedin.com']];
                foreach (self::REDES as $r) {
                    if (array_key_exists($r, $c['redes'])) {
                        $b['contacto']['redes'][$r] = Sanitize::url($c['redes'][$r], $hosts[$r]);
                    }
                }
            }
        }
        if (isset($in['pago']) && is_array($in['pago'])) {
            if (array_key_exists('nombre_nit', $in['pago'])) { $b['pago']['nombre_nit'] = Sanitize::text($in['pago']['nombre_nit'], 120); }
        }
        if (isset($in['presentacion']) && is_array($in['presentacion']) && array_key_exists('acepto', $in['presentacion'])) {
            $b['presentacion']['acepto'] = (bool) $in['presentacion']['acepto'];
        }
        return $b;
    }

    private static function domain($v): string
    {
        $s = strtolower(Sanitize::text($v, 120));
        $s = preg_replace('#^https?://#', '', $s) ?? '';
        $s = preg_replace('#^www\.#', '', $s) ?? '';
        $s = explode('/', $s)[0];
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $s) ? $s : '';
    }

    private static function youtube($v): string
    {
        $u = Sanitize::url($v, ['youtube.com', 'youtu.be']);
        return $u;
    }

    /** Errores de validación para poder crear la vista previa (mensajes en español). */
    public static function validateForBuild(array $b): array
    {
        $e = [];
        if (!in_array($b['plan'] ?? '', ['info', 'tienda'], true)) { $e['plan'] = 'Elija un plan.'; }
        if (trim((string) ($b['negocio']['nombre'] ?? '')) === '') { $e['negocio.nombre'] = 'Escriba el nombre de su negocio.'; }
        if (!in_array($b['negocio']['rubro'] ?? '', self::RUBROS, true)) { $e['negocio.rubro'] = 'Elija el rubro de su negocio.'; }
        $wa = (string) ($b['contacto']['whatsapp'] ?? '');
        if (strlen($wa) < 8 || strlen($wa) > 15) { $e['contacto.whatsapp'] = 'Escriba un número de WhatsApp válido, con código de país.'; }
        if (($b['plan'] ?? '') === 'tienda') {
            $ok = false;
            foreach ($b['tienda']['productos'] ?? [] as $p) {
                if (trim((string) $p['nombre']) !== '') { $ok = true; }
            }
            if (!$ok) { $e['tienda.productos'] = 'Agregue al menos 1 producto.'; }
        } else {
            $ok = false;
            foreach ($b['contenido']['servicios'] ?? [] as $s) {
                if (trim((string) $s['nombre']) !== '') { $ok = true; }
            }
            if (!$ok) { $e['contenido.servicios'] = 'Agregue al menos 1 servicio.'; }
        }
        if (($b['correo_contacto'] ?? '') === '' && ($b['plan'] ?? '') !== 'tienda') {
            // recomendado pero no bloqueante: si falta, los mensajes llegan a info@ del dominio (se anota)
        }
        return $e;
    }

    /**
     * Fusiona el análisis de la presentación sobre el borrador. REGLA: el formulario manda; la presentación solo completa vacíos.
     * $usar = flags confirmados por el cliente; $fotos = ids de archivos (kind presentacion_img) aprobados.
     * @return array{data:array,conflictos:array}
     */
    public static function mergePresentation(array $b, array $an, array $usar, array $fotos, int $orderId): array
    {
        $conf = [];
        $origen = $b['origen'] ?? [];
        $valid = self::fileIds($orderId);
        $get = fn($k) => is_array($an[$k] ?? null) ? ($an[$k]['v'] ?? '') : '';
        $mark = function (string $path) use (&$origen) { $origen[$path] = 'presentacion'; };

        if (!empty($usar['nombre']) && ($v = Sanitize::text($get('nombre'), 80)) !== '') {
            if ($b['negocio']['nombre'] === '') { $b['negocio']['nombre'] = $v; $mark('negocio.nombre'); }
            elseif (mb_strtolower($b['negocio']['nombre']) !== mb_strtolower($v)) { $conf[] = ['campo' => 'Nombre del negocio', 'form' => $b['negocio']['nombre'], 'pres' => $v]; }
        }
        if (!empty($usar['rubro'])) {
            $r = mb_strtolower(Sanitize::text($get('rubro_sugerido'), 40));
            $map = ['abogado' => 'abogado', 'abogados' => 'abogado', 'bufete' => 'abogado', 'clinica' => 'clinica', 'clínica' => 'clinica', 'medico' => 'clinica', 'médico' => 'clinica', 'taller' => 'taller', 'ropa' => 'ropa', 'restaurante' => 'restaurante', 'transporte' => 'transporte', 'contabilidad' => 'contabilidad', 'importaciones' => 'importaciones'];
            $rr = $map[$r] ?? ($r !== '' ? 'otro' : '');
            if ($rr !== '' && $b['negocio']['rubro'] === '') { $b['negocio']['rubro'] = $rr; $mark('negocio.rubro'); }
        }
        if (!empty($usar['frase']) && ($v = Sanitize::text($get('frase_principal'), 120)) !== '' && $b['contenido']['frase'] === '') {
            $b['contenido']['frase'] = $v; $mark('contenido.frase');
        }
        if (!empty($usar['quienes']) && ($v = Sanitize::multiline($get('quienes_somos'), 1500)) !== '' && $b['contenido']['quienes'] === '') {
            $b['contenido']['quienes'] = $v; $mark('contenido.quienes');
        }
        // Servicios confirmados (por índice)
        if (!empty($usar['servicios']) && is_array($usar['servicios'])) {
            $have = array_map(fn($s) => mb_strtolower($s['nombre']), $b['contenido']['servicios']);
            foreach ($usar['servicios'] as $idx) {
                $s = $an['servicios'][(int) $idx] ?? null;
                if (!$s) { continue; }
                $nom = Sanitize::text($s['nombre'] ?? '', 80);
                if ($nom === '' || in_array(mb_strtolower($nom), $have, true) || count($b['contenido']['servicios']) >= self::MAX_SERVICIOS) { continue; }
                $b['contenido']['servicios'][] = ['nombre' => $nom, 'descripcion' => Sanitize::text($s['descripcion'] ?? '', 300), 'foto' => null, 'origen' => 'pres'];
                $have[] = mb_strtolower($nom);
                $mark('contenido.servicios.' . (count($b['contenido']['servicios']) - 1));
            }
        }
        if (!empty($usar['productos']) && is_array($usar['productos']) && $b['plan'] === 'tienda') {
            $have = array_map(fn($s) => mb_strtolower($s['nombre']), $b['tienda']['productos']);
            $cats = array_map(fn($c) => mb_strtolower($c['nombre']), $b['tienda']['categorias']);
            foreach ($usar['productos'] as $idx) {
                $p = $an['productos'][(int) $idx] ?? null;
                if (!$p) { continue; }
                $nom = Sanitize::text($p['nombre'] ?? '', 120);
                if ($nom === '' || in_array(mb_strtolower($nom), $have, true) || count($b['tienda']['productos']) >= self::MAX_PRODUCTOS) { continue; }
                $cat = Sanitize::text($p['categoria'] ?? '', 60);
                $b['tienda']['productos'][] = ['nombre' => $nom, 'categoria' => $cat, 'precio' => Sanitize::price($p['precio'] ?? 0), 'descripcion' => Sanitize::text($p['descripcion'] ?? '', 600), 'foto' => null, 'stock' => 0, 'origen' => 'pres'];
                $have[] = mb_strtolower($nom);
                if ($cat !== '' && !in_array(mb_strtolower($cat), $cats, true)) {
                    $b['tienda']['categorias'][] = ['nombre' => $cat, 'padre' => ''];
                    $cats[] = mb_strtolower($cat);
                }
            }
        }
        $c = is_array($an['contacto'] ?? null) ? $an['contacto'] : [];
        if (!empty($usar['contacto'])) {
            $pairs = [
                ['whatsapp', 'whatsapp', fn($v) => Sanitize::phoneDigits($v, 15), 'WhatsApp'],
                ['telefono', 'telefono', fn($v) => Sanitize::phoneDisplay($v), 'Teléfono'],
                ['direccion', 'direccion', fn($v) => Sanitize::text($v, 200), 'Dirección'],
            ];
            foreach ($pairs as [$ak, $bk, $fn, $label]) {
                $v = $fn(is_array($c[$ak] ?? null) ? ($c[$ak]['v'] ?? '') : '');
                if ($v === '') { continue; }
                $cur = (string) $b['contacto'][$bk];
                if ($cur === '') { $b['contacto'][$bk] = $v; $mark('contacto.' . $bk); }
                elseif (Sanitize::phoneDigits($cur) !== Sanitize::phoneDigits($v) && in_array($bk, ['whatsapp', 'telefono'], true)) { $conf[] = ['campo' => $label, 'form' => $cur, 'pres' => $v]; }
                elseif ($bk === 'direccion' && mb_strtolower($cur) !== mb_strtolower($v)) { $conf[] = ['campo' => $label, 'form' => $cur, 'pres' => $v]; }
            }
            $correo = Sanitize::email(is_array($c['correo'] ?? null) ? ($c['correo']['v'] ?? '') : '');
            if ($correo !== '') {
                if ($b['correo_contacto'] === '') { $b['correo_contacto'] = $correo; $mark('correo_contacto'); }
                elseif ($b['correo_contacto'] !== $correo) { $conf[] = ['campo' => 'Correo', 'form' => $b['correo_contacto'], 'pres' => $correo]; }
            }
            if (!empty($usar['horario'])) {
                $h = Sanitize::text(is_array($c['horario'] ?? null) ? ($c['horario']['v'] ?? '') : '', 160);
                if ($h !== '' && $b['contacto']['horario'] === '') { $b['contacto']['horario'] = $h; $mark('contacto.horario'); }
            }
        }
        if (!empty($usar['horario']) && empty($usar['contacto'])) {
            $h = Sanitize::text(is_array($c['horario'] ?? null) ? ($c['horario']['v'] ?? '') : '', 160);
            if ($h !== '' && $b['contacto']['horario'] === '') { $b['contacto']['horario'] = $h; $mark('contacto.horario'); }
        }
        if (!empty($usar['redes']) && is_array($c['redes'] ?? null)) {
            $hosts = ['facebook' => ['facebook.com', 'fb.com'], 'instagram' => ['instagram.com'], 'tiktok' => ['tiktok.com'], 'youtube' => ['youtube.com', 'youtu.be'], 'x' => ['x.com', 'twitter.com'], 'linkedin' => ['linkedin.com']];
            foreach (self::REDES as $r) {
                $v = Sanitize::url($c['redes'][$r] ?? '', $hosts[$r]);
                if ($v !== '' && $b['contacto']['redes'][$r] === '') { $b['contacto']['redes'][$r] = $v; $mark('contacto.redes.' . $r); }
            }
        }
        // fotos aprobadas de la presentación
        $okFotos = [];
        foreach ((array) $fotos as $fid) {
            if (isset($valid[(int) $fid]) && $valid[(int) $fid] === 'presentacion_img') {
                $okFotos[] = (int) $fid;
            }
        }
        $b['presentacion']['fotos_usar'] = $okFotos;
        $b['presentacion']['usar'] = array_map(fn($x) => is_array($x) ? array_values(array_map('intval', $x)) : (bool) $x, $usar);
        $b['presentacion']['confirmada'] = true;
        $b['presentacion']['estado'] = 'confirmada';
        $b['origen'] = $origen;
        return ['data' => $b, 'conflictos' => $conf];
    }
}
