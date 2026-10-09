#!/usr/bin/env bash
# SOLO PARA PRUEBAS. Crea un sitio WordPress local completo y lo construye paso a paso con sc-provision.php.
#
#   run-local.sh <nombre> <puerto> [opciones de gen-job.php...]
#   Variables: NOSIM=1 (sin simulador de Elementor)  NOWC=1 (sin simulador de WooCommerce)
#              STEPS="install media pages store finish" (pasos a ejecutar)  NOSERVE=1 (no iniciar php -S)
#              KEEP=1 (no recrear DB/carpeta si existen)  BUDGET=90 (segundos por paso)
# Resultado: http://127.0.0.1:<puerto>/  (sitio en /tmp/t2b/sites/<nombre>, DB t2b_<nombre>)
set -u
NAME="${1:?nombre}"; PORT="${2:?puerto}"; shift 2
HERE="$(cd "$(dirname "$0")" && pwd)"; PACK="$(cd "$HERE/.." && pwd)"
ROOT=/tmp/t2b/sites; SITE="$ROOT/$NAME"; DB="t2b_${NAME//[^a-zA-Z0-9]/_}"
STEPS="${STEPS:-install media pages store finish}"; BUDGET="${BUDGET:-90}"
mkdir -p "$ROOT"
if [ -z "${KEEP:-}" ]; then
  [ -f "$SITE/.php.pid" ] && kill "$(cat "$SITE/.php.pid")" 2>/dev/null
  rm -rf "$SITE"; mysql -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  cp -al /opt/wp-core "$SITE"
  mkdir -p "$SITE/wp-content/mu-plugins" "$SITE/wp-content/themes" "$SITE/wp-content/plugins" "$SITE/wp-content/sc-jobs/job1"
  cp -a "$PACK/theme/servicom" "$SITE/wp-content/themes/servicom"
  cp -a "$PACK/mu-plugins/." "$SITE/wp-content/mu-plugins/"
  rm -rf "$SITE/wp-content/mu-plugins/servicom-core/node_modules"
  [ -z "${NOSIM:-}" ] && cp "$HERE/elementor-sim.php" "$SITE/wp-content/mu-plugins/"
  if [ -z "${NOWC:-}" ] && grep -q '"plan": "tienda"' <(php "$HERE/gen-job.php" "$SITE/wp-content/sc-jobs/job1" --port="$PORT" "$@" >/dev/null; cat "$SITE/wp-content/sc-jobs/job1/manifest.json"); then
    cp "$HERE/wc-sim.php" "$SITE/wp-content/mu-plugins/"
  fi
  cp "$HERE/zz-sc-test-stubs.php" "$SITE/wp-content/mu-plugins/"
  cp "$HERE/router.php" "$SITE/router.php"
  php "$HERE/gen-job.php" "$SITE/wp-content/sc-jobs/job1" --port="$PORT" "$@" >/dev/null || exit 1
  cat > "$SITE/wp-config.php" <<PHP
<?php
define('DB_NAME', '$DB'); define('DB_USER', 'root'); define('DB_PASSWORD', ''); define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
define('AUTH_KEY','k1$NAME'); define('SECURE_AUTH_KEY','k2$NAME'); define('LOGGED_IN_KEY','k3$NAME'); define('NONCE_KEY','k4$NAME');
define('AUTH_SALT','s1$NAME'); define('SECURE_AUTH_SALT','s2$NAME'); define('LOGGED_IN_SALT','s3$NAME'); define('NONCE_SALT','s4$NAME');
\$table_prefix = 'wp_';
define('WP_DEBUG', true); define('WP_DEBUG_DISPLAY', false); define('WP_DEBUG_LOG', true);
$( [ -z "${SC_TEST_URL:-}" ] && echo "define('WP_HOME', 'http://127.0.0.1:$PORT'); define('WP_SITEURL', 'http://127.0.0.1:$PORT');" )
define('DISABLE_WP_CRON', true); define('FS_METHOD', 'direct');
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require_once ABSPATH . 'wp-settings.php';
PHP
  echo "<?php return '$(php -r 'echo bin2hex(random_bytes(20));')';" > "$SITE/wp-content/sc-jobs/secret.php"
  cp "$PACK/provision/sc-provision.php" "$SITE/sc-provision.php"
fi
cd "$SITE" || exit 1
for s in $STEPS; do
  n=0
  while : ; do
    n=$((n+1))
    out="$(php sc-provision.php wp-content/sc-jobs/job1 "$s" --budget="$BUDGET" 2>&1 | tail -n 1)"
    echo "[$s] ${out:0:600}"
    if echo "$out" | grep -q '"ok":true' && ! echo "$out" | grep -q '"more":true'; then break; fi
    if echo "$out" | grep -q '"ok":true'; then [ $n -lt 40 ] && continue; fi
    echo "FALLO en $s"; exit 2
  done
done
if [ -z "${NOSERVE:-}" ]; then
  (cd "$SITE" && setsid nohup php -S 127.0.0.1:"$PORT" router.php </dev/null >"$SITE/.php-server.log" 2>&1 & echo $! >"$SITE/.php.pid")
  sleep 1
  echo "SITIO: http://127.0.0.1:$PORT/ (clave de vista previa: $(php -r '$m=json_decode(file_get_contents("'"$SITE"'/wp-content/sc-jobs/job1/manifest.json"),true);echo $m["preview"]["key"];'))"
fi
