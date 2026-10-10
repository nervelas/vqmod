#!/usr/bin/env bash
# Crea entregas/cambios-AAAAMMDD-HHMM.zip SOLO con lo que está dentro de salida/ (rutas desde la raíz del sitio: wp-content/...).
# Uso: bash herramientas/empaquetar.sh "descripción corta del cambio"
set -euo pipefail
cd "$(dirname "$0")/.."
DESC="${1:-cambios}"
[ -d salida ] && [ -n "$(find salida -type f -print -quit)" ] || { echo "ERROR: la carpeta salida/ está vacía. Copie ahí SOLO los archivos que cambió, con su ruta (salida/wp-content/...)."; exit 1; }
# 1) Solo se permite wp-content/
BAD=$(cd salida && find . -mindepth 1 -maxdepth 1 ! -name wp-content -print)
[ -z "$BAD" ] || { echo "ERROR: en salida/ solo puede haber la carpeta wp-content/. Sobra: $BAD"; exit 1; }
# 2) Nada que no debe viajar
FORB=$(cd salida && find . \( -iname 'wp-config*' -o -iname '*.sql' -o -iname '*.sql.gz' -o -iname '.env*' -o -iname '*.log' -o -iname '.htaccess' -o -iname '*.pem' -o -iname '*.key' \) -print)
[ -z "$FORB" ] || { echo "ERROR: estos archivos no deben incluirse: $FORB"; exit 1; }
# 3) Sintaxis PHP
FAIL=0
while IFS= read -r f; do php -l "$f" >/dev/null 2>&1 || { echo "ERROR de sintaxis PHP en $f"; php -l "$f" || true; FAIL=1; }; done < <(find salida -name '*.php' -type f)
[ "$FAIL" -eq 0 ] || exit 1
# 4) Los archivos de cambios de contenido deben tener ID único y no repetir uno ya entregado
for f in $(find salida/wp-content/mu-plugins -maxdepth 1 -name 'sc-cambios-*.php' 2>/dev/null); do
  id=$(sed -n "s/.*\$id = 'sc_cambio_\([A-Za-z0-9_]*\)'.*/\1/p" "$f" | head -1)
  [ -n "$id" ] && [ "$id" != "AAAAMMDD_HHMM" ] || { echo "ERROR: $f necesita un ID real (AAAAMMDD_HHMM) en la línea \$id."; exit 1; }
  [ "$(basename "$f")" = "sc-cambios-$id.php" ] || { echo "ERROR: el nombre del archivo debe ser sc-cambios-$id.php"; exit 1; }
done
mkdir -p entregas
OUT="entregas/cambios-$(date +%Y%m%d-%H%M).zip"
(cd salida && zip -q -r -X "../$OUT" wp-content)
echo "Listo: $OUT   ($DESC)"
unzip -l "$OUT" | tail -n +4 | head -n -2
echo
echo "Para aplicar: subir el ZIP a la raíz del sitio (donde está wp-config.php), 'Extract', y abrir la web una vez."
