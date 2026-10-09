# Informe final — «Tu web en 5 minutos» (Servicom)

## Qué se entrega
- `servicom-5min-portal.zip` — portal completo (sube a la carpeta del subdominio del portal; **un solo hosting**: el de servicom.gt). Incluye instalador web, panel del dueño, API del formulario, constructor por pasos, tema `servicom` (5 estilos), mu-plugin «Servicom Core», script de aprovisionamiento, IA (redacción + análisis de presentaciones), cron y herramientas.
- `LEEME.md` (instalación), `DECISIONES.md` (decisiones tomadas), este informe.

## Qué se probó y resultado (entorno local propio: Apache real + MariaDB + simulador de cPanel)
Todo se ejecuta con `tests/run_all.sh`. **Resultado final: 0 fallos.**

| Prueba | Resultado |
|---|---|
| Sintaxis PHP 8.3, **PHP 8.0.30 (WASM)** y escáner de sintaxis/funciones 8.1+ (155 archivos) | 0 errores, 0 hallazgos |
| IA y presentaciones: PDF texto/escaneado, PPTX, DOCX por rubros, vacío, corrupto, con contraseña, macros, >10 MB, formato no permitido, inglés, datos que contradicen el formulario, órdenes a la IA, zip bombs (memoria < 64 MB), XXE, tope de gasto, fallback a textos base | 259/259 |
| Flujo completo: formulario → vista previa → pago → aprobación → publicación (sitio sin barra ni noindex, correos, purga de archivos) | 20/20 |
| Presentaciones por HTTP (análisis asíncrono, revisión, prioridad del formulario, inyección de prompt, IA caída/sin crédito/JSON inválido, límite de 3) | 60/60 |
| Ciclo de vida: reservados, regeneración (máx. 3), pago rechazado, renovación (aviso a 30 días, vencida, renovar), suspender/reactivar, **asignar dominio** (URLs reemplazadas, sin restos), **borrado a los 15 días** sin tocar publicados/_base/ajenos | 51/51 |
| Fallos simulados: IA caída, crédito agotado, tope de gasto, token inválido, disco lleno (BD y copia a medias), fallo de paginado/instalación, subdominio ya existente, **kill -9 en medio de los pasos**, análisis interrumpido → rollback **sin huérfanos** y mensajes amables | 43/43 |
| Constructor por HTTP firmado (hosting sin `proc_open`) | 5/5 |
| SSL real con CA local: espera de certificado, límite, aviso, reanudación, HTTPS válido, HSTS, cookie Secure | 10/10 |
| Seguridad: SQLi, XSS, CSRF, subidas maliciosas (php/svg/html/polyglot), IDOR, fuerza bruta, accesos directos a app/ storage/ uploads, cabeceras | 69/69 |
| Panel: 2FA TOTP (vector RFC 6238), contraseña, ajustes (secretos cifrados, sección cPanel del hosting único, sin pantalla ni selector de hostings), diagnóstico, prueba de IA, búsqueda y filtros | 33/33 |
| Demos desde el panel y enlaces de la barra de vista previa | 17/17 |
| Navegador (Chromium) contra el backend real: wizard completo con presentación a 360/390/768/1024/1440 px y tienda con 60 productos; sin errores de consola ni scroll horizontal | todos OK |
| Matriz de webs generadas (9 rubros × 5 estilos, 1 y 30 servicios, tienda 1 y 60 productos, con/sin logo y fotos, textos larguísimos/cortos, emojis, inglés): 19 sitios × 7 páginas × 5 anchos | sin desbordes, imágenes rotas, errores de consola ni mensajes PHP |

