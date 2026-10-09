#!/usr/bin/env bash
# SOLO PARA PRUEBAS. Mata (kill -9) cada paso a mitad varias veces, reintenta y comprueba que no hay duplicados.
# uso: kill-test.sh <nombre> <puerto> [opciones gen-job]   (p. ej. --plan=tienda --products=60 --services=12)
HERE="$(cd "$(dirname "$0")" && pwd)"
NAME="$1"; PORT="$2"; shift 2
STEPS="install" NOSERVE=1 "$HERE/run-local.sh" "$NAME" "$PORT" "$@" | cut -c1-100
S=/tmp/t2b/sites/$NAME; cd "$S" || exit 1
for step in media pages store finish; do
  for delay in 0.4 0.9 1.6 2.4; do
    timeout -s KILL "$delay" php sc-provision.php wp-content/sc-jobs/job1 $step --budget=100 >/dev/null 2>&1
    echo "[$step] kill -9 a ${delay}s (rc=$?)"
  done
  n=0
  while : ; do
    n=$((n+1)); out="$(php sc-provision.php wp-content/sc-jobs/job1 $step --budget=100 2>&1 | tail -n1)"
    echo "[$step] final: ${out:0:200}"
    echo "$out" | grep -q '"ok":true' || { echo FALLO; exit 2; }
    echo "$out" | grep -q '"more":true' && [ $n -lt 30 ] && continue
    break
  done
  # segunda ejecución completa (idempotencia)
  out="$(php sc-provision.php wp-content/sc-jobs/job1 $step --budget=100 2>&1 | tail -n1)"; echo "[$step] repetido: ${out:0:120}"
done
npr=$(php -r '$m=json_decode(file_get_contents("wp-content/sc-jobs/job1/manifest.json"),true);echo count($m["store"]["productos"]);')
nsv=$(php -r '$m=json_decode(file_get_contents("wp-content/sc-jobs/job1/manifest.json"),true);echo count($m["content"]["servicios"]);')
php "$HERE/validate-site.php" "$S" --expect-products=$npr --expect-services=$nsv | cut -c1-700
mysql -N -e "SELECT post_type,COUNT(*) FROM t2b_${NAME//[^a-zA-Z0-9]/_}.wp_posts WHERE post_status<>'trash' GROUP BY post_type" | tr '\n' ' '; echo
