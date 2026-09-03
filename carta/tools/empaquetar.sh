#!/bin/sh
# Arma menugold.zip listo para subir a public_html y descomprimir encima.
#
# Deliberadamente NO viajan en el paquete:
#   config/config.php   la configuración del cliente (base de datos, llaves)
#   install/install.lock  la marca de "ya instalado"
#   storage/logs, storage/cache, storage/backups  datos vivos del servidor
# Así descomprimir sobre una instalación en marcha actualiza el código y las
# fotos del ejemplo sin borrar nada de lo que el restaurante ya tiene.
set -e
RAIZ=$(cd "$(dirname "$0")/.." && pwd)
DEST=${1:-"$RAIZ/../menugold.zip"}
TMP=$(mktemp -d)

cd "$RAIZ"
rm -f "$DEST"
tar -cf - \
  --exclude='./config/config.php' \
  --exclude='./install/install.lock' \
  --exclude='./storage/logs/*' \
  --exclude='./storage/cache/*' \
  --exclude='./storage/backups/*' \
  --exclude='./tools/fotos/*.jpg' \
  --exclude='./.git' \
  --exclude='*.DS_Store' \
  . | (cd "$TMP" && tar -xf -)

# Carpetas de trabajo: van vacías pero tienen que existir y ser escribibles.
for d in storage/logs storage/cache storage/backups; do
  mkdir -p "$TMP/$d"
  printf 'Deny from all\n' > "$TMP/$d/.htaccess"
  : > "$TMP/$d/.gitkeep"
done

cd "$TMP"
zip -qr9 "$DEST" . -x '.*'
cd /
rm -rf "$TMP"

echo "ZIP: $DEST"
echo "peso: $(du -h "$DEST" | cut -f1)"
echo "archivos: $(unzip -l "$DEST" | tail -1 | awk '{print $2}')"
if unzip -l "$DEST" | grep -q 'config/config.php'; then
  echo "ERROR: config.php se coló en el paquete" >&2; exit 1
fi
if unzip -l "$DEST" | grep -q 'install.lock'; then
  echo "ERROR: install.lock se coló en el paquete" >&2; exit 1
fi
echo "sin config.php ni install.lock: correcto"
