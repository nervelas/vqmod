<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

/**
 * Escáner de patrones típicos de malware. Los patrones se arman por partes para que este
 * archivo no se detecte a sí mismo ni dispare falsos positivos en auditorías de texto.
 * @return array<int,array{0:string,1:string,2:string}> [severidad, descripción, expresión]
 */
function gx_scan_rules(string $kind): array
{
    $eva = 'ev' . 'al';
    $b64 = 'base' . '64_' . 'decode';
    $php = [
        ['crítico', 'Ejecución dinámica de código', '/\b' . $eva . '\s*\(/i'],
        ['crítico', 'Decodificación base64 (típica de ofuscación)', '/\b' . $b64 . '\s*\(/i'],
        ['crítico', 'Descompresión de código ofuscado', '/\b(gz' . 'inflate|gz' . 'uncompress|gz' . 'decode|str_' . 'rot13)\s*\(/i'],
        ['crítico', 'Ejecución de comandos del sistema', '/(?<![\w>:$])(shell_' . 'exec|pass' . 'thru|sys' . 'tem|proc_' . 'open|po' . 'pen|pcntl_' . 'exec|ex' . 'ec|show_' . 'source)\s*\(/i'],
        ['crítico', 'Función dinámica o aserción con cadena', '/\b(create_' . 'function|ass' . 'ert)\s*\(\s*[\'"$]/i'],
        ['crítico', 'preg_replace con modificador de ejecución', '/preg_' . 'replace\s*\(\s*([\'"])([^\w\s\\\\])[^\n]{0,160}?\2[imsuxADSUXJ]*e[imsuxADSUXJe]*\1\s*,/i'],
        ['alto', 'include/require con datos de la petición', '/\b(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)/i'],
        ['alto', 'Variables variables sobre la petición', '/\$\$[a-z_]+\s*=\s*\$_(GET|POST|REQUEST)|\b(ex' . 'tract)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i'],
        ['alto', 'Deserialización de datos externos', '/\bun' . 'serialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i'],
        ['alto', 'Firmas de webshells conocidas', '/\b(c99shell|r57shell|wso\s*shell|FilesMan|b374k|WebShell|China\s*Chopper|0byte)\b/i'],
        ['medio', 'Cadena hexadecimal/octal larga (ofuscación)', '/(\\\\x[0-9a-f]{2}){14,}|(\\\\[0-7]{3}){14,}/i'],
        ['medio', 'Llamada a función desde la petición', '/\$_(GET|POST|REQUEST|COOKIE)\s*\[[^\]]+\]\s*\(/'],
    ];
    $web = [
        ['alto', 'iframe oculto', '/<iframe[^>]*(display\s*:\s*none|visibility\s*:\s*hidden|width\s*=\s*["\']?0|height\s*=\s*["\']?0)/i'],
        ['alto', 'Script externo inyectado', '/<script[^>]+src\s*=\s*["\']?\s*(https?:)?\/\//i'],
        ['alto', 'Ofuscación en JavaScript', '/document\.write\s*\(\s*(un' . 'escape|atob)|String\.fromCharCode\s*\((\s*\d+\s*,){12,}/i'],
        ['alto', 'Uso de ' . $eva . ' en JavaScript', '/\b' . $eva . '\s*\(\s*(atob|un' . 'escape|String)/i'],
        ['medio', 'Redirección sospechosa por meta/JS', '/<meta[^>]+http-equiv\s*=\s*["\']?refresh[^>]+url\s*=\s*https?:\/\//i'],
        ['medio', 'Enlaces ocultos (SEO spam)', '/<a[^>]+style\s*=\s*["\'][^"\']*(display\s*:\s*none|left\s*:\s*-\d{4})/i'],
    ];
    $htaccess = [
        ['alto', 'Manejador/handler agregado', '/^\s*(AddHandler|AddType\s+application\/x-httpd|SetHandler)\b/im'],
        ['crítico', 'Código PHP precargado', '/^\s*(php_(admin_)?value\s+)?auto_(prepend|append)_file\s*[= ]/im'],
        ['alto', 'Redirección a dominio externo', '/RewriteRule\s+.*\s+https?:\/\/(?!%\{HTTP_HOST\}|%1)/i'],
        ['medio', 'Reescritura condicionada por buscador/referer', '/RewriteCond\s+%\{HTTP_(REFERER|USER_AGENT)\}.*(google|bing|yahoo|baidu)/i'],
    ];
    return $kind === 'php' ? $php : ($kind === 'htaccess' ? $htaccess : $web);
}

/**
 * Escanea los archivos del sitio. @return array{findings:array<int,array<string,string>>,scanned:int}
 */
function gx_malware_scan(): array
{
    $findings = [];
    $scanned = 0;
    $self = '_core/lib/scanner.php';
    $files = gx_list_files();
    foreach (['uploads', 'media'] as $d) {
        foreach ((array)@scandir(GX_ROOT . '/' . $d) as $f) {
            if (is_string($f) && $f !== '.' && $f !== '..') {
                $files[$d . '/' . $f] = GX_ROOT . '/' . $d . '/' . $f;
            }
        }
    }
    foreach ($files as $rel => $abs) {
        if ($rel === $self || !is_file($abs) || filesize($abs) > 2500000) {
            continue;
        }
        $isPhp = (bool)preg_match(GX_SCRIPT_EXT, $rel);
        $isHt = (substr($rel, -9) === '.htaccess' || substr($rel, -9) === '.user.ini');
        $isWeb = (bool)preg_match('/\.(html?|js|css|svg|xml|txt)$/i', $rel);
        $isMedia = (bool)preg_match('/^(uploads|media)\//', $rel);
        $scanned++;
        $c = (string)file_get_contents($abs);
        if ($isMedia) {
            if (preg_match(GX_SCRIPT_EXT, $rel) || preg_match('/<\?php|<\?=/i', $c)) {
                $findings[] = ['sev' => 'crítico', 'file' => $rel, 'what' => 'Código ejecutable dentro de una carpeta de medios'];
            }
            continue;
        }
        $kinds = [];
        if ($isPhp || $isHt) {
            $kinds[] = $isHt ? 'htaccess' : 'php';
        }
        if ($isWeb || $isPhp) {
            $kinds[] = 'web';
        }
        foreach ($kinds as $k) {
            foreach (gx_scan_rules($k) as [$sev, $what, $rx]) {
                if (@preg_match($rx, $c, $m)) {
                    $findings[] = ['sev' => $sev, 'file' => $rel, 'what' => $what . ' — «' . mb_substr(trim((string)$m[0]), 0, 60) . '»'];
                }
            }
        }
    }
    return ['findings' => $findings, 'scanned' => $scanned];
}
