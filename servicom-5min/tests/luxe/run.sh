#!/usr/bin/env bash
# LUXE: construye webs de ejemplo con MUY poca información (logo + servicios) y las revisa en Chromium;
# luego prueba el editor en vivo. Requiere el entorno e2e (tests/e2e/setup.sh) y tests/e2e/mock_ai.
cd "$(dirname "$0")/../.."
FAILS=0
build_and_qa() {
  local label="$1"; shift
  php tests/e2e/flow_basic.php --skip-publish=1 "$@" > /tmp/lx-build.log 2>&1
  if grep -q "FAIL" /tmp/lx-build.log; then echo "  ✗ $label: la construcción reportó fallas"; grep FAIL /tmp/lx-build.log | cut -c1-220 | head -3; FAILS=$((FAILS+1)); fi
  local row slug key
  row=$(mysql -N s5test -e "select slug, preview_key from s5_orders order by id desc limit 1")
  slug=${row%%$'\t'*}; key=${row##*$'\t'}
  echo "== $label ($slug)"
  tests/luxe/sync.sh "$slug"
  node tests/luxe/qa.js "http://$slug.servicom.test:8200" "$key" || FAILS=$((FAILS+1))
  LAST_SLUG=$slug
}
build_and_qa "abogado, oscuro, 2 servicios, sin fotos"          --rubro=abogado --estilo=1 --servicios=2 --fotos=0 --logo=1 --logofile=$PWD/tests/luxe/fixtures/azul_oro.png --nombre="Bufete Lex Prima"
build_and_qa "restaurante, claro, 4 servicios, con fotos"       --rubro=restaurante --estilo=2 --servicios=4 --fotos=1 --logo=1 --logofile=$PWD/tests/luxe/fixtures/rojo_negro.png --nombre="La Cantina Dorada"
build_and_qa "tienda de ropa, 3 servicios, 6 productos"          --plan=tienda --rubro=ropa --estilo=4 --servicios=3 --productos=6 --fotos=1 --logo=1 --logofile=$PWD/tests/luxe/fixtures/magenta_naranja.png --nombre="Boutique Aurora"
build_and_qa "clínica, moderno oscuro, sin logo, 1 servicio"     --rubro=clinica --estilo=3 --servicios=1 --fotos=0 --logo=0 --nombre="Clínica Vida Plena"
build_and_qa "taller, audaz, textos largos con emojis"           --rubro=taller --estilo=5 --servicios=3 --fotos=1 --logo=1 --logofile=$PWD/tests/luxe/fixtures/verde.png --largo=emoji --nombre="Taller Mecánico Ñuñoa"
echo "== editor en vivo"
node tests/luxe/editor.e2e.js "$LAST_SLUG" > /tmp/lx-editor.log 2>&1 || FAILS=$((FAILS+1))
tail -3 /tmp/lx-editor.log
echo "LUXE FALLAS: $FAILS"
exit $FAILS
