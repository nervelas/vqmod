#!/usr/bin/env bash
# SOLO PARA PRUEBAS. HTTP firmado de sc-provision.php: firma válida / inválida / vencida / sin secreto.
# uso: http-test.sh <nombre-de-sitio-ya-creado> <puerto>
S=/tmp/t2b/sites/$1; PORT=$2
(cd $S && setsid nohup php -S 127.0.0.1:$PORT router.php </dev/null >/tmp/t2b/http-$1.log 2>&1 &)
sleep 1
SECRET=$(php -r '$s=include "'$S'/wp-content/sc-jobs/secret.php";echo $s;')
sig() { php -r 'echo hash_hmac("sha256","'$1'|job1|'$2'","'$SECRET'");'; }
now=$(date +%s)
req() { curl -s -w " [HTTP %{http_code}]\n" "http://127.0.0.1:$PORT/sc-provision.php?job=job1&step=$1&ts=$2&sig=$3$4" | cut -c1-260; }
echo "-- firma válida (status):";   req status $now $(sig status $now)
echo "-- firma inválida:";          req status $now $(sig status $now | tr 0-9a-f a-f0-9)
echo "-- firma vencida (-300s):";   req status $((now-300)) $(sig status $((now-300)))
echo "-- futuro (+300s):";          req status $((now+300)) $(sig status $((now+300)))
echo "-- sin firma:";               curl -s -w " [HTTP %{http_code}]\n" "http://127.0.0.1:$PORT/sc-provision.php?job=job1&step=status" | cut -c1-200
echo "-- job con traversal:";       curl -s -w " [HTTP %{http_code}]\n" "http://127.0.0.1:$PORT/sc-provision.php?job=../x&step=status&ts=$now&sig=$(sig status $now)" | cut -c1-200
echo "-- paso desconocido (firmado):"; req nada $now $(sig nada $now)
echo "-- finish por HTTP:";         req finish $now $(sig finish $now)
echo "-- qa por HTTP:";             req qa $now $(sig qa $now)
echo "-- publish sin correo:";      req publish $now $(sig publish $now)
echo "-- publish con args:";        req publish $now $(sig publish $now) "&email=cliente@example.test&name=Cliente"
pkill -f "php -S 127.0.0.1:$PORT" 2>/dev/null || true
