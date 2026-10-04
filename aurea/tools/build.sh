#!/bin/bash
# Construye aurea-agenda.zip (contenido listo para descomprimir en la raíz del hosting, sin carpeta contenedora).
set -e
SRC="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$SRC/../aurea-agenda.zip}"
STAGE="$(mktemp -d)"
php "$SRC/tools/extract_lang.php" >/dev/null
rsync -a --exclude 'tests/' --exclude 'tools/' --exclude 'config/config.php' --exclude 'config/installed.lock' \
  --exclude 'storage/logs/*' --exclude 'storage/cache/*' --exclude 'storage/sessions/*' --exclude 'storage/private/*' --exclude 'storage/backups/*' \
  --exclude 'uploads/*' --exclude '.git' --exclude '.gitignore' --exclude 'dist/' --exclude '*.zip' --exclude 'REPORTE_VERIFICACION.md' --exclude '.DS_Store' "$SRC/" "$STAGE/"
cp "$SRC/uploads/.htaccess" "$STAGE/uploads/.htaccess"; cp "$SRC/uploads/index.html" "$STAGE/uploads/index.html"
for d in logs cache sessions private backups; do : > "$STAGE/storage/$d/.gitkeep"; done
mkdir -p "$STAGE/tests" && rm -rf "$STAGE/tests"
rm -f "$OUT"; (cd "$STAGE" && zip -qr -X "$OUT" . -x '*.gitkeep.bak')
rm -rf "$STAGE"
echo "$OUT ($(du -h "$OUT" | cut -f1))"
