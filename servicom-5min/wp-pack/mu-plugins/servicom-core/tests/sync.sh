#!/usr/bin/env bash
# Copia el mu-plugin actual al sitio de pruebas (sin recrearlo).
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; MU="$(cd "$HERE/../.." && pwd)"
DIR="${1:-/tmp/t2a}"
cp -a "$MU/servicom-core.php" "$DIR/site/wp-content/mu-plugins/"
rm -rf "$DIR/site/wp-content/mu-plugins/servicom-core"; cp -a "$MU/servicom-core" "$DIR/site/wp-content/mu-plugins/"
rm -rf "$DIR/site/wp-content/mu-plugins/servicom-core/tests"
