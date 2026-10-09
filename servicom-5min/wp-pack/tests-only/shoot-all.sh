#!/usr/bin/env bash
# SOLO PARA PRUEBAS. Sirve cada sitio de la matriz y toma capturas (5 anchos en 4 páginas; todas las páginas a 390).
HERE="$(cd "$(dirname "$0")" && pwd)"; port=8121; : > /tmp/t2b/shots.log
for name in ${@:-info1 info30 tienda1 tienda60 nofotos largo corto emoji special ingles sincontacto tiendaen}; do
  S=/tmp/t2b/sites/$name
  (cd $S && setsid nohup php -S 127.0.0.1:$port router.php </dev/null >/tmp/t2b/srv-$name.log 2>&1 & echo $! > $S/.php.pid)
  sleep 1
  echo "== $name" >> /tmp/t2b/shots.log
  (cd /tmp && NODE_PATH=/opt/node-tools/node_modules node $HERE/shots.js $S $port $name 360,390,768,1024,1440 /,/contacto/ 2>&1 | tail -8) >> /tmp/t2b/shots.log
  (cd /tmp && MAXP=9 NODE_PATH=/opt/node-tools/node_modules node $HERE/shots.js $S $port ${name}-all 390 2>&1 | tail -8) >> /tmp/t2b/shots.log
  bash "$HERE/kill-sites.sh"
  port=$((port+1)); [ $port -gt 8129 ] && port=8121
done
echo FIN >> /tmp/t2b/shots.log
