#!/usr/bin/env bash
# Ejecuta toda la batería. Uso: tests/run_all.sh [--fresh]   (--fresh recrea el sitio de pruebas)
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ "${1:-}" = "--fresh" ] || [ ! -f /tmp/t2a/env.php ]; then "$HERE/setup.sh" || exit 1; else "$HERE/sync.sh"; fi
rm -f /tmp/t2a/debug.log; rc=0
for t in business hardening roles instructions instructions_luxe pending aplicar preview domain selfcheck; do
  php "$HERE/t_$t.php" > /tmp/t2a/out_$t.txt 2>&1 || rc=1
  tail -1 /tmp/t2a/out_$t.txt; grep "FAIL" /tmp/t2a/out_$t.txt
done
if [ -s /tmp/t2a/debug.log ]; then echo "AVISO: debug.log tiene entradas:"; cat /tmp/t2a/debug.log; rc=1; else echo "debug.log limpio (sin warnings/notices)"; fi
exit $rc
