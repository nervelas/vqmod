#!/usr/bin/env bash
# SOLO PARA PRUEBAS. Construye una matriz de sitios, los valida y rastrea por HTTP.
# uso: matrix.sh [filtro]   (resultados en /tmp/t2b/matrix.log; sitios en /tmp/t2b/sites/<nombre>)
HERE="$(cd "$(dirname "$0")" && pwd)"
LOG=/tmp/t2b/matrix.log; : > $LOG
declare -A CFG
CFG[info1]="--services=1 --banner=1 --logo=0 --svcphotos=none --gallery=0 --youtube=0 --style=1 --rubro=abogado"
CFG[info30]="--services=30 --style=2 --rubro=clinica --svcphotos=mixed"
CFG[tienda1]="--plan=tienda --products=1 --cats=1 --style=3 --rubro=ropa --services=0"
CFG[tienda60]="--plan=tienda --products=60 --cats=6 --style=4 --rubro=ropa --services=0 --banner=3"
CFG[nofotos]="--banner=0 --gallery=0 --svcphotos=none --logo=0 --style=5 --rubro=taller --services=6"
CFG[largo]="--text=long --services=5 --style=1 --rubro=contabilidad"
CFG[corto]="--text=short --services=2 --style=2 --rubro=restaurante"
CFG[emoji]="--text=emoji --services=4 --style=3 --rubro=transporte"
CFG[special]="--text=special --services=3 --style=4 --rubro=importaciones"
CFG[ingles]="--lang=en --style=5 --rubro=otro --services=3 --banner=3"
CFG[sincontacto]="--wa=0 --phone=0 --address=0 --map=0 --social=0 --about=0 --youtube=0 --services=3 --style=2 --rubro=abogado"
CFG[tiendaen]="--plan=tienda --lang=en --products=8 --cats=3 --style=1 --rubro=ropa --banner=2 --services=0"
port=8121
for name in info1 info30 tienda1 tienda60 nofotos largo corto emoji special ingles sincontacto tiendaen; do
  [[ -n "${1:-}" && "$name" != *$1* ]] && continue
  echo "=== $name (${CFG[$name]})" | tee -a $LOG
  s=$(date +%s)
  BUDGET=100 NOSERVE=1 "$HERE/run-local.sh" "$name" $port ${CFG[$name]} 2>&1 | cut -c1-260 >> $LOG
  e1=$(( $(date +%s)-s ))
  S=/tmp/t2b/sites/$name
  npr=$(php -r '$m=json_decode(file_get_contents("'$S'/wp-content/sc-jobs/job1/manifest.json"),true);echo count($m["store"]["productos"]);')
  nsv=$(php -r '$m=json_decode(file_get_contents("'$S'/wp-content/sc-jobs/job1/manifest.json"),true);echo count($m["content"]["servicios"]);')
  php "$HERE/validate-site.php" $S --expect-products=$npr --expect-services=$nsv 2>&1 | cut -c1-900 >> $LOG
  echo "tiempo build: ${e1}s" >> $LOG
  port=$((port+1)); [ $port -gt 8129 ] && port=8121
done
echo FIN >> $LOG
