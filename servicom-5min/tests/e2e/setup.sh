#!/usr/bin/env bash
# Prepara el entorno E2E local: Apache (puertos 8200 sitios / 8201 portal), MariaDB, DNS comodín, portal instalado
# desde el ZIP real vía instalador web, paquete base con WordPress local y host simulado de cPanel.
set -euo pipefail
PROJ="$(cd "$(dirname "$0")/../.." && pwd)"
T=/tmp/s5test
ZIP="$PROJ/dist/servicom-5min-portal-TEST.zip"
echo "== servicios"
pgrep -x mariadbd >/dev/null || service mariadb start >/dev/null
pgrep -x dnsmasq >/dev/null || dnsmasq --conf-file=/etc/dnsmasq.d/s5test.conf --pid-file=/tmp/dnsmasq.pid
apache2ctl -k stop >/dev/null 2>&1 || true; sleep 1
echo "== reinicio de datos"
rm -rf $T/portal $T/webs $T/vroot $T/mail $T/sim $T/config.php $T/run/*.log /tmp/servicom-secrets
echo ok > $T/run/ai-mode 2>/dev/null; mkdir -p $T/portal $T/webs $T/vroot $T/mail $T/sim $T/run
mysql -e "DROP DATABASE IF EXISTS s5test; CREATE DATABASE s5test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 CREATE USER IF NOT EXISTS 's5t'@'localhost' IDENTIFIED BY 's5tpass'; GRANT ALL ON s5test.* TO 's5t'@'localhost';
 CREATE USER IF NOT EXISTS 's5admin'@'localhost' IDENTIFIED BY 's5adminpass'; GRANT ALL PRIVILEGES ON *.* TO 's5admin'@'localhost' WITH GRANT OPTION;"
for d in $(mysql -N -e "SHOW DATABASES LIKE 'sim\_%'"); do mysql -e "DROP DATABASE \`$d\`"; done
for u in $(mysql -N -e "SELECT user FROM mysql.user WHERE user LIKE 'sim\_%'"); do mysql -e "DROP USER '$u'@'localhost'"; done
(cd $T/portal && unzip -q "$ZIP")
# SOLO PRUEBAS: sin HTTPS local, se quita la redirección a https del .htaccess del portal
python3 - <<'PY'
import re
p='/tmp/s5test/portal/.htaccess'; s=open(p).read()
s=re.sub(r"  # Forzar HTTPS \(producción\)\n.*?\n  RewriteRule \^\(\.\*\)\$ https://%\{HTTP_HOST\}/\$1 \[R=301,L\]\n","",s,flags=re.S)
open(p,'w').write(s)
PY
chown -R www-data:www-data $T
apache2ctl -k start 2>&1 | grep -v "Could not reliably" || true; sleep 2
echo "== instalador web"
J=$T/run/cj.txt; rm -f $J
H="Host: crear.servicom.test:8201"
TOK=$(curl -s -c $J -H "$H" http://127.0.0.1:8201/install.php | grep -o 'name="t" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
[ -n "$TOK" ] || { echo "No se obtuvo token del instalador"; curl -s -H "$H" http://127.0.0.1:8201/install.php | head -20; exit 1; }
RES=$(curl -s -b $J -H "$H" -X POST http://127.0.0.1:8201/install.php \
  --data-urlencode "t=$TOK" --data-urlencode db_host=localhost --data-urlencode db_name=s5test --data-urlencode db_user=s5t --data-urlencode db_pass=s5tpass \
  --data-urlencode db_prefix=s5_ --data-urlencode dominio=servicom.test --data-urlencode portal=crear --data-urlencode email=dueno@servicom.test \
  --data-urlencode 'pass=Contrasena-Segura-123' --data-urlencode webs=$T/webs \
  --data-urlencode cp_host=localhost --data-urlencode cp_port=2083 --data-urlencode cp_user=sc_cuenta --data-urlencode cp_token=TOKENDEPRUEBA123 --data-urlencode cp_home=$T)
echo "$RES" | grep -q "Listo" && echo "instalado (el aviso de cPanel es lo esperado: no hay cPanel real aquí)" || { echo "$RES" | sed 's/<[^>]*>/ /g' | tr -s ' \n' | head -20; exit 1; }
[ ! -f $T/portal/install.php ] && echo "install.php eliminado" || { echo "install.php NO se eliminó"; exit 1; }
echo "== paquete base"
chown -R www-data:www-data $T
runuser -u www-data -- env S5_CONFIG_FILE=$T/config.php php $T/portal/tools/build_base.php --webs=$T/webs --wp-dir=/opt/wp-core --skip-download --locale=es_ES
cp "$PROJ/wp-pack/tests-only/elementor-sim.php" $T/webs/_base/wp-content/mu-plugins/ 2>/dev/null || echo "(sin elementor-sim aún)"
[ -f "$PROJ/wp-pack/tests-only/wc-sim.php" ] && cp "$PROJ/wp-pack/tests-only/wc-sim.php" $T/webs/_base/wp-content/mu-plugins/ || true
chown -R www-data:www-data $T
echo "== host simulado y ajustes"
runuser -u www-data -- env S5_CONFIG_FILE=$T/config.php php "$PROJ/tests/e2e/seed.php" "$T"
echo "OK. Portal: http://crear.servicom.test:8201/  Panel: /admin (dueno@servicom.test)"
