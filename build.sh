#!/usr/bin/env bash
# Genera los ZIP instalables de CGM (tema + plugin) en dist/.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"
mkdir -p dist
rm -f dist/cgm-lifestyle.zip dist/cgm-core.zip

EXCLUDES=( -x "*.DS_Store" -x "*/.git/*" -x "*/node_modules/*" -x "*.log" -x "*/.*" )

echo "→ Empaquetando tema cgm-lifestyle…"
zip -r -q dist/cgm-lifestyle.zip cgm-lifestyle "${EXCLUDES[@]}"

echo "→ Empaquetando plugin cgm-core…"
zip -r -q dist/cgm-core.zip cgm-core "${EXCLUDES[@]}"

echo "✔ Listo:"
ls -lh dist/*.zip
