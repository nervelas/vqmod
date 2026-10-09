#!/usr/bin/env bash
# Verifica el ZIP de PRODUCCIÓN: contenido, sin secretos/pruebas, sintaxis, instalación real con el instalador web.
set -u
cd "$(dirname "$0")/.."
./build/make_zip.sh >/dev/null 2>&1 || { echo "FALLO make_zip"; exit 1; }
Z=dist/servicom-5min-portal.zip; F=0
chk() { if eval "$2"; then echo "  OK   $1"; else echo "  FAIL $1"; F=$((F+1)); fi; }
L=$(unzip -Z1 $Z)
echo "== Contenido del ZIP del portal ($(du -h $Z | cut -f1), $(echo "$L" | wc -l) entradas)"
for need in index.php install.php .htaccess LEEME.md app/bootstrap.php app/routes.php wp-pack/theme/servicom/style.css wp-pack/mu-plugins/servicom-core.php wp-pack/provision/sc-provision.php vendor/phpmailer/src/PHPMailer.php tools/build_base.php tools/cron.php library/catalog.json library/FALTANTES.md assets/css/base.css assets/js/wizard.js; do chk "contiene $need" 'echo "$L" | grep -qx "$need"'; done
for bad in 'tests/' 'tests-only' 'SimCpanelApi' 'node_modules' '\.git/' '\.log$' 'elementor-sim' 'wc-sim' '\.sh$' '^docs/' '^build/' '^dist/' '^agent/'; do chk "no contiene $bad" '! echo "$L" | grep -qE "$bad"'; done
chk "sin config.php" '! echo "$L" | grep -qE "(^|/)config\.php$"'
chk "storage vacío (solo .gitkeep/.htaccess)" '! echo "$L" | grep "^storage/" | grep -vE "\.gitkeep$|\.htaccess$|/$" | grep -q .'
echo "== Secretos"
T=$(mktemp -d); unzip -q "$PWD/$Z" -d $T
chk "sin claves tipo sk-ant / contraseñas literales" '! grep -rIE "sk-ant-[A-Za-z0-9_-]{10,}|BEGIN (RSA|PRIVATE)|Contrasena-Segura|s5adminpass|s5tpass" $T --include=*.php --include=*.js --include=*.json --include=*.md -l | grep -q .'
echo "== Sintaxis (8.3) de todo el ZIP"
BAD=$(find $T -name '*.php' | xargs -n1 php -l 2>&1 | grep -v '^No syntax' | head -3); chk "php -l sin errores" '[ -z "$BAD" ]'
chk "sin restos del agente (agent, Hmac, AgentServer)" '! echo "$L" | grep -qiE "agent|Hmac"'
rm -rf $T
echo "FALLOS: $F"; exit $F