## Qué NO pudo probarse contra el sistema real (dígalo al probar)
1. **cPanel real**: no hay acceso a su documentación ni a un servidor. Los endpoints (UAPI `Mysql::*`, `DomainInfo::domains_data`, `Variables::get_user_information`; API2 `SubDomain::addsubdomain/delsubdomain`, `AddonDomain::addaddondomain/deladdondomain`) están implementados de memoria; todo el flujo se probó con un simulador que reproduce subdominios (host virtual real en Apache) y bases de datos reales. Parámetros a vigilar en el primer uso: formato de `dir` del subdominio (se envía relativo al home), `deladdondomain`, límites de longitud del usuario MySQL (se ajustan solos con `get_restrictions`). El *Diagnóstico* del panel y una **demo** son la primera prueba recomendada.
2. **Elementor, WooCommerce y Fluent Forms reales**: wordpress.org está bloqueado en el entorno. El constructor genera datos en el formato documentado de Elementor 3.2x (ver `wp-pack/mu-plugins/servicom-core/includes/builder/README-FORMATO.md` con los puntos sin validar: controles del fondo *slideshow*, kit global, API de WooCommerce, CSS regenerado) y se probó contra simuladores propios (solo en pruebas). El formulario de contacto es propio (no depende de Fluent Forms). **La tienda con WooCommerce real es el riesgo principal.** `tools/build_base.php` descarga las versiones más nuevas compatibles con PHP 8.0 y avisa si alguna exige más.
3. **API real de Claude**: sin clave ni red; probada con una API simulada (cadena sonnet → haiku → textos base, PDF nativo, topes). Precios/límites usados (del skill `claude-api`): Sonnet 5.5 USD 2/10 y Haiku 5.5 USD 0.10/0.50 por millón de tokens, PDF 32 MB/600 páginas (se usa 24 MB). Son **editables** en Ajustes.
4. **Fotos de stock (Unsplash/Pexels)**: bloqueadas. La biblioteca (`library/`) está vacía con el mecanismo listo (`tools/stock_add.php`, `library/FALTANTES.md` lista exactamente 3 fotos *hero* + 6 de *servicio* por rubro). Sin fotos, las tarjetas usan cajas de icono y el banner un fondo del estilo: nunca imágenes falsas.
5. **PHP 8.0 en ejecución completa**: no hay PHP 8.0 nativo; se verificó sintaxis con PHP 8.0.30 (WASM) y las pruebas de IA/presentaciones también corren en él (sin `zip`). El resto corre en 8.3.
6. **Desbordes horizontales en producción**: el QA automático del portal no los mide (requiere navegador); lo garantiza el CSS del tema y se verificó con Playwright en las pruebas.
7. Navegadores distintos de Chromium (iOS/Safari), lector de pantalla.

## Alcance: un solo hosting
Se quitó el agente del segundo hosting (código, instalador, ZIP, pruebas) y la pantalla/selector de hostings. Quedan, **desactivados y sin interfaz**, la tabla `hosts`, la columna `orders.host_id` y la interfaz `HostDriver` para añadir un segundo hosting en el futuro.

## Decisiones importantes (todas en `DECISIONES.md`)
- Las webs viven en `webs-clientes/<slug>` (fuera de `public_html`); `servicom.gt` nunca se toca: subdominios reservados, un subdominio ya existente jamás se adopta, nombres de BD/usuario nunca se adoptan ni se borran si no son nuestros, borrado confinado a `webs-clientes` con doble verificación y bitácora.
- La vista previa es **privada** (clave `?scpk=` entregada solo por el portal; la barra enlaza con esa clave, nunca con el token del borrador) y `noindex`.
- Al publicar se borran la presentación original y las fotos subidas al portal (ya están en WordPress); se conserva el comprobante.
- Secretos (token cPanel, clave IA, SMTP) cifrados con libsodium en BD; la llave vive en `../servicom-secrets/config.php` fuera del docroot.
- Plugins del paquete base: solo Elementor y WooCommerce (oficiales de wordpress.org). `DISALLOW_FILE_MODS`: sin instalar/actualizar plugins desde el panel del cliente (actualice el paquete base con `build_base.php`).

## Los 5 pasos para ponerlo a funcionar
1. Suba y descomprima `servicom-5min-portal.zip` en el docroot de `crear.servicom.gt`; cree BD+usuario MySQL y abra `/install.php`.
2. En cPanel cree un **token de API** y regístrelo en el propio instalador (servidor `localhost`, usuario, token, home); luego se edita en *Ajustes → cPanel*.
3. Ejecute `php tools/build_base.php` (descarga WordPress + Elementor + WooCommerce de wordpress.org).
4. Cron diario: `0 3 * * * php /ruta/portal/tools/cron.php`. En *Ajustes*: datos bancarios, correo/WhatsApp, clave de IA (y cargue crédito), SMTP. Revise *Diagnóstico* y cree una **demo** como primera prueba.
