#!/usr/bin/env bash
# Matriz de construcciones (sin publicar). Salida: /tmp/s5test/run/matrix.log y URLs en /tmp/s5test/run/matrix_urls.txt
cd "$(dirname "$0")/../.."
: > /tmp/s5test/run/matrix_urls.txt
run() { echo ">> $*"; if timeout 900 php tests/e2e/flow_basic.php --skip-publish=1 "$@" > /tmp/s5test/run/m.out 2>&1; then echo "   OK"; else echo "   FALLO"; grep -E "FAIL" /tmp/s5test/run/m.out | cut -c1-300; fi; cat /tmp/s5test/run/last_url 2>/dev/null; tail -n 3 /tmp/s5test/run/m.out | grep "vista previa:" | sed 's/.*vista previa: //;s/ (.*//' >> /tmp/s5test/run/matrix_urls.txt; }
i=0
for r in abogado clinica taller ropa restaurante transporte contabilidad importaciones otro; do
  i=$((i+1)); e=$(( (i-1) % 5 + 1 ))
  run --plan=info --rubro=$r --estilo=$e --servicios=4 --nombre="Demo $r Estilo$e"
done
run --plan=tienda --rubro=ropa --estilo=2 --productos=1 --nombre="Tienda uno"
run --plan=tienda --rubro=restaurante --estilo=5 --productos=60 --nombre="Tienda sesenta"
run --plan=tienda --rubro=importaciones --estilo=3 --productos=6 --fotos=0 --logo=0 --nombre="Tienda sin fotos"
run --plan=info --rubro=abogado --estilo=1 --servicios=1 --fotos=0 --logo=0 --nombre="Un servicio sin fotos"
run --plan=info --rubro=contabilidad --estilo=4 --servicios=30 --nombre="Treinta servicios"
run --plan=info --rubro=taller --estilo=3 --servicios=3 --largo=largo --nombre="x"
run --plan=info --rubro=clinica --estilo=2 --servicios=3 --largo=corto
run --plan=info --rubro=otro --estilo=5 --servicios=3 --largo=emoji --nombre="Café Ñandú 😀 & <Hijos> \"Ltda\""
run --plan=info --rubro=restaurante --estilo=1 --servicios=3 --idioma=en --nombre="English Bistro"
run --plan=tienda --rubro=ropa --estilo=4 --productos=5 --largo=emoji --idioma=en --nombre="Shop Ñ"
echo FIN
