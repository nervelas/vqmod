#!/usr/bin/env bash
# Ejecuta TODA la batería de pruebas desde cero y resume. Requiere el entorno descrito en tests/README.md.
set -u
cd "$(dirname "$0")/.."
LOG=/tmp/s5test/run/suite.log; : > $LOG
res() { printf "%-34s %s\n" "$1" "$2" | tee -a $LOG; }
run() { local n="$1"; shift; if out=$("$@" 2>&1); then res "$n" "OK  $(echo "$out" | grep -E 'OK$|Resultado|Seguridad|Presentaciones|Ciclo|Fallos simulados|Agente:|SSL:|PHP 8.0|Compatibilidad' | tail -1)"; else res "$n" "FALLO"; echo "$out" | grep -E "FAIL|Error|FALLO" | head -8 | tee -a $LOG; FAILS=$((FAILS+1)); fi; }
FAILS=0
echo "== Sintaxis y compatibilidad"
run "php -l (8.3)" bash -c 'find app tools install.php index.php wp-pack -name "*.php" -not -path "*/tests-only/*" | xargs -n1 php -l 2>&1 | grep -v "^No syntax" | head -5; test -z "$(find app tools install.php index.php wp-pack -name "*.php" | xargs -n1 php -l 2>&1 | grep -v "^No syntax")"'
run "Compatibilidad PHP 8.0 (escáner)" php tests/compat80.php app install.php index.php tools wp-pack
run "Sintaxis PHP 8.0.30 (WASM)" bash -c 'cd /tmp/p80 && node lint.mjs /home/user/vqmod/servicom-5min/app /home/user/vqmod/servicom-5min/install.php /home/user/vqmod/servicom-5min/index.php /home/user/vqmod/servicom-5min/tools /home/user/vqmod/servicom-5min/wp-pack'
run "Cifrado de secretos (sin sodium)" php tests/crypto.php
run "Cifrado en PHP 8.0.30 (WASM, sin sodium)" bash -c 'cd /tmp/p80 && node run1.mjs | tee /dev/stderr | grep -q "Crypto: 17/17"'
run "IA y presentaciones (unitarias)" php tests/ai/run.php
./build/make_zip.sh --with-sim >/dev/null 2>&1
./tests/e2e/setup.sh >/dev/null 2>&1 && /tmp/mockctl.sh start
run "Flujo completo (formulario→pago→publicar)" php tests/e2e/flow_basic.php
run "Presentaciones por HTTP" php tests/e2e/flow_pres.php
run "Ciclo de vida" php tests/e2e/lifecycle.php
run "Fallos simulados y rollback" php tests/e2e/faults.php
run "Constructor por HTTP firmado" php tests/e2e/flow_httprunner.php
run "Demos y enlaces de la barra" php tests/e2e/demo.php
run "Panel (2FA, ajustes, diagnóstico)" php tests/e2e/panel.php
run "SSL real (CA local)" php tests/e2e/ssl.php
run "Seguridad del portal" php tests/e2e/security.php
run "cPanel por localhost (SSL)" php tests/e2e/cpanel_ssl.php
run "Fotos de stock (servidores simulados)" php tests/stock/run.php
run "LUXE: 5 webs con poca información + QA visual + editor en vivo" bash tests/luxe/run.sh
run "mu-plugin Servicom Core (roles, editor, instrucciones, vista previa…)" bash -c "cd wp-pack/mu-plugins/servicom-core/tests && bash run_all.sh --fresh > /tmp/mu_suite.txt 2>&1; php t_editor.php >> /tmp/mu_suite.txt 2>&1; ! grep -E 'FAIL|[1-9][0-9]* fallos' /tmp/mu_suite.txt"
mkdir -p /tmp/oldzip && git show e4201a1:servicom-5min/entrega/servicom-5min-portal.zip > /tmp/oldzip/old.zip 2>/dev/null
./build/make_patch.sh >/dev/null 2>&1
run "Parche de acceso sobre instalación existente" php tests/e2e/parche.php /tmp/oldzip/old.zip dist/parche-acceso.zip
echo "== Navegador (Chromium)"
( cd tests/portal/e2e && for w in 360 390 768 1024 1440; do run "Wizard real $w px" bash -c "node flowReal.js $w | tee /dev/stderr | grep -q 'errores consola: \[\] overflows: 0'"; done )
run "Tienda 60 productos (390 px)" bash -c 'cd tests/portal/e2e && node flowRealB.js 390 60 | tee /dev/stderr | grep -q "errores consola: \[\]"'
echo "FALLOS: $FAILS"
exit $FAILS
