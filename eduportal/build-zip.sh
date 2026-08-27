#!/usr/bin/env bash
# Empaqueta EduPortal para subir a cPanel. Ejecutar desde la carpeta del proyecto.
set -e
ORIGEN="$(cd "$(dirname "$0")" && pwd)"
DESTINO="${1:-$ORIGEN/../eduportal.zip}"
TMP="$(mktemp -d)"
PAQ="$TMP/eduportal"

mkdir -p "$PAQ"
cp -r "$ORIGEN/." "$PAQ/"

# Archivos que nunca deben viajar en el paquete
rm -f  "$PAQ/config/config.php" "$PAQ/install/.lock" "$PAQ/router-pruebas.php" "$PAQ/build-zip.sh"
rm -rf "$PAQ/tools"
rm -rf "$PAQ/.git" "$PAQ/node_modules"
find "$PAQ/storage" -type f ! -name '.htaccess' ! -name '.gitkeep' ! -name 'index.html' -delete
find "$PAQ/storage" -type d -empty -not -path "$PAQ/storage" -exec rm -rf {} + 2>/dev/null || true
mkdir -p "$PAQ/storage/uploads" "$PAQ/storage/backups" "$PAQ/storage/logs" "$PAQ/storage/cache" "$PAQ/storage/sessions"
for d in uploads backups logs cache sessions; do
  touch "$PAQ/storage/$d/.gitkeep"
  cp "$PAQ/storage/.htaccess" "$PAQ/storage/$d/.htaccess" 2>/dev/null || true
done

# Permisos coherentes
find "$PAQ" -type d -exec chmod 755 {} +
find "$PAQ" -type f -exec chmod 644 {} +

rm -f "$DESTINO"
( cd "$PAQ" && zip -rq "$DESTINO" . -x '.DS_Store' '*/.DS_Store' )
rm -rf "$TMP"
echo "Paquete generado: $DESTINO"
