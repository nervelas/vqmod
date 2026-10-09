#!/usr/bin/env bash
# Genera dist/servicom-5min-portal.zip
# Uso: build/make_zip.sh [--with-sim]   (--with-sim incluye el simulador de cPanel: SOLO para pruebas locales)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
WITH_SIM=0; [ "${1:-}" = "--with-sim" ] && WITH_SIM=1
OUT_NAME="servicom-5min-portal"; [ $WITH_SIM = 1 ] && OUT_NAME="servicom-5min-portal-TEST"
mkdir -p "$DIST"
P="$STAGE/portal"; mkdir -p "$P"
cd "$ROOT"
cp index.php install.php .htaccess .user.ini robots.txt "$P/"
[ -f LEEME.md ] && cp LEEME.md "$P/" || true
[ -f INFORME.md ] && cp INFORME.md "$P/" || true
[ -f docs/DECISIONES.md ] && cp docs/DECISIONES.md "$P/DECISIONES.md" || true
rsync -a --exclude 'config.php' --exclude '.DS_Store' app/ "$P/app/"
[ $WITH_SIM = 0 ] && rm -f "$P/app/Provision/SimCpanelApi.php"
rsync -a assets/ "$P/assets/"
rsync -a library/ "$P/library/"
mkdir -p "$P/tools"; cp tools/cron.php tools/build_base.php tools/worker.php tools/stock_add.php "$P/tools/"
mkdir -p "$P/vendor/phpmailer"; rsync -a vendor/phpmailer/src "$P/vendor/phpmailer/"; cp vendor/.htaccess "$P/vendor/" 2>/dev/null || true
mkdir -p "$P/wp-pack"
rsync -a --exclude 'tests' --exclude 'tests-only' --exclude '*.sh' wp-pack/ "$P/wp-pack/"
for d in logs sessions uploads jobs work cache; do mkdir -p "$P/storage/$d"; : > "$P/storage/$d/.gitkeep"; done
printf 'Require all denied\nDeny from all\n' > "$P/storage/.htaccess"
for d in app library wp-pack vendor tools; do printf 'Require all denied\nDeny from all\n' > "$P/$d/.htaccess"; done
find "$P" -name '.DS_Store' -delete
rm -f "$DIST/$OUT_NAME.zip"; (cd "$P" && zip -qr -9 "$DIST/$OUT_NAME.zip" . -x '*.orig')
rm -rf "$STAGE"
ls -la "$DIST"
