<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Crypto;
use S5\Core\Fs;
use S5\Core\Settings;

/** Arma el manifiesto de construcción (contrato §7) a partir del pedido confirmado. */
final class Manifest
{
    public static function jobDir(int $orderId): string
    {
        return Files::root() . '/jobs/' . $orderId;
    }

    /**
     * Garantiza que los textos del manifiesto traigan el esquema ampliado
     * (CONTRATO-LUXE §2) y un texto por cada servicio (incluidos los sugeridos),
     * aunque se hayan guardado antes de LUXE o vengan solo de BaseTexts::textos().
     */
    private static function completarTextos(array $texts, array $b): array
    {
        $texts = TextSchema::completarLuxe($texts, $b);
        $base = null;
        $ts = (isset($texts['servicios']) && is_array($texts['servicios'])) ? array_values($texts['servicios']) : [];
        $rubro = (string) ($b['negocio']['rubro'] ?: 'otro');
        foreach ($b['contenido']['servicios'] as $i => $s) {
            $it = (isset($ts[$i]) && is_array($ts[$i])) ? $ts[$i] : [];
            $ok = !empty($it['resumen']) && !empty($it['descripcion']);
            if (!$ok) {
                if ($base === null) {
                    $base = BaseTexts::textosCompletos($b)['servicios'];
                }
                $bs = $base[$i] ?? ['resumen' => '', 'descripcion' => ''];
                $it['resumen'] = $it['resumen'] ?? ($s['resumen'] ?? $bs['resumen']);
                $it['descripcion'] = !empty($it['descripcion']) ? $it['descripcion'] : (!empty($s['descripcion']) ? $s['descripcion'] : $bs['descripcion']);
                if (empty($it['resumen'])) {
                    $it['resumen'] = $bs['resumen'];
                }
            }
            $ic = TextSchema::icono($it['icono'] ?? '');
            if ($ic === '') {
                $ic = TextSchema::icono($s['icono'] ?? '');
            }
            $it['icono'] = $ic !== '' ? $ic : BaseTexts::iconoServicio((string) $s['nombre'], $rubro, $i);
            $ts[$i] = ['resumen' => (string) $it['resumen'], 'descripcion' => (string) $it['descripcion'], 'icono' => $it['icono']];
        }
        $texts['servicios'] = array_slice($ts, 0, count($b['contenido']['servicios']));
        return $texts;
    }

