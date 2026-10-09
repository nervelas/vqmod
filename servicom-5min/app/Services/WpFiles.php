<?php
declare(strict_types=1);
namespace S5\Services;

/** Contenido de wp-config.php y .htaccess de cada web de cliente. */
final class WpFiles
{
    public static function salts(): string
    {
        $out = '';
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $k) {
            $v = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
            $out .= "define('$k', " . var_export($v, true) . ");\n";
        }
        return $out;
    }

    public static function config(array $db, string $prefix, bool $https): string
    {
        $q = fn($s) => var_export((string) $s, true);
        $c = "<?php\n/** Generado por Servicom. No edite este archivo. */\n";
        $c .= "define('DB_NAME', {$q($db['db'])});\ndefine('DB_USER', {$q($db['user'])});\ndefine('DB_PASSWORD', {$q($db['pass'])});\ndefine('DB_HOST', {$q($db['host'])});\n";
        $c .= "define('DB_CHARSET', 'utf8mb4');\ndefine('DB_COLLATE', '');\n";
        $c .= self::salts();
        $c .= "\$table_prefix = {$q($prefix)};\n";
        $c .= "define('WP_DEBUG', false);\ndefine('WP_DEBUG_DISPLAY', false);\ndefine('WP_DEBUG_LOG', false);\n";
        $c .= "define('DISALLOW_FILE_EDIT', true);\ndefine('DISALLOW_FILE_MODS', true);\ndefine('WP_AUTO_UPDATE_CORE', false);\n";
        $c .= "define('AUTOMATIC_UPDATER_DISABLED', true);\ndefine('WP_MEMORY_LIMIT', '256M');\ndefine('WP_POST_REVISIONS', 5);\ndefine('EMPTY_TRASH_DAYS', 7);\n";
        $c .= "define('CONCATENATE_SCRIPTS', false);\n";
        if ($https) {
            $c .= "define('FORCE_SSL_ADMIN', true);\nif (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') { \$_SERVER['HTTPS'] = 'on'; }\n";
        }
        $c .= "if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\nrequire_once ABSPATH . 'wp-settings.php';\n";
        return $c;
    }

    public static function htaccess(bool $https, bool $suspended = false): string
    {
        $deny = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
        $h = "# BEGIN Servicom\nOptions -Indexes\nServerSignature Off\n";
        if ($suspended) {
            $h .= "ErrorDocument 503 /sc-suspendido.html\n<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteCond %{REQUEST_URI} !^/sc-suspendido\\.html$\n  RewriteRule ^ - [R=503,L]\n</IfModule>\n";
        }
        $h .= "<IfModule mod_headers.c>\n  Header always set X-Content-Type-Options \"nosniff\"\n  Header always set X-Frame-Options \"SAMEORIGIN\"\n  Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n  Header always set Permissions-Policy \"camera=(), microphone=(), geolocation=(self), payment=()\"\n";
        if ($https) {
            $h .= "  Header always set Strict-Transport-Security \"max-age=31536000\"\n";
        }
        $h .= "</IfModule>\n";
        $h .= "<FilesMatch \"^(wp-config\\.php|xmlrpc\\.php|readme\\.html|license\\.txt|wp-config-sample\\.php)$\">\n$deny</FilesMatch>\n";
        $h .= "<FilesMatch \"\\.(log|sql|bak|ini|sh|inc|swp)$\">\n$deny</FilesMatch>\n";
        $h .= "<IfModule mod_deflate.c>\n  AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/javascript application/json image/svg+xml\n</IfModule>\n";
        $h .= "<IfModule mod_expires.c>\n  ExpiresActive On\n  ExpiresByType image/webp \"access plus 1 year\"\n  ExpiresByType image/jpeg \"access plus 1 year\"\n  ExpiresByType image/png \"access plus 1 year\"\n  ExpiresByType image/svg+xml \"access plus 1 year\"\n  ExpiresByType font/woff2 \"access plus 1 year\"\n  ExpiresByType text/css \"access plus 1 month\"\n  ExpiresByType application/javascript \"access plus 1 month\"\n</IfModule>\n";
        if ($https) {
            $h .= "<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteCond %{HTTPS} !=on\n  RewriteCond %{HTTP:X-Forwarded-Proto} !https\n  RewriteCond %{REQUEST_URI} !^/\\.well-known/\n  RewriteCond %{REQUEST_URI} !^/sc-provision\\.php\n  RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]\n</IfModule>\n";
        }
        $h .= "# END Servicom\n\n# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n";
        return $h;
    }

    public static function uploadsHtaccess(): string
    {
        return "# Servicom: nunca ejecutar PHP en uploads\n<FilesMatch \"\\.(php|php[0-9]|phtml|phar|pl|py|cgi|sh)$\">\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n</FilesMatch>\nOptions -Indexes -ExecCGI\n";
    }
}
