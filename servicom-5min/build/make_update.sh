#!/usr/bin/env bash
# Genera dist/actualizacion-lujo.zip: SOLO los archivos nuevos/cambiados respecto de la instalación que ya tiene el cliente
# (ZIP entregado en e4201a1 + parche-3). Se descomprime encima del portal; no toca config, base de datos, storage ni install.php.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
W=$(mktemp -d); mkdir -p "$W/inst" "$W/new"
git show e4201a1:servicom-5min/entrega/servicom-5min-portal.zip > "$W/old.zip"
(cd "$W/inst" && unzip -q ../old.zip && unzip -oq "$ROOT/entrega/parche-3.zip")
(cd "$W/new" && unzip -q "$ROOT/dist/servicom-5min-portal.zip")
: > "$W/changed.txt"
(cd "$W/new" && find . -type f | sed 's#^\./##' | sort) | while read -r f; do
  case "$f" in install.php|storage/*|.htaccess|LEEME.md|INFORME.md|DECISIONES.md) continue;; esac
  if [ ! -f "$W/inst/$f" ] || ! cmp -s "$W/new/$f" "$W/inst/$f"; then echo "$f" >> "$W/changed.txt"; fi
done
rm -f dist/actualizacion-lujo.zip
(cd "$W/new" && zip -q -9 "$ROOT/dist/actualizacion-lujo.zip" -@ < "$W/changed.txt")
wc -l < "$W/changed.txt"
unzip -l dist/actualizacion-lujo.zip | tail -1
echo "$W" > /tmp/update_work_dir
