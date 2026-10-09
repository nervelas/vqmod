#!/usr/bin/env bash
# Monta un WordPress local de pruebas (MariaDB por socket, php -S en 127.0.0.1:8111).
# Uso: tests/setup.sh [directorio]    (por defecto /tmp/t2a)
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CORE="$(cd "$HERE/.." && pwd)"                 # .../mu-plugins/servicom-core
MU="$(cd "$CORE/.." && pwd)"                    # .../mu-plugins
PACK="$(cd "$MU/.." && pwd)"                    # .../wp-pack
DIR="${1:-/tmp/t2a}"
SITE="$DIR/site"
PORT=8111
DB=t2a_core
PREFIX="w$(head -c4 /dev/urandom | od -An -tx1 | tr -d ' \n' | cut -c1-5)_"

pkill -f "php -S 127.0.0.1:$PORT" 2>/dev/null || true
mysql -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4;"
rm -rf "$DIR"; mkdir -p "$SITE"
cp -a /opt/wp-core/. "$SITE/"
mkdir -p "$SITE/wp-content/mu-plugins" "$SITE/wp-content/themes" "$SITE/wp-content/uploads"
cp -a "$MU/servicom-core.php" "$SITE/wp-content/mu-plugins/"
cp -a "$MU/servicom-core" "$SITE/wp-content/mu-plugins/"
rm -rf "$SITE/wp-content/mu-plugins/servicom-core/tests"
if [ -d "$PACK/theme/servicom" ]; then cp -a "$PACK/theme/servicom" "$SITE/wp-content/themes/servicom"; fi
cp -a "$HERE/fixtures/t2a-classic" "$SITE/wp-content/themes/t2a-classic"
cp "$HERE/fixtures/t2a-env.php" "$SITE/wp-content/mu-plugins/t2a-env.php"

cat > "$SITE/wp-config.php" <<PHP
<?php
define('DB_NAME','$DB'); define('DB_USER','root'); define('DB_PASSWORD',''); define('DB_HOST','localhost');
define('DB_CHARSET','utf8mb4'); define('DB_COLLATE','');
define('AUTH_KEY','a'); define('SECURE_AUTH_KEY','b'); define('LOGGED_IN_KEY','c'); define('NONCE_KEY','d');
define('AUTH_SALT','e'); define('SECURE_AUTH_SALT','f'); define('LOGGED_IN_SALT','g'); define('NONCE_SALT','h');
\$table_prefix = '$PREFIX';
define('WP_DEBUG', true); define('WP_DEBUG_LOG', '$DIR/debug.log'); define('WP_DEBUG_DISPLAY', false);
define('WP_HOME','http://127.0.0.1:$PORT'); define('WP_SITEURL','http://127.0.0.1:$PORT');
define('WP_ENVIRONMENT_TYPE','local');
define('WP_HTTP_BLOCK_EXTERNAL', true); define('WP_ACCESSIBLE_HOSTS', '127.0.0.1,localhost');
define('AUTOMATIC_UPDATER_DISABLED', true);
if ( ! defined('ABSPATH') ) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
PHP

cat > "$DIR/router.php" <<'PHP'
<?php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = $_SERVER['DOCUMENT_ROOT'] . $p;
if ($p !== '/' && is_file($f)) { return false; }
if (is_dir($f) && is_file(rtrim($f,'/').'/index.php')) { $_SERVER['SCRIPT_NAME'] = rtrim($p,'/').'/index.php'; chdir($f); require rtrim($f,'/').'/index.php'; return true; }
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'].'/index.php';
require $_SERVER['DOCUMENT_ROOT'].'/index.php';
PHP

php "$HERE/install.php" "$SITE" "http://127.0.0.1:$PORT"
echo "<?php return ['dir'=>'$DIR','site'=>'$SITE','url'=>'http://127.0.0.1:$PORT','prefix'=>'$PREFIX','db'=>'$DB'];" > "$DIR/env.php"
( cd "$SITE" && PHP_CLI_SERVER_WORKERS=4 setsid nohup php -S 127.0.0.1:$PORT -t "$SITE" "$DIR/router.php" > "$DIR/server.log" 2>&1 < /dev/null & ) > /dev/null 2>&1 < /dev/null
sleep 1
echo "Listo: http://127.0.0.1:$PORT  (DB $DB, prefijo $PREFIX)"
php "$HERE/seed.php"
rm -f "$DIR/debug.log"
