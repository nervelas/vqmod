<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Fs;

/**
 * Construye el paquete base (webs-clientes/_base): WordPress + plugins oficiales de wordpress.org
 * + tema y mu-plugin de Servicom. Se ejecuta UNA vez (y al actualizar), nunca en cada creación.
 * Solo se descarga de wordpress.org (versiones oficiales y gratuitas).
 */
final class BaseBuilder
{
    public const MAX_PHP = '8.0';
    /** Plugins permitidos (slug de wordpress.org). Contact Form 7 solo si Fluent Forms no se puede usar. */
    public const PLUGINS = ['elementor', 'woocommerce', 'fluentform'];

    /** @var callable fn(string $url): string */
    private $fetch;
    private $log;
    private string $tmp;

    public function __construct(?callable $fetch = null, ?callable $log = null, ?string $tmp = null)
    {
        $this->fetch = $fetch ?? [$this, 'httpGet'];
        $this->log = $log ?? static function (string $m): void { echo $m . "\n"; };
        $this->tmp = $tmp ?? (sys_get_temp_dir() . '/s5base-' . bin2hex(random_bytes(4)));
    }

    private function say(string $m): void
    {
        ($this->log)($m);
    }

    private function httpGet(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'ServicomBaseBuilder/1.0', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP]);
        $b = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if (!is_string($b) || $code !== 200) {
            throw new \RuntimeException("No se pudo descargar $url (HTTP $code $err)");
        }
        return $b;
    }

    private function get(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (!preg_match('/(^|\.)wordpress\.org$/', $host)) {
            throw new \RuntimeException('Solo se permite descargar de wordpress.org: ' . $host);
        }
        return ($this->fetch)($url);
    }

    /** @return array{version:string,download:string} última versión de WordPress compatible con PHP 8.0 */
    public function coreOffer(string $locale = 'en_US'): array
    {
        $j = json_decode($this->get('https://api.wordpress.org/core/version-check/1.7/?locale=' . rawurlencode($locale)), true);
        foreach (($j['offers'] ?? []) as $o) {
            if (($o['response'] ?? '') !== 'upgrade' && ($o['response'] ?? '') !== 'latest') { continue; }
            if (version_compare((string) ($o['php_version'] ?? '5.0'), self::MAX_PHP, '<=')) {
                return ['version' => (string) $o['version'], 'download' => (string) ($o['packages']['full'] ?? $o['download'] ?? '')];
            }
        }
        throw new \RuntimeException('Ninguna versión de WordPress es compatible con PHP ' . self::MAX_PHP);
    }

    /** Lee "Requires PHP:" del readme principal dentro de un zip de plugin. */
    public function zipRequiresPhp(string $zipPath, string $slug): string
    {
        $z = new \ZipArchive();
        if ($z->open($zipPath) !== true) { return '99'; }
        $txt = (string) $z->getFromName($slug . '/readme.txt');
        $z->close();
        if (preg_match('/^\s*Requires PHP:\s*([0-9.]+)/mi', $txt, $m)) { return $m[1]; }
        return '5.6';
    }

    /**
     * Elige la última versión del plugin cuyo "Requires PHP" sea ≤ 8.0 y la descarga.
     * @return array{version:string,zip:string,note:string}
     */
    public function fetchPlugin(string $slug): array
    {
        $info = json_decode($this->get('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . rawurlencode($slug) . '&request[fields][versions]=1'), true);
        if (!is_array($info) || empty($info['version'])) {
            throw new \RuntimeException("No se encontró el plugin $slug en wordpress.org");
        }
        $cands = [['v' => (string) $info['version'], 'zip' => (string) ($info['download_link'] ?? '')]];
        $vers = is_array($info['versions'] ?? null) ? $info['versions'] : [];
        uksort($vers, fn($a, $b) => version_compare((string) $b, (string) $a));
        foreach ($vers as $v => $zip) {
            if ($v === 'trunk' || preg_match('/[a-z]/i', (string) $v)) { continue; }
            if ($v !== $info['version']) { $cands[] = ['v' => (string) $v, 'zip' => (string) $zip]; }
        }
        $n = 0;
        foreach ($cands as $c) {
            if (++$n > 25) { break; }
            $req = (string) ($info['requires_php'] ?? '');
            if ($n === 1 && $req !== '' && version_compare($req, self::MAX_PHP, '>')) { continue; }
            $path = $this->tmp . '/' . $slug . '-' . $c['v'] . '.zip';
            @mkdir($this->tmp, 0750, true);
            file_put_contents($path, $this->get($c['zip']));
            $r = $this->zipRequiresPhp($path, $slug);
            if (version_compare($r, self::MAX_PHP, '<=')) {
                $note = $c['v'] === $info['version'] ? '' : "AVISO: la última versión de $slug ({$info['version']}) exige PHP superior a " . self::MAX_PHP . "; se usa {$c['v']}.";
                return ['version' => $c['v'], 'zip' => $path, 'note' => $note];
            }
            @unlink($path);
        }
        throw new \RuntimeException("No hay una versión de $slug compatible con PHP " . self::MAX_PHP);
    }

    /** Extrae un zip evitando rutas fuera del destino (zip-slip). */
    public function extract(string $zipPath, string $dest, ?string $stripFirst = null): void
    {
        $z = new \ZipArchive();
        if ($z->open($zipPath) !== true) { throw new \RuntimeException('Zip dañado: ' . basename($zipPath)); }
        @mkdir($dest, 0755, true);
        $total = 0;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $st = $z->statIndex($i);
            $name = (string) $st['name'];
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, "\0") || str_contains($name, '\\')) { throw new \RuntimeException('Entrada de zip no permitida: ' . $name); }
            $total += (int) $st['size'];
            if ($total > 1500 * 1048576) { throw new \RuntimeException('Zip demasiado grande'); }
            $rel = $name;
            if ($stripFirst !== null && str_starts_with($rel, $stripFirst . '/')) { $rel = substr($rel, strlen($stripFirst) + 1); }
            if ($rel === '') { continue; }
            $target = $dest . '/' . $rel;
            if (str_ends_with($name, '/')) { @mkdir($target, 0755, true); continue; }
            @mkdir(dirname($target), 0755, true);
            $in = $z->getStream($name);
            if (!$in) { continue; }
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($out); fclose($in);
            @chmod($target, 0644);
        }
        $z->close();
    }

    private function copyTree(string $src, string $dst): void
    {
        if (!is_dir($src)) { throw new \RuntimeException('Falta la carpeta ' . $src); }
        @mkdir($dst, 0755, true);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        $len = strlen(rtrim($src, '/')) + 1;
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), $len);
            if (str_contains($rel, '/tests/') || str_starts_with($rel, 'tests/') || str_contains($rel, '/tests-only') || str_ends_with($rel, '.md') && str_contains($rel, 'tests')) { continue; }
            if ($f->isDir()) { @mkdir($dst . '/' . $rel, 0755, true); } else { copy($f->getPathname(), $dst . '/' . $rel); @chmod($dst . '/' . $rel, 0644); }
        }
    }

    /**
     * @param array $o base (ruta _base), wp_dir (usar un WordPress local en vez de descargar), plugins_dir (carpeta con plugins ya listos),
     *                 locale, with_store, wp_pack (ruta de wp-pack)
     * @return array informe
     */
    public function build(array $o): array
    {
        $base = rtrim((string) $o['base'], '/');
        $pack = rtrim((string) ($o['wp_pack'] ?? (S5_ROOT . '/wp-pack')), '/');
        $locale = (string) ($o['locale'] ?? 'es_ES');
        $report = ['notes' => [], 'versions' => []];
        $stage = $this->tmp . '/stage';
        @mkdir($stage, 0750, true);
        // 1) WordPress
        if (!empty($o['wp_dir'])) {
            $this->say('Usando WordPress local de ' . $o['wp_dir']);
            $this->copyTree((string) $o['wp_dir'], $stage);
            $report['versions']['wordpress'] = 'local';
        } else {
            $offer = $this->coreOffer($locale);
            $this->say('Descargando WordPress ' . $offer['version'] . ' …');
            $zip = $this->tmp . '/wp.zip';
            file_put_contents($zip, $this->get($offer['download']));
            $this->extract($zip, $stage, 'wordpress');
            $report['versions']['wordpress'] = $offer['version'];
            if ($locale !== 'en_US') {
                try {
                    $lz = $this->tmp . '/lang.zip';
                    file_put_contents($lz, $this->get('https://downloads.wordpress.org/translation/core/' . $offer['version'] . '/' . $locale . '.zip'));
                    $this->extract($lz, $stage . '/wp-content/languages');
                } catch (\Throwable $e) {
                    $report['notes'][] = 'Idioma ' . $locale . ' no descargado: ' . $e->getMessage();
                }
            }
        }
        // 2) Plugins
        $plugs = $o['plugins'] ?? self::PLUGINS;
        foreach ($plugs as $slug) {
            if (!empty($o['plugins_dir']) && is_dir($o['plugins_dir'] . '/' . $slug)) {
                $this->copyTree($o['plugins_dir'] . '/' . $slug, $stage . '/wp-content/plugins/' . $slug);
                $report['versions'][$slug] = 'local';
                continue;
            }
            if (!empty($o['skip_download'])) { $report['notes'][] = "Plugin $slug omitido (skip_download)."; continue; }
            try {
                $this->say("Descargando $slug …");
                $r = $this->fetchPlugin($slug);
                $this->extract($r['zip'], $stage . '/wp-content/plugins');
                $report['versions'][$slug] = $r['version'];
                if ($r['note'] !== '') { $report['notes'][] = $r['note']; }
            } catch (\Throwable $e) {
                $report['notes'][] = "ERROR con $slug: " . $e->getMessage();
            }
        }
        // 3) Limpieza de WordPress
        foreach (['wp-content/plugins/akismet', 'wp-content/plugins/hello.php', 'readme.html', 'license.txt', 'wp-config-sample.php', 'wp-content/themes/twentytwentythree', 'wp-content/themes/twentytwentytwo', 'wp-content/themes/twentytwentyfive', 'wp-content/themes/twentytwentyfour'] as $rm) {
            $p = $stage . '/' . $rm;
            if (is_dir($p)) { Fs::rmTreeSafe($p, [$stage]); } elseif (is_file($p)) { @unlink($p); }
        }
        // se conserva un tema por defecto como respaldo de WordPress
        // 4) Servicom: tema, mu-plugin, script de aprovisionamiento
        $this->copyTree($pack . '/theme/servicom', $stage . '/wp-content/themes/servicom');
        @mkdir($stage . '/wp-content/mu-plugins', 0755, true);
        copy($pack . '/mu-plugins/servicom-core.php', $stage . '/wp-content/mu-plugins/servicom-core.php');
        $this->copyTree($pack . '/mu-plugins/servicom-core', $stage . '/wp-content/mu-plugins/servicom-core');
        @mkdir($stage . '/.provision', 0755, true);
        copy($pack . '/provision/sc-provision.php', $stage . '/.provision/sc-provision.php');
        @unlink($stage . '/wp-config.php');
        foreach (['/wp-content/index.php'] as $f) { if (!is_file($stage . $f)) { file_put_contents($stage . $f, "<?php\n// Silence is golden.\n"); } }
        // 5) Verificación mínima
        foreach (['wp-load.php', 'wp-settings.php', 'wp-includes/version.php', 'wp-content/themes/servicom/style.css', 'wp-content/mu-plugins/servicom-core.php'] as $need) {
            if (!is_file($stage . '/' . $need)) { throw new \RuntimeException('El paquete base quedó incompleto: falta ' . $need); }
        }
        // 6) Publicación atómica
        $old = $base . '.old-' . date('YmdHis');
        if (is_dir($base)) {
            if (!@rename($base, $old)) { throw new \RuntimeException('No se pudo reemplazar el paquete base existente.'); }
        }
        @mkdir(dirname($base), 0755, true);
        if (!@rename($stage, $base)) {
            $this->copyTree($stage, $base);
        }
        file_put_contents($base . '/VERSION.json', json_encode(['built_at' => gmdate('c'), 'versions' => $report['versions'], 'notes' => $report['notes']], JSON_PRETTY_PRINT));
        @unlink($base . '/.filelist.json');
        if (isset($old) && is_dir($old)) { Fs::rmTreeSafe($old, [dirname($base)]); }
        if (is_dir($this->tmp)) { Fs::rmTreeSafe($this->tmp, [dirname($this->tmp)]); }
        return $report;
    }
}
