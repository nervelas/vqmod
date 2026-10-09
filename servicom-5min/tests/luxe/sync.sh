#!/usr/bin/env bash
# Copia tema y mu-plugin al sitio de prueba generado (iteración rápida de diseño). Uso: sync.sh <slug>
S=${1:-bufete-nandu-asociados}; D=/tmp/s5test/webs/$S/wp-content
P="$(cd "$(dirname "$0")/../.." && pwd)/wp-pack"
rsync -a --delete "$P/theme/servicom/" "$D/themes/servicom/"
rsync -a "$P/mu-plugins/" "$D/mu-plugins/" --exclude tests --exclude tests-only
chown -R www-data:www-data "$D/themes/servicom" "$D/mu-plugins" 2>/dev/null
