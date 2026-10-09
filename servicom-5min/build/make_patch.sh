#!/usr/bin/env bash
# Genera dist/parche-acceso.zip: SOLO los archivos que cambian (se descomprime encima de la instalación existente).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
FILES="app/Core/Auth.php app/Services/Diagnostics.php app/Provision/CpanelHttpApi.php app/Controllers/AdminController.php app/views/admin/login.php app/views/admin/recuperar.php assets/admin/admin.js"
mkdir -p dist
rm -f dist/parche-acceso.zip
zip -q -9 dist/parche-acceso.zip $FILES
unzip -l dist/parche-acceso.zip
