#!/usr/bin/env bash
# VARIEDAD: construye N webs de negocios distintos y comprueba que (1) cada una pasa el QA visual (menú, contraste, desbordes, modo claro/oscuro)
# y (2) cada una tiene un ADN de diseño DIFERENTE (no hay dos iguales). Uso: tests/luxe/variedad.sh [N=8]
cd "$(dirname "$0")/../.."
N=${1:-8}; FAILS=0; : > /tmp/variedad_dna.txt
NAMES=("Bufete Solano & Asociados" "Clínica Dental Sonrisa" "Taller Hermanos Pérez" "Boutique Luna Roja" "Restaurante La Hacienda" "Transportes Rápido Sur" "Contadores Asociados GT" "Importadora Mundo Global" "Estudio Jurídico Alvarado" "Centro Médico Vida Plena" "Mecánica Express Norte" "Joyas Aurora Dorada")
RUBROS=(abogado clinica taller ropa restaurante transporte contabilidad importaciones abogado clinica taller ropa)
LOGOS=(azul_oro rojo_negro magenta_naranja verde gris azul_oro rojo_negro magenta_naranja verde gris azul_oro rojo_negro)
for i in $(seq 0 $((N-1))); do
  nombre="${NAMES[$i]}"; rubro="${RUBROS[$i]}"; logo="$PWD/tests/luxe/fixtures/${LOGOS[$i]}.png"
  php tests/e2e/flow_basic.php --skip-publish=1 --rubro=$rubro --servicios=3 --fotos=1 --logo=1 --logofile=$logo --nombre="$nombre" > /tmp/lx-build.log 2>&1
  grep -q FAIL /tmp/lx-build.log && { echo "  ✗ $nombre: la construcción reportó fallas"; FAILS=$((FAILS+1)); }
  row=$(mysql -N s5test -e "select slug, preview_key from s5_orders order by id desc limit 1"); slug=${row%%$'\t'*}; key=${row##*$'\t'}
  tests/luxe/sync.sh "$slug" >/dev/null 2>&1
  jar=$(mktemp); curl -s -c $jar "http://$slug.servicom.test:8200/?scpk=$key" -o /dev/null
  dna=$(curl -s -b $jar "http://$slug.servicom.test:8200/" | grep -o 'dna-[a-z]*-[a-z0-9]*' | sort -u | tr '\n' ' ')
  ord=$(curl -s -b $jar "http://$slug.servicom.test:8200/" | grep -o 'lx-sec--[a-z]*' | uniq | tr '\n' ' ')
  rm -f $jar
  echo "$nombre | $dna| $ord" | tee -a /tmp/variedad_dna.txt
  node tests/luxe/qa.js "http://$slug.servicom.test:8200" "$key" || FAILS=$((FAILS+1))
done
uniq_n=$(cut -d'|' -f2,3 /tmp/variedad_dna.txt | sort -u | wc -l)
echo "ADN distintos: $uniq_n de $N"
[ "$uniq_n" -eq "$N" ] || { echo "  ✗ hay webs con el MISMO diseño"; FAILS=$((FAILS+1)); }
echo "VARIEDAD FALLAS: $FAILS"; exit $FAILS
