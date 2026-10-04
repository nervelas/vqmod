#!/usr/bin/env bash
# Construye agenda-premium.zip (sin carpeta contenedora, sin pruebas ni datos de desarrollo).
# Uso: bash tests/build-zip.sh <carpeta_destino>
set -euo pipefail
SRC="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:?Indica la carpeta de destino}"
STAGE="$(mktemp -d)"
mkdir -p "$OUT"
rsync -a --exclude 'tests/' --exclude 'node_modules/' --exclude 'config/config.php' --exclude 'config/installed.lock' \
  --exclude 'storage/*' --exclude '.git*' --exclude '*.log' --exclude '.DS_Store' "$SRC/" "$STAGE/"
# Esqueleto de storage (bloqueado por .htaccess)
cp "$SRC/storage/.htaccess" "$STAGE/storage/.htaccess"
for d in uploads logs cache backups sessions imports; do mkdir -p "$STAGE/storage/$d"; done
find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +
rm -f "$OUT/agenda-premium.zip"
(cd "$STAGE" && zip -qr -X "$OUT/agenda-premium.zip" .)
rm -rf "$STAGE"
echo "Creado $OUT/agenda-premium.zip"
