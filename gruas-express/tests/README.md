# Pruebas

Batería de extremo a extremo usada para la verificación (sección 7). Requiere el ZIP generado y un Apache de prueba.

1. `php tools/build_content.php <originales>` → minificado con esbuild (`tools/src`) → `php tools/package.php`
2. Descomprimir `dist/gruas-express-towing.zip` en una carpeta limpia (raíz del dominio de prueba) servida por Apache con `AllowOverride All` en el puerto 8081.
3. `python3 tests/run_tests.py` (Apache + `.htaccess`) o `GX_MODE=builtin GX_BASE=http://127.0.0.1:8082 GX_WEB=/ruta/raiz python3 tests/run_tests.py` con `php -S` (omite lo que depende del servidor web).

Las capturas de `screenshots/` se tomaron con Chromium (Playwright) sobre el ZIP final.
