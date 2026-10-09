# Pruebas

Todo se ejecuta con `tests/run_all.sh` (≈ 30 min) sobre el entorno local descrito abajo.

| Archivo | Qué cubre |
|---|---|
| `compat80.php`, `lint80.mjs` | Sin sintaxis/funciones de PHP 8.1+; análisis sintáctico con PHP 8.0.30 (WASM, `/tmp/p80`) |
| `ai/run.php` | Presentaciones (PDF/PPTX/DOCX, zip bombs, XXE, macros, cifrados), IA con mock, topes de gasto, textos base (259 pruebas) |
| `e2e/flow_basic.php` | Formulario → vista previa → pago → aprobación → publicación |
| `e2e/flow_pres.php` | Presentaciones por HTTP, inyección de prompt, IA caída |
| `e2e/lifecycle.php` | Reservados, regeneración, pago rechazado, publicación, renovación, suspensión, dominio, borrado a los 15 días |
| `e2e/faults.php` | Token inválido, disco lleno, fallos a medias, cierre violento (kill -9), rollback sin huérfanos |
| `e2e/flow_httprunner.php` | Constructor por HTTP firmado (hosting sin `proc_open`) |
| `e2e/ssl.php` | Espera de certificado, límite, reanudación y HTTPS con CA local |
| `e2e/security.php` | SQLi, XSS, CSRF, subidas maliciosas, fuerza bruta, accesos directos, IDOR |
| `e2e/shots_sites.mjs` | Capturas 360/390/768/1024/1440 de webs generadas (desbordes, imágenes rotas, consola) |
| `portal/e2e/flowReal*.js` | Wizard completo en Chromium contra el backend real |

## Entorno local (lo crea `e2e/setup.sh`)
Apache + mod_php 8.3 (`:8201` portal, `:8200` webs, `:8443` HTTPS), MariaDB, dnsmasq (`*.servicom.test`),
simulador de cPanel (`app/Provision/SimCpanelApi.php`: subdominios = enlaces simbólicos para `VirtualDocumentRoot`, BD reales),
API de Claude simulada (`e2e/mock_ai.php` en `:8210`), WordPress 6.4.3 (apt) + simuladores de Elementor/WooCommerce (`wp-pack/tests-only/`).
NO se probó contra: cPanel real, Elementor/WooCommerce reales, API real de Claude.