    /**
     * @return array{manifest:array,assets:array} assets = archivos para stageJob (con 'local', 'sha256', 'file_key', 'order_token')
     */
    public static function build(array $o, array $texts, string $jobId, array $opts = []): array
    {
        $b = Brief::completarServicios(Orders::data($o));   // LUXE: servicios típicos del rubro si el cliente dio menos de 4 (no aplica a tienda)
        $rubro = $b['negocio']['rubro'] ?: 'otro';
        $stockIn = (isset($b['_stock']) && is_array($b['_stock'])) ? $b['_stock'] : [];   // lo escribe StockImages (CONTRATO-LUXE §7)
        $stockSlots = (isset($stockIn['slots']) && is_array($stockIn['slots'])) ? $stockIn['slots'] : [];
        $hasStock = false;
        foreach (['hero', 'about', 'servicios', 'galeria'] as $sl) {
            if (!empty($stockSlots[$sl]) && is_array($stockSlots[$sl])) {
                $hasStock = true;
            }
        }
        $stockCredit = [];
        foreach ((isset($stockIn['assets']) && is_array($stockIn['assets'])) ? $stockIn['assets'] : [] as $sa) {
            if (is_array($sa) && isset($sa['file'])) {
                $stockCredit[(int) $sa['file']] = mb_substr(trim(strip_tags((string) ($sa['credit'] ?? ''))), 0, 200, 'UTF-8');
            }
        }
        $texts = self::completarTextos($texts, $b);
        $en = ($b['negocio']['idioma'] ?? 'es') === 'en';
        $dir = self::jobDir((int) $o['id']) . '/assets';
        Fs::mkdir($dir, 0750);
        $manifestAssets = [];
        $stage = [];
        $n = 0;
        $byFile = [];

        $addFile = function (?int $fileId, string $alt, string $role = '', string $credit = '') use (&$manifestAssets, &$stage, &$n, &$byFile, $o, $dir): ?string {
            if (!$fileId) {
                return null;
            }
            if (isset($byFile[$fileId])) {
                return $byFile[$fileId];
            }
            $f = Files::get($fileId, (int) $o['id']);
            if (!$f || !str_starts_with((string) $f['mime'], 'image/')) {
                return null;
            }
            $src = Files::path($f);
            if (!is_file($src)) {
                return null;
            }
            $n++;
            $ext = pathinfo((string) $f['stored'], PATHINFO_EXTENSION) ?: 'webp';
            $key = 'a' . $n . '.' . $ext;
            if (!@copy($src, $dir . '/' . $key)) {
                return null;
            }
            $id = 'a' . $n;
            $as = ['id' => $id, 'path' => 'assets/' . $key, 'mime' => $f['mime'], 'w' => (int) $f['w'], 'h' => (int) $f['h'], 'alt' => $alt, 'role' => $role !== '' ? $role : 'galeria'];
            if ($credit !== '') {
                $as['credit'] = $credit;
            }
            $manifestAssets[] = $as;
            $stage[] = ['path' => 'assets/' . $key, 'local' => $dir . '/' . $key, 'sha256' => hash_file('sha256', $dir . '/' . $key), 'size' => (int) filesize($dir . '/' . $key), 'file_key' => $key, 'order_token' => $o['token']];
            return $byFile[$fileId] = $id;
        };
        $addStock = function (string $slot, int $seed, string $alt) use (&$manifestAssets, &$stage, &$n, $o, $dir, $rubro): ?string {
            $s = Stock::pick($rubro, $slot, $seed);
            if (!$s) {
                return null;
            }
            $n++;
            $ext = pathinfo($s['path'], PATHINFO_EXTENSION) ?: 'jpg';
            $key = 'a' . $n . '.' . $ext;
            if (!@copy($s['path'], $dir . '/' . $key)) {
                return null;
            }
            $sz = @getimagesize($dir . '/' . $key) ?: [0, 0];
            $id = 'a' . $n;
            $manifestAssets[] = ['id' => $id, 'path' => 'assets/' . $key, 'mime' => mime_content_type($dir . '/' . $key) ?: 'image/jpeg', 'w' => (int) $sz[0], 'h' => (int) $sz[1], 'alt' => $s['alt'] !== '' ? $s['alt'] : $alt, 'role' => 'stock'];
            $stage[] = ['path' => 'assets/' . $key, 'local' => $dir . '/' . $key, 'sha256' => hash_file('sha256', $dir . '/' . $key), 'size' => (int) filesize($dir . '/' . $key), 'file_key' => $key, 'order_token' => $o['token']];
            return $id;
        };

        $name = $b['negocio']['nombre'];
        $logo = $addFile($b['negocio']['logo'] ?? null, $name, 'logo');

        // Fotos aprobadas de la presentación: banner (si el cliente no puso) y galería
        $presFotos = array_map('intval', $b['presentacion']['fotos_usar'] ?? []);
        $bannerIds = array_map('intval', $b['contenido']['banner'] ?? []);
        $galIds = array_map('intval', $b['contenido']['galeria'] ?? []);
        $usedExplicit = $bannerIds;
        foreach ($b['contenido']['servicios'] as $s) {
            if (!empty($s['foto'])) { $usedExplicit[] = (int) $s['foto']; }
        }
        foreach ($b['tienda']['productos'] ?? [] as $p) {
            if (!empty($p['foto'])) { $usedExplicit[] = (int) $p['foto']; }
        }
        $usedExplicit = array_merge($usedExplicit, $galIds, [(int) ($b['negocio']['logo'] ?? 0)]);
        $freePres = array_values(array_filter($presFotos, fn($x) => !in_array($x, $usedExplicit, true)));
        if (!$bannerIds && $freePres) {
            $bannerIds = array_splice($freePres, 0, min(3, count($freePres)));
        }
        $galIds = array_merge($galIds, $freePres);

        $banner = [];
        foreach ($bannerIds as $i => $fid) {
            if ($a = $addFile($fid, $name . ' - banner ' . ($i + 1), 'banner')) { $banner[] = $a; }
        }
        if (!$banner && !$hasStock && ($st = $addStock('hero', 0, $name))) {
            $banner[] = $st;
        }
        $gal = [];
        foreach ($galIds as $i => $fid) {
            if ($a = $addFile($fid, $name . ' - ' . ($en ? 'photo' : 'foto') . ' ' . ($i + 1), 'galeria')) { $gal[] = $a; }
        }

        $icons = TextSchema::ICONOS_RUBRO[$rubro] ?? TextSchema::ICONOS_RUBRO['otro'];
        $servicios = [];
        foreach ($b['contenido']['servicios'] as $i => $s) {
            $foto = $addFile($s['foto'] ?? null, $s['nombre'], 'servicio');
            if (!$foto && !$hasStock) {
                $foto = $addStock('servicio', $i, $s['nombre']);
            }
            $ts = (isset($texts['servicios'][$i]) && is_array($texts['servicios'][$i])) ? $texts['servicios'][$i] : [];
            $icono = TextSchema::icono($s['icono'] ?? '');
            if ($icono === '') {
                $icono = TextSchema::icono($ts['icono'] ?? '');
            }
            if ($icono === '') {
                $icono = BaseTexts::iconoServicio((string) $s['nombre'], $rubro, $i);
            }
            $desc = trim((string) ($s['descripcion'] ?? ''));
            if ($desc === '') {
                $desc = (string) ($ts['descripcion'] ?? '');
            }
            $servicios[] = ['nombre' => $s['nombre'], 'descripcion' => $desc, 'foto' => $foto, 'icono' => $icono, 'origen' => (string) ($s['origen'] ?? 'form')];
        }

        // Fotos de stock descargadas por el portal (orders.data._stock): fileId -> id de asset
        $stock = [];
        foreach (['hero', 'about', 'servicios', 'galeria'] as $sl) {
            $ids = [];
            foreach (array_slice(array_values((array) ($stockSlots[$sl] ?? [])), 0, 12) as $k => $fid) {
                $fid = (int) $fid;
                if ($fid > 0 && ($a = $addFile($fid, $name . ' - ' . $sl . ' ' . ($k + 1), 'stock', $stockCredit[$fid] ?? ''))) {
                    $ids[] = $a;
                }
            }
            if ($ids) {
                $stock[$sl] = $ids;
            }
        }

        $store = null;
        if ($b['plan'] === 'tienda') {
            $prods = [];
            foreach ($b['tienda']['productos'] as $p) {
                $prods[] = ['nombre' => $p['nombre'], 'categoria' => $p['categoria'], 'precio' => (float) $p['precio'], 'descripcion' => $p['descripcion'], 'foto' => $addFile($p['foto'] ?? null, $p['nombre']), 'stock' => (int) $p['stock']];
            }
            $store = [
                'categorias' => $b['tienda']['categorias'], 'productos' => $prods, 'umbral_stock' => (int) $b['tienda']['umbral_stock'],
                'correo_alertas' => $b['tienda']['correo_alertas'] ?: $b['correo_contacto'],
                'correo_pedidos' => $b['tienda']['correo_pedidos'] ?: $b['correo_contacto'],
                'banco' => $b['tienda']['banco'], 'contra_entrega' => (bool) $b['tienda']['contra_entrega'], 'nota_entrega' => $b['tienda']['nota_entrega'],
            ];
        }

        $wa = (string) $b['contacto']['whatsapp'];
        $waMsg = $b['contacto']['whatsapp_msg'] ?: ($en ? 'Hello, I saw your website and would like more information.' : 'Hola, vi su sitio web y quisiera más información.');
        $portal = Settings::portalUrl();
        $key = (string) $o['preview_key'];
        $scheme = Settings::scheme();
        $siteUrl = $scheme . '://' . $o['fqdn'] . Orders::portSuffix();
        $mode = !empty($opts['mode']) ? $opts['mode'] : (!empty($o['is_demo']) ? 'demo' : 'preview');

        $manifest = [
            'version' => 1,
            'job' => $jobId,
            'site' => [
                'url' => $siteUrl, 'slug' => $o['slug'], 'title' => $name, 'locale' => $en ? 'en_US' : 'es_ES', 'lang' => $en ? 'en' : 'es',
                'timezone' => 'America/Guatemala', 'admin_user' => $o['wp_user'], 'admin_pass' => Crypto::decrypt((string) $o['wp_pass']),
                'admin_email' => (string) (Settings::get('owner_email', '') ?: 'admin@' . Settings::baseDomain()), 'table_prefix' => $o['wp_prefix'],
            ],
            'plan' => $b['plan'], 'style' => (int) $b['negocio']['estilo'], 'rubro' => $rubro,
            'business' => [
                'nombre' => $name, 'whatsapp' => $wa, 'whatsapp_msg' => $waMsg, 'telefono' => $b['contacto']['telefono'],
                'correo_contacto' => $b['correo_contacto'], 'direccion' => $b['contacto']['direccion'], 'mapa_url' => $b['contacto']['mapa_url'],
                'horario' => $b['contacto']['horario'], 'redes' => $b['contacto']['redes'], 'logo' => $logo, 'youtube' => $b['contenido']['youtube'],
                'footer_credit' => (string) Settings::get('footer_credit', 'Sitio creado por Servicom'),
            ],
            'texts' => $texts,
            'design' => ['hint' => ['rubro' => $rubro, 'estilo' => (int) $b['negocio']['estilo']], 'colores' => array_values(array_filter(array_map('strval', (array) ($b['presentacion']['colores'] ?? [])), fn($c) => (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $c)))],
            'content' => ['banner' => $banner, 'quienes' => $texts['nosotros_texto'] ?? $b['contenido']['quienes'], 'servicios' => $servicios, 'galeria' => $gal] + ($stock ? ['stock' => $stock] : []),
            'store' => $store,
            'assets' => $manifestAssets,
            'preview' => [
                'mode' => $mode, 'key' => $key, 'portal_url' => $portal,
                'pay_url' => $portal . '/vp/' . $key . '/pagar', 'edit_url' => $portal . '/vp/' . $key . '/editar',
                'wa_servicom' => Sanitize_wa((string) Settings::get('wa_servicom', '')),
            ],
            'support' => ['wa_servicom' => Sanitize_wa((string) Settings::get('wa_servicom', '')), 'email_owner' => (string) Settings::get('owner_email', '')],
        ];
        return ['manifest' => $manifest, 'assets' => $stage];
    }
}

function Sanitize_wa(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}
